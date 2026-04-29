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