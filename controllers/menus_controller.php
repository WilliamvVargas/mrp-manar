<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/menus_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$menuModel = new Menu($pdo);

// Reglas de validación del nombre del menú (compartidas por registrar y validar_campo).
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => MENU_NOMBRE_MIN_LENGTH,
    'max'       => MENU_NOMBRE_MAX_LENGTH,
];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'registrar':

        retrasar();

        $nombre   = trim($_POST['nombre'] ?? '');
        $estado   = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $posicion = $_POST['posicion'] ?? '';          // vacío = al final (MAX + 1)

        $errores = [];

        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }

        enviarErrorCamposFormulario($errores);

        try {

            // Si llega una posición se usa; si no, el modelo asigna MAX + 1.
            if ($menuModel->crear($nombre, $estado, $posicion)) {
                echo json_encode([
                    'status'  => 'success',
                    'message' => 'Menú creado con éxito.'
                ]);
            }

        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'obtener':

        $id = $_GET['id'] ?? '';

        try {

            $menu = $menuModel->buscarPorId($id);

            if ($menu) {
                echo json_encode([
                    'status' => 'success',
                    'data'   => $menu
                ]);
            } else {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Menú no encontrado.'
                ]);
            }

        } catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    case 'editar':

        retrasar();

        $id       = $_POST['id_registro'] ?? 0;
        $nombre   = trim($_POST['nombre'] ?? '');
        $estado   = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $posicion = $_POST['posicion'] ?? '';          // vacío = no se reordena

        $errores = [];

        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }

        enviarErrorCamposFormulario($errores);

        try {

            $filasAfectadas = $menuModel->actualizar($id, $nombre, $estado);

            // Solo reordena si se eligió una posición en el modal Asignar Posición.
            $cambioPosicion = 0;
            if (is_numeric($posicion) && (int) $posicion >= 1) {
                $cambioPosicion = $menuModel->reposicionar($id, $posicion);
            }

            if ($filasAfectadas > 0 || $cambioPosicion > 0) {
                echo json_encode([
                    'status'  => 'success',
                    'message' => 'Menú actualizado con éxito.'
                ]);
            } else {
                echo json_encode([
                    'status'  => 'no_changes',
                    'message' => 'No se realizaron cambios.'
                ]);
            }

        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'validar_campo':

        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        $errores = [];

        if ($campo === 'nombre') {
            $errores['nombre'] = validarCampoTexto($valor, 'Nombre', $REGLAS_NOMBRE);
        }

        if (!empty($errores['nombre'])) {
            echo json_encode([
                'status' => 'error',
                'type'   => 'fields',
                'errors' => $errores
            ]);
            exit;
        }

        echo json_encode(['status' => 'success']);
        exit;

    case 'cambiar_estado':

        $id = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador no válido.']);
            exit;
        }

        try {
            $menuModel->cambiarEstado($id);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'listar_orden':

        // Listado completo ordenado por posición (lo usa el modal de Asignar Posición).
        try {
            echo json_encode([
                'status' => 'success',
                'data'   => $menuModel->listarOrdenados()
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    case 'listar':

        // Listado paginado para la tabla principal (DataTables server-side).
        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Filtros: buscador (#consulta) por nombre, y filtro de estado.
        $consulta = trim($_GET['consulta'] ?? '');
        $estado   = $_GET['estado'] ?? '';   // '' = todos, '1' = activo, '0' = inactivo

        // Columna y dirección de ordenamiento (índice -> nombre lógico).
        $columnas     = ['posicion', 'nombre', 'estado'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'posicion';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'asc';

        try {
            $totalRegistros = $menuModel->contarTodos();
            $totalFiltrados = $menuModel->contarFiltrados($consulta, $estado);
            $datos          = $menuModel->listarPagina($consulta, $estado, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[MENUS] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los menús.'
            ]);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
}
