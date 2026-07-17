<?php
/**
 * ============================================================================
 *  BACKTEST 1/3 — Exporta series de ENTRENAMIENTO (ocultando las últimas 26 SEMANAS)
 *  y la demanda real de esas semanas ocultas, para validar el forecast. Grano SEMANAL.
 * ----------------------------------------------------------------------------
 *  Escribe en assets/librerias/python/backtest/ (misma estructura que el paso 1, columna 'semana'):
 *    - grupos.csv             (grupo_id -> familia, sub_familia)
 *    - grupos_demanda.csv     (demanda de ENTRENAMIENTO: hasta la semana de corte)
 *    - grupos_presupuesto.csv (regresor semanal, historia hasta la última semana oculta)
 *    - grupos_real.csv        (demanda REAL de las 26 semanas ocultas, para comparar)
 *    - meta.csv               (semana de corte + las 26 semanas evaluadas)
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

const OCULTOS = 52; // semanas a ocultar y evaluar

function claveGrupo($f, $s) { return mb_strtoupper(trim((string) $f)) . '||' . mb_strtoupper(trim((string) $s)); }

/** Lunes (ISO) de la semana que contiene la fecha 'yyyy-MM-dd'. */
function lunesDe($fechaYmd) {
    $d      = new DateTime($fechaYmd);
    $offset = (int) $d->format('N') - 1;
    if ($offset > 0) { $d->modify("-$offset days"); }
    return $d->format('Y-m-d');
}

/** Mes 'yyyy-MM' 12 meses antes (para imputar el presupuesto de meses faltantes). */
function mesMenos12($mesKey) {
    $d = DateTime::createFromFormat('Y-m-d', $mesKey . '-01');
    $d->modify('-12 months');
    return $d->format('Y-m');
}

/** Prorratea el presupuesto MENSUAL de un grupo a SEMANAS por tasa diaria, entre dos fechas. */
function presupuestoSemanal($key, $iniMonday, $finSunday, $presGrupoMes) {
    $semana = [];
    $d   = new DateTime($iniMonday);
    $fin = new DateTime($finSunday);
    while ($d <= $fin) {
        $mesKey  = $d->format('Y-m');
        $budMes  = $presGrupoMes[$key][$mesKey] ?? ($presGrupoMes[$key][mesMenos12($mesKey)] ?? 0.0);
        $diasMes = (int) $d->format('t');
        $tasaDia = $diasMes > 0 ? $budMes / $diasMes : 0.0;
        $offset  = (int) $d->format('N') - 1;
        $lunes   = (clone $d)->modify($offset > 0 ? "-$offset days" : '+0 days')->format('Y-m-d');
        $semana[$lunes] = ($semana[$lunes] ?? 0.0) + $tasaDia;
        $d->modify('+1 day');
    }
    ksort($semana);
    return $semana;
}

header('Content-Type: text/plain; charset=utf-8');
$DIR = __DIR__ . '/../python/backtest';
if (!is_dir($DIR)) { mkdir($DIR, 0777, true); }

// ---- Ventana: últimas 26 semanas completas como "ocultas" -----------------
$hoy             = new DateTime('today');
$lunesEstaSemana = new DateTime(lunesDe($hoy->format('Y-m-d')));
$finActual       = (clone $lunesEstaSemana)->modify('-1 day');       // domingo anterior = fin de la última semana completa
$finStr          = $finActual->format('Y-m-d');
$ultimaSemana    = lunesDe($finActual->format('Y-m-d'));             // lunes de la última semana completa

$ocultos = [];
$cur = (new DateTime($ultimaSemana))->modify('-' . (OCULTOS - 1) . ' weeks');
for ($i = 0; $i < OCULTOS; $i++) { $ocultos[] = $cur->format('Y-m-d'); $cur->modify('+7 days'); }
$ocultoSet        = array_flip($ocultos);
$corteMonday      = (new DateTime($ocultos[0]))->modify('-7 days')->format('Y-m-d');   // última semana de entrenamiento
$finOcultosSunday = (new DateTime(end($ocultos)))->modify('+6 days')->format('Y-m-d'); // domingo de la última semana oculta

