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

        // Consulta Stock: existencias por artículo y bodega (lectura desde SAP / SQL Server).
        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->stock();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[CONSULTAS_SAP] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al ejecutar la consulta Stock.']);
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

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
}
