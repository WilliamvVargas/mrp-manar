<?php

    declare(strict_types=1);
    header('Content-Type: application/json');
    session_start();

    require_once '../config/conexion.php'; 
    require_once 'funciones_validacion.php';

    $response = ['status' => 'error', 'errors' => []];

    $userPost = $_POST['usuario'] ?? '';
    $passPost = $_POST['password'] ?? '';

    //Validación usuario
    $response['errors'] = validarDatosUsuario($userPost, $passPost);

    if (empty($response['errors'])) {

        try {

            // Validación contra Base de Datos
            $stmt = $pdo->prepare("SELECT id, password_hash FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$userPost]);
            $userDb = $stmt->fetch();

            if ($userDb && password_verify($passPost, $userDb['password_hash'])) {

                $_SESSION['usuario_id'] = $userDb['id'];
                $_SESSION['usuario_nombre'] = $userPost;

                $response['status'] = 'success';
            } else {
                $response['errors']['auth'] = "Usuario o Contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $response['errors']['db'] = "Error de base de datos.";
        }

    }

    echo json_encode($response);

?>