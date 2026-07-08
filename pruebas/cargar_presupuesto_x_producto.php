<?php
/**
 * ============================================================================
 *  SCRIPT DE PRUEBA — Llena la tabla `presupuesto_x_producto`.
 * ----------------------------------------------------------------------------
 *  Estima la demanda POR PRODUCTO repartiendo el presupuesto ($ venta neta) del
 *  grupo (familia, sub-familia) según la participación histórica de cada producto.
 *
 *  REGLAS TEMPORALES:
 *   - Se estima solo el presupuesto de meses ANTERIORES al mes actual
 *     (no el mes en curso ni futuros).
 *   - Por cada mes-año se estiman SOLO los productos que se VENDIERON ese mes.
 *     Un producto que aún no existía o que se descontinuó no aparece en los
 *     meses sin venta (se excluye automáticamente).
 *
 *  MÉTODO (ventana MÓVIL de 12 meses, pesos exponenciales, α = 0.85):
 *    Para el mes estimado M y cada producto p del grupo g:
 *      candidatos    = productos con venta neta > 0 en M
 *      peso del mes:   w = α^k     (k = meses de M hacia atrás; 0..11, M incluido)
 *      neto_pond_p   = Σ w·neto_p  (12 meses hasta M)
 *      cant_pond_p   = Σ w·cantidad_p
 *      participación = neto_pond_p / Σ neto_pond (solo candidatos)   -> renormalizada
 *      precio        = neto_pond_p / cant_pond_p
 *      venta_est $   = presupuesto_g(M) × participación
 *      cantidad est. = venta_est / precio
 *    Así, dentro de un mes, la venta estimada de los productos vendidos suma el
 *    presupuesto del grupo (reparto completo entre los activos ese mes).
 *
 *  Fuentes:
 *    - Ventas reales por producto/mes: SAP (SQL Server) vía la consulta V3.
 *    - Presupuesto por (familia, sub-familia, año, mes): MySQL tabla presupuestos
 *      (se SUMA sobre canal/sub_canal). Unión de grupos por NOMBRE normalizado.
 *
 *  Ejecutar en el navegador local:  http://localhost/manar/pruebas/cargar_presupuesto_x_producto.php
 *  (Antes debe existir la tabla: sql/presupuesto_x_producto.sql)
 *
 *  OJO: script de pruebas, SIN control de acceso. No exponer en producción.
 * ============================================================================
 */

// --- Guardia mínima: solo desde el equipo local ---------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo disponible localmente.');
}

set_time_limit(0); // el barrido histórico puede tardar

require_once __DIR__ . '/../config/conexion.php';           // $pdo       (MySQL)
require_once __DIR__ . '/../config/conexion_sqlserver.php';  // $pdoSqlsrv (SQL Server / SAP)
require_once __DIR__ . '/../models/consultas_sap_model.php'; // class ConsultaSap (consulta V3)

// --- Parámetros del método -------------------------------------------------
const ALFA    = 0.85; // factor de decaimiento (más peso a lo reciente)
const VENTANA = 12;   // meses de la ventana móvil

// Margen de seguridad (uplift): sube la cantidad estimada para que quede POR ENCIMA
// de la real. El método, sin margen, queda ~11% por debajo (rezago de productos/ventas
// en crecimiento). Con 1.075 el agregado queda ~5-7% arriba. Súbelo/bájalo según cuánto
// colchón quieras. OJO: no garantiza superar CADA mes (lo real tiene picos), sí el
// agregado y la mayoría de los casos.
const MARGEN_SEGURIDAD = 1.075;

/** Clave normalizada del grupo (familia + sub-familia) para cruzar SAP ↔ presupuesto. */
function claveGrupo($familia, $subFamilia) {
    return mb_strtoupper(trim((string) $familia)) . '||' . mb_strtoupper(trim((string) $subFamilia));
}

/** Índice de mes (año*12 + mes-1) a partir de 'yyyy-MM'. */
function idxMes($ym) {
    $a = (int) substr($ym, 0, 4);
    $m = (int) substr($ym, 5, 2);
    return $a * 12 + ($m - 1);
}

/** 'yyyy-MM' a partir de un índice de mes (inverso de idxMes). */
function ymDeIdx($idx) {
    return sprintf('%04d-%02d', intdiv($idx, 12), ($idx % 12) + 1);
}

header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset='utf-8'><title>Cargar presupuesto_x_producto</title>";
echo "<pre style='font:13px/1.55 ui-monospace,Consolas,monospace; padding:18px; max-width:1200px'>";

function linea($msg = '') { echo htmlspecialchars($msg) . "\n"; }

try {
    // ---------------------------------------------------------------------
    // 1) Corte: solo se estima hasta el mes ANTERIOR al actual.
    // ---------------------------------------------------------------------
    $hoy     = new DateTime();
    $anioHoy = (int) $hoy->format('Y');
    $mesHoy  = (int) $hoy->format('n');
    // Última fecha de ventas a considerar: fin del mes anterior al actual.
    $finVentas = (clone $hoy)->modify('first day of this month')->modify('-1 day')->format('Y-m-d');

    linea("=== Cargar presupuesto_x_producto ===");
    linea("Alfa = " . ALFA . "   |   Ventana móvil = " . VENTANA . " meses   |   Margen seguridad = x" . MARGEN_SEGURIDAD);
    linea("Corte de estimación: solo presupuesto anterior a "
        . sprintf('%04d-%02d', $anioHoy, $mesHoy) . " (excluye el mes actual y futuros)");
    linea("Ventas reales consideradas hasta: $finVentas");
    linea();

    // ---------------------------------------------------------------------
    // 2) Ventas reales por producto y mes (consulta V3), toda la historia
    //    hasta el mes anterior. Se arma una serie mensual por producto.
    // ---------------------------------------------------------------------
    $sap    = new ConsultaSap($pdoSqlsrv);
    $ventas = $sap->facturasNotasCreditoPorArticulo('', $finVentas);
    linea("Filas de ventas (V3): " . count($ventas));

    // serie[grupoKey][codigo] = ['nombre','fam','sub','meses'=>[ 'yyyy-MM'=>['neto','cant'] ]]
    $serie = [];
    foreach ($ventas as $r) {
        $g   = claveGrupo($r['Familia'], $r['SubFamilia']);
        $cod = $r['CodArticulo'];
        $ym  = $r['FechaDocumento']; // 'yyyy-MM'
        if (!isset($serie[$g][$cod])) {
            $serie[$g][$cod] = [
                'nombre' => $r['Articulo'],
                'fam'    => trim((string) $r['Familia']),
                'sub'    => trim((string) $r['SubFamilia']),
                'meses'  => [],
            ];
        }
        if (!isset($serie[$g][$cod]['meses'][$ym])) {
            $serie[$g][$cod]['meses'][$ym] = ['neto' => 0.0, 'cant' => 0.0];
        }
        $serie[$g][$cod]['meses'][$ym]['neto'] += (float) $r['TotalNeto'];
        $serie[$g][$cod]['meses'][$ym]['cant'] += (float) $r['Cantidad'];
    }
    linea("Grupos con ventas: " . count($serie));

    // Venta real por grupo-mes (suma de los productos del grupo) -> numerador del factor.
    $realGrupoMes = [];
    foreach ($serie as $g => $prods) {
        foreach ($prods as $info) {
            foreach ($info['meses'] as $ym => $v) {
                $realGrupoMes[$g][$ym] = ($realGrupoMes[$g][$ym] ?? 0.0) + $v['neto'];
            }
        }
    }

    // Índice de estacionalidad por grupo y mes-del-año (sobre TODA la historia):
    //   s(g,mes) = neto medio del grupo en ese mes-del-año / neto medio mensual del grupo.
    // Sirve para desestacionalizar la tasa de cada producto antes de la participación:
    // pega más fuerte en los nuevos (datos concentrados en pocas temporadas) y casi nada
    // en los establecidos (sus meses cubren todo el año y se compensan). Acotado a [0.3, 3].
    $estacion = [];
    foreach ($realGrupoMes as $g => $meses) {
        $sumMes = array_fill(1, 12, 0.0);
        $cntMes = array_fill(1, 12, 0);
        $total  = 0.0; $n = 0;
        foreach ($meses as $ym => $neto) {
            $m = (int) substr($ym, 5, 2);
            $sumMes[$m] += $neto; $cntMes[$m]++;
            $total += $neto; $n++;
        }
        $avgAll = ($n > 0) ? $total / $n : 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $avgM = ($cntMes[$m] > 0) ? $sumMes[$m] / $cntMes[$m] : $avgAll;
            $s    = ($avgAll > 0) ? $avgM / $avgAll : 1.0;
            $estacion[$g][$m] = min(3.0, max(0.3, $s));
        }
    }
    linea("Índice de estacionalidad calculado para " . count($estacion) . " grupos.");
    linea();

    // ---------------------------------------------------------------------
    // 3) Presupuesto agregado a (año, mes, familia, sub-familia), solo meses
    //    anteriores al actual.
    // ---------------------------------------------------------------------
    $sqlPres = "
        SELECT anio, mes,
               TRIM(familia)     AS familia,
               TRIM(sub_familia) AS sub_familia,
               SUM(venta)        AS presupuesto
        FROM presupuestos
        WHERE familia IS NOT NULL AND sub_familia IS NOT NULL AND venta IS NOT NULL
          AND (anio < ? OR (anio = ? AND mes < ?))
        GROUP BY anio, mes, TRIM(familia), TRIM(sub_familia)
    ";
    $stmtPres = $pdo->prepare($sqlPres);
    $stmtPres->execute([$anioHoy, $anioHoy, $mesHoy]);
    $presupuestos = $stmtPres->fetchAll();
    linea("Grupos-mes de presupuesto (hasta el mes anterior): " . count($presupuestos));

    // Presupuesto por grupo-mes -> denominador del factor de cumplimiento.
    // De paso se acumula el total y el nombre por grupo, para la conciliación final.
    $presGrupoMes = [];
    $presTotal    = [];
    $presNombre   = [];
    foreach ($presupuestos as $pr) {
        $g  = claveGrupo($pr['familia'], $pr['sub_familia']);
        $ym = sprintf('%04d-%02d', $pr['anio'], $pr['mes']);
        $presGrupoMes[$g][$ym] = (float) $pr['presupuesto'];
        $presTotal[$g] = ($presTotal[$g] ?? 0.0) + (float) $pr['presupuesto'];
        if (!isset($presNombre[$g])) {
            $presNombre[$g] = ['fam' => trim((string) $pr['familia']), 'sub' => trim((string) $pr['sub_familia'])];
        }
    }

    // ---------------------------------------------------------------------
    // 4) Reparto e inserción. Recalcula toda la tabla desde cero.
    //    Por cada grupo-mes: ventana móvil de 12 meses hasta ese mes y
    //    reparto solo entre los productos vendidos ese mes.
    // ---------------------------------------------------------------------
    $pdo->exec("TRUNCATE TABLE presupuesto_x_producto");
    $ins = $pdo->prepare("
        INSERT INTO presupuesto_x_producto
            (anio, mes, familia, sub_familia, producto_codigo, producto_nombre,
             presupuesto_neto, neto_pond_producto, neto_pond_grupo, cantidad_pond_producto,
             participacion, precio_unitario, factor_cumplimiento, venta_estimada, cantidad_estimada,
             cantidad_real, diferencia,
             alfa, ventana_meses)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $pdo->beginTransaction();
    $filas = 0;
    $periodosConEstim = 0;      // grupo-mes que produjeron estimación
    $periodosSinGrupo = 0;      // grupo del presupuesto sin ventas (nombre no cruza / sin historia)
    $periodosSinVenta = 0;      // grupo con ventas pero ningún producto vendido ese mes
    $candDescartados  = 0;      // producto vendido ese mes pero sin neto/cant ponderado válido

    foreach ($presupuestos as $pr) {
        $g = claveGrupo($pr['familia'], $pr['sub_familia']);
        if (empty($serie[$g])) { $periodosSinGrupo++; continue; }

        $pIdx  = ((int) $pr['anio']) * 12 + ((int) $pr['mes'] - 1);
        $ymBud = sprintf('%04d-%02d', $pr['anio'], $pr['mes']);

        // Candidatos: productos del grupo con venta neta positiva en ESE mes.
        $cands = [];
        foreach ($serie[$g] as $cod => $info) {
            $ventaMes = $info['meses'][$ymBud] ?? null;
            if ($ventaMes === null || $ventaMes['cant'] <= 0) { continue; } // no se vendió ese mes

            // Ventana móvil: 12 meses hasta M (M incluido, k=0). Se usa la TASA
            // media por mes activo (Σw·neto / Σw), NO la suma acumulada, para no
            // penalizar a los productos con poca historia (nuevos) frente a los
            // establecidos: ambos se comparan por su nivel mensual, no por cuánta
            // historia arrastran.
            //
            // La base de la PARTICIPACIÓN se desestacionaliza dividiendo el neto de
            // cada mes por el índice del grupo s(g,mes): así un producto que lanzó en
            // temporada alta/baja no queda sesgado. El precio y el nivel real se
            // guardan SIN desestacionalizar (valores reales).
            $netoPondRaw = 0.0; $cantPondRaw = 0.0; $netoPondDes = 0.0; $pesoActivo = 0.0;
            foreach ($info['meses'] as $ym => $v) {
                $k = $pIdx - idxMes($ym);
                if ($k < 0 || $k >= VENTANA) { continue; }
                $w = pow(ALFA, $k);
                $s = $estacion[$g][(int) substr($ym, 5, 2)] ?? 1.0;
                $netoPondRaw += $w * $v['neto'];
                $cantPondRaw += $w * $v['cant'];
                $netoPondDes += $w * ($v['neto'] / $s); // desestacionalizado
                $pesoActivo  += $w;
            }
            if ($pesoActivo <= 0) { $candDescartados++; continue; }
            $netoRate    = $netoPondRaw / $pesoActivo; // neto real medio por mes activo
            $cantRate    = $cantPondRaw / $pesoActivo; // cantidad real media por mes activo
            $netoRateDes = $netoPondDes / $pesoActivo; // neto desestacionalizado -> participación
            if ($netoRate <= 0 || $cantRate <= 0 || $netoRateDes <= 0) { $candDescartados++; continue; }

            $cands[] = [
                'cod'       => $cod,
                'nombre'    => $info['nombre'],
                'fam'       => $info['fam'],
                'sub'       => $info['sub'],
                'neto_pond' => $netoRate,    // tasa real (para precio y referencia)
                'cant_pond' => $cantRate,    // tasa real de cantidad
                'des'       => $netoRateDes, // base de la participación (desestacionalizada)
                'cant_real' => $ventaMes['cant'], // cantidad REAL vendida ese mes (V3)
            ];
        }

        if (empty($cands)) { $periodosSinVenta++; continue; }

        // Denominador de la participación: suma de las tasas DESESTACIONALIZADAS de los
        // candidatos (reparto completo del presupuesto). El nivel real del grupo se guarda
        // aparte como referencia (Σ tasa real).
        $denom = 0.0; $netoGrupoRef = 0.0;
        foreach ($cands as $c) { $denom += $c['des']; $netoGrupoRef += $c['neto_pond']; }
        if ($denom <= 0) { $periodosSinVenta++; continue; }

        // Factor de cumplimiento del grupo: cuánto se realizó vs. lo presupuestado,
        // ponderado sobre los 12 meses hasta M (solo meses con presupuesto).
        $numF = 0.0; $denF = 0.0;
        for ($k = 0; $k < VENTANA; $k++) {
            $ymK = ymDeIdx($pIdx - $k);
            if (!isset($presGrupoMes[$g][$ymK])) { continue; } // solo meses presupuestados
            $w    = pow(ALFA, $k);
            $denF += $w * $presGrupoMes[$g][$ymK];
            $numF += $w * ($realGrupoMes[$g][$ymK] ?? 0.0);
        }
        $factor = ($denF > 0) ? max(0.0, $numF / $denF) : 1.0;

        $presN = (float) $pr['presupuesto'];
        foreach ($cands as $c) {
            $part     = $c['des'] / $denom;                        // 0..1 (desestacionalizada, renormalizada)
            $precio   = $c['neto_pond'] / $c['cant_pond'];         // precio real
            $ventaEst = $presN * $factor * $part * MARGEN_SEGURIDAD; // $ ajustado por cumplimiento + margen
            $cantEst  = $precio > 0 ? $ventaEst / $precio : 0;     // unidades

            // Comparación contra lo real (V3): cantidad real del mes y diferencia % con decimales.
            $cantReal = (float) $c['cant_real'];
            $difPct   = ($cantReal != 0) ? ($cantEst - $cantReal) / $cantReal * 100 : null; // (est-real)/real %

            $ins->execute([
                (int) $pr['anio'], (int) $pr['mes'], $c['fam'], $c['sub'],
                $c['cod'], $c['nombre'],
                round($presN, 2),
                round($c['neto_pond'], 2),
                round($netoGrupoRef, 2),
                round($c['cant_pond'], 4),
                round($part, 8),
                round($precio, 4),
                round($factor, 6),
                round($ventaEst, 2),
                round($cantEst, 4),
                round($cantReal, 4),
                ($difPct !== null) ? round($difPct, 4) : null,
                ALFA, VENTANA,
            ]);
            $filas++;
        }
        $periodosConEstim++;
    }
    $pdo->commit();

    // ---------------------------------------------------------------------
    // 5) Resumen y muestra.
    // ---------------------------------------------------------------------
    linea();
    linea("Grupos-mes con estimación : $periodosConEstim");
    linea("Grupos-mes sin cruce/historia (grupo sin ventas) : $periodosSinGrupo");
    linea("Grupos-mes sin productos vendidos ese mes         : $periodosSinVenta");
    linea("Candidatos descartados (sin neto/cant ponderado)  : $candDescartados");
    linea("Filas insertadas en presupuesto_x_producto        : $filas");
    linea();

    $tot = $pdo->query("
        SELECT COUNT(*) AS n, SUM(venta_estimada) AS v, SUM(cantidad_estimada) AS c
        FROM presupuesto_x_producto
    ")->fetch();
    linea("TOTAL tabla -> filas: {$tot['n']}   venta_estimada: "
        . number_format((float) $tot['v'], 2) . "   cantidad_estimada: "
        . number_format((float) $tot['c'], 2));
    linea();

    linea("Muestra (15 filas más recientes):");
    linea(sprintf("%-4s %-3s %-16s %-16s %-12s %8s %11s %11s %10s",
        'Año', 'Mes', 'Familia', 'SubFamilia', 'Producto',
        'Factor', 'Cant.Est.', 'Cant.Real', 'Dif.%'));
    linea(str_repeat('-', 108));
    $muestra = $pdo->query("
        SELECT anio, mes, familia, sub_familia, producto_codigo,
               factor_cumplimiento, cantidad_estimada, cantidad_real, diferencia
        FROM presupuesto_x_producto
        ORDER BY anio DESC, mes DESC, familia, sub_familia, venta_estimada DESC
        LIMIT 15
    ")->fetchAll();
    foreach ($muestra as $m) {
        linea(sprintf("%-4s %-3s %-16s %-16s %-12s %8.3f %11.2f %11.2f %9.2f%%",
            $m['anio'], $m['mes'],
            mb_substr((string) $m['familia'], 0, 16),
            mb_substr((string) $m['sub_familia'], 0, 16),
            mb_substr((string) $m['producto_codigo'], 0, 12),
            (float) $m['factor_cumplimiento'],
            (float) $m['cantidad_estimada'],
            (float) $m['cantidad_real'],
            (float) $m['diferencia']));
    }

    // ---------------------------------------------------------------------
    // 6) Conciliación de grupos (se actualiza sola en cada corrida):
    //    - presupuesto que NO cruza con ventas (no se reparte).
    //    - ventas que NO tienen presupuesto (sin cobertura).
    //    Rango: los meses estimados (anteriores al mes actual).
    // ---------------------------------------------------------------------
    linea();
    linea("======================================================================");
    linea(" CONCILIACIÓN DE GRUPOS (familia / sub-familia) — rango estimado");
    linea("======================================================================");

    // Presupuesto sin ventas: grupos presupuestados que no tienen ninguna venta.
    $presSinVenta = [];
    foreach ($presTotal as $g => $tot) {
        if (!isset($serie[$g])) { $presSinVenta[$g] = $tot; }
    }
    arsort($presSinVenta);
    linea();
    linea("PRESUPUESTO SIN VENTAS (" . count($presSinVenta) . ") — presupuesto que NO se reparte:");
    if (!$presSinVenta) { linea("  (ninguno)"); }
    foreach ($presSinVenta as $g => $tot) {
        linea(sprintf("  %-14s / %-24s  presupuesto=%s",
            $presNombre[$g]['fam'], $presNombre[$g]['sub'], number_format($tot)));
    }

    // Ventas sin presupuesto: grupos que venden pero no tienen ninguna fila de presupuesto.
    $ventaSinPres = [];
    foreach ($serie as $g => $prods) {
        if (isset($presTotal[$g])) { continue; }
        $tot = 0.0;
        foreach ($realGrupoMes[$g] as $neto) { $tot += $neto; }
        $first = reset($prods);
        $ventaSinPres[$g] = ['tot' => $tot, 'fam' => $first['fam'], 'sub' => $first['sub']];
    }
    uasort($ventaSinPres, fn($a, $b) => $b['tot'] <=> $a['tot']);
    linea();
    linea("VENTAS SIN PRESUPUESTO (" . count($ventaSinPres) . ") — ventas sin cobertura de presupuesto:");
    if (!$ventaSinPres) { linea("  (ninguno)"); }
    foreach ($ventaSinPres as $v) {
        linea(sprintf("  %-14s / %-24s  venta_real=%s",
            $v['fam'], $v['sub'], number_format($v['tot'])));
    }

    linea();
    linea("Listo.");

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    linea();
    linea("ERROR: " . $e->getMessage());
    linea("(Si la tabla no existe, ejecuta primero sql/presupuesto_x_producto.sql)");
}

echo "</pre>";
