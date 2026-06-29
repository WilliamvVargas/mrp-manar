<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/menus_model.php';
require_once __DIR__ . '/../models/iconos_model.php';
require_once __DIR__ . '/../models/item_menus_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$menuModel     = new Menu($pdo);
$iconoModel    = new Icono($pdo);
$itemMenuModel = new ItemMenu($pdo);

// Reglas de validación del Nombre del ítem.
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => ITEM_MENU_NOMBRE_MIN_LENGTH,
    'max'       => ITEM_MENU_NOMBRE_MAX_LENGTH,
];

/**
 * Valida el Menú padre elegido: requerido + que exista en la BD.
 *
 * @return string|null Error si falla, null si pasa.
 */
function validarMenuPadre($valor, Menu $menuModel)
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return 'Debe seleccionar un <b>Menú</b>.';
    }
    if (!ctype_digit($valor) || !$menuModel->buscarPorId((int) $valor)) {
        return 'El <b>Menú</b> seleccionado no es válido.';
    }
    return null;
}

/**
 * Valida el Ícono elegido (opcional): si viene, debe ser un id existente en la
 * tabla de iconos (se guarda como llave foránea icono_id).
 *
 * @return string|null Error si falla, null si pasa.
 */
function validarIconoId($valor, Icono $iconoModel)
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return null;   // el ícono es opcional
    }
    if (!ctype_digit($valor) || !$iconoModel->buscarPorId((int) $valor)) {
        return 'El <b>Ícono</b> seleccionado no existe en el catálogo.';
    }
    return null;
}

/**
 * Normaliza el Enlace a un valor apto para usarse como ruta/URL: minúsculas, sin
 * acentos, espacios convertidos a guiones y sin caracteres ajenos a una ruta.
 *
 * @return string Enlace normalizado (puede quedar vacío si no había nada válido).
 */
function normalizarEnlace($valor)
{
    $e = mb_strtolower(trim((string) $valor), 'UTF-8');
    $e = strtr($e, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'
    ]);
    $e = preg_replace('/\s+/', '-', $e);          // espacios -> guiones
    $e = preg_replace('#[^a-z0-9/_.-]+#', '', $e); // solo caracteres aptos para una ruta
    $e = preg_replace('#/{2,}#', '/', $e);         // colapsa barras repetidas
    $e = trim($e, '/-');                            // sin barras/guiones en los extremos

    return $e;
}

/**
 * Valida el Enlace (obligatorio): se normaliza por referencia a un formato apto para
 * URL y debe quedar con contenido y dentro del largo máximo.
 *
 * @return string|null Error si falla, null si pasa.
 */
function validarEnlace(&$enlace)
{
    $enlace = normalizarEnlace($enlace);

    if ($enlace === '') {
        return 'El campo <b>Enlace</b> es obligatorio.';
    }
    if (mb_strlen($enlace) > ITEM_MENU_ENLACE_MAX_LENGTH) {
        return 'El campo <b>Enlace</b> no puede superar los ' . ITEM_MENU_ENLACE_MAX_LENGTH . ' caracteres.';
    }
    return null;
}

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
// 'menus' y 'listar_orden' los usan los modales de accesos (perfiles/usuarios) y 'reordenar' lo usa
// el mantenedor de menús, así que quedan exentas.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('item-menus', $action, ['menus', 'listar_orden', 'reordenar']);

