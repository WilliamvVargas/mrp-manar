<?php
/**
 * ============================================================================
 *  PASO 1/3 — Exporta las series por grupo para el forecast SEMANAL (Solución 1).
 * ----------------------------------------------------------------------------
 *  Genera CSVs en assets/librerias/python/forecast/ que consume Prophet. El grano
 *  temporal es la SEMANA ISO, identificada por la fecha de su LUNES ('yyyy-MM-dd'):
 *    - grupos.csv              : grupo_id, familia, sub_familia
 *    - grupos_demanda.csv      : grupo_id, semana, demanda   (unidades, historia real)
 *    - grupos_presupuesto.csv  : grupo_id, semana, presupuesto ($, historia + 52 semanas
 *                                futuras). El presupuesto vive por MES en MySQL; se prorratea
 *                                a semana por TASA DIARIA (presupuesto_mes / días_del_mes),
 *                                sumando los días de cada semana -> resuelve las semanas que
 *                                cruzan meses. Los meses futuros sin presupuesto se imputan
 *                                con el mismo mes del año anterior.
 *    - productos_demanda.csv   : grupo_id, producto_codigo, producto_nombre, semana, demanda
 *    - productos_estado.csv    : producto_codigo, activo
 *    - meta.csv                : ultimo_actual (lunes de la última semana completa) + las 52
 *                                semanas a pronosticar (lunes de cada una)
 *
 *  Demanda real = Cantidad (facturas - NC) por artículo y DÍA (SAP), agregada a semana ISO,
 *  hasta la última semana COMPLETA (la anterior a la semana en curso).
 *
 *  Ejecutar:  http://localhost/manar/assets/librerias/forecast/forecast_export.php   (o por CLI)
 *  OJO: script de pruebas, sin control de acceso.
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../../../config/conexion.php';                    // $pdo (MySQL)
require_once __DIR__ . '/../../../config/conexion_sqlserver_factory.php';  // conectarSap()
require_once __DIR__ . '/../../../models/consultas_sap_model.php';

const HORIZONTE = 52;   // semanas a pronosticar

// Contexto del presupuesto: empresa activa + versión. Lo pasa el controlador de la
// Explosión de Forecast para NO mezclar el presupuesto de todas las empresas/versiones.
//   CLI:  php forecast_export.php <empresa_id> <version>
//   HTTP: ?empresa_id=...&version=...
// Si no se pasan (ejecución suelta de depuración), se usa TODO el presupuesto (legacy).
if (PHP_SAPI === 'cli') {
    $EMPRESA_ID   = (isset($argv[1]) && $argv[1] !== '') ? $argv[1] : null;
    $VERSION_PRES = (isset($argv[2]) && $argv[2] !== '') ? $argv[2] : null;
} else {
    $EMPRESA_ID   = (isset($_GET['empresa_id']) && $_GET['empresa_id'] !== '') ? $_GET['empresa_id'] : null;
    $VERSION_PRES = (isset($_GET['version']) && $_GET['version'] !== '') ? $_GET['version'] : null;
}

// Conexión SAP de la EMPRESA (no la por defecto). En CLI no hay sesión, así que la demanda
// real DEBE salir de la SAP de la empresa recibida; si no, el forecast de otra empresa se
// calcularía con los productos de la empresa por defecto (Manar).
try {
    $pdoSqlsrv = conectarSap($pdo, $EMPRESA_ID);
} catch (Throwable $e) {
    error_log('[FORECAST export SAP] ' . $e->getMessage());
    echo 'ERROR: no se pudo conectar a SAP de la empresa. ' . $e->getMessage() . "\n";
    exit(1);
}

function claveGrupo($f, $s) { return mb_strtoupper(trim((string) $f)) . '||' . mb_strtoupper(trim((string) $s)); }

/** Lunes (ISO) de la semana que contiene la fecha 'yyyy-MM-dd'. Devuelve 'yyyy-MM-dd'. */
function lunesDe($fechaYmd) {
    $d      = new DateTime($fechaYmd);
    $offset = (int) $d->format('N') - 1;   // N: 1=lunes .. 7=domingo
    if ($offset > 0) { $d->modify("-$offset days"); }
    return $d->format('Y-m-d');
}

/** Mes 'yyyy-MM' 12 meses antes (para imputar el presupuesto de meses futuros/faltantes). */
function mesMenos12($mesKey) {
    $d = DateTime::createFromFormat('Y-m-d', $mesKey . '-01');
    $d->modify('-12 months');
    return $d->format('Y-m');
}

header('Content-Type: text/plain; charset=utf-8');

$DIR = __DIR__ . '/../python/forecast';
if (!is_dir($DIR)) { mkdir($DIR, 0777, true); }

// ---- Ventana temporal (semanas ISO) ---------------------------------------
$hoy             = new DateTime('today');
$lunesEstaSemana = new DateTime(lunesDe($hoy->format('Y-m-d')));                 // lunes de la semana en curso
$finActual       = (clone $lunesEstaSemana)->modify('-1 day');                   // domingo anterior = fin de la última semana COMPLETA
$finStr          = $finActual->format('Y-m-d');
$ultimaSemana    = lunesDe($finActual->format('Y-m-d'));                         // lunes de la última semana completa

$forecast = [];
$cur = clone $lunesEstaSemana;
for ($i = 0; $i < HORIZONTE; $i++) { $forecast[] = $cur->format('Y-m-d'); $cur->modify('+7 days'); }
$ultForecast      = end($forecast);
$finForecastSunday = (new DateTime($ultForecast))->modify('+6 days')->format('Y-m-d'); // domingo de la última semana de forecast

echo "Última semana real (lunes): $ultimaSemana   |   Forecast: {$forecast[0]} a $ultForecast (" . HORIZONTE . " semanas)\n";

// ---- 1) Demanda real por producto/DÍA (SAP), agregada a semana ISO --------
$sap    = new ConsultaSap($pdoSqlsrv);
$ventas = $sap->demandaDiariaPorArticulo('', $finStr);
echo "Filas demanda diaria: " . count($ventas) . "\n";

$grupos = []; $gruposInfo = []; $grupoDem = []; $prodAgg = []; $next = 1;
foreach ($ventas as $r) {
    $key = claveGrupo($r['Familia'], $r['SubFamilia']);
    if (!isset($grupos[$key])) { $grupos[$key] = $next; $gruposInfo[$next] = [trim((string) $r['Familia']), trim((string) $r['SubFamilia'])]; $next++; }
    $id  = $grupos[$key];
    $sem = lunesDe($r['Fecha']);   // lunes de la semana ISO del día
    $c   = (float) $r['Cantidad'];
    $grupoDem[$id][$sem] = ($grupoDem[$id][$sem] ?? 0.0) + $c;
    if (!isset($prodAgg[$id][$r['CodArticulo']])) { $prodAgg[$id][$r['CodArticulo']] = ['nombre' => $r['Articulo'], 'semanas' => []]; }
    $prodAgg[$id][$r['CodArticulo']]['semanas'][$sem] = ($prodAgg[$id][$r['CodArticulo']]['semanas'][$sem] ?? 0.0) + $c;
}
echo "Grupos: " . count($gruposInfo) . "\n";

// ---- 2) Presupuesto por grupo/MES (MySQL) ---------------------------------
// Se acota a la empresa + versión recibidas (si vienen). Así el forecast usa SOLO el
// presupuesto de esa empresa/versión, sin mezclar el resto.
$condPres = ['familia IS NOT NULL', 'sub_familia IS NOT NULL', 'venta IS NOT NULL'];
$parPres  = [];
if ($EMPRESA_ID !== null)   { $condPres[] = 'empresa_id = ?'; $parPres[] = $EMPRESA_ID; }
if ($VERSION_PRES !== null) { $condPres[] = 'version = ?';    $parPres[] = $VERSION_PRES; }
echo 'Presupuesto: empresa=' . ($EMPRESA_ID ?? '(todas)') . ' | version=' . ($VERSION_PRES ?? '(todas)') . "\n";

$presGrupoMes = [];
$stPres = $pdo->prepare("
    SELECT anio, mes, TRIM(familia) fam, TRIM(sub_familia) sub, SUM(venta) p
    FROM presupuestos
    WHERE " . implode(' AND ', $condPres) . "
    GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)
");
$stPres->execute($parPres);
$rows = $stPres->fetchAll();
foreach ($rows as $pr) {
    $key = claveGrupo($pr['fam'], $pr['sub']);
    $presGrupoMes[$key][sprintf('%04d-%02d', $pr['anio'], $pr['mes'])] = (float) $pr['p'];
}

