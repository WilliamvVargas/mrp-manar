<?php
/**
 * ============================================================================
 *  BACKTEST 1/3 — Exporta series de ENTRENAMIENTO (ocultando los últimos 12 meses)
 *  y la demanda real de esos meses ocultos, para validar el forecast.
 * ----------------------------------------------------------------------------
 *  Escribe en assets/librerias/python/backtest/:
 *    - grupos.csv             (grupo_id -> familia, sub_familia)
 *    - grupos_demanda.csv     (demanda de ENTRENAMIENTO: hasta el mes de corte)
 *    - grupos_presupuesto.csv (regresor, historia hasta el último mes real)
 *    - grupos_real.csv        (demanda REAL de los 12 meses ocultos, para comparar)
 *    - meta.csv               (mes de corte + los 12 meses evaluados)
 *
 *  Ejecutar:  http://localhost/manar/assets/librerias/forecast/forecast_backtest_export.php
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../config/conexion_sqlserver.php';
require_once __DIR__ . '/../../../models/consultas_sap_model.php';

const OCULTOS = 12; // meses a ocultar y evaluar

function claveGrupo($f, $s) { return mb_strtoupper(trim((string) $f)) . '||' . mb_strtoupper(trim((string) $s)); }
function ymAIdx($ym)  { return ((int) substr($ym, 0, 4)) * 12 + ((int) substr($ym, 5, 2) - 1); }
function idxAYm($idx) { return sprintf('%04d-%02d', intdiv($idx, 12), ($idx % 12) + 1); }

header('Content-Type: text/plain; charset=utf-8');
$DIR = __DIR__ . '/../python/backtest';
if (!is_dir($DIR)) { mkdir($DIR, 0777, true); }

// ---- Ventana: últimos 12 meses reales como "ocultos" ----------------------
$hoy       = new DateTime();
$finActual = (clone $hoy)->modify('first day of this month')->modify('-1 day'); // último día mes anterior
$finStr    = $finActual->format('Y-m-d');
$ultimoYm  = $finActual->format('Y-m');            // 2026-06

$ocultos = [];
$cur = (clone $finActual)->modify('first day of this month')->modify('-' . (OCULTOS - 1) . ' months');
for ($i = 0; $i < OCULTOS; $i++) { $ocultos[] = $cur->format('Y-m'); $cur->modify('+1 month'); }
$ocultoSet   = array_flip($ocultos);
$corteIdx    = ymAIdx($ocultos[0]) - 1;            // último mes de entrenamiento
$corteYm     = idxAYm($corteIdx);                  // 2025-06

echo "Corte entrenamiento: hasta $corteYm | Evalúa: {$ocultos[0]} a $ultimoYm\n";

// ---- Demanda real por producto/mes (V3), hasta el mes actual --------------
$sap    = new ConsultaSap($pdoSqlsrv);
$ventas = $sap->facturasNotasCreditoPorArticulo('', $finStr);

$grupos = []; $gruposInfo = []; $demTrain = []; $demReal = []; $next = 1;
foreach ($ventas as $r) {
    $key = claveGrupo($r['Familia'], $r['SubFamilia']);
    if (!isset($grupos[$key])) { $grupos[$key] = $next; $gruposInfo[$next] = [trim((string) $r['Familia']), trim((string) $r['SubFamilia'])]; $next++; }
    $id = $grupos[$key]; $ym = $r['FechaDocumento']; $c = (float) $r['Cantidad'];
    if (ymAIdx($ym) <= $corteIdx)   { $demTrain[$id][$ym] = ($demTrain[$id][$ym] ?? 0.0) + $c; }
    if (isset($ocultoSet[$ym]))     { $demReal[$id][$ym]  = ($demReal[$id][$ym]  ?? 0.0) + $c; }
}

// ---- Presupuesto por grupo/mes --------------------------------------------
$presGrupoMes = [];
foreach ($pdo->query("
    SELECT anio, mes, TRIM(familia) fam, TRIM(sub_familia) sub, SUM(venta) p
    FROM presupuestos WHERE familia IS NOT NULL AND sub_familia IS NOT NULL AND venta IS NOT NULL
    GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)")->fetchAll() as $pr) {
    $presGrupoMes[claveGrupo($pr['fam'], $pr['sub'])][sprintf('%04d-%02d', $pr['anio'], $pr['mes'])] = (float) $pr['p'];
}

// ---- Escribir CSVs (solo grupos con demanda de entrenamiento) --------------
$fG = fopen("$DIR/grupos.csv", 'w');             fputcsv($fG, ['grupo_id', 'familia', 'sub_familia']);
$fD = fopen("$DIR/grupos_demanda.csv", 'w');     fputcsv($fD, ['grupo_id', 'ym', 'demanda']);
$fP = fopen("$DIR/grupos_presupuesto.csv", 'w'); fputcsv($fP, ['grupo_id', 'ym', 'presupuesto']);
$fR = fopen("$DIR/grupos_real.csv", 'w');        fputcsv($fR, ['grupo_id', 'ym', 'demanda_real']);

$nGrupos = 0;
foreach ($gruposInfo as $id => $g) {
    if (empty($demTrain[$id])) { continue; } // sin datos de entrenamiento -> no se puede backtestear
    $nGrupos++;
    fputcsv($fG, [$id, $g[0], $g[1]]);
    $key = claveGrupo($g[0], $g[1]);

    $meses = $demTrain[$id]; ksort($meses);
    foreach ($meses as $ym => $d) { fputcsv($fD, [$id, $ym, round($d, 4)]); }

    // presupuesto desde el primer mes de entrenamiento hasta el último mes evaluado (real/imputado).
    $claves = array_keys($demTrain[$id]); sort($claves);
    $desde = ymAIdx($claves[0]); $hasta = ymAIdx($ultimoYm);
    for ($i = $desde; $i <= $hasta; $i++) {
        $ym = idxAYm($i);
        $val = $presGrupoMes[$key][$ym] ?? ($presGrupoMes[$key][idxAYm($i - 12)] ?? 0.0);
        fputcsv($fP, [$id, $ym, round($val, 2)]);
    }

    if (!empty($demReal[$id])) {
        $mr = $demReal[$id]; ksort($mr);
        foreach ($mr as $ym => $d) { fputcsv($fR, [$id, $ym, round($d, 4)]); }
    }
}
fclose($fG); fclose($fD); fclose($fP); fclose($fR);

$fM = fopen("$DIR/meta.csv", 'w'); fputcsv($fM, ['clave', 'valor']);
fputcsv($fM, ['ultimo_actual', $corteYm]);
foreach ($ocultos as $k => $m) { fputcsv($fM, ["forecast_$k", $m]); }
fclose($fM);

echo "Grupos exportados: $nGrupos -> assets/librerias/python/backtest/\n";
echo "Siguiente: python/venv/Scripts/python.exe assets/librerias/python/forecast_prophet.py backtest\n";
