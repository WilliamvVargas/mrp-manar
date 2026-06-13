<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';
require_once '../includes/funciones_validacion.php';

function existeUsuario($conexion, $usuario, $idUsuario = null) {

    if ($idUsuario) {

        $sql = "SELECT COUNT(*) 
                FROM usuarios 
                WHERE usuario = ? AND id != ? 
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([$usuario, $idUsuario]);
    } 
    else {

        $sql = "SELECT COUNT(*) 
                FROM usuarios 
                WHERE usuario = ? 
                LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$usuario]);
    }
    
    $total = $stmt->fetchColumn();
    return $total > 0;
}


$action = $_REQUEST['action'] ?? '';

if ($action === 'listar') {

    try {

        $sql = "SELECT id, 
                       usuario,
                       nombres,
                       apellidos,
                       DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha 
                FROM usuarios 
                ORDER BY created_at DESC";
        
        $query = $pdo->prepare("$sql");
        $query->execute();
        $usuarios = $query->fetchAll();

        echo json_encode([
            'status' => 'success',
            'data' => $usuarios
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}
else if ($action === 'obtener') {

    $id = $_GET['id'] ?? '';

    try {

        $sql = "SELECT id, 
                       usuario,
                       nombres,
                       apellidos
                FROM usuarios 
                WHERE id = ?";

        $query = $pdo->prepare("$sql");
        $query->execute([$id]);
        $usuario = $query->fetch();

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
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error: ' . $e->getMessage()]
        );
    }
    exit;
}
else if ($action === 'registrar') {

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

    if (!empty($errores)) {
        echo json_encode([
            'status' => 'error',
            'type' => 'fields',
            'errors' => $errores
        ]);
        exit;
    }

    try {
  
        $validar = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $validar->execute([$usuario]);
        
        if ($validar->rowCount() > 0) {
            echo json_encode([
                'status' => 'error', 
                'type' => 'fields', 
                'errors' => ['usuario' => 'Este nombre de usuario ya está en uso.']
            ]);
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $query = $pdo->prepare("INSERT INTO usuarios (usuario,
                                                      nombres,
                                                      apellidos,
                                                      password_hash) 
                                VALUES (?, ?, ?, ?)");
        
        if ($query->execute([$usuario,
                             $nombres,
                             $apellidos,
                             $password_hash])) {

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
        echo json_encode(['status' => 'error', 'type' => 'general', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;

}
else if ($action === 'editar') {

    retrasar();

    $id = $_POST['id_usuario'] ?? 0;
    $usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');

    $errores = [];

    // Validamos campos del formulario
    if ($err = validarCampoTexto($usuario, 'Usuario', 'usuario'))
        $errores['usuario'] = $err;

    if ($err = validarCampoTexto($nombres, 'Nombres', 'nombres')) 
        $errores['nombres'] = $err;

    if ($err = validarCampoTexto($apellidos, 'Apellidos', 'apellidos'))
        $errores['apellidos'] = $err;

    if (!empty($errores)) {
        echo json_encode([
            'status' => 'error',
            'type' => 'fields',
            'errors' => $errores
        ]);
        exit;
    }

    try {

        $validar = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $validar->execute([$usuario, $id]);
        
        if ($validar->rowCount() > 0) {
            echo json_encode([
                'status' => 'error', 
                'type' => 'fields', 
                'errors' => ['usuario' => 'Este nombre de usuario ya está en uso por otra cuenta.']
            ]);
            exit;
        }

        // Actualizamos estrictamente el campo usuario
        $query = $pdo->prepare("UPDATE usuarios 
                                SET usuario = ?,
                                    nombres = ?,
                                    apellidos = ?
                                WHERE id = ?");

        $query->execute([$usuario, 
                         $nombres,
                         $apellidos,
                         $id]);

        if ($query->rowCount() > 0) {
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
        echo json_encode([
            'status' => 'error', 
            'type' => 'general', 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}
else if ($action === 'cambiar_password') {

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

    if (!empty($errores)) {
        echo json_encode([
            'status' => 'error',
            'type' => 'fields',
            'errors' => $errores
        ]);
        exit;
    }


    try {

        $validar = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        $validar->execute([$id]);
        $usuario = $validar->fetch();

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $query = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $query->execute([$password_hash, $id]);

        if ($query->rowCount() > 0) {
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
        echo json_encode([
            'status' => 'error', 
            'type' => 'general', 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;


}
else if ($action === 'generar_password') {

    retrasar();

    $id = $_POST['id_usuario'] ?? 0;
    $nombre_usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';

    try {


        $sql = "SELECT id, 
                       usuario 
                FROM usuarios 
                WHERE id = ?";

        $query = $pdo->prepare("$sql");
        $query->execute([$id]);
        $usuario = $query->fetch();

        if ($usuario)
            $nombre_usuario = $usuario['usuario'];

        $nuevo_password = generarPasswordInteligente($nombre_usuario);

        echo json_encode([
            'status' => 'success',
            'password' => $nuevo_password,
        ]);

    }
    catch (PDOException $e) {

        echo json_encode([
            'status' => 'error', 
            'message' => 'Error: ' . $e->getMessage()]
        );

    }
    exit;

}
else if ($action === 'eliminar') {

    retrasar();

    $id = $_POST['id_usuario'] ?? 0;

    try {

        $validar = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
        $validar->execute([$id]);
        
        if ($validar->rowCount() === 0) {
            echo json_encode([
                'status' => 'error', 
                'type' => 'fields',
                'message' => 'El usuario que intenta eliminar no existe.'
            ]);
            exit;
        }


        $query = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        
        if ($query->execute([$id])) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Usuario eliminado con éxito.'
            ]);
        }
    } 
    catch (PDOException $e) {

        echo json_encode([
            'status' => 'error', 
            'type' => 'general', 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}
else if ($action === 'validar_campo') {

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

}