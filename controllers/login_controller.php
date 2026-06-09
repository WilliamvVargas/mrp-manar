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
    require_once '../includes/funciones_validacion.php';

    $response = ['status' => 'error', 'errors' => []];

    $userPost = trim($_POST['usuario'] ?? '');
    $passPost = trim($_POST['password'] ?? '');

    if ($err = validarCampoTexto($userPost, 'Usuario', ['requerido' => true]))
        $response['errors']['usuario'] = $err;

    if ($err = validarCampoTexto($passPost, 'Contraseña', ['requerido' => true]))
        $response['errors']['password'] = $err;


    if (empty($response['errors'])) {

        try {

            // Validación contra Base de Datos
            $stmt = $pdo->prepare("SELECT id, password_hash FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$userPost]);
            $userDb = $stmt->fetch();

            if ($userDb && password_verify($passPost, $userDb['password_hash'])) {

                // Se guardan datos importantes en la sesión
                $_SESSION['usuario_id'] = $userDb['id'];
                $_SESSION['usuario_nombre'] = $userPost;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $url_defecto = 'dashboard';

                // Se añade una ruta de redirección en caso de haber ingresado por un url
                if (isset($_SESSION['redirect_to'])) {
                    $url_redireccion = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']);
                    $url_defecto = $url_redireccion;
                } 

                $response['redirect'] = $url_defecto;


                $response['status'] = 'success';
            } else {
                $response['errors']['auth'] = "Usuario o Contraseña incorrectos.";
            }
        } 
        catch (PDOException $e) {
            $response['errors']['db'] = "Error de base de datos.";
        }

    }
    else{
        $response['type'] = 'fields';
    }

    echo json_encode($response);

?>