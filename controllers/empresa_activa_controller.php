<?php
    /*
     * Cambio de la EMPRESA ACTIVA del usuario en sesión (selector del navbar).
     * Disponible para cualquier usuario autenticado (no depende de accesos por sección):
     * solo requiere sesión válida + CSRF (los valida includes/auth.php).
     *
     * Setea $_SESSION['empresa_id'], que es lo que lee la fábrica conectarSap() para armar
     * la conexión SAP. Valida que la empresa esté asignada al usuario (no se puede activar
     * una empresa ajena).
     */
    require_once __DIR__ . '/../includes/auth.php';                 // sesión + CSRF en POST
    require_once __DIR__ . '/../config/conexion.php';               // $pdo
    require_once __DIR__ . '/../models/usuario_empresa_model.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $action = $_REQUEST['action'] ?? '';
    $model  = new UsuarioEmpresa($pdo);

    switch ($action) {

        case 'cambiar':

            $idUsuario = $_SESSION['usuario_id'] ?? null;
            $idEmpresa = $_POST['empresa_id'] ?? '';

            if ($idEmpresa === '' || !$model->perteneceA($idUsuario, $idEmpresa)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Empresa no válida para este usuario.']);
                exit;
            }

            $_SESSION['empresa_id'] = $idEmpresa;
            echo json_encode(['status' => 'success', 'message' => 'Empresa activa actualizada.']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
?>