switch ($action) {

    case 'menus':

        // Lista de menús para el combobox (id, nombre, estado + cantidad de ítems asociados).
        try {
            $menus    = $menuModel->listarOrdenados();
            $conteos  = $itemMenuModel->contarPorTodosLosMenus();

            foreach ($menus as &$m) {
                $m['total_items'] = $conteos[(int) $m['id']] ?? 0;
            }
            unset($m);

            echo json_encode([
                'status' => 'success',
                'data'   => $menus
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'iconos':

        // Catálogo de iconos para el selector de botones (id, nombre, tipo, valor, archivo).
        try {
            echo json_encode([
                'status' => 'success',
                'data'   => $iconoModel->listarOrdenados()
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'listar_orden':

        // Ítems (de todos los menús) ordenados; el modal Asignar Posición filtra por el
        // menú elegido en el formulario. La posición es relativa a cada menú.
        try {
            echo json_encode([
                'status' => 'success',
                'data'   => $itemMenuModel->listarOrdenados()
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'listar':

        // Listado paginado para la tabla principal (DataTables server-side).
        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Filtros: buscador (nombre/enlace), menú y estado.
        $consulta = trim($_GET['consulta'] ?? '');
        $menuId   = $_GET['menu_id'] ?? '';   // '' = todos
        $estado   = $_GET['estado'] ?? '';    // '' = todos, '1' = activo, '0' = inactivo

        // Columna y dirección de ordenamiento (índice -> nombre lógico). Ícono (5) y
        // Acciones (7) no son ordenables.
        $columnas     = [0 => 'id', 1 => 'nombre', 2 => 'enlace', 3 => 'menu', 4 => 'posicion', 6 => 'estado'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'id';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'asc';

        try {
            $totalRegistros = $itemMenuModel->contarTodos();
            $totalFiltrados = $itemMenuModel->contarFiltrados($consulta, $menuId, $estado);
            $datos          = $itemMenuModel->listarPagina($consulta, $menuId, $estado, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[ITEM_MENUS] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los ítems menú.'
            ]);
        }
        exit;

    case 'validar_campo':

        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        $errores = [];

        if ($campo === 'nombre') {
            $errores['nombre'] = validarCampoTexto($valor, 'Nombre', $REGLAS_NOMBRE);
        }
        else if ($campo === 'icono_id') {
            $errores['icono_id'] = validarIconoId($valor, $iconoModel);
        }
        else if ($campo === 'enlace') {
            $errores['enlace'] = validarEnlace($valor);
        }
        else if ($campo === 'menu_id') {
            $errores['menu_id'] = validarMenuPadre($valor, $menuModel);
        }

        // Descarta los campos sin error (validarCampoTexto devuelve null si pasa).
        $errores = array_filter($errores);

        if (!empty($errores)) {
            echo json_encode([
                'status' => 'error',
                'type'   => 'fields',
                'errors' => $errores
            ]);
            exit;
        }

        echo json_encode(['status' => 'success']);
        exit;

    case 'registrar':

        retrasar();

        $menuId   = $_POST['menu_id'] ?? '';
        $nombre   = trim($_POST['nombre'] ?? '');
        $iconoId  = trim($_POST['icono_id'] ?? '');
        $enlace   = trim($_POST['enlace'] ?? '');
        $estado   = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $posicion = $_POST['posicion'] ?? '';          // vacío = al final (dentro del menú)

        // Validación de campos (mismas reglas que la validación instantánea).
        $errores = [];
        if ($err = validarMenuPadre($menuId, $menuModel)) {
            $errores['menu_id'] = $err;
        }
        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }
        if ($err = validarIconoId($iconoId, $iconoModel)) {
            $errores['icono_id'] = $err;
        }
        if ($err = validarEnlace($enlace)) {   // normaliza $enlace por referencia
            $errores['enlace'] = $err;
        }
        enviarErrorCamposFormulario($errores);

        try {
            $itemMenuModel->crear([
                'menu_id'  => (int) $menuId,
                'nombre'   => $nombre,
                'icono_id' => $iconoId,   // FK al catálogo de iconos (o vacío)
                'enlace'   => $enlace,  // normalizado (apto para URL) por validarEnlace
                'estado'   => $estado,
                'posicion' => $posicion,
            ], $_SESSION['usuario_id']);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Ítem menú creado con éxito.'
            ]);

        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'cambiar_estado':

        // Alterna activo/inactivo del ítem (botón de la columna Acciones).
        $id = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador no válido.']);
            exit;
        }

        try {
            $itemMenuModel->cambiarEstado($id);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'obtener':

        // Datos de un ítem para poblar el formulario de edición.
        $id = $_GET['id'] ?? '';
        if (!ctype_digit((string) $id)) {
            echo json_encode(['status' => 'error', 'message' => 'Identificador no válido.']);
            exit;
        }

        try {
            $item = $itemMenuModel->buscarPorId((int) $id);

            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'El ítem menú no existe.']);
                exit;
            }

            echo json_encode([
                'status' => 'success',
                'data'   => $item
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'actualizar':

        retrasar();

        $id       = $_POST['id'] ?? '';
        $menuId   = $_POST['menu_id'] ?? '';
        $nombre   = trim($_POST['nombre'] ?? '');
        $iconoId  = trim($_POST['icono_id'] ?? '');
        $enlace   = trim($_POST['enlace'] ?? '');
        $estado   = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $posicion = $_POST['posicion'] ?? '';          // vacío = al final (dentro del menú)

        // El ítem debe existir.
        if (!ctype_digit((string) $id) || !$itemMenuModel->buscarPorId((int) $id)) {
            echo json_encode(['status' => 'error', 'message' => 'El ítem menú no existe.']);
            exit;
        }

        // Validación de campos (mismas reglas que la creación).
        $errores = [];
        if ($err = validarMenuPadre($menuId, $menuModel)) {
            $errores['menu_id'] = $err;
        }
        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }
        if ($err = validarIconoId($iconoId, $iconoModel)) {
            $errores['icono_id'] = $err;
        }
        if ($err = validarEnlace($enlace)) {   // normaliza $enlace por referencia
            $errores['enlace'] = $err;
        }
        enviarErrorCamposFormulario($errores);

        try {
            $itemMenuModel->actualizar((int) $id, [
                'menu_id'  => (int) $menuId,
                'nombre'   => $nombre,
                'icono_id' => $iconoId,   // FK al catálogo de iconos (o vacío)
                'enlace'   => $enlace,    // normalizado (apto para URL) por validarEnlace
                'estado'   => $estado,
                'posicion' => $posicion,
            ], $_SESSION['usuario_id']);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Ítem menú actualizado con éxito.'
            ]);

        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'eliminar':

        retrasar();

        $id = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador no válido.']);
            exit;
        }

        try {
            if ($itemMenuModel->eliminar($id) > 0) {
                echo json_encode([
                    'status'  => 'success',
                    'message' => 'Ítem menú eliminado con éxito.'
                ]);
            } else {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'El ítem menú que intenta eliminar no existe.'
                ]);
            }
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'reordenar':

        // Reordenamiento masivo de los ítems de un menú (botón Asignar Posición de la raíz).
        retrasar();

        $orden = $_POST['orden'] ?? [];

        if (!is_array($orden) || count($orden) === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Orden no válido.']);
            exit;
        }

        try {
            $itemMenuModel->reordenar($orden);
            echo json_encode([
                'status'  => 'success',
                'message' => 'Orden actualizado con éxito.'
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;
}
