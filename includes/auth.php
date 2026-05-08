<?php

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario_id'])) {

        //Retorna mensaje de error de no autorizado en caso que se haya utilizado una llamada via AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'session_expired',
                'message' => 'Su sesión ha expirado, por favor inicie sesión nuevamente.'
            ]);
            exit;
        }

        //Se guarda la url de un mantenedor y se envia a que primero ingrese sus credenciales
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header("Location: index?error=session_expired"); 
        exit;
    }

?>