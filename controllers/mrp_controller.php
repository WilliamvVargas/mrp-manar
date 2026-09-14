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
                require_once __DIR__ . '/../config/config.php';               // FECHA_SIMULADA

                // "Hoy" efectivo. TEMPORAL: FECHA_SIMULADA (config.php) permite trabajar con el
                // snapshot antiguo de la BD tratando esa fecha como hoy. En producción va en ''
                // -> usa la fecha real del servidor.
                $hoy = (defined('FECHA_SIMULADA') && FECHA_SIMULADA !== '') ? FECHA_SIMULADA : date('Y-m-d');

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
                foreach ((new ConsultaWms($pdoWms, codigoEmpresaWms($pdo)))->stockVencimientoPorProducto(30, $hoy) as $r) {
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

                // Ventana de CÁLCULO fija (semanas): la proyección, el Stock Teórico y el
                // Estado/Urgencia se calculan SIEMPRE sobre este máximo, NO sobre lo que el usuario
                // elige ver. El "horizonte" pasó a ser solo de la VISTA (cuántas semanas se muestran)
                // y lo aplica el cliente filtrando filas — así no recalcula al ampliar/reducir.
                // $barsTend = nº fijo de barras de la mini-tendencia hacia adelante desde cada semana.
                $maxSemanas = 52;
                $barsTend   = 16;

                // El horizonte va desde la semana de "$hoy" hacia adelante: el forecast puede tener
                // semanas ya pasadas, que no deben contar para la reposición.
                $lunesActual = date('Y-m-d', strtotime('monday this week', strtotime($hoy . ' 12:00:00')));

                // Parámetros del plan: stock de seguridad = (lead time del producto, en semanas) ×
                // demanda promedio semanal → se mantiene como colchón tantas semanas de demanda como
                // dure la reposición. Lot-for-lot; lead time por defecto si el producto no tiene
                // historia propia ni U_LeadTime.
                $leadDefaultSem = 4;

                // 3.5) Entradas EN CAMINO por producto y SEMANA de llegada (lunes ISO): OC +
                // facturas de reserva + producción, con su fecha esperada. Habilita el time-phase:
                // cada recepción suma al saldo en la semana en que realmente llega.
                $entradas = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->entradasEnCaminoPorSemana() as $r) {
                    if (empty($r['Fecha'])) { continue; }
                    $cod = trim($r['ItemCode']);
                    $lun = date('Y-m-d', strtotime('monday this week', strtotime($r['Fecha'] . ' 12:00:00')));
                    $entradas[$cod][$lun] = ($entradas[$cod][$lun] ?? 0) + (float) $r['Cantidad'];
                }

                // 3.6) Comprometido de VENTAS (OV abiertas) por producto y SEMANA de entrega (lunes
                // ISO). Habilita el consumo de forecast: por semana, demanda = max(forecast, OV). Las
                // OV sin fecha de entrega se tratan como backorder (descuento inmediato al saldo).
                $ovSemana   = [];   // [cod][lunesISO] => cantidad OV con entrega esa semana
                $ovSinFecha = [];   // [cod] => cantidad OV sin DocDueDate (backorder)
                foreach ((new ConsultaSap($pdoSqlsrv))->comprometidoVentasPorSemana() as $r) {
                    $cod = trim($r['ItemCode']);
                    $qty = (float) $r['Cantidad'];
                    if (empty($r['Fecha'])) {
                        $ovSinFecha[$cod] = ($ovSinFecha[$cod] ?? 0) + $qty;
                    } else {
                        $lun = date('Y-m-d', strtotime('monday this week', strtotime($r['Fecha'] . ' 12:00:00')));
                        $ovSemana[$cod][$lun] = ($ovSemana[$cod][$lun] ?? 0) + $qty;
                    }
                }

                // 3.7) Comprometido de PRODUCCIÓN (consumo de componentes por OP liberada) por
                // producto y SEMANA (lunes ISO). Es consumo adicional (no venta): se SUMA al
                // comprometido de la semana y se resta del saldo, pero NO entra al max con el forecast.
                $prodSemana   = [];   // [cod][lunesISO] => consumo de producción esa semana
                $prodSinFecha = [];   // [cod] => consumo de producción sin fecha (backorder)
                foreach ((new ConsultaSap($pdoSqlsrv))->comprometidoProduccionPorSemana() as $r) {
                    $cod = trim($r['ItemCode']);
                    $qty = (float) $r['Cantidad'];
                    if (empty($r['Fecha'])) {
                        $prodSinFecha[$cod] = ($prodSinFecha[$cod] ?? 0) + $qty;
                    } else {
                        $lun = date('Y-m-d', strtotime('monday this week', strtotime($r['Fecha'] . ' 12:00:00')));
                        $prodSemana[$cod][$lun] = ($prodSemana[$cod][$lun] ?? 0) + $qty;
                    }
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

                    // Ventana futura del forecast (desde la semana actual), acotada al MÁXIMO de
                    // cálculo (fijo), no al horizonte de la vista.
                    $serieFutura = array_values(array_filter(
                        $serie[$cod] ?? [],
                        function ($w) use ($lunesActual) { return $w['semana'] >= $lunesActual; }
                    ));
                    $ventana = array_slice($serieFutura, 0, $maxSemanas);

                    $nSem = count($ventana);

                    // Entradas en camino de este producto por semana de llegada.
                    $entradasProd = $entradas[$cod] ?? [];

                    // Comprometido de este producto por semana: VENTAS (OV) y PRODUCCIÓN (consumo de
                    // componentes de OP), cada uno con su fecha; más los "sin fecha" (backorder up-front).
                    $ovProd   = $ovSemana[$cod] ?? [];
                    $ovSF     = $ovSinFecha[$cod] ?? 0;
                    $prodProd = $prodSemana[$cod] ?? [];
                    $prodSF   = $prodSinFecha[$cod] ?? 0;

                    // Demanda EFECTIVA por semana (max(forecast, OV) + consumo de producción), sobre
                    // toda la serie futura. Es la MISMA demanda que consume la proyección.
                    $demEfect = [];
                    foreach ($serieFutura as $k => $w) {
                        $sem = $w['semana'];
                        $demEfect[$k] = max((float) $w['demanda'], (float) ($ovProd[$sem] ?? 0))
                                      + (float) ($prodProd[$sem] ?? 0);
                    }
                    // Stock de seguridad ROLLING (móvil) por semana: para la semana i, la demanda
                    // efectiva de las próximas leadSem semanas contadas DESDE i (ventana hacia
                    // adelante). Cubre la reposición desde ESA semana: en tramos de baja/nula venta
                    // baja solo, en temporada alta sube. Reemplaza al valor único congelado desde hoy.
                    $L     = max(1, $leadSem);
                    $totSF = count($serieFutura);
                    $stockSegSem = [];
                    foreach ($serieFutura as $k => $w) {
                        $s = 0.0;
                        for ($j = $k; $j < $k + $L && $j < $totSF; $j++) { $s += $demEfect[$j]; }
                        $stockSegSem[$k] = $s;
                    }

                    // Stock Teórico POR SEMANA (columna): balance acumulado considerando solo los
                    // documentos comprometidos (sin forecast ni reposición sugerida). La 1ª semana
                    // parte del stock físico; cada semana siguiente ARRASTRA el teórico de la semana
                    // anterior y le suma lo que entra (En Pedido) y le resta lo comprometido
                    // (OV + consumo de producción) de esa semana.
                    $teoricoSem = [];
                    $teorico    = (float) $stock;
                    foreach ($ventana as $i => $w) {
                        $sem = $w['semana'];
                        $teorico += (float) ($entradasProd[$sem] ?? 0)
                                  - (float) ($ovProd[$sem] ?? 0)
                                  - (float) ($prodProd[$sem] ?? 0);
                        $teoricoSem[$i] = $teorico;
                    }

                    // Arranque de la proyección = Stock Físico, ajustado solo por OV / producción
                    // SIN fecha (compromisos sin semana asignada). Los documentos VENCIDOS (llegada
                    // o entrega con fecha ya pasada) se IGNORAN: no se suma la OC atrasada ni se
                    // restan las OV/producción vencidas. El saldo parte del stock real y solo se
                    // mueve con lo que tiene fecha DENTRO del horizonte (time-phase).
                    $saldoInicial = $stock - $ovSF - $prodSF;

                    // Proyección TIME-PHASED (lot-for-lot): cada semana SUMA lo que llega esa semana
                    // (OC/reserva/producción por su fecha), resta la demanda, y si el saldo caería bajo
                    // el stock de seguridad (y ya pasó el lead time) planifica una recepción.
                    $saldo    = $saldoInicial;
                    $recibir  = [];   // recepción planificada por índice de semana
                    $saldoSem = [];   // saldo proyectado al cierre de cada semana
                    foreach ($ventana as $i => $w) {
                        $saldo += ($entradasProd[$w['semana']] ?? 0);
                        // Consumo de forecast: la demanda efectiva de la semana es la MAYOR entre el
                        // forecast y las OV firmes con entrega esa semana (evita doble conteo).
                        $saldo -= max((float) $w['demanda'], (float) ($ovProd[$w['semana']] ?? 0));
                        // Consumo de producción (componentes de OP): es adicional a la venta, se resta aparte.
                        $saldo -= (float) ($prodProd[$w['semana']] ?? 0);
                        $rec    = 0;
                        $segSem = $stockSegSem[$i] ?? 0;   // seguridad rolling de ESTA semana
                        // Una orden NUEVA recién puede llegar en la semana L (antes tendría que
                        // haberse colocado en el pasado). Las semanas 0..L-1 sin stock quedan en
                        // QUIEBRE (saldo negativo): no llega mercadería y no se puede vender.
                        if ($i >= $leadSem && $saldo < $segSem) {
                            $rec   = (int) ceil($segSem - $saldo);
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

                    // Estado sobre la VENTANA DEL LEAD TIME (no la vista): ¿quiebra o baja del stock
                    // de seguridad ANTES de poder reponer? Es la ventana donde la situación ya es
                    // inevitable (una orden nueva recién llega en la semana leadSem). No depende del
                    // horizonte que el usuario elija ver.
                    $saldoLead = array_slice($saldoSem, 0, max(1, $leadSem), true);
                    $estado = 'ok';
                    $estadoSem = 0;
                    foreach ($saldoLead as $i => $s) {
                        // estadoSem = semanas DESDE HOY hasta el quiebre (0 = esta semana, 1 = la
                        // próxima, ...). El índice ya es 0-based, así que se usa tal cual.
                        if ($s < 0) { $estado = 'quiebre'; $estadoSem = $i; break; }
                    }
                    if ($estado === 'ok') {
                        foreach ($saldoLead as $i => $s) {
                            if ($s < ($stockSegSem[$i] ?? 0)) { $estado = 'ajustado'; break; }
                        }
                    }

                    // Tendencia (para las barras): por semana, demanda (altura) + estado de esa
                    // semana (color, igual que los badges): quiebre / ajustado / ok.
                    // Se proyecta el saldo sobre TODA la serie futura (no solo el horizonte),
                    // para que cada fila pueda mostrar una ventana de N semanas HACIA ADELANTE
                    // desde su posición, con el mismo número de barras en todas las filas.
                    $tendencia = [];
                    $saldoT = $saldoInicial;
                    foreach ($serieFutura as $i => $w) {
                        $saldoT += ($entradasProd[$w['semana']] ?? 0);
                        $saldoT -= max((float) $w['demanda'], (float) ($ovProd[$w['semana']] ?? 0));
                        $saldoT -= (float) ($prodProd[$w['semana']] ?? 0);
                        $segT = $stockSegSem[$i] ?? 0;
                        if ($i >= $leadSem && $saldoT < $segT) {
                            $saldoT += (int) ceil($segT - $saldoT);
                        }
                        $e = ($saldoT < 0) ? 'quiebre' : (($saldoT < $segT) ? 'ajustado' : 'ok');
                        $tendencia[$i] = ['d' => round((float) $w['demanda'], 1), 'e' => $e];
                    }

                    // Campos de producto (se repiten en cada fila-semana).
                    $filaBase = [
                        'producto_codigo'  => $b['producto_codigo'],
                        'producto_nombre'  => $b['producto_nombre'],
                        'familia'          => $b['familia'],
                        'sub_familia'      => $b['sub_familia'],
                        'proveedor'        => $abast[$cod]['Proveedor'] ?? null,
                        // Stock mín/máx de SAP (OITW bodega 010). Hoy 0 si no están cargados.
                        'stock_min'        => (float) ($abast[$cod]['StockMin'] ?? 0),
                        'stock_max'        => (float) ($abast[$cod]['StockMax'] ?? 0),
                        'lead_time'        => $leadSem,               // lead time usado (semanas)
                        'stock_wms'        => round($stock),
                        'stock_por_vencer' => round($porVencer),
                        'dias_prox_venc'   => $diasProxVenc,
                        'comprometido'     => round($comprometido),
                        'en_pedido'        => round($enPedido),
                        'en_produccion'    => round($enProduccion),
                        // Estado de abastecimiento del producto (para la columna Estado).
                        'estado'           => $estado,
                        'estado_sem'       => $estadoSem,
                        // Urgencia del producto = total a ORDENAR dentro de la ventana del lead time
                        // (decisiones inminentes), no sobre toda la vista. Ordena la tabla por
                        // producto sin depender del horizonte mostrado ni dispersar sus semanas.
                        'sugerido_total'   => (int) array_sum(array_slice($ordenar, 0, max(1, $leadSem))),
                    ];

                    // Una fila por semana: demanda, saldo proyectado, y sugerido a ORDENAR esa semana.
                    if ($ventana) {
                        foreach ($ventana as $i => $w) {
                            $ovSem   = (float) ($ovProd[$w['semana']] ?? 0);
                            $prodSem = (float) ($prodProd[$w['semana']] ?? 0);
                            $data[] = $filaBase + [
                                'semana'           => $w['semana'],
                                // Índice de la semana dentro del producto (0-based): la vista filtra
                                // por él para mostrar solo las primeras N (N = horizonte elegido).
                                'sem_idx'          => $i,
                                'demanda_forecast' => round($w['demanda']),
                                // Demanda efectiva usada por la proyección = max(forecast, OV firme).
                                // 'ov_semana' permite a la vista marcar cuándo mandó la OV (indicador azul).
                                'ov_semana'        => round($ovSem),
                                // Comprometido de la semana = OV + consumo de producción (columna).
                                'comprometido_semana' => round($ovSem + $prodSem),
                                // Stock Teórico acumulado al cierre de esta semana (solo comprometidos).
                                'stock_teorico'    => round($teoricoSem[$i]),
                                // Stock de seguridad ROLLING de esta semana (demanda efectiva de las
                                // próximas leadSem semanas contadas desde ella).
                                'stock_seguridad'  => round($stockSegSem[$i] ?? 0),
                                'demanda_efectiva' => round(max((float) $w['demanda'], $ovSem)),
                                // Recepción = lo EN CAMINO (OC + reserva + producción) que llega en
                                // ESTA semana según su fecha esperada. Time-phased: 0 en las semanas
                                // en que no llega nada (a diferencia del total foto "En Pedido").
                                'recepcion'        => round($entradasProd[$w['semana']] ?? 0),
                                // Tendencia = ventana FIJA de $barsTend semanas HACIA ADELANTE desde
                                // ESTA semana (mismo nº de barras en todas las filas; independiente
                                // del horizonte de la vista; al final de la serie puede acortarse).
                                'tendencia'        => array_slice($tendencia, $i, $barsTend),
                                'saldo_proyectado' => round($saldoSem[$i]),
                                'sugerido'         => (int) $ordenar[$i],
                            ];
                        }
                    } else {
                        $data[] = $filaBase + [
                            'semana' => '', 'sem_idx' => 0, 'demanda_forecast' => 0, 'ov_semana' => 0,
                            'comprometido_semana' => 0, 'stock_teorico' => round($stock),
                            'stock_seguridad' => 0, 'demanda_efectiva' => 0, 'recepcion' => 0,
                            'tendencia' => [], 'saldo_proyectado' => round($saldoInicial), 'sugerido' => 0,
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