echo "Corte entrenamiento: hasta $corteMonday | Evalúa: {$ocultos[0]} a $ultimaSemana (" . OCULTOS . " semanas)\n";

// ---- Demanda real por producto/DÍA (SAP) agregada a semana ISO ------------
$sap    = new ConsultaSap($pdoSqlsrv);
$ventas = $sap->demandaDiariaPorArticulo('', $finStr);

$grupos = []; $gruposInfo = []; $demTrain = []; $demReal = []; $next = 1;
foreach ($ventas as $r) {
    $key = claveGrupo($r['Familia'], $r['SubFamilia']);
    if (!isset($grupos[$key])) { $grupos[$key] = $next; $gruposInfo[$next] = [trim((string) $r['Familia']), trim((string) $r['SubFamilia'])]; $next++; }
    $id  = $grupos[$key];
    $sem = lunesDe($r['Fecha']);
    $c   = (float) $r['Cantidad'];
    if ($sem <= $corteMonday)     { $demTrain[$id][$sem] = ($demTrain[$id][$sem] ?? 0.0) + $c; }
    if (isset($ocultoSet[$sem]))  { $demReal[$id][$sem]  = ($demReal[$id][$sem]  ?? 0.0) + $c; }
}

// ---- Presupuesto por grupo/MES (MySQL) ------------------------------------
$presGrupoMes = [];
foreach ($pdo->query("
    SELECT anio, mes, TRIM(familia) fam, TRIM(sub_familia) sub, SUM(venta) p
    FROM presupuestos WHERE familia IS NOT NULL AND sub_familia IS NOT NULL AND venta IS NOT NULL
    GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)")->fetchAll() as $pr) {
    $presGrupoMes[claveGrupo($pr['fam'], $pr['sub'])][sprintf('%04d-%02d', $pr['anio'], $pr['mes'])] = (float) $pr['p'];
}

// ---- Escribir CSVs (solo grupos con demanda de entrenamiento) --------------
$fG = fopen("$DIR/grupos.csv", 'w');             fputcsv($fG, ['grupo_id', 'familia', 'sub_familia']);
$fD = fopen("$DIR/grupos_demanda.csv", 'w');     fputcsv($fD, ['grupo_id', 'semana', 'demanda']);
$fP = fopen("$DIR/grupos_presupuesto.csv", 'w'); fputcsv($fP, ['grupo_id', 'semana', 'presupuesto']);
$fR = fopen("$DIR/grupos_real.csv", 'w');        fputcsv($fR, ['grupo_id', 'semana', 'demanda_real']);

$nGrupos = 0;
foreach ($gruposInfo as $id => $g) {
    if (empty($demTrain[$id])) { continue; } // sin datos de entrenamiento -> no se puede backtestear
    $nGrupos++;
    fputcsv($fG, [$id, $g[0], $g[1]]);
    $key = claveGrupo($g[0], $g[1]);

    $sem = $demTrain[$id]; ksort($sem);
    foreach ($sem as $s => $d) { fputcsv($fD, [$id, $s, round($d, 4)]); }

    // presupuesto semanal desde la primera semana de entrenamiento hasta la última semana oculta.
    $claves  = array_keys($demTrain[$id]); sort($claves);
    $presSem = presupuestoSemanal($key, $claves[0], $finOcultosSunday, $presGrupoMes);
    foreach ($presSem as $s => $v) { fputcsv($fP, [$id, $s, round($v, 2)]); }

    if (!empty($demReal[$id])) {
        $mr = $demReal[$id]; ksort($mr);
        foreach ($mr as $s => $d) { fputcsv($fR, [$id, $s, round($d, 4)]); }
    }
}
fclose($fG); fclose($fD); fclose($fP); fclose($fR);

$fM = fopen("$DIR/meta.csv", 'w'); fputcsv($fM, ['clave', 'valor']);
fputcsv($fM, ['ultimo_actual', $corteMonday]);
foreach ($ocultos as $k => $s) { fputcsv($fM, ["forecast_$k", $s]); }
fclose($fM);

echo "Grupos exportados: $nGrupos -> assets/librerias/python/backtest/\n";
echo "Siguiente: python/venv/Scripts/python.exe assets/librerias/python/forecast_prophet.py backtest\n";
