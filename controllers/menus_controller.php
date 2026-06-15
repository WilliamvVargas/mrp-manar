<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/menus_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$menuModel = new Menu($pdo);

// Reglas de validación del nombre del menú (compartidas por registrar y validar_campo).
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => MENU_NOMBRE_MIN_LENGTH,
    'max'       => MENU_NOMBRE_MAX_LENGTH,
];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'registrar':

        retrasar();

        $nombre   = trim($_POST['nombre'] ?? '');
        $estado   = isset($_POST['estado']) ? 1 : 0;   // switch: presente = activo
        $posicion = $_POST['posicion'] ?? '';          // vacío = al final (MAX + 1)

        $errores = [];

        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }

        enviarErrorCamposFormulario($errores);

        try {

            // Si llega una posición se usa; si no, el modelo asigna MAX + 1.
            if ($menuModel->crear($nombre, $estado, $posicion)) {
                echo json_encode([
                    'status'  => 'success',
                    'message' => 'Menú creado con éxito.'
                ]);
            }

        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'validar_campo':

        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        $errores = [];

        if ($campo === 'nombre') {
            $errores['nombre'] = validarCampoTexto($valor, 'Nombre', $REGLAS_NOMBRE);
        }

        if (!empty($errores['nombre'])) {
            echo json_encode([
                'status' => 'error',
                'type'   => 'fields',
                'errors' => $errores
            ]);
            exit;
        }

        echo json_encode(['status' => 'success']);
        exit;

    case 'listar':

        try {
            echo json_encode([
                'status' => 'success',
                'data'   => $menuModel->listarOrdenados()
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e, null);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
}
