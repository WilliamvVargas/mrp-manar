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
        $anio       = $_GET['anio'] ?? '';                // '' = todos
        $mes        = $_GET['mes'] ?? '';                 // '' = todos

        // Ordenamiento multi-columna (índice de DataTables -> nombre lógico). Los índices 7
        // ("Cantidad Ajustada") y 10 ("Acciones") son placeholders sin orden: van como null.
        $columnas = ['anio', 'mes', 'producto_codigo', 'producto_nombre', 'familia', 'sub_familia', 'demanda_forecast', null, 'venta_presupuestada', 'created_at', null];
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
            $forecastModel  = new Forecast($pdo);
            $totalRegistros = $forecastModel->contarTodos();
            $totalFiltrados = $forecastModel->contarFiltrados($consulta, $familia, $subFamilia, $anio, $mes);
            $datos          = $forecastModel->listarPagina($consulta, $familia, $subFamilia, $anio, $mes, $ordenes, $inicio, $longitud);

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

        // Valores para los filtros de Año, Familia y Sub-Familia (los meses son estáticos 1-12 en el front).
        try {
            $forecastModel = new Forecast($pdo);
            echo json_encode([
                'status'       => 'success',
                'anios'        => $forecastModel->aniosDisponibles(),
                'familias'     => $forecastModel->familiasDisponibles(),
                'sub_familias' => $forecastModel->subFamiliasDisponibles()
            ]);
        } catch (PDOException $e) {
            error_log('[FORECAST][filtros] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'anios' => []]);
        }
        exit;

    case 'grafico_producto':

        // Serie mensual del producto para el gráfico: demanda/venta reales (SAP / SQL Server) +
        // Cantidad Forecast por producto y su demanda valorizada en $ (MySQL). Mismo formato
        // que la V3 de Consultas SAP, pero se plotea la Cantidad Forecast (demanda_forecast).
        require_once __DIR__ . '/../config/conexion_sqlserver.php';
        require_once __DIR__ . '/../models/consultas_sap_model.php';

        $itemCode = trim($_GET['itemcode'] ?? '');
        if ($itemCode === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se indicó el producto.']);
            exit;
        }

        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->demandaMensualProducto($itemCode);

            // Precio unitario realizado (neto/cantidad) ponderado a los últimos 12 meses de venta
            // real (α=0.85, más peso a lo reciente). Sale de SAP -> es independiente del presupuesto,
            // por lo que valorizar la demanda con él NO es circular. Sirve para llevar la demanda
            // futura (unidades) a $ y compararla contra la venta presupuestada en el mismo eje.
            $precio = null;
            if (!empty($datos)) {
                $idxMes = function ($ym) { return ((int) substr($ym, 0, 4)) * 12 + ((int) substr($ym, 5, 2) - 1); };
                $ultimo = 0;
                foreach ($datos as $d) { $ultimo = max($ultimo, $idxMes($d['FechaDocumento'])); }
                $sumNeto = 0.0; $sumCant = 0.0;
                foreach ($datos as $d) {
                    $k = $ultimo - $idxMes($d['FechaDocumento']);
                    if ($k < 0 || $k >= 12) { continue; }   // solo la ventana de 12 meses
                    $w = pow(0.85, $k);
                    $sumNeto += $w * (float) $d['Neto'];
                    $sumCant += $w * (float) $d['Demanda'];
                }
                if ($sumCant > 0) { $precio = $sumNeto / $sumCant; }
            }

            // Forecast (MySQL): Cantidad Forecast del producto, valorizada en $ (demanda × precio
            // realizado) para compararla contra la venta neta real en el mismo eje. Si falla, el
            // gráfico muestra solo la historia real.
            $forecast = [];
            try {
                $st = $pdo->prepare("
                    SELECT anio, mes, demanda_forecast AS df
                    FROM forecast_x_producto WHERE producto_codigo = ? ORDER BY anio, mes
                ");
                $st->execute([$itemCode]);

                foreach ($st->fetchAll() as $r) {
                    $df = (float) $r['df'];
                    $forecast[] = [
                        'ym'                => sprintf('%04d-%02d', $r['anio'], $r['mes']),
                        'DemandaForecast'   => $df,
                        'DemandaValorizada' => ($precio !== null) ? $df * $precio : null,
                    ];
                }
            } catch (Throwable $e) {
                error_log('[FORECAST][grafico] ' . $e->getMessage());
                $forecast = [];
            }

            echo json_encode(['status' => 'success', 'data' => $datos, 'forecast' => $forecast]);
        } catch (PDOException $e) {
            error_log('[FORECAST] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener la demanda del producto.']);
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

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[FORECAST][parametros_mrp] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener los parámetros MRP del producto.']);
        }
        exit;

    case 'parametros_mrp_todos':

        // Parámetros para MRP de TODOS los productos (bodega 010) desde SAP / SQL Server (CLPRDMANAR).
        require_once __DIR__ . '/../config/conexion_sqlserver.php';
        require_once __DIR__ . '/../models/consultas_sap_model.php';

        try {
            $model = new ConsultaSap($pdoSqlsrv);
            $datos = $model->parametrosMrp();

            echo json_encode(['status' => 'success', 'data' => $datos]);
        } catch (PDOException $e) {
            error_log('[FORECAST][parametros_mrp_todos] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al obtener los parámetros MRP.']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
}
