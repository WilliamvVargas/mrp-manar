<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/usuario_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$usuarioModel = new Usuario($pdo);

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'listar':

        try {

            $usuarios = $usuarioModel->listarTodos();

            echo json_encode([
                'status' => 'success',
                'data' => $usuarios
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    case 'obtener':

        $id = $_GET['id'] ?? '';

        try {

            $usuario = $usuarioModel->buscarPorId($id);

            if ($usuario) {
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

    case 'registrar':

        retrasar();

        $usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errores = [];

        // Validamos campos del formulario
        if ($err = validarCampoTexto($usuario, 'Usuario', 'usuario', $pdo))
            $errores['usuario'] = $err;

        if ($err = validarCampoTexto($nombres, 'Nombres', 'nombres'))
            $errores['nombres'] = $err;

        if ($err = validarCampoTexto($apellidos, 'Apellidos', 'apellidos'))
            $errores['apellidos'] = $err;

        if ($err = validarCampoTexto($password, 'Contraseña', 'password'))
            $errores['password'] = $err;

        if ($err = validarCampoTexto($confirm_password, 'Repetir Contraseña', ['requerido' => true,
                                                                               'coincide_con' => $password]))
            $errores['confirm_password'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            if ($usuarioModel->crear($usuario, $nombres, $apellidos, $password_hash)) {

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

        $errores = [];

        // Validamos campos del formulario
        if ($err = validarCampoTexto($usuario, 'Usuario', 'usuario', $pdo))
            $errores['usuario'] = $err;

        if ($err = validarCampoTexto($nombres, 'Nombres', 'nombres'))
            $errores['nombres'] = $err;

        if ($err = validarCampoTexto($apellidos, 'Apellidos', 'apellidos'))
            $errores['apellidos'] = $err;

        enviarErrorCamposFormulario($errores);

        try {

            $filasAfectadas = $usuarioModel->actualizarDatos($id, $usuario, $nombres, $apellidos);

            if ($filasAfectadas > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Usuario actualizado con éxito.'
                ]);
            }
            else {
                echo json_encode([
                    'status' => 'no_changes',
                    'message' => 'No se realizaron cambios.'
                ]);
            }

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

            $filasAfectadas = $usuarioModel->actualizarPassword($id, $password_hash);

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

            if (!$usuarioModel->existePorId($id)) {
                echo json_encode([
                    'status' => 'error',
                    'type' => 'fields',
                    'message' => 'El usuario que intenta eliminar no existe.'
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
        else{
           $errores[$campo] = validarCampoTexto($valor, $nombreFormulario, $campo, $pdo);
        }

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
