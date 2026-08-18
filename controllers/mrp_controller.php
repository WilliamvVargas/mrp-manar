<?php
    /*
     * Controlador del mantenedor MRP.
     *
     * Cruza tres fuentes por producto:
     *   - Forecast (MySQL, forecast_x_producto)              -> demanda proyectada.
     *   - Stock vigente del WMS (SGL WMS)                     -> disponibilidad real.
     *   - Abastecimiento SAP bodega 010 (comprometido / en pedido / en producción).
     * y calcula un "sugerido a reponer".
     *
     * Devuelve todo en una sola respuesta (DataTable client-side): la lista es acotada
     * (productos con forecast) y el cruce entre bases se resuelve mejor en memoria.
     */
    require_once __DIR__ . '/../includes/auth.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $action = $_REQUEST['action'] ?? '';

    require_once __DIR__ . '/../includes/control_acceso_controlador.php';
    exigirAccesoControlador('mrp', $action);

    require_once __DIR__ . '/../config/conexion.php';        // $pdo (MySQL)
    require_once __DIR__ . '/../models/forecast_model.php';  // Forecast

    switch ($action) {

        case 'filtros':

            // Opciones de los filtros Familia / Sub-Familia (presentes en el forecast).
            try {
                $model = new Forecast($pdo);
                echo json_encode([
                    'familias'     => $model->familiasDisponibles(),
                    'sub_familias' => $model->subFamiliasDisponibles(),
                ]);
            } catch (Throwable $e) {
                error_log('[MRP][filtros] ' . $e->getMessage());
                echo json_encode(['familias' => [], 'sub_familias' => []]);
            }
            exit;

        case 'listar':

            // Lista MRP por producto (cruce forecast + WMS + SAP).
            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv (SAP)
                require_once __DIR__ . '/../config/conexion_wms.php';         // $pdoWms (WMS)
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap
                require_once __DIR__ . '/../models/consultas_wms_model.php';  // ConsultaWms

                // 1) Base: todos los productos con forecast (MySQL).
                $forecastModel = new Forecast($pdo);
                $base = $forecastModel->listarPagina('', '', '', [], 0, -1);

                // Serie semanal del forecast por producto (ordenada), para sumar el horizonte del
                // lead time. Guarda la semana (lunes ISO) y la demanda de cada punto.
                $serie = [];
                foreach ($forecastModel->demandaSemanalPorProducto() as $r) {
                    $serie[trim($r['producto_codigo'])][] = [
                        'semana'  => $r['semana_inicio'],
                        'demanda' => (float) $r['demanda'],
                    ];
                }

                // 2) Stock vigente del WMS por producto (con visibilidad de vencimiento, umbral 30 días).
                $stockWms = [];
                foreach ((new ConsultaWms($pdoWms))->stockVencimientoPorProducto(30) as $r) {
                    $stockWms[trim($r['CodArticulo'])] = $r;
                }

                // 3) Abastecimiento SAP (lead time / comprometido / en pedido / en producción), bodega 010.
                $abast = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->abastecimientoPorProducto() as $r) {
                    $abast[trim($r['ItemCode'])] = $r;
                }

                // 4) Merge + sugerido a reponer.
                $data = [];
                foreach ($base as $b) {
                    $cod          = trim($b['producto_codigo']);
                    $stock        = (float) ($stockWms[$cod]['Cantidad'] ?? 0);
                    $porVencer    = (float) ($stockWms[$cod]['PorVencer'] ?? 0);
                    $diasProxVenc = isset($stockWms[$cod]) ? (int) $stockWms[$cod]['DiasProxVencer'] : null;
                    $comprometido = (float) ($abast[$cod]['Comprometido'] ?? 0);
                    $enPedido     = (float) ($abast[$cod]['EnPedido'] ?? 0);
                    $enProduccion = (float) ($abast[$cod]['EnProduccion'] ?? 0);

                    // Lead time (U_LeadTime, días). Cobertura = semanas que abarca el lead time,
                    // redondeando hacia arriba; fallback de 1 semana si no hay lead time (0/vacío).
                    $leadDias = (int) ($abast[$cod]['LeadTime'] ?? 0);
                    $semanas  = ($leadDias > 0) ? (int) ceil($leadDias / 7) : 1;

                    // Demanda a cubrir = forecast de las próximas 'semanas' semanas (ventana del
                    // lead time). Se guarda también el rango de semanas que abarca.
                    $ventana     = array_slice($serie[$cod] ?? [], 0, $semanas);
                    $demandaFc   = array_sum(array_column($ventana, 'demanda'));
                    $semanaDesde = $ventana ? $ventana[0]['semana'] : '';
                    $semanaHasta = $ventana ? $ventana[count($ventana) - 1]['semana'] : '';

                    // Sugerido a reponer: cubrir la demanda del lead time + lo comprometido,
                    // descontando el stock disponible y lo que ya viene en camino.
                    $sugerido = $demandaFc + $comprometido - $stock - $enPedido - $enProduccion;
                    if ($sugerido < 0) { $sugerido = 0; }

                    $data[] = [
                        'producto_codigo'  => $b['producto_codigo'],
                        'producto_nombre'  => $b['producto_nombre'],
                        'familia'          => $b['familia'],
                        'sub_familia'      => $b['sub_familia'],
                        'lead_time'        => $leadDias,
                        'demanda_forecast' => round($demandaFc),
                        'semana_desde'     => $semanaDesde,
                        'semana_hasta'     => $semanaHasta,
                        'stock_wms'        => round($stock),
                        'stock_por_vencer' => round($porVencer),
                        'dias_prox_venc'   => $diasProxVenc,
                        'comprometido'     => round($comprometido),
                        'en_pedido'        => round($enPedido),
                        'en_produccion'    => round($enProduccion),
                        'sugerido'         => round($sugerido),
                    ];
                }

                echo json_encode(['status' => 'success', 'data' => $data]);
            } catch (Throwable $e) {
                error_log('[MRP][listar] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al construir el MRP.']);
            }
            exit;

        case 'detalle_stock':

            // Detalle de stock del WMS de un producto (pestaña "Stock" del detalle MRP).
            try {
                require_once __DIR__ . '/../config/conexion_wms.php';        // $pdoWms
                require_once __DIR__ . '/../models/consultas_wms_model.php'; // ConsultaWms

                $itemCode = trim($_GET['itemcode'] ?? '');
                if ($itemCode === '') {
                    echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
                    exit;
                }

                $datos = (new ConsultaWms($pdoWms))->stockDetallePorProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_stock] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar el detalle de stock.']);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
?>
