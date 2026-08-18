<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion_sqlserver.php';
require_once __DIR__ . '/../models/consultas_sap_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
// (el guard carga su propia conexión MySQL; este controlador usa SQL Server por separado.)
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('consultas-sap', $action);

switch ($action) {

    case 'odv':

        // Consulta ODV: Órdenes de Venta abiertas (lectura desde SAP / SQL Server).
        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->ordenesVenta();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta ODV.']);
        }
        exit;

    case 'oc':

        // Consulta OC: Órdenes de Compra abiertas (lectura desde SAP / SQL Server).
        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->ordenesCompra();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta OC.']);
        }
        exit;

    case 'stock':

        // Consulta Stock: inventario en tiempo real por pallet/lote/ubicación desde el WMS
        // (SGL WMS / SQL Server), no desde SAP. Usa su propia conexión $pdoWms.
        try {
            require_once __DIR__ . '/../config/conexion_wms.php';        // expone $pdoWms
            require_once __DIR__ . '/../models/consultas_wms_model.php'; // clase ConsultaWms

            $model = new ConsultaWms($pdoWms);
            $datos = $model->stock();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_WMS] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta de Stock (WMS).']);
        }
        exit;

    case 'stock_producto':

        // Consulta Stock por Producto: stock del WMS agregado por artículo (suma de todas las
        // líneas), excluyendo las vencidas. Usa la conexión $pdoWms.
        try {
            require_once __DIR__ . '/../config/conexion_wms.php';        // expone $pdoWms
            require_once __DIR__ . '/../models/consultas_wms_model.php'; // clase ConsultaWms

            $model = new ConsultaWms($pdoWms);
            $datos = $model->stockPorProducto();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_WMS] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta de Stock por Producto (WMS).']);
        }
        exit;

    case 'facs_ncs':

        // Consulta Facturas y Notas de Crédito: resumen por cabecera (lectura desde SAP / SQL Server).
        // Filtro opcional por fecha del documento (el modelo valida el formato).
        try {
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->facturasNotasCredito($desde, $hasta);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta de Facturas y Notas de Crédito.']);
        }
        exit;

    case 'facs_ncs_v2':

        // Consulta v2: líneas de facturas + notas de crédito en un listado plano (lectura SAP).
        try {
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->lineasFacturasNotasCredito($desde, $hasta);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta de líneas de Facturas y Notas de Crédito.']);
        }
        exit;

    case 'facs_ncs_v3':

        // Consulta v3: líneas de Facturas + NC agrupadas por fecha del documento y código de artículo.
        try {
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->facturasNotasCreditoPorArticulo($desde, $hasta);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta v3 de Facturas y Notas de Crédito.']);
        }
        exit;

    case 'facs_ncs_v4':

        // Consulta v4: líneas de Facturas + NC agrupadas por año-mes y familia.
        try {
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->facturasNotasCreditoPorFamilia($desde, $hasta);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta v4 (por familia).']);
        }
        exit;

    case 'docs_articulo_mes':

        // Detalle v3: documentos (facturas/NC) de un artículo en un año-mes.
        try {
            $anioMes  = $_GET['aniomes'] ?? '';
            $itemCode = $_GET['itemcode'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->documentosPorArticuloMes($anioMes, $itemCode);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener los documentos del artículo.']);
        }
        exit;

    case 'lineas':

        // Líneas de una factura (INV1) o nota de crédito (RIN1), por DocEntry (lectura desde SAP).
        try {
            $tipo     = $_GET['tipo'] ?? '';
            $docEntry = $_GET['docentry'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->lineasDocumento($tipo, $docEntry);

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener las líneas del documento.']);
        }
        exit;

    case 'grafico_producto':

        // Serie de demanda mensual (cantidad) de un artículo, toda su historia (sin filtro de fecha),
        // más el FORECAST por producto (MySQL) y su presupuesto futuro (presupuesto del grupo ×
        // participación del producto).
        try {
            $itemCode = $_GET['itemcode'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->demandaMensualProducto($itemCode);

            // Forecast + presupuesto futuro (MySQL). Si la tabla no existe, queda vacío.
            $forecast = [];
            try {
                require_once __DIR__ . '/../config/conexion.php'; // $pdo (MySQL)

                $st = $pdo->prepare("
                    SELECT anio, mes, familia, sub_familia, demanda_forecast_corr AS df, participacion AS part
                    FROM forecast_x_producto WHERE producto_codigo = ? ORDER BY anio, mes
                ");
                $st->execute([$itemCode]);
                $fr = $st->fetchAll();

                if ($fr) {
                    // Presupuesto del grupo por año-mes (para repartir por la participación).
                    $bp = $pdo->prepare("
                        SELECT anio, mes, SUM(venta) AS v FROM presupuestos
                        WHERE TRIM(familia) = ? AND TRIM(sub_familia) = ? AND venta IS NOT NULL
                        GROUP BY anio, mes
                    ");
                    $bp->execute([$fr[0]['familia'], $fr[0]['sub_familia']]);
                    $bud = [];
                    foreach ($bp->fetchAll() as $b) { $bud[sprintf('%04d-%02d', $b['anio'], $b['mes'])] = (float) $b['v']; }

                    foreach ($fr as $r) {
                        $ym = sprintf('%04d-%02d', $r['anio'], $r['mes']);
                        $gb = $bud[$ym] ?? null;
                        $forecast[] = [
                            'ym'                => $ym,
                            'DemandaForecast'   => (float) $r['df'],
                            'PresupuestoFuturo' => ($gb !== null) ? $gb * (float) $r['part'] : null,
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log('[CONSULTAS_SAP forecast] ' . $e->getMessage());
                $forecast = [];
            }

            echo json_encode(['status' => 'success', 'data' => $datos, 'forecast' => $forecast]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener la demanda del artículo.']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
}
