<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';
require_once '../includes/funciones_validacion.php';


function retrasar(){
    time_nanosleep(0, 500000000);
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'listar') {

    try {

        $sql = "SELECT id, 
                       usuario, 
                       DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha 
                FROM usuarios ORDER BY id DESC";
        
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
                       usuario 
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

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errores = validarDatosNuevoUsuario($usuario, $password, $confirm_password);

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
        $query = $pdo->prepare("INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?)");
        
        if ($query->execute([$usuario, $password_hash])) {
            echo json_encode(['status' => 'success', 'message' => 'Usuario creado con éxito']);
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
    $usuario = trim($_POST['usuario'] ?? '');

    $errores = validarUsuario($usuario);

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
        $query = $pdo->prepare("UPDATE usuarios SET usuario = ? WHERE id = ?");
        $query->execute([$usuario, $id]);

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
    $confirm_password = $_POST['confirm-password'] ?? '';


    $errores = validarPassword($password, $confirm_password);

    if (!empty($errores)) {
        echo json_encode([
            'status' => 'error',
            'type' => 'fields',
            'errors' => $errores
        ]);
        exit;
    }


    try {

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $query = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $query->execute([$password_hash, $id]);

        if ($query->rowCount() > 0) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'La contraseña ha sido actualizada con éxito.'
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
    $nombre_usuario = $_POST['usuario'] ?? '';
    $es_actualizacion = boolval($_POST['es_actualizacion'] ?? FALSE );

    try {

        if($es_actualizacion){


            $sql = "SELECT id, 
                           usuario 
                    FROM usuarios 
                    WHERE id = ?";

            $query = $pdo->prepare("$sql");
            $query->execute([$id]);
            $usuario = $query->fetch();

            if ($usuario) {

                $nuevo_password = generarPasswordInteligente($usuario['usuario']);
                $password_hash = password_hash($nuevo_password, PASSWORD_BCRYPT);


                $query = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
                $query->execute([$password_hash, $id]);


                if ($query->rowCount() > 0) {
                    echo json_encode([
                        'status' => 'success', 
                        'message' => 'La contraseña ha sido actualizada con éxito. <hr>Nueva Contraseña: <b>'.$nuevo_password.'</b>',
                    ]);
                } 
                else {
                    echo json_encode([
                        'status' => 'no_changes', 
                        'message' => 'No se realizaron cambios.'
                    ]);
                }

            } 
            else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado.'
                ]);
            }


        }
        else{

            $nuevo_password = generarPasswordInteligente($nombre_usuario);

            echo json_encode([
                'status' => 'success', 
                'password' => $nuevo_password,
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
                'errors' => ['id' => 'El usuario que intenta eliminar no existe.']
            ]);
            exit;
        }


        $query = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        
        if ($query->execute([$id])) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Usuario eliminado con éxito'
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