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
            require_once __DIR__ . '/../config/conexion.php';            // $pdo (MySQL, para empresa_wms)
            require_once __DIR__ . '/../config/conexion_wms.php';        // expone $pdoWms + codigoEmpresaWms()
            require_once __DIR__ . '/../models/consultas_wms_model.php'; // clase ConsultaWms

            $model = new ConsultaWms($pdoWms, codigoEmpresaWms($pdo));
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
            require_once __DIR__ . '/../config/conexion.php';            // $pdo (MySQL, para empresa_wms)
            require_once __DIR__ . '/../config/conexion_wms.php';        // expone $pdoWms + codigoEmpresaWms()
            require_once __DIR__ . '/../models/consultas_wms_model.php'; // clase ConsultaWms

            $model = new ConsultaWms($pdoWms, codigoEmpresaWms($pdo));
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

        // Serie SEMANAL (ISO) de un artículo: demanda/venta reales (SAP, por día agregado a
        // semana; clave = lunes 'yyyy-MM-dd'), más la Cantidad Forecast semanal (MySQL).
        try {
            $itemCode = $_GET['itemcode'] ?? '';

            $model = new ConsultaSap($pdoSqlsrv);

            // Lunes ISO de una fecha 'yyyy-MM-dd'.
            $lunes = function ($ymd) {
                return date('Y-m-d', strtotime('monday this week', strtotime($ymd . ' 12:00:00')));
            };

            // Historia real por DÍA (SAP) agregada a SEMANA ISO (clave = lunes).
            $porSemana = [];
            foreach ($model->demandaDiariaProducto($itemCode) as $d) {
                $lun = $lunes($d['Fecha']);
                if (!isset($porSemana[$lun])) { $porSemana[$lun] = ['Demanda' => 0.0, 'Neto' => 0.0]; }
                $porSemana[$lun]['Demanda'] += (float) $d['Demanda'];
                $porSemana[$lun]['Neto']    += (float) $d['Neto'];
            }
            ksort($porSemana);
            $datos = [];
            foreach ($porSemana as $lun => $v) {
                $datos[] = ['FechaDocumento' => $lun, 'Demanda' => $v['Demanda'], 'Neto' => $v['Neto']];
            }

            // Forecast SEMANAL (MySQL): Cantidad Forecast por semana (clave = semana_inicio, lunes).
            // Si la tabla no existe o falla, el gráfico muestra solo la historia real.
            $forecast = [];
            try {
                require_once __DIR__ . '/../config/conexion.php'; // $pdo (MySQL)

                $st = $pdo->prepare("
                    SELECT semana_inicio, demanda_forecast AS df
                    FROM forecast_x_producto
                    WHERE producto_codigo = ? AND empresa_id = ? ORDER BY semana_inicio
                ");
                $st->execute([$itemCode, $_SESSION['empresa_id'] ?? null]);
                foreach ($st->fetchAll() as $r) {
                    $forecast[] = [
                        'ym'              => (string) $r['semana_inicio'],   // lunes ISO
                        'DemandaForecast' => (float) $r['df'],
                    ];
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
