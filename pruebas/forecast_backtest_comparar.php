<?php
/**
 * ============================================================================
 *  EXPERIMENTO — Compara el backtest CON vs SIN el presupuesto-regresor.
 * ----------------------------------------------------------------------------
 *  Lee de assets/librerias/python/backtest/:
 *    grupos.csv, grupos_real.csv, grupos_forecast_reg.csv, grupos_forecast_noreg.csv
 *  (correr antes:  python forecast_prophet.py backtest reg
 *                  python forecast_prophet.py backtest noreg)
 *
 *  Por grupo calcula bias% y MAPE% de cada configuración y marca la mejor
 *  (menor MAPE). Así se ve si el presupuesto sucio está ayudando o estorbando.
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
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

/** bias% y MAPE% de un grupo dado real[ym] y forecast[ym]. */
function metricas($realG, $fcG) {
    $sumR = 0.0; $sumF = 0.0; $ape = [];
    foreach ($realG as $ym => $r) {
        if (!isset($fcG[$ym])) { continue; }
        $f = $fcG[$ym]; $sumR += $r; $sumF += $f;
        if ($r > 0) { $ape[] = abs($r - $f) / $r; }
    }
    if ($sumR <= 0) { return null; }
    return [
        'sumR' => $sumR,
        'bias' => ($sumF / $sumR - 1) * 100,
        'mape' => count($ape) ? array_sum($ape) / count($ape) * 100 : null,
    ];
}

header('Content-Type: text/plain; charset=utf-8');
$DIR = __DIR__ . '/../assets/librerias/python/backtest';

$grupos = [];
foreach (leerCsv("$DIR/grupos.csv") as $r) { $grupos[(int) $r['grupo_id']] = [$r['familia'], $r['sub_familia']]; }

$real = [];
foreach (leerCsv("$DIR/grupos_real.csv") as $r) { $real[(int) $r['grupo_id']][$r['ym']] = (float) $r['demanda_real']; }

function leerForecast($ruta) {
    $fc = [];
    foreach (leerCsv($ruta) as $r) { $fc[(int) $r['grupo_id']][$r['ym']] = (float) $r['yhat']; }
    return $fc;
}
$fcReg   = leerForecast("$DIR/grupos_forecast_reg.csv");
$fcNoReg = leerForecast("$DIR/grupos_forecast_noreg.csv");

if (empty($fcReg) || empty($fcNoReg)) {
    exit("Faltan archivos. Corré antes:\n  python forecast_prophet.py backtest reg\n  python forecast_prophet.py backtest noreg\n");
}

$filas = [];
foreach ($grupos as $id => $g) {
    if (empty($real[$id])) { continue; }
    $mR = isset($fcReg[$id])   ? metricas($real[$id], $fcReg[$id])   : null;
    $mN = isset($fcNoReg[$id]) ? metricas($real[$id], $fcNoReg[$id]) : null;
    if (!$mR || !$mN) { continue; }
    $filas[] = ['g' => $g[0] . ' / ' . $g[1], 'real' => $mR['sumR'], 'R' => $mR, 'N' => $mN];
}

// Ordena por volumen real (grupos importantes primero).
usort($filas, fn($a, $b) => $b['real'] <=> $a['real']);

echo "Backtest CON vs SIN presupuesto-regresor (MAPE = error mensual; Bias = nivel)\n";
echo "Menor MAPE = mejor. Ganador por grupo:\n\n";
echo str_pad('Grupo', 34) . str_pad('MAPE reg', 10) . str_pad('MAPE noreg', 12)
   . str_pad('Bias reg', 10) . str_pad('Bias noreg', 12) . "Mejor\n";
echo str_repeat('-', 96) . "\n";

$ganaReg = 0; $ganaNoReg = 0; $sumMapeR = 0; $sumMapeN = 0; $cuenta = 0;
foreach ($filas as $x) {
    $mr = $x['R']['mape']; $mn = $x['N']['mape'];
    $mejor = ($mr !== null && $mn !== null) ? ($mn < $mr ? 'SIN reg' : 'con reg') : '—';
    if ($mejor === 'SIN reg') { $ganaNoReg++; } elseif ($mejor === 'con reg') { $ganaReg++; }
    if ($mr !== null && $mn !== null) { $sumMapeR += $mr; $sumMapeN += $mn; $cuenta++; }
    echo str_pad(mb_substr($x['g'], 0, 32), 34)
       . str_pad($mr !== null ? number_format($mr, 1) : '-', 10)
       . str_pad($mn !== null ? number_format($mn, 1) : '-', 12)
       . str_pad(number_format($x['R']['bias'], 1), 10)
       . str_pad(number_format($x['N']['bias'], 1), 12)
       . $mejor . "\n";
}

echo str_repeat('-', 96) . "\n";
echo "Gana CON reg: $ganaReg grupos  |  Gana SIN reg: $ganaNoReg grupos\n";
if ($cuenta) {
    echo "MAPE promedio -> con reg: " . number_format($sumMapeR / $cuenta, 1)
       . "%   |   sin reg: " . number_format($sumMapeN / $cuenta, 1) . "%\n";
}
