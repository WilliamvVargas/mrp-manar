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
        
        $stmt = $pdo->prepare("$sql");
        $stmt->execute();
        $usuarios = $stmt->fetchAll();

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
        $stmt = $pdo->prepare("$sql");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

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
  
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $check->execute([$usuario]);
        
        if ($check->rowCount() > 0) {
            echo json_encode([
                'status' => 'error', 
                'type' => 'fields', 
                'errors' => ['usuario' => 'Este nombre de usuario ya está en uso.']
            ]);
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?)");
        
        if ($stmt->execute([$usuario, $password_hash])) {
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

        $check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $check->execute([$usuario, $id]);
        
        if ($check->rowCount() > 0) {
            echo json_encode([
                'status' => 'error', 
                'type' => 'fields', 
                'errors' => ['usuario' => 'Este nombre de usuario ya está en uso por otra cuenta.']
            ]);
            exit;
        }

        // Actualizamos estrictamente el campo usuario
        $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ? WHERE id = ?");
        $stmt->execute([$usuario, $id]);

        if ($stmt->rowCount() > 0) {
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

    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error', 
            'type' => 'general', 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($action === 'cambiar_password') {


    echo json_encode([
        'status' => 'error',
        'type' => 'fields',
        'errors' => $_POST
    ]);
    exit;

}