<?php
    $host = 'localhost';
    $db   = 'mrp_manar';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die(json_encode(['status' => 'error', 'message' => 'Error de conexión']));
    }

?>