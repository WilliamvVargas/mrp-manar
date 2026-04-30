<?php
session_start();
require_once '../config/conexion.php'; // Subimos un nivel para llegar a includes

// Verificamos sesión por seguridad
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no autorizada']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'listar') {
    try {

        $sql = "SELECT id, usuario, 
                DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha 
                FROM usuarios ORDER BY id DESC";
        
        $stmt = $pdo->prepare($sql);
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

if ($action === 'registrar') {
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validación de campos vacíos
    if (empty($usuario) || empty($password) || empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
        exit;
    }

    // 2. Validación de coincidencia de contraseñas
    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
        exit;
    }

    // 3. Validación de longitud mínima (opcional, pero recomendada)
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
        exit;
    }

    try {
        // 4. Validación de usuario duplicado (usando tu estructura UNIQUE)
        $check = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $check->execute([$usuario]);
        
        if ($check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre de usuario ya existe.']);
            exit;
        }

        // 5. Todo OK -> Proceder al INSERT
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?)";
        $stmt = $conexion->prepare($sql);
        
        if ($stmt->execute([$usuario, $password_hash])) {
            echo json_encode(['status' => 'success', 'message' => 'Usuario creado correctamente.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
    exit;
}