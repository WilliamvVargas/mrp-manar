<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';
require_once '../includes/funciones_validacion.php';

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

if ($action === 'obtener') {

    $id = intval($_GET['id'] ?? 0);

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

if ($action === 'registrar') {

    time_nanosleep(0, 500000000);

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

if ($action === 'editar') {

    time_nanosleep(0, 500000000);

    $id = intval($_POST['id_usuario'] ?? 0);
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

if ($action === 'cambiar_password') {

    $id = intval($_POST['id_usuario'] ?? 0);
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