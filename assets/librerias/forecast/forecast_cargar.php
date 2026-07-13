<?php
/**
 * ============================================================================
 *  PASO 3/3 — Desagrega el forecast del grupo a PRODUCTOS e inserta en la tabla.
 * ----------------------------------------------------------------------------
 *  Lee de assets/librerias/python/forecast/:
 *    - grupos.csv            (grupo_id -> familia, sub_familia)
 *    - productos_demanda.csv (demanda real por producto/mes, para la participación)
 *    - grupos_forecast.csv   (pronóstico Prophet por grupo/mes)
 *    - meta.csv              (último mes real)
 *
 *  Participación de cada producto en su grupo = tasa media ponderada de los últimos
 *  12 meses (peso exponencial alfa^k, más peso a lo reciente), renormalizada entre
 *  los productos activos. Productos sin ventas recientes (discontinuados) -> 0.
 *
 *    demanda_forecast(producto, mes) = forecast_grupo(mes) * participacion(producto)
 *
 *  Recalcula toda la tabla forecast_x_producto.
 *  Ejecutar (después de los pasos 1 y 2):  http://localhost/manar/assets/librerias/forecast/forecast_cargar.php
 *  Requiere antes: sql/forecast_x_producto.sql
 * ============================================================================
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}
set_time_limit(0);

require_once __DIR__ . '/../../../config/conexion.php'; // $pdo (MySQL)

const ALFA    = 0.85;
const VENTANA = 12;

function ymAIdx($ym) { return ((int) substr($ym, 0, 4)) * 12 + ((int) substr($ym, 5, 2) - 1); }

/** Lee un CSV con cabecera y devuelve un arreglo de filas asociativas. */
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
$DIR = __DIR__ . '/../python/forecast';

try {
    // ---- Cargar CSVs -----------------------------------------------------
    $ultimoYm = '';
    foreach (leerCsv("$DIR/meta.csv") as $r) { if ($r['clave'] === 'ultimo_actual') { $ultimoYm = $r['valor']; } }
    if ($ultimoYm === '') { throw new RuntimeException('No se pudo leer meta.csv (¿corriste el paso 1?).'); }
    $ultIdx = ymAIdx($ultimoYm);

    $grupos = [];
    foreach (leerCsv("$DIR/grupos.csv") as $r) { $grupos[(int) $r['grupo_id']] = [$r['familia'], $r['sub_familia']]; }

    $prod = []; // [id][cod] => ['nombre'=>, 'meses'=>[ym=>dem]]
    foreach (leerCsv("$DIR/productos_demanda.csv") as $r) {
        $id = (int) $r['grupo_id']; $cod = $r['producto_codigo'];
        if (!isset($prod[$id][$cod])) { $prod[$id][$cod] = ['nombre' => $r['producto_nombre'], 'meses' => []]; }
        $prod[$id][$cod]['meses'][$r['ym']] = ($prod[$id][$cod]['meses'][$r['ym']] ?? 0.0) + (float) $r['demanda'];
    }

    $fc = []; // [id][ym] => ['yhat'=>, 'lo'=>, 'hi'=>, 'metodo'=>]
    foreach (leerCsv("$DIR/grupos_forecast.csv") as $r) {
        $fc[(int) $r['grupo_id']][$r['ym']] = [
            'yhat'   => (float) $r['yhat'],
            'lo'     => (float) $r['yhat_lower'],
            'hi'     => (float) $r['yhat_upper'],
            'metodo' => $r['metodo'],
        ];
    }

    // Estado de actividad: los productos inactivos en SAP se excluyen del forecast.
    $activo = [];
    foreach (leerCsv("$DIR/productos_estado.csv") as $r) { $activo[$r['producto_codigo']] = ((int) $r['activo'] === 1); }

    // Presupuesto $ del grupo por (familia, sub-familia, año-mes). Sirve para guardar la venta
    // presupuestada de cada producto = participacion × presupuesto del grupo. El cruce es por
    // NOMBRE normalizado (MAYÚSCULAS + trim), mismo criterio que el resto del pipeline.
    $presGrupoMes = [];
    foreach ($pdo->query("
        SELECT anio, mes, TRIM(familia) fam, TRIM(sub_familia) sub, SUM(venta) v
        FROM presupuestos
        WHERE familia IS NOT NULL AND sub_familia IS NOT NULL AND venta IS NOT NULL
        GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)
    ")->fetchAll() as $r) {
        $k = mb_strtoupper($r['fam']) . '||' . mb_strtoupper($r['sub']) . '||' . sprintf('%04d-%02d', $r['anio'], $r['mes']);
        $presGrupoMes[$k] = (float) $r['v'];
    }

    echo "Grupos: " . count($grupos) . " | con forecast: " . count($fc)
       . " | último mes real: $ultimoYm | estado productos: " . count($activo)
       . " | grupos-mes con presupuesto: " . count($presGrupoMes) . "\n";

    // ---- Insertar --------------------------------------------------------
    $pdo->exec("TRUNCATE TABLE forecast_x_producto");
    $ins = $pdo->prepare("
        INSERT INTO forecast_x_producto
            (anio, mes, familia, sub_familia, producto_codigo, producto_nombre,
             demanda_forecast, forecast_grupo, forecast_grupo_min, forecast_grupo_max,
             participacion, metodo, presupuesto_grupo, venta_presupuestada)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $pdo->beginTransaction();
    $filas = 0; $gruposUsados = 0; $inactivosExcluidos = 0;
    foreach ($grupos as $id => $g) {
        if (empty($prod[$id]) || empty($fc[$id])) { continue; }

        // Participación: tasa ponderada (12 meses hasta el último real) por producto ACTIVO.
        $rates = []; $nombres = [];
        foreach ($prod[$id] as $cod => $info) {
            if (isset($activo[$cod]) && !$activo[$cod]) { $inactivosExcluidos++; continue; } // inactivo en SAP -> fuera

            $np = 0.0; $peso = 0.0;
            foreach ($info['meses'] as $ym => $dem) {
                $k = $ultIdx - ymAIdx($ym);
                if ($k < 0 || $k >= VENTANA) { continue; }
                $w = pow(ALFA, $k);
                $np += $w * $dem; $peso += $w;
            }
            if ($peso <= 0) { continue; }
            $rate = $np / $peso;
            if ($rate <= 0) { continue; } // sin ventas recientes -> discontinuado
            $rates[$cod] = $rate; $nombres[$cod] = $info['nombre'];
        }
        $suma = array_sum($rates);
        if ($suma <= 0) { continue; }
        $gruposUsados++;

        foreach ($fc[$id] as $ym => $f) {
            $anio = (int) substr($ym, 0, 4);
            $mes  = (int) substr($ym, 5, 2);
            // Presupuesto $ del grupo en este mes (mismo para todos sus productos).
            $claveBud  = mb_strtoupper(trim((string) $g[0])) . '||' . mb_strtoupper(trim((string) $g[1])) . '||' . $ym;
            $presGrupo = $presGrupoMes[$claveBud] ?? null;

            foreach ($rates as $cod => $rate) {
                $part = $rate / $suma;
                $demF = $f['yhat'] * $part;
                // Venta presupuestada del producto = participacion × presupuesto del grupo ($).
                $ventaPres = ($presGrupo !== null) ? $presGrupo * $part : null;
                $ins->execute([
                    $anio, $mes, $g[0], $g[1], $cod, $nombres[$cod],
                    round($demF), // unidades enteras (>= 0.5 sube)
                    round($f['yhat'], 4),
                    round($f['lo'], 4),
                    round($f['hi'], 4),
                    round($part, 8),
                    $f['metodo'],
                    ($presGrupo  !== null) ? round($presGrupo, 2)  : null,
                    ($ventaPres  !== null) ? round($ventaPres, 2)  : null,
                ]);
                $filas++;
            }
        }
    }
    $pdo->commit();

    // ---- Resumen y muestra ----------------------------------------------
    echo "Grupos usados: $gruposUsados | Filas insertadas: $filas | Productos inactivos excluidos: $inactivosExcluidos\n\n";

    $tot = $pdo->query("SELECT COUNT(*) n, SUM(demanda_forecast) d FROM forecast_x_producto")->fetch();
    echo "TOTAL: {$tot['n']} filas | demanda_forecast total: " . number_format((float) $tot['d'], 0) . "\n\n";

    echo "Muestra (primer mes de forecast, top demanda):\n";
    echo str_pad('Año', 5) . str_pad('Mes', 4) . str_pad('Familia', 16) . str_pad('SubFamilia', 16)
       . str_pad('Producto', 13) . str_pad('Dem.Forecast', 14) . str_pad('Particip.', 11) . "Método\n";
    echo str_repeat('-', 90) . "\n";
    $q = $pdo->query("
        SELECT anio, mes, familia, sub_familia, producto_codigo, demanda_forecast, participacion, metodo
        FROM forecast_x_producto
        WHERE (anio, mes) = (SELECT anio, mes FROM forecast_x_producto ORDER BY anio, mes LIMIT 1)
        ORDER BY demanda_forecast DESC LIMIT 15
    ");
    foreach ($q->fetchAll() as $m) {
        echo str_pad($m['anio'], 5) . str_pad($m['mes'], 4)
           . str_pad(mb_substr($m['familia'], 0, 15), 16) . str_pad(mb_substr($m['sub_familia'], 0, 15), 16)
           . str_pad(mb_substr($m['producto_codigo'], 0, 12), 13)
           . str_pad(number_format((float) $m['demanda_forecast'], 2), 14)
           . str_pad(number_format((float) $m['participacion'], 6), 11)
           . $m['metodo'] . "\n";
    }
    echo "\nListo.\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "(¿Ejecutaste sql/forecast_x_producto.sql y los pasos 1 y 2?)\n";
}
