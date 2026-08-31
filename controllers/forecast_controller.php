<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/forecast_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('forecast', $action);

// Helpers de semana ISO (para el gráfico semanal).
/** Lunes ISO ('yyyy-MM-dd') de la semana que contiene la fecha. */
function fcLunes($ymd) {
    return date('Y-m-d', strtotime('monday this week', strtotime($ymd . ' 12:00:00')));
}
/** Índice de semana relativo a un lunes de referencia (para ponderar la ventana reciente). */
function fcSemIdx($mondayYmd) {
    return (int) round((strtotime($mondayYmd . ' 12:00:00') - strtotime('2020-01-06 12:00:00')) / 604800);
}

/**
 * Arma el prompt (en español) para que la IA describa el forecast de un producto a partir de
 * los "hechos" ya calculados. Le da contexto de qué significan los datos y le prohíbe inventar.
 */
function promptResumenForecast(array $h) {
    return
        "Eres un analista de demanda. A partir de estos datos del PRONÓSTICO (forecast) semanal " .
        "de un producto, redacta un RESUMEN en ESPAÑOL de 2 a 4 frases, en prosa (sin viñetas), " .
        "claro para alguien de negocio (no técnico).\n" .
        "IMPORTANTE: son cantidades PRONOSTICADAS a FUTURO, NO ventas reales. Usa lenguaje de " .
        "expectativa (se proyecta, se espera, se estima), nunca en pasado (evita 'se vendió').\n" .
        "Explica: la magnitud esperada (total y promedio por semana), la TENDENCIA, y algún pico " .
        "relevante. Menciona brevemente la confianza si la calidad es baja. NO inventes cifras: " .
        "usa SOLO los valores dados. Responde SIEMPRE en español.\n\n" .
        "Notas de contexto: las unidades son cantidades del producto; el horizonte es de " .
        "'semanas_forecast' semanas (semanal, por lunes ISO); 'usa_presupuesto' indica si el " .
        "presupuesto se usó como apoyo del cálculo; 'calidad' resume la confiabilidad.\n\n" .
        "Datos (JSON):\n" . json_encode($h, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

switch ($action) {

    case 'listar':

        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Filtro del buscador (#consulta): por nombre o código de producto.
        $consulta = trim($_GET['consulta'] ?? '');

        // Filtros de la tabla.
        $familia    = trim($_GET['familia'] ?? '');       // '' = todas
        $subFamilia = trim($_GET['sub_familia'] ?? '');   // '' = todas
        $calidad    = trim($_GET['calidad'] ?? '');       // '' = todas (Alta/Media/Baja)

        // Ordenamiento multi-columna (índice de DataTables -> nombre lógico de la vista agrupada).
        // Índice 4 ("Cálculo Forecast") es placeholder sin orden: null mantiene alineados los índices.
        $columnas = ['producto_codigo', 'producto_nombre', 'familia', 'sub_familia', 'version', null, null, 'total_forecast', 'forecast_sig_semana'];
        $ordenes  = [];
        if (isset($_GET['order']) && is_array($_GET['order'])) {
            foreach ($_GET['order'] as $o) {
                $idx = isset($o['column']) ? (int) $o['column'] : -1;
                if (isset($columnas[$idx]) && $columnas[$idx] !== null) {
                    $ordenes[] = ['col' => $columnas[$idx], 'dir' => $o['dir'] ?? 'asc'];
                }
            }
        }

        try {
            $forecastModel  = new Forecast($pdo, $_SESSION['empresa_id'] ?? null);
            $totalRegistros = $forecastModel->contarTodos();
            $totalFiltrados = $forecastModel->contarFiltrados($consulta, $familia, $subFamilia, $calidad);
            $datos          = $forecastModel->listarPagina($consulta, $familia, $subFamilia, $calidad, $ordenes, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[FORECAST] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los registros.'
            ]);
        }
        exit;

    case 'filtros':

        // Valores para los filtros de Familia y Sub-Familia.
        try {
            $forecastModel = new Forecast($pdo, $_SESSION['empresa_id'] ?? null);
            echo json_encode([
                'status'       => 'success',
                'familias'     => $forecastModel->familiasDisponibles(),
                'sub_familias' => $forecastModel->subFamiliasDisponibles()
            ]);
        } catch (PDOException $e) {
            error_log('[FORECAST][filtros] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'familias' => [], 'sub_familias' => []]);
        }
        exit;

    case 'grafico_producto':

        // Serie SEMANAL del producto para el gráfico: demanda/venta reales (SAP, por día agregado
        // a semana ISO) + Cantidad Forecast por semana y su demanda valorizada en $ (MySQL).
        require_once __DIR__ . '/../config/conexion_sqlserver.php';
        require_once __DIR__ . '/../models/consultas_sap_model.php';

        $itemCode = trim($_GET['itemcode'] ?? '');
        if ($itemCode === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
            exit;
        }

        try {
            $model = new ConsultaSap($pdoSqlsrv);

            // Historia real por DÍA (SAP) agregada a SEMANA ISO (clave = lunes). La clave temporal
            // del gráfico (FechaDocumento) es el lunes 'yyyy-MM-dd'.
            $porSemana = []; // [lunes] => ['Demanda'=>, 'Neto'=>]
            foreach ($model->demandaDiariaProducto($itemCode) as $d) {
                $lun = fcLunes($d['Fecha']);
                if (!isset($porSemana[$lun])) { $porSemana[$lun] = ['Demanda' => 0.0, 'Neto' => 0.0]; }
                $porSemana[$lun]['Demanda'] += (float) $d['Demanda'];
                $porSemana[$lun]['Neto']    += (float) $d['Neto'];
            }
            ksort($porSemana);

            // Desglose de ventas por CLIENTE y semana (para el tooltip de "Venta Neta").
            // [lunes][codCliente] => ['cliente'=>nombre, 'neto'=>$]
            $cliPorSemana = [];
            foreach ($model->ventasClienteDiariaProducto($itemCode) as $c) {
                $lun = fcLunes($c['Fecha']);
                $key = (string) $c['CodCliente'];
                if (!isset($cliPorSemana[$lun][$key])) {
                    $nombre = trim((string) $c['Cliente']) !== '' ? $c['Cliente'] : $c['CodCliente'];
                    $cliPorSemana[$lun][$key] = ['cliente' => $nombre, 'neto' => 0.0, 'cantidad' => 0.0];
                }
                $cliPorSemana[$lun][$key]['neto']     += (float) $c['Neto'];
                $cliPorSemana[$lun][$key]['cantidad'] += (float) $c['Cantidad'];
            }

            $datos = [];
            foreach ($porSemana as $lun => $v) {
                // Top 3 clientes de la semana, por dos criterios: por venta ($) para el tooltip de
                // Venta Neta, y por unidades para el tooltip de Demanda Histórica.
                $base = array_values($cliPorSemana[$lun] ?? []);

                $porNeto = $base;
                usort($porNeto, function ($a, $b) { return $b['neto'] <=> $a['neto']; });
                $clientesVenta = array_slice($porNeto, 0, 3);

                $porCantidad = $base;
                usort($porCantidad, function ($a, $b) { return $b['cantidad'] <=> $a['cantidad']; });
                $clientesDemanda = array_slice($porCantidad, 0, 3);

                $datos[] = [
                    'FechaDocumento'  => $lun,
                    'Demanda'         => $v['Demanda'],
                    'Neto'            => $v['Neto'],
                    'Clientes'        => $clientesVenta,
                    'ClientesDemanda' => $clientesDemanda,
                ];
            }

            // Precio unitario realizado (neto/cantidad) ponderado a las últimas 52 semanas de venta
            // real (α=0.96, más peso a lo reciente). Independiente del presupuesto -> valorizar la
            // demanda con él NO es circular. Sirve para llevar la demanda futura (unidades) a $.
            $precio = null;
            if (!empty($datos)) {
                $ultimo = 0;
                foreach ($datos as $d) { $ultimo = max($ultimo, fcSemIdx($d['FechaDocumento'])); }
                $sumNeto = 0.0; $sumCant = 0.0;
                foreach ($datos as $d) {
                    $k = $ultimo - fcSemIdx($d['FechaDocumento']);
                    if ($k < 0 || $k >= 52) { continue; }   // solo la ventana de 52 semanas
                    $w = pow(0.96, $k);
                    $sumNeto += $w * (float) $d['Neto'];
                    $sumCant += $w * (float) $d['Demanda'];
                }
                if ($sumCant > 0) { $precio = $sumNeto / $sumCant; }
            }

            // Forecast SEMANAL (MySQL): Cantidad Forecast valorizada en $ + detalle por semana.
            //   - Tipo 'Forecast'  -> demanda_forecast (forecast_x_producto).
            //   - Tipo 'Histórico' -> la MISMA demanda semanal que dibuja el gráfico ($datos).
            $forecast = [];
            $detalle  = [];
            try {
                // Ajustes manuales por semana (tabla independiente que sobrevive a las re-proyecciones).
                require_once __DIR__ . '/../models/forecast_ajuste_model.php';
                $mapaAjustes = (new ForecastAjuste($pdo, $_SESSION['empresa_id'] ?? null))->mapaPorProducto($itemCode);

                $st = $pdo->prepare("
                    SELECT semana_inicio, demanda_forecast AS df
                    FROM forecast_x_producto
                    WHERE producto_codigo = ? AND empresa_id = ? ORDER BY semana_inicio
                ");
                $st->execute([$itemCode, $_SESSION['empresa_id'] ?? null]);
                foreach ($st->fetchAll() as $r) {
                    $sem = (string) $r['semana_inicio'];   // lunes ISO
                    $df  = (float) $r['df'];
                    $ts  = strtotime($sem);
                    $forecast[] = [
                        'ym'                => $sem,       // clave temporal del gráfico (lunes)
                        'DemandaForecast'   => $df,
                        'DemandaValorizada' => ($precio !== null) ? $df * $precio : null,
                    ];
                    $detalle[] = [
                        'semana'            => $sem,
                        'tipo'              => 'Forecast',
                        'demanda_historica' => null,
                        'demanda_forecast'  => $df,
                        'iso_year'          => (int) date('o', $ts),   // año ISO (para guardar el ajuste)
                        'iso_week'          => (int) date('W', $ts),   // semana ISO
                        'cantidad_ajustada' => $mapaAjustes[$sem] ?? null,
                    ];
                }

                foreach ($datos as $h) {
                    $detalle[] = [
                        'semana'            => $h['FechaDocumento'],
                        'tipo'              => 'Histórico',
                        'demanda_historica' => ($h['Demanda'] !== null) ? (float) $h['Demanda'] : null,
                        'demanda_forecast'  => null,
                    ];
                }

                // Orden por semana (dentro de la misma, 'Forecast' antes que 'Histórico').
                usort($detalle, function ($a, $b) {
                    return [$a['semana'], $a['tipo']] <=> [$b['semana'], $b['tipo']];
                });
            } catch (Throwable $e) {
                error_log('[FORECAST][grafico] ' . $e->getMessage());
                $forecast = [];
                $detalle  = [];
            }

            echo json_encode(['status' => 'success', 'data' => $datos, 'forecast' => $forecast, 'detalle' => $detalle]);
        } catch (PDOException $e) {
            error_log('[FORECAST] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener la demanda del producto.']);
        }
        exit;

    case 'describir_grafico':

        // Resumen en lenguaje natural (IA local / Ollama) del forecast del producto.
        $itemCode = trim($_GET['itemcode'] ?? '');
        if ($itemCode === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
            exit;
        }
        if (empty($_SESSION['empresa_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'No hay una empresa activa seleccionada.']);
            exit;
        }

        try {
            $model  = new Forecast($pdo, $_SESSION['empresa_id']);
            $hechos = $model->hechosForecastProducto($itemCode);
            if (!$hechos) {
                echo json_encode(['status' => 'error', 'message' => 'Este producto no tiene forecast para la empresa activa.']);
                exit;
            }

            // Cuántas semanas tienen ajuste manual (contexto extra para la descripción).
            require_once __DIR__ . '/../models/forecast_ajuste_model.php';
            $ajustes = (new ForecastAjuste($pdo, $_SESSION['empresa_id']))->mapaPorProducto($itemCode);
            $hechos['semanas_con_ajuste_manual'] = is_array($ajustes) ? count($ajustes) : 0;

            require_once __DIR__ . '/../includes/ia_cliente.php';
            $r = iaGenerarTexto(promptResumenForecast($hechos));

            if (!$r['ok']) {
                echo json_encode(['status' => 'error', 'message' => $r['error']]);
                exit;
            }
            echo json_encode(['status' => 'success', 'resumen' => $r['texto']]);
        } catch (Throwable $e) {
            error_log('[FORECAST][describir] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al generar el resumen.']);
        }
        exit;

    case 'parametros_mrp':

        // Parámetros para MRP del producto (bodega 010) desde SAP / SQL Server (CLPRDMANAR).
        require_once __DIR__ . '/../config/conexion_sqlserver.php';
        require_once __DIR__ . '/../models/consultas_sap_model.php';

        $itemCode = trim($_GET['itemcode'] ?? '');
        if ($itemCode === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
            exit;
        }

        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->parametrosMrpProducto($itemCode);

            // El "En Mano" (OnHand) se reemplaza por el stock VIGENTE del WMS (otra conexión).
            require_once __DIR__ . '/../config/conexion_wms.php';        // $pdoWms
            require_once __DIR__ . '/../models/consultas_wms_model.php'; // ConsultaWms
            $stockWms = (new ConsultaWms($pdoWms, codigoEmpresaWms($pdo)))->stockPorProductoMap();
            foreach ($datos as &$fila) {
                $fila['OnHand'] = $stockWms[trim($fila['ItemCode'])] ?? 0;
            }
            unset($fila);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[FORECAST][parametros_mrp] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener los parámetros MRP del producto.']);
        }
        exit;

    case 'guardar_ajuste':

        // Guarda (upsert) la Cantidad Ajustada MANUAL de una semana del forecast de un producto.
        // POST: auth.php ya validó sesión + CSRF. Se acota a la empresa activa.
        require_once __DIR__ . '/../models/forecast_ajuste_model.php';

        if (empty($_SESSION['empresa_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'No hay una empresa activa.']);
            exit;
        }

        $itemCode = trim($_POST['itemcode'] ?? '');
        $isoYear  = (int) ($_POST['iso_year'] ?? 0);
        $isoWeek  = (int) ($_POST['iso_week'] ?? 0);
        $semana   = trim($_POST['semana_inicio'] ?? '');
        $cantRaw  = trim((string) ($_POST['cantidad'] ?? ''));

        if ($itemCode === '' || $isoYear <= 0 || $isoWeek <= 0 || $isoWeek > 53
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana)) {
            echo json_encode(['status' => 'error', 'message' => 'Datos de la semana incompletos o inválidos.']);
            exit;
        }

        // Cantidad: entero de 0 al máximo permitido por la columna.
        if (!preg_match('/^\d+$/', $cantRaw)) {
            echo json_encode(['status' => 'error', 'message' => 'La cantidad debe ser un número entero mayor o igual a 0.']);
            exit;
        }
        $cantidad = (int) $cantRaw;
        if ($cantidad < 0 || $cantidad > ForecastAjuste::MAX_CANTIDAD) {
            echo json_encode(['status' => 'error', 'message' => 'La cantidad está fuera del rango permitido.']);
            exit;
        }

        try {
            $ajusteModel = new ForecastAjuste($pdo, $_SESSION['empresa_id']);
            $ajusteModel->guardar($itemCode, $isoYear, $isoWeek, $semana, $cantidad, $_SESSION['usuario_id'] ?? null);

            echo json_encode([
                'status'   => 'success',
                'cantidad' => $cantidad,
                'message'  => 'Cantidad ajustada guardada.',
            ]);
        } catch (Throwable $e) {
            error_log('[FORECAST][guardar_ajuste] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la cantidad ajustada.']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
}
