<?php
/**
 * ============================================================================
 *  BACKTEST 3/3 — Calcula error + FACTOR por grupo y los guarda en forecast_backtest.
 *  Además aplica el factor a forecast_x_producto (columnas factor + demanda_forecast_corr).
 * ----------------------------------------------------------------------------
 *  Lee de python/backtest/:  grupos.csv, grupos_forecast.csv, grupos_real.csv, meta.csv
 *
 *    factor(g)  = suma_real / suma_forecast   (acotado a [0.25, 4])
 *    bias_pct   = (suma_forecast / suma_real - 1) * 100   (negativo = subestima)
 *    mape       = promedio(|real - forecast| / real) * 100
 *
 *  Requiere antes: sql/forecast_backtest.sql y haber corrido:
 *    1) forecast_backtest_export.php   2) python forecast_prophet.py backtest
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../config/conexion.php';

const FACTOR_MIN = 0.25;
const FACTOR_MAX = 4.0;

function leerCsv($ruta) {
    $filas = [];
    if (($fh = fopen($ruta, 'r')) === false) { return $filas; }
    $cab = fgetcsv($fh);
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) === 1 && $r[0] === null) { continue; }
        $filas[] = array_combine($cab, $r);
    }
    fclose($fh);
    return $filas;
}

header('Content-Type: text/plain; charset=utf-8');
$DIR = __DIR__ . '/../python/backtest';

try {
    $grupos = [];
    foreach (leerCsv("$DIR/grupos.csv") as $r) { $grupos[(int) $r['grupo_id']] = [$r['familia'], $r['sub_familia']]; }

    $fc = []; // [id][ym] => yhat ; $metodo[id]
    $metodo = [];
    foreach (leerCsv("$DIR/grupos_forecast.csv") as $r) {
        $id = (int) $r['grupo_id'];
        $fc[$id][$r['ym']] = (float) $r['yhat'];
        $metodo[$id] = $r['metodo'];
    }

    $real = [];
    foreach (leerCsv("$DIR/grupos_real.csv") as $r) {
        $real[(int) $r['grupo_id']][$r['ym']] = (float) $r['demanda_real'];
    }

    // ---- Métricas por grupo ---------------------------------------------
    $pdo->exec("TRUNCATE TABLE forecast_backtest");
    $ins = $pdo->prepare("
        INSERT INTO forecast_backtest
            (familia, sub_familia, metodo, meses_evaluados, desde, hasta,
             suma_real, suma_forecast, factor, bias_pct, mape)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    $pdo->beginTransaction();
    $filas = 0; $resumen = [];
    foreach ($grupos as $id => $g) {
        if (empty($real[$id]) || empty($fc[$id])) { continue; }

        $meses = array_keys($real[$id]); sort($meses);
        $sumR = 0.0; $sumF = 0.0; $ape = []; $n = 0;
        foreach ($meses as $ym) {
            if (!isset($fc[$id][$ym])) { continue; }
            $r = $real[$id][$ym]; $f = $fc[$id][$ym];
            $sumR += $r; $sumF += $f; $n++;
            if ($r > 0) { $ape[] = abs($r - $f) / $r; }
        }
        if ($n === 0 || $sumR <= 0) { continue; }

        $factor = ($sumF > 0) ? $sumR / $sumF : 1.0;
        $factor = min(FACTOR_MAX, max(FACTOR_MIN, $factor));
        $bias   = ($sumR > 0) ? ($sumF / $sumR - 1) * 100 : 0.0;   // <0 = subestima
        $mape   = count($ape) ? array_sum($ape) / count($ape) * 100 : null;

        $ins->execute([
            $g[0], $g[1], $metodo[$id] ?? '', $n, $meses[0], $meses[count($meses) - 1],
            round($sumR, 4), round($sumF, 4), round($factor, 6),
            round($bias, 4), $mape !== null ? round($mape, 4) : null,
        ]);
        $filas++;
        $resumen[] = ['g' => $g[0] . ' / ' . $g[1], 'metodo' => $metodo[$id] ?? '', 'sumR' => $sumR, 'sumF' => $sumF, 'factor' => $factor, 'bias' => $bias, 'mape' => $mape];
    }
    $pdo->commit();

    // ---- Aplicar el factor a forecast_x_producto ------------------------
    // Agrega columnas si no existen y recalcula la demanda corregida.
    $cols = $pdo->query("SHOW COLUMNS FROM forecast_x_producto LIKE 'factor'")->fetchAll();
    if (!$cols) {
        $pdo->exec("ALTER TABLE forecast_x_producto
                      ADD COLUMN factor decimal(10,6) NULL AFTER metodo,
                      ADD COLUMN demanda_forecast_corr decimal(15,4) NULL AFTER factor");
    }
    // baseline (grupos sin backtest): factor 1
    $pdo->exec("UPDATE forecast_x_producto SET factor = 1, demanda_forecast_corr = demanda_forecast");
    // aplica el factor del backtest por grupo
    $pdo->exec("
        UPDATE forecast_x_producto f
        JOIN forecast_backtest b ON f.familia = b.familia AND f.sub_familia = b.sub_familia
        SET f.factor = b.factor,
            f.demanda_forecast_corr = ROUND(f.demanda_forecast * b.factor)
    ");

    // ---- Reporte --------------------------------------------------------
    echo "Grupos evaluados: $filas\n\n";
    usort($resumen, fn($a, $b) => abs($b['bias']) <=> abs($a['bias']));
    echo str_pad('Grupo', 36) . str_pad('Mét.', 10) . str_pad('Real', 10) . str_pad('Forecast', 10)
       . str_pad('Bias%', 9) . str_pad('MAPE%', 9) . "Factor\n";
    echo str_repeat('-', 92) . "\n";
    foreach ($resumen as $x) {
        echo str_pad(mb_substr($x['g'], 0, 34), 36)
           . str_pad($x['metodo'], 10)
           . str_pad(number_format($x['sumR'], 0), 10)
           . str_pad(number_format($x['sumF'], 0), 10)
           . str_pad(number_format($x['bias'], 1), 9)
           . str_pad($x['mape'] !== null ? number_format($x['mape'], 1) : '-', 9)
           . number_format($x['factor'], 3) . "\n";
    }

    $tot = $pdo->query("SELECT ROUND(SUM(demanda_forecast)) d, ROUND(SUM(demanda_forecast_corr)) c FROM forecast_x_producto")->fetch();
    echo "\nforecast_x_producto -> demanda total: " . number_format((float) $tot['d'])
       . "  |  corregida (con factor): " . number_format((float) $tot['c']) . "\n";
    echo "\nListo.\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "(¿Corriste sql/forecast_backtest.sql, el export y el prophet backtest?)\n";
}
