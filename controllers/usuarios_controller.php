<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';
require_once '../includes/funciones_validacion.php';

$action = $_GET['action'] ?? '';

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
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errores = validarDatosUsuario($usuario, $password, $confirm_password, true);

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