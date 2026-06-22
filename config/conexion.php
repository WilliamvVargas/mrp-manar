<?php
    // Conexión a MySQL vía PDO. Expone $pdo. Los datos de conexión viven como
    // constantes en config/config_mysql.php.

    require_once __DIR__ . '/config_mysql.php';

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO(
            'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DB . ';charset=utf8mb4',
            MYSQL_USER,
            MYSQL_PASS,
            $opciones
        );
    } catch (PDOException $e) {

        $response['errors']['db'] = "Error de conexión.";
        die(json_encode($response));
    }

?>
