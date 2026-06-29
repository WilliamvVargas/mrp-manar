<?php

    require_once __DIR__ . '/../config/sesion.php';

    $sesionValida = isset($_SESSION['usuario_id']);
    $usuarioBloqueado = false;

    // Con sesión activa, el usuario debe seguir ACTIVO (estado = 1). Si fue inactivado se cierra
    // la sesión y se lo marca como bloqueado (mensaje propio); si ya no existe, sesión expirada.
    if ($sesionValida) {
        require_once __DIR__ . '/../config/conexion.php';

        $stmtEstado = $pdo->prepare("SELECT estado FROM usuarios WHERE id = ? LIMIT 1");
        $stmtEstado->execute([$_SESSION['usuario_id']]);
        $estadoUsuario = $stmtEstado->fetchColumn();

        if ($estadoUsuario === false || (int) $estadoUsuario !== 1) {
            $usuarioBloqueado = ($estadoUsuario !== false);   // existe pero inactivo = bloqueado
            $_SESSION = [];
            session_destroy();
            $sesionValida = false;
        }
    }

    if (!$sesionValida) {

        $codigoError = $usuarioBloqueado ? 'usuario_bloqueado' : 'session_expired';

        //Retorna mensaje de error de no autorizado en caso que se haya utilizado una llamada via AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status'  => $codigoError,
                'error'   => $codigoError,
                'message' => $usuarioBloqueado
                    ? 'Usuario bloqueado'
                    : 'Su sesión ha expirado, por favor inicie sesión nuevamente.'
            ]);
            exit;
        }

        //Se guarda la url de un mantenedor y se envia a que primero ingrese sus credenciales
        if (!$usuarioBloqueado) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        }
        header("Location: index?error={$codigoError}");
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