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

    //Validacion token CSRF en caso que quiera manipular un registro.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        $tokenRecibido = $_POST['csrf_token'] ?? '';
        $tokenSesion = $_SESSION['csrf_token'] ?? '';

        if (empty($tokenRecibido) || $tokenRecibido !== $tokenSesion) {
            header('Content-Type: application/json');
            http_response_code(403);
            
            echo json_encode([
                'status' => 'csrf_error',
                'success' => false,
                'message' => 'Error de seguridad: Solicitud no autorizada (CSRF inválido).'
            ]);
            exit;
        }
    }

?>