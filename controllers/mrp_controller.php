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
                $model = new Forecast($pdo, $_SESSION['empresa_id'] ?? null);
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

                // 1) Base: todos los productos con forecast (MySQL) de la empresa activa.
                $forecastModel = new Forecast($pdo, $_SESSION['empresa_id'] ?? null);
                $base = $forecastModel->listarPagina('', '', '', '', [], 0, -1);

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
                foreach ((new ConsultaWms($pdoWms, codigoEmpresaWms($pdo)))->stockVencimientoPorProducto(30) as $r) {
                    $stockWms[trim($r['CodArticulo'])] = $r;
                }

                // 3) Abastecimiento SAP (lead time / comprometido / en pedido / en producción), bodega 010.
                $abast = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->abastecimientoPorProducto() as $r) {
                    $abast[trim($r['ItemCode'])] = $r;
                }

                // Horizonte de planificación (en semanas): el usuario elige cuántas semanas de
                // forecast se acumulan para la demanda. Reemplaza al lead time como ventana.
                // (El lead time se considerará a futuro.) Se valida contra la lista permitida.
                $horizontesValidos = [1, 2, 3, 4, 8, 13, 26, 52];
                $horizonte = (int) ($_GET['horizonte'] ?? 4);
                if (!in_array($horizonte, $horizontesValidos, true)) { $horizonte = 4; }

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

                    // Lead time (U_LeadTime) EN SEMANAS. Informativo por ahora (columna); la
                    // ventana de demanda la define el Horizonte, no el lead time.
                    $leadSemanas = (int) ($abast[$cod]['LeadTime'] ?? 0);

                    // Demanda a cubrir = forecast de las próximas 'horizonte' semanas. Se guarda
                    // también el rango de semanas que abarca.
                    $ventana     = array_slice($serie[$cod] ?? [], 0, $horizonte);
                    $demandaFc   = array_sum(array_column($ventana, 'demanda'));
                    $semanaDesde = $ventana ? $ventana[0]['semana'] : '';
                    $semanaHasta = $ventana ? $ventana[count($ventana) - 1]['semana'] : '';

                    // Stock teórico: disponibilidad neta = stock actual + lo que viene en camino
                    // (en pedido + en producción) − lo comprometido a clientes.
                    $stockTeorico = $stock + $enPedido + $enProduccion - $comprometido;

                    // Sugerido a reponer: cubrir la demanda del lead time + lo comprometido,
                    // descontando el stock disponible y lo que ya viene en camino.
                    $sugerido = $demandaFc + $comprometido - $stock - $enPedido - $enProduccion;
                    if ($sugerido < 0) { $sugerido = 0; }

                    $data[] = [
                        'producto_codigo'  => $b['producto_codigo'],
                        'producto_nombre'  => $b['producto_nombre'],
                        'familia'          => $b['familia'],
                        'sub_familia'      => $b['sub_familia'],
                        'proveedor'        => $abast[$cod]['Proveedor'] ?? null,
                        'lead_time'        => $leadSemanas,
                        'demanda_forecast' => round($demandaFc),
                        'semana_desde'     => $semanaDesde,
                        'semana_hasta'     => $semanaHasta,
                        'stock_wms'        => round($stock),
                        'stock_por_vencer' => round($porVencer),
                        'dias_prox_venc'   => $diasProxVenc,
                        'comprometido'     => round($comprometido),
                        'en_pedido'        => round($enPedido),
                        'en_produccion'    => round($enProduccion),
                        'stock_teorico'    => round($stockTeorico),
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

                $datos = (new ConsultaWms($pdoWms, codigoEmpresaWms($pdo)))->stockDetallePorProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_stock] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar el detalle de stock.']);
            }
            exit;

        case 'detalle_comprometido':

            // Detalle del "Comprometido": líneas de ODV abiertas del producto (SAP, bodega 010),
            // desde la SAP de la empresa activa.
            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv (SAP)
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap

                $itemCode = trim($_GET['itemcode'] ?? '');
                if ($itemCode === '') {
                    echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
                    exit;
                }

                $datos = (new ConsultaSap($pdoSqlsrv))->comprometidoVentasPorProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_comprometido] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar el comprometido de ventas.']);
            }
            exit;

        case 'detalle_en_pedido':

            // Detalle de "En Pedido": líneas de OC abiertas del producto (SAP, bodega 010),
            // desde la SAP de la empresa activa.
            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv (SAP)
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap

                $itemCode = trim($_GET['itemcode'] ?? '');
                if ($itemCode === '') {
                    echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
                    exit;
                }

                $datos = (new ConsultaSap($pdoSqlsrv))->enPedidoComprasPorProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_en_pedido] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar las órdenes de compra.']);
            }
            exit;

        case 'detalle_forecast':

            // Detalle del forecast SEMANAL del producto (de la empresa activa, MySQL).
            try {
                $itemCode = trim($_GET['itemcode'] ?? '');
                if ($itemCode === '') {
                    echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
                    exit;
                }

                $model = new Forecast($pdo, $_SESSION['empresa_id'] ?? null);
                $datos = $model->serieSemanalProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_forecast] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar el forecast del producto.']);
            }
            exit;

        case 'detalle_en_produccion':

            // Detalle de "En Producción": órdenes de producción liberadas del producto (SAP),
            // desde la SAP de la empresa activa.
            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv (SAP)
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap

                $itemCode = trim($_GET['itemcode'] ?? '');
                if ($itemCode === '') {
                    echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
                    exit;
                }

                $datos = (new ConsultaSap($pdoSqlsrv))->enProduccionPorProducto($itemCode);
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } catch (Throwable $e) {
                error_log('[MRP][detalle_en_produccion] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al cargar las órdenes de producción.']);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
?>
