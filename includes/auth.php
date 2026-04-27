<?php

    declare(strict_types=1);
    header('Content-Type: application/json');
    session_start();

    $tokenPost = $_POST['csrf_token'] ?? '';
    $tokenSession = $_SESSION['csrf_token'] ?? '';
    $response = ['status' => 'error', 'errors' => []];

    if (empty($tokenPost) || !hash_equals($tokenSession, $tokenPost)) {
        $response['errors']['auth'] = "Sesión inválida o token expirado. <br>Por favor, recarga la página.";
        echo json_encode($response);
        exit;
    }
    
    require_once '../config/conexion.php'; 
    require_once 'funciones_validacion.php';

    $response = ['status' => 'error', 'errors' => []];

    $userPost = trim($_POST['usuario'] ?? '');
    $passPost = trim($_POST['password'] ?? '');

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
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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