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

                // Lead time real por PRODUCTO (mediana OC->recepción, 24 meses) para el plan
                // time-phased. Se exige un mínimo de historia (>=3 recepciones) para confiar.
                $leadProd = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->leadTimePorProducto() as $r) {
                    if ((int) $r['recepciones'] >= 3) {
                        $leadProd[trim($r['item'])] = (float) $r['mediana'];   // en días
                    }
                }

                // Horizonte de planificación (en semanas): el usuario elige cuántas semanas de
                // forecast se acumulan para la demanda. Reemplaza al lead time como ventana.
                // (El lead time se considerará a futuro.) Se valida contra la lista permitida.
                $horizontesValidos = [1, 2, 3, 4, 8, 13, 26, 52];
                $horizonte = (int) ($_GET['horizonte'] ?? 4);
                if (!in_array($horizonte, $horizontesValidos, true)) { $horizonte = 4; }

                // El horizonte va desde la semana ACTUAL hacia adelante: el forecast puede tener
                // semanas ya pasadas, que no deben contar para la reposición.
                $lunesActual = date('Y-m-d', strtotime('monday this week'));

                // Parámetros del plan (v1): stock de seguridad = N semanas de demanda (configurable
                // desde la vista); lot-for-lot; lead time por defecto si el producto no tiene
                // historia propia ni U_LeadTime.
                $semanasSeguridad = (int) ($_GET['semanas_seguridad'] ?? 2);
                if ($semanasSeguridad < 0)  { $semanasSeguridad = 0; }
                if ($semanasSeguridad > 52) { $semanasSeguridad = 52; }
                $leadDefaultSem = 4;

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

                    // Lead time del producto EN SEMANAS: propio (mediana OC/7, redondeo hacia arriba,
                    // conservador) -> U_LeadTime de SAP -> default.
                    $uLead = (int) ($abast[$cod]['LeadTime'] ?? 0);
                    if (isset($leadProd[$cod])) {
                        $leadSem = (int) ceil($leadProd[$cod] / 7);
                    } elseif ($uLead > 0) {
                        $leadSem = $uLead;
                    } else {
                        $leadSem = $leadDefaultSem;
                    }

                    // Ventana futura del forecast (desde la semana actual), acotada al horizonte.
                    $serieFutura = array_values(array_filter(
                        $serie[$cod] ?? [],
                        function ($w) use ($lunesActual) { return $w['semana'] >= $lunesActual; }
                    ));
                    $ventana = array_slice($serieFutura, 0, $horizonte);

                    // Stock de seguridad = N semanas de la demanda promedio semanal (del horizonte).
                    $nSem     = count($ventana);
                    $demProm  = $nSem > 0 ? array_sum(array_column($ventana, 'demanda')) / $nSem : 0.0;
                    $stockSeg = $semanasSeguridad * $demProm;

                    // Disponible inicial = stock + en camino − comprometido. (v1: en pedido/producción
                    // se consideran disponibles desde ya; se afinará por fecha de llegada a futuro.)
                    $disponible   = $stock + $enPedido + $enProduccion - $comprometido;
                    $stockTeorico = $disponible;

                    // Proyección TIME-PHASED (lot-for-lot): recorre semana a semana; cuando el saldo
                    // caería bajo el stock de seguridad, planifica la recepción que lo restituye.
                    $saldo    = $disponible;
                    $recibir  = [];   // recepción planificada por índice de semana
                    $saldoSem = [];   // saldo proyectado al cierre de cada semana
                    foreach ($ventana as $i => $w) {
                        $saldo -= (float) $w['demanda'];
                        $rec = 0;
                        // Una orden NUEVA recién puede llegar en la semana L (antes tendría que
                        // haberse colocado en el pasado). Las semanas 0..L-1 sin stock quedan en
                        // QUIEBRE (saldo negativo): no llega mercadería y no se puede vender.
                        if ($i >= $leadSem && $saldo < $stockSeg) {
                            $rec   = (int) ceil($stockSeg - $saldo);
                            $saldo += $rec;
                        }
                        $recibir[$i]  = $rec;
                        $saldoSem[$i] = $saldo;
                    }

                    // La recepción de la semana i (i >= L) se ORDENA en la semana i − L (>= 0).
                    $ordenar = array_fill(0, max(1, $nSem), 0);
                    foreach ($recibir as $i => $rec) {
                        if ($rec > 0) { $ordenar[$i - $leadSem] += $rec; }
                    }

                    // Campos de producto (se repiten en cada fila-semana).
                    $filaBase = [
                        'producto_codigo'  => $b['producto_codigo'],
                        'producto_nombre'  => $b['producto_nombre'],
                        'familia'          => $b['familia'],
                        'sub_familia'      => $b['sub_familia'],
                        'proveedor'        => $abast[$cod]['Proveedor'] ?? null,
                        'lead_time'        => $leadSem,               // lead time usado (semanas)
                        'stock_wms'        => round($stock),
                        'stock_por_vencer' => round($porVencer),
                        'dias_prox_venc'   => $diasProxVenc,
                        'comprometido'     => round($comprometido),
                        'en_pedido'        => round($enPedido),
                        'en_produccion'    => round($enProduccion),
                        'stock_teorico'    => round($stockTeorico),
                        'stock_seguridad'  => round($stockSeg),
                        // Urgencia del producto (total a ordenar en el horizonte): ordena la tabla
                        // por producto sin dispersar sus semanas.
                        'sugerido_total'   => (int) array_sum($ordenar),
                    ];

                    // Una fila por semana: demanda, saldo proyectado, y sugerido a ORDENAR esa semana.
                    if ($ventana) {
                        foreach ($ventana as $i => $w) {
                            $data[] = $filaBase + [
                                'semana'           => $w['semana'],
                                'demanda_forecast' => round($w['demanda']),
                                'saldo_proyectado' => round($saldoSem[$i]),
                                'sugerido'         => (int) $ordenar[$i],
                            ];
                        }
                    } else {
                        $data[] = $filaBase + [
                            'semana' => '', 'demanda_forecast' => 0,
                            'saldo_proyectado' => round($disponible), 'sugerido' => 0,
                        ];
                    }
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