/**
 * Prorratea el presupuesto MENSUAL de un grupo a SEMANAS por tasa diaria, entre dos fechas.
 * tasa_diaria(mes) = presupuesto_mes / días_del_mes ; presupuesto_semana = Σ tasa_diaria de sus días.
 * Los meses sin presupuesto (futuros/faltantes) se imputan con el mismo mes del año anterior.
 *
 * @return array [ 'yyyy-MM-dd' (lunes) => presupuesto_semana ]
 */
function presupuestoSemanal($key, $iniMonday, $finSunday, $presGrupoMes) {
    $semana = [];
    $d   = new DateTime($iniMonday);
    $fin = new DateTime($finSunday);
    while ($d <= $fin) {
        $mesKey  = $d->format('Y-m');
        $budMes  = $presGrupoMes[$key][$mesKey] ?? ($presGrupoMes[$key][mesMenos12($mesKey)] ?? 0.0);
        $diasMes = (int) $d->format('t');
        $tasaDia = $diasMes > 0 ? $budMes / $diasMes : 0.0;

        $offset = (int) $d->format('N') - 1;
        $lunes  = (clone $d)->modify($offset > 0 ? "-$offset days" : '+0 days')->format('Y-m-d');
        $semana[$lunes] = ($semana[$lunes] ?? 0.0) + $tasaDia;

        $d->modify('+1 day');
    }
    ksort($semana);
    return $semana;
}

// ---- 3) Escribir CSVs ------------------------------------------------------
$fG = fopen("$DIR/grupos.csv", 'w');            fputcsv($fG, ['grupo_id', 'familia', 'sub_familia']);
$fD = fopen("$DIR/grupos_demanda.csv", 'w');    fputcsv($fD, ['grupo_id', 'semana', 'demanda']);
$fP = fopen("$DIR/grupos_presupuesto.csv", 'w');fputcsv($fP, ['grupo_id', 'semana', 'presupuesto']);
$fA = fopen("$DIR/productos_demanda.csv", 'w'); fputcsv($fA, ['grupo_id', 'producto_codigo', 'producto_nombre', 'semana', 'demanda']);

foreach ($gruposInfo as $id => $g) {
    fputcsv($fG, [$id, $g[0], $g[1]]);
    $key = claveGrupo($g[0], $g[1]);

    // demanda del grupo por semana (ordenada)
    $semanas = $grupoDem[$id]; ksort($semanas);
    foreach ($semanas as $sem => $d) { fputcsv($fD, [$id, $sem, round($d, 4)]); }

    // presupuesto del grupo por semana: desde la primera semana de demanda hasta la última de forecast.
    $clavesDem = array_keys($grupoDem[$id]); sort($clavesDem);
    $iniMonday = $clavesDem[0];
    $presSem   = presupuestoSemanal($key, $iniMonday, $finForecastSunday, $presGrupoMes);
    foreach ($presSem as $sem => $val) { fputcsv($fP, [$id, $sem, round($val, 2)]); }

    // demanda por producto y semana
    foreach ($prodAgg[$id] as $cod => $info) {
        foreach ($info['semanas'] as $sem => $d) { fputcsv($fA, [$id, $cod, $info['nombre'], $sem, round($d, 4)]); }
    }
}
fclose($fG); fclose($fD); fclose($fP); fclose($fA);

// Estado de actividad del NEGOCIO (OITM.U_Sta_Art): activo = 'Activo'. Los 'Descontinuado'
// se excluirán del forecast (paso 3), redistribuyendo su participación a los activos.
// Criterio cambiado el 2026-08-17 desde validFor/frozenFor a U_Sta_Art: los flags estándar
// estaban desactualizados e incluían ~71 descontinuados en el pronóstico.
$estadoAct = [];
foreach ($sap->estadoActividadProductos() as $e) {
    $estadoAct[$e['ItemCode']] = (trim((string) ($e['U_Sta_Art'] ?? '')) === 'Activo') ? 1 : 0;
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
fputcsv($fM, ['ultimo_actual', $ultimaSemana]);
foreach ($forecast as $k => $s) { fputcsv($fM, ["forecast_$k", $s]); }
fclose($fM);

echo "CSVs escritos en assets/librerias/python/forecast/\n";
echo "Listo. Siguiente paso: assets/librerias/python/forecast_prophet.py\n";
