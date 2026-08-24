<?php
/**
 * ============================================================================
 *  BACKTEST 3/3 — Calcula error + FACTOR por grupo y los guarda en forecast_backtest.
 *  Además aplica el factor a forecast_x_producto (columnas factor + demanda_forecast_corr).
 * ----------------------------------------------------------------------------
 *  Lee de assets/librerias/python/backtest/:  grupos.csv, grupos_forecast.csv, grupos_real.csv, meta.csv
 *
 *    factor(g)  = suma_real / suma_forecast   (acotado a [0.25, 4])
 *    bias_pct   = (suma_forecast / suma_real - 1) * 100   (negativo = subestima)
 *    mape       = promedio(|real - forecast| / real) * 100
 *
 *  Requiere antes: la tabla forecast_backtest (parte del esquema en sql/manar.sql) y haber corrido:
 *    1) forecast_backtest_export.php   2) python forecast_prophet.py backtest
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../../../config/conexion.php';

const FACTOR_MIN = 0.25;
const FACTOR_MAX = 4.0;

// Empresa activa + versión: el backtest se guarda estampado por empresa y el factor solo se
// aplica a las filas de forecast_x_producto de esa empresa.
//   CLI:  php forecast_backtest_cargar.php <empresa_id> <version>   |   HTTP: ?empresa_id=&version=
if (PHP_SAPI === 'cli') {
    $EMPRESA_ID   = (isset($argv[1]) && $argv[1] !== '') ? $argv[1] : null;
    $VERSION_PRES = (isset($argv[2]) && $argv[2] !== '') ? $argv[2] : null;
} else {
    $EMPRESA_ID   = (isset($_GET['empresa_id']) && $_GET['empresa_id'] !== '') ? $_GET['empresa_id'] : null;
    $VERSION_PRES = (isset($_GET['version']) && $_GET['version'] !== '') ? $_GET['version'] : null;
}

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

    $fc = []; // [id][semana] => yhat ; $metodo[id]
    $metodo = [];
    foreach (leerCsv("$DIR/grupos_forecast.csv") as $r) {
        $id = (int) $r['grupo_id'];
        $fc[$id][$r['semana']] = (float) $r['yhat'];
        $metodo[$id] = $r['metodo'];
    }

    $real = [];
    foreach (leerCsv("$DIR/grupos_real.csv") as $r) {
        $real[(int) $r['grupo_id']][$r['semana']] = (float) $r['demanda_real'];
    }

    // ---- Métricas por grupo ---------------------------------------------
    // Se reemplaza SOLO el backtest de la empresa activa (no toda la tabla), para conservar
    // el de otras empresas. <=> es igualdad null-safe (ejecución suelta sin empresa -> NULL).
    $delBt = $pdo->prepare("DELETE FROM forecast_backtest WHERE empresa_id <=> ?");
    $delBt->execute([$EMPRESA_ID]);

    $ins = $pdo->prepare("
        INSERT INTO forecast_backtest
            (empresa_id, version_presupuesto,
             familia, sub_familia, metodo, semanas_evaluadas, desde, hasta,
             suma_real, suma_forecast, factor, bias_pct, mape)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $pdo->beginTransaction();
    $filas = 0; $resumen = [];
    foreach ($grupos as $id => $g) {
        if (empty($real[$id]) || empty($fc[$id])) { continue; }

        $semanas = array_keys($real[$id]); sort($semanas);
        $sumR = 0.0; $sumF = 0.0; $ape = []; $n = 0;
        foreach ($semanas as $sem) {
            if (!isset($fc[$id][$sem])) { continue; }
            $r = $real[$id][$sem]; $f = $fc[$id][$sem];
            $sumR += $r; $sumF += $f; $n++;
            if ($r > 0) { $ape[] = abs($r - $f) / $r; }
        }
        if ($n === 0 || $sumR <= 0) { continue; }

        $factor = ($sumF > 0) ? $sumR / $sumF : 1.0;
        $factor = min(FACTOR_MAX, max(FACTOR_MIN, $factor));
        $bias   = ($sumR > 0) ? ($sumF / $sumR - 1) * 100 : 0.0;   // <0 = subestima
        $mape   = count($ape) ? array_sum($ape) / count($ape) * 100 : null;

        $ins->execute([
            $EMPRESA_ID, $VERSION_PRES,
            $g[0], $g[1], $metodo[$id] ?? '', $n, $semanas[0], $semanas[count($semanas) - 1],
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
    // baseline (grupos sin backtest): factor 1. Solo la empresa activa (no pisa otras).
    $st = $pdo->prepare("UPDATE forecast_x_producto SET factor = 1, demanda_forecast_corr = demanda_forecast WHERE empresa_id <=> ?");
    $st->execute([$EMPRESA_ID]);
    // aplica el factor del backtest por grupo (empatando por empresa, no global)
    $st = $pdo->prepare("
        UPDATE forecast_x_producto f
        JOIN forecast_backtest b
          ON f.familia = b.familia AND f.sub_familia = b.sub_familia
         AND f.empresa_id <=> b.empresa_id
        SET f.factor = b.factor,
            f.demanda_forecast_corr = ROUND(f.demanda_forecast * b.factor)
        WHERE f.empresa_id <=> ?
    ");
    $st->execute([$EMPRESA_ID]);

    // ---- Calidad del registro -------------------------------------------
    // Score (0–7) = 2×historia + mape + método, y de ahí Alta/Media/Baja. Mide cuánta data
    // y confiabilidad respaldan cada forecast. LEFT JOIN: los grupos sin backtest -> mape NULL
    // (score de mape 0). Ver la fórmula documentada en el mantenedor de Forecast.
    $scoreExpr =
        "( (CASE WHEN f.semanas_historia >= 52 THEN 2 WHEN f.semanas_historia >= 12 THEN 1 ELSE 0 END) * 2"
      . " + (CASE WHEN b.mape IS NULL THEN 0 WHEN b.mape < 30 THEN 2 WHEN b.mape < 60 THEN 1 ELSE 0 END)"
      . " + (CASE WHEN f.metodo = 'prophet' THEN 1 ELSE 0 END) )";
    $stCal = $pdo->prepare("
        UPDATE forecast_x_producto f
        LEFT JOIN forecast_backtest b
          ON f.familia = b.familia AND f.sub_familia = b.sub_familia AND f.empresa_id <=> b.empresa_id
        SET f.calidad = CASE
            WHEN $scoreExpr >= 5 THEN 'Alta'
            WHEN $scoreExpr >= 3 THEN 'Media'
            ELSE 'Baja'
        END
        WHERE f.empresa_id <=> ?
    ");
    $stCal->execute([$EMPRESA_ID]);

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

    $tot = $pdo->prepare("SELECT ROUND(SUM(demanda_forecast)) d, ROUND(SUM(demanda_forecast_corr)) c FROM forecast_x_producto WHERE empresa_id <=> ?");
    $tot->execute([$EMPRESA_ID]);
    $tot = $tot->fetch();
    echo "\nforecast_x_producto -> demanda total: " . number_format((float) $tot['d'])
       . "  |  corregida (con factor): " . number_format((float) $tot['c']) . "\n";
    echo "\nListo.\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "(¿Importaste sql/manar.sql, y corriste el export y el prophet backtest?)\n";
}
