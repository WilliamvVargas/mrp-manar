<?php
/**
 * ============================================================================
 *  PASO 1/3 — Exporta las series por grupo para el forecast (Solución 1).
 * ----------------------------------------------------------------------------
 *  Genera CSVs en assets/librerias/python/forecast/ que consume Prophet:
 *    - grupos.csv              : grupo_id, familia, sub_familia
 *    - grupos_demanda.csv      : grupo_id, ym, demanda   (unidades, historia real V3)
 *    - grupos_presupuesto.csv  : grupo_id, ym, presupuesto ($, historia + 12 meses futuros;
 *                                los meses futuros sin presupuesto se imputan con el mismo
 *                                mes del año anterior -> el regresor queda completo)
 *    - productos_demanda.csv   : grupo_id, producto_codigo, producto_nombre, ym, demanda
 *    - meta.csv                : ultimo_actual + los 12 meses a pronosticar
 *
 *  Demanda real = Cantidad de la V3 (facturas - NC), por producto y año-mes, hasta el
 *  último mes COMPLETO (mes anterior al actual).
 *
 *  Ejecutar:  http://localhost/manar/pruebas/forecast_export.php   (o por CLI)
 *  OJO: script de pruebas, sin control de acceso.
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/conexion_sqlserver.php';
require_once __DIR__ . '/../models/consultas_sap_model.php';

const HORIZONTE = 12;

function claveGrupo($f, $s) { return mb_strtoupper(trim((string) $f)) . '||' . mb_strtoupper(trim((string) $s)); }
function ymAIdx($ym)  { return ((int) substr($ym, 0, 4)) * 12 + ((int) substr($ym, 5, 2) - 1); }
function idxAYm($idx) { return sprintf('%04d-%02d', intdiv($idx, 12), ($idx % 12) + 1); }

header('Content-Type: text/plain; charset=utf-8');

$DIR = __DIR__ . '/../assets/librerias/python/forecast';
if (!is_dir($DIR)) { mkdir($DIR, 0777, true); }

// ---- Ventana temporal -----------------------------------------------------
$hoy       = new DateTime();
$finActual = (clone $hoy)->modify('first day of this month')->modify('-1 day'); // último día mes anterior
$finStr    = $finActual->format('Y-m-d');
$ultimoYm  = $finActual->format('Y-m');   // último mes con datos completos
$forecast  = [];
$cur = (clone $hoy)->modify('first day of this month');
for ($i = 0; $i < HORIZONTE; $i++) { $forecast[] = $cur->format('Y-m'); $cur->modify('+1 month'); }
$ultForecast = end($forecast);

echo "Último mes real: $ultimoYm   |   Forecast: {$forecast[0]} a $ultForecast\n";

// ---- 1) Demanda real por producto/mes (V3) --------------------------------
$sap    = new ConsultaSap($pdoSqlsrv);
$ventas = $sap->facturasNotasCreditoPorArticulo('', $finStr);
echo "Filas V3: " . count($ventas) . "\n";

$grupos = []; $gruposInfo = []; $grupoDem = []; $prodAgg = []; $next = 1;
foreach ($ventas as $r) {
    $key = claveGrupo($r['Familia'], $r['SubFamilia']);
    if (!isset($grupos[$key])) { $grupos[$key] = $next; $gruposInfo[$next] = [trim((string) $r['Familia']), trim((string) $r['SubFamilia'])]; $next++; }
    $id  = $grupos[$key];
    $ym  = $r['FechaDocumento'];
    $c   = (float) $r['Cantidad'];
    $grupoDem[$id][$ym] = ($grupoDem[$id][$ym] ?? 0.0) + $c;
    if (!isset($prodAgg[$id][$r['CodArticulo']])) { $prodAgg[$id][$r['CodArticulo']] = ['nombre' => $r['Articulo'], 'meses' => []]; }
    $prodAgg[$id][$r['CodArticulo']]['meses'][$ym] = ($prodAgg[$id][$r['CodArticulo']]['meses'][$ym] ?? 0.0) + $c;
}
echo "Grupos: " . count($gruposInfo) . "\n";

// ---- 2) Presupuesto por grupo/mes (MySQL) ---------------------------------
$presGrupoMes = [];
$rows = $pdo->query("
    SELECT anio, mes, TRIM(familia) fam, TRIM(sub_familia) sub, SUM(venta) p
    FROM presupuestos
    WHERE familia IS NOT NULL AND sub_familia IS NOT NULL AND venta IS NOT NULL
    GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)
")->fetchAll();
foreach ($rows as $pr) {
    $key = claveGrupo($pr['fam'], $pr['sub']);
    $presGrupoMes[$key][sprintf('%04d-%02d', $pr['anio'], $pr['mes'])] = (float) $pr['p'];
}

// ---- 3) Escribir CSVs ------------------------------------------------------
$fG = fopen("$DIR/grupos.csv", 'w');            fputcsv($fG, ['grupo_id', 'familia', 'sub_familia']);
$fD = fopen("$DIR/grupos_demanda.csv", 'w');    fputcsv($fD, ['grupo_id', 'ym', 'demanda']);
$fP = fopen("$DIR/grupos_presupuesto.csv", 'w');fputcsv($fP, ['grupo_id', 'ym', 'presupuesto']);
$fA = fopen("$DIR/productos_demanda.csv", 'w'); fputcsv($fA, ['grupo_id', 'producto_codigo', 'producto_nombre', 'ym', 'demanda']);

foreach ($gruposInfo as $id => $g) {
    fputcsv($fG, [$id, $g[0], $g[1]]);
    $key = claveGrupo($g[0], $g[1]);

    // demanda del grupo (ordenada)
    $meses = $grupoDem[$id]; ksort($meses);
    foreach ($meses as $ym => $d) { fputcsv($fD, [$id, $ym, round($d, 4)]); }

    // presupuesto del grupo: desde el primer mes de demanda hasta el último de forecast,
    // imputando faltantes (incluidos los meses futuros) con el mismo mes del año anterior.
    $clavesDem = array_keys($grupoDem[$id]); sort($clavesDem);
    $desde = ymAIdx($clavesDem[0]);
    $hasta = ymAIdx($ultForecast);
    for ($i = $desde; $i <= $hasta; $i++) {
        $ym  = idxAYm($i);
        $val = $presGrupoMes[$key][$ym] ?? null;
        if ($val === null) { $val = $presGrupoMes[$key][idxAYm($i - 12)] ?? 0.0; } // imputa con año anterior
        fputcsv($fP, [$id, $ym, round($val, 2)]);
    }

    // demanda por producto
    foreach ($prodAgg[$id] as $cod => $info) {
        foreach ($info['meses'] as $ym => $d) { fputcsv($fA, [$id, $cod, $info['nombre'], $ym, round($d, 4)]); }
    }
}
fclose($fG); fclose($fD); fclose($fP); fclose($fA);

// Estado de actividad (OITM): activo = validFor 'Y' y NO congelado. Los inactivos se
// excluirán del forecast (paso 3), redistribuyendo su participación a los activos.
$estadoAct = [];
foreach ($sap->estadoActividadProductos() as $e) {
    $estadoAct[$e['ItemCode']] = (($e['validFor'] ?? 'N') === 'Y' && ($e['frozenFor'] ?? 'N') !== 'Y') ? 1 : 0;
}
$fE = fopen("$DIR/productos_estado.csv", 'w'); fputcsv($fE, ['producto_codigo', 'activo']);
$nAct = 0; $nInact = 0;
foreach ($prodAgg as $prods) {
    foreach ($prods as $cod => $info) {
        $a = $estadoAct[$cod] ?? 1; // no está en OITM -> se asume activo (no se excluye por dudas)
        fputcsv($fE, [$cod, $a]);
        $a ? $nAct++ : $nInact++;
    }
}
fclose($fE);
echo "Estado productos -> activos: $nAct | inactivos: $nInact\n";

$fM = fopen("$DIR/meta.csv", 'w'); fputcsv($fM, ['clave', 'valor']);
fputcsv($fM, ['ultimo_actual', $ultimoYm]);
foreach ($forecast as $k => $m) { fputcsv($fM, ["forecast_$k", $m]); }
fclose($fM);

echo "CSVs escritos en assets/librerias/python/forecast/\n";
echo "Listo. Siguiente paso: assets/librerias/python/forecast_prophet.py\n";
