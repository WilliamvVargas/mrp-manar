<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/usuario_model.php';
require_once __DIR__ . '/../models/perfiles_model.php';
require_once __DIR__ . '/../models/usuario_empresa_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$usuarioModel        = new Usuario($pdo);
$perfilModel         = new Perfil($pdo);
$usuarioEmpresaModel = new UsuarioEmpresa($pdo);

// El primer usuario (sembrado) se llama "admin": no puede cambiar su nombre de usuario
// ni eliminarse. Se identifica por su nombre de usuario (el id es un UUID).
const USUARIO_ADMIN = 'admin';

/**
 * Valida el Perfil elegido: requerido + que exista en la BD.
 *
 * @return string|null Error si falla, null si pasa.
 */
function validarPerfilUsuario($valor, Perfil $perfilModel)
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return 'Debe seleccionar un <b>Perfil</b>.';
    }
    if (!ctype_digit($valor) || !$perfilModel->buscarPorId((int) $valor)) {
        return 'El <b>Perfil</b> seleccionado no es válido.';
    }
    return null;
}

/**
 * Valida las Empresas asignadas: al menos una (obligatorio) y que todas existan.
 *
 * @return string|null Error si falla, null si pasa.
 */
function validarEmpresasUsuario(array $empresasSel, PDO $pdo)
{
    $ids = array_values(array_filter($empresasSel, function ($v) {
        return $v !== '' && $v !== null;
    }));

    if (count($ids) === 0) {
        return 'Debe asignar al menos una <b>Empresa</b>.';
    }

    $unicos       = array_values(array_unique($ids));
    $placeholders = implode(',', array_fill(0, count($unicos), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE id IN ($placeholders)");
    $stmt->execute($unicos);
    if ((int) $stmt->fetchColumn() !== count($unicos)) {
        return 'Alguna <b>Empresa</b> seleccionada no es válida.';
    }
    return null;
}

/**
 * Firma normalizada de una asignación de empresas (ids ordenados + la por defecto), para
 * detectar cambios al editar sin depender del orden en que llegan.
 *
 * @return string
 */
function firmaEmpresas(array $ids, $porDefecto)
{
    $ids = array_values(array_unique(array_filter($ids, function ($v) {
        return $v !== '' && $v !== null;
    })));
    sort($ids);
    if (!in_array($porDefecto, $ids, true)) {
        $porDefecto = $ids[0] ?? '';
    }
    return implode(',', $ids) . '|' . $porDefecto;
}

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
// 'validar_campo' y 'cambiar_password_propio' las usa el modal de contraseña propia del navbar
// (cualquier usuario), así que quedan exentas.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('usuarios', $action, ['validar_campo', 'cambiar_password_propio']);

switch ($action) {

    case 'listar':

        retrasar();

        // Parámetros que envía DataTables en modo server-side
        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Filtro propio del buscador (#consulta): por usuario, nombres o apellidos
        $consulta = trim($_GET['consulta'] ?? '');

        // Filtros adicionales de la tabla.
        $idPerfil = $_GET['id_perfil'] ?? '';   // '' = todos
        $estado   = $_GET['estado'] ?? '';      // '', '1' o '0'

        // Columna y dirección de ordenamiento (índice -> nombre lógico)
        $columnas     = ['usuario', 'nombres', 'apellidos', 'perfil', 'fecha', 'estado'];
        $idxOrden     = (int) ($_GET['order'][0]['column'] ?? 4);
        $columnaOrden = $columnas[$idxOrden] ?? 'fecha';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'desc';

        try {

            $totalRegistros = $usuarioModel->contarTodos();
            $totalFiltrados = $usuarioModel->contarFiltrados($consulta, $idPerfil, $estado);
            $datos          = $usuarioModel->listarPagina($consulta, $idPerfil, $estado, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[BD] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los usuarios.'
            ]);
        }
        exit;

    case 'obtener':

        $id = $_GET['id'] ?? '';

        try {

            $usuario = $usuarioModel->buscarPorId($id);

            if ($usuario) {
                // Empresas asignadas (para marcar los checkboxes y la por defecto).
                $usuario['empresas'] = $usuarioEmpresaModel->empresasDe($id);
                echo json_encode([
                    'status' => 'success',
                    'data' => $usuario
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado.'
                ]);
            }

        } catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    case 'empresas_catalogo':

        // Catálogo de empresas para los checkboxes del formulario de usuario.
        try {
            $empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY posicion ASC, nombre ASC")
                            ->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $empresas]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'registrar':

        retrasar();

        $usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $idPerfil = $_POST['id_perfil'] ?? '';
        $estado = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $empresasSel    = isset($_POST['empresas']) && is_array($_POST['empresas']) ? $_POST['empresas'] : [];
        $empresaDefecto = $_POST['empresa_defecto'] ?? '';

        $errores = [];

        // Validamos campos del formulario
        if ($err = validarCampoTexto($usuario, 'Usuario', 'usuario', $pdo))
            $errores['usuario'] = $err;

        if ($err = validarCampoTexto($nombres, 'Nombres', 'nombres'))
            $errores['nombres'] = $err;

        if ($err = validarCampoTexto($apellidos, 'Apellidos', 'apellidos'))
            $errores['apellidos'] = $err;

        if ($err = validarPerfilUsuario($idPerfil, $perfilModel))
            $errores['id_perfil'] = $err;

        if ($err = validarEmpresasUsuario($empresasSel, $pdo))
            $errores['empresas'] = $err;

        if ($err = validarCampoTexto($password, 'Contraseña', 'password'))
            $errores['password'] = $err;

        if ($err = validarCampoTexto($confirm_password, 'Repetir Contraseña', ['requerido' => true,
                                                                               'coincide_con' => $password]))
            $errores['confirm_password'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            if ($usuarioModel->crear($usuario, $nombres, $apellidos, $password_hash, $idPerfil, $estado, $_SESSION['usuario_id'])) {

                // Recupera el id (UUID lo pone el trigger) para asignar sus empresas.
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt->execute([$usuario]);
                $nuevoId = $stmt->fetchColumn();
                if ($nuevoId) {
                    $usuarioEmpresaModel->sincronizar($nuevoId, $empresasSel, $empresaDefecto, $_SESSION['usuario_id']);
                }

                echo json_encode(['status' => 'success',
                                  'message' => 'Usuario creado con éxito',
                                  'credenciales' => [
                                                        'usuario' => $usuario,
                                                        'password' => $password
                                                    ]
                                 ]);

            }

        }
        catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'editar':

        retrasar();

        $id = $_POST['id_registro'] ?? 0;
        $usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $idPerfil = $_POST['id_perfil'] ?? '';
        $estado = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $empresasSel    = isset($_POST['empresas']) && is_array($_POST['empresas']) ? $_POST['empresas'] : [];
        $empresaDefecto = $_POST['empresa_defecto'] ?? '';

        $errores = [];

        // Validamos campos del formulario
        if ($err = validarCampoTexto($usuario, 'Usuario', 'usuario', $pdo))
            $errores['usuario'] = $err;

        if ($err = validarCampoTexto($nombres, 'Nombres', 'nombres'))
            $errores['nombres'] = $err;

        if ($err = validarCampoTexto($apellidos, 'Apellidos', 'apellidos'))
            $errores['apellidos'] = $err;

        if ($err = validarPerfilUsuario($idPerfil, $perfilModel))
            $errores['id_perfil'] = $err;

        if ($err = validarEmpresasUsuario($empresasSel, $pdo))
            $errores['empresas'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            // El usuario debe existir (guardamos su estado actual para comparar cambios).
            $actual = $usuarioModel->buscarPorId($id);
            if (!$actual) {
                echo json_encode(['status' => 'error', 'message' => 'El usuario no existe.']);
                exit;
            }

            // El usuario "admin" no puede cambiar su nombre de usuario (sí el resto de sus datos).
            if ($actual['usuario'] === USUARIO_ADMIN && $usuario !== USUARIO_ADMIN) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'No se puede cambiar el nombre de usuario de <b>admin</b>.'
                ]);
                exit;
            }

            // El usuario "admin" tampoco puede cambiar de estado: se conserva el actual.
            if ($actual['usuario'] === USUARIO_ADMIN) {
                $estado = (int) $actual['estado'];
            }

            // Firma de las empresas actuales vs. las enviadas (para incluirlas en la
            // detección de cambios: pudieron cambiar aunque los datos del usuario no).
            $empresasActuales = $usuarioEmpresaModel->empresasDe($id);
            $idsActuales      = array_column($empresasActuales, 'id_empresa');
            $defectoActual    = '';
            foreach ($empresasActuales as $e) {
                if ((int) $e['por_defecto'] === 1) { $defectoActual = $e['id_empresa']; break; }
            }
            $cambioEmpresas = firmaEmpresas($idsActuales, $defectoActual)
                           !== firmaEmpresas($empresasSel, $empresaDefecto);

            // Sin cambios: los datos son idénticos a los actuales. Se detecta comparando el DATO
            // (no por filas afectadas), porque actualizar tocaría updated_by y daría un falso
            // "éxito" en un registro recién creado (cuyo updated_by aún es NULL).
            $sinCambios = $actual['usuario']   === $usuario
                       && $actual['nombres']   === $nombres
                       && $actual['apellidos'] === $apellidos
                       && (string) $actual['id_perfil'] === (string) $idPerfil
                       && (int) $actual['estado'] === $estado
                       && !$cambioEmpresas;

            if ($sinCambios) {
                echo json_encode([
                    'status'  => 'no_changes',
                    'message' => 'No se realizaron cambios.'
                ]);
                exit;
            }

            $usuarioModel->actualizarDatos($id, $usuario, $nombres, $apellidos, $idPerfil, $estado, $_SESSION['usuario_id']);
            $usuarioEmpresaModel->sincronizar($id, $empresasSel, $empresaDefecto, $_SESSION['usuario_id']);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Usuario actualizado con éxito.'
            ]);

        }
        catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'cambiar_estado':

        $id = $_POST['id'] ?? '';

        $registro = ($id !== '') ? $usuarioModel->buscarPorId($id) : null;

        if (!$registro) {
            echo json_encode(['status' => 'error', 'message' => 'El usuario no existe.']);
            exit;
        }

        // El usuario "admin" no puede cambiar de estado.
        if ($registro['usuario'] === USUARIO_ADMIN) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'No se puede cambiar el estado del usuario <b>admin</b>.'
            ]);
            exit;
        }

        try {
            $usuarioModel->cambiarEstado($id, $_SESSION['usuario_id']);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'cambiar_password_propio':

        retrasar();

        // El usuario en sesión cambia SU PROPIA contraseña: el id viene de la sesión, nunca del
        // cliente, así que no puede cambiar la de otro usuario.
        $id = $_SESSION['usuario_id'];
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errores = [];

        if ($err = validarCampoTexto($password, 'Contraseña', 'password'))
            $errores['password'] = $err;

        if ($err = validarCampoTexto($confirm_password, 'Repetir Contraseña', ['requerido' => true,
                                                                               'coincide_con' => $password]))
            $errores['confirm_password'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $usuarioModel->actualizarPassword($id, $password_hash, $_SESSION['usuario_id']);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Tu contraseña ha sido actualizada con éxito.'
            ]);

        }
        catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'cambiar_password':

        retrasar();

        $id = $_POST['id_usuario'] ?? 0;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errores = [];

        if ($err = validarCampoTexto($password, 'Contraseña', 'password'))
            $errores['password'] = $err;

        if ($err = validarCampoTexto($confirm_password, 'Repetir Contraseña', ['requerido' => true,
                                                                               'coincide_con' => $password]))
            $errores['confirm_password'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            $usuario = $usuarioModel->buscarPorId($id);

            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $filasAfectadas = $usuarioModel->actualizarPassword($id, $password_hash, $_SESSION['usuario_id']);

            if ($filasAfectadas > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'La contraseña ha sido actualizada con éxito.',
                    'credenciales' => [
                                        'usuario' => $usuario['usuario'],
                                        'password' => $password
                                      ]
                ]);
            }
            else {
                echo json_encode([
                    'status' => 'no_changes',
                    'message' => 'No se realizaron cambios.'.$id
                ]);
            }

        }
        catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'generar_password':

        retrasar();

        $id = $_POST['id_usuario'] ?? 0;
        $nombre_usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';

        try {

            $usuario = $usuarioModel->buscarPorId($id);

            if ($usuario)
                $nombre_usuario = $usuario['usuario'];

            $nuevo_password = generarPasswordInteligente($nombre_usuario);

            echo json_encode([
                'status' => 'success',
                'password' => $nuevo_password,
            ]);

        }
        catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    case 'eliminar':

        retrasar();

        $id = $_POST['id_usuario'] ?? 0;

        try {

            $registro = $usuarioModel->buscarPorId($id);

            if (!$registro) {
                echo json_encode([
                    'status' => 'error',
                    'type' => 'fields',
                    'message' => 'El usuario que intenta eliminar no existe.'
                ]);
                exit;
            }

            // El usuario "admin" no se puede eliminar.
            if ($registro['usuario'] === USUARIO_ADMIN) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'No se puede eliminar al usuario <b>admin</b>.'
                ]);
                exit;
            }

            if ($usuarioModel->eliminar($id)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Usuario eliminado con éxito.'
                ]);
            }
        }
        catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'validar_campo':

        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';
        $extra = $_POST['extra'] ?? '';

        $nombresLegibles = [
            'usuario'   => 'Usuario',
            'nombres'   => 'Nombres',
            'apellidos' => 'Apellidos',
            'password'  => 'Contraseña',
            'confirm_password'  => 'Repetir Contraseña'
        ];

        $nombreFormulario = $nombresLegibles[$campo] ?? ucfirst($campo);

        $errores = [];

        if( $campo === 'confirm_password')

            $errores[$campo] = validarCampoTexto($valor, $nombreFormulario,
                                                                    ['requerido' => true,
                                                                     'coincide_con' => $extra]);
        else if ($campo === 'id_perfil')
            $errores[$campo] = validarPerfilUsuario($valor, $perfilModel);
        else if (in_array($campo, ['usuario', 'nombres', 'apellidos', 'password'], true))
           $errores[$campo] = validarCampoTexto($valor, $nombreFormulario, $campo, $pdo);
        // Otros campos (p. ej. el switch 'estado') no tienen validación instantánea.

        if (!empty($errores[$campo])) {
            echo json_encode([
                'status' => 'error',
                'type' => 'fields',
                'errors' => $errores
            ]);
            exit;
        }

        echo json_encode(['status' => 'success']);
        exit;

    default:

        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Acción no válida.'
        ]);
        exit;
}
