<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Catálogo de nombres válidos de Bootstrap Icons (para validar el tipo 'bootstrap').
$ICONOS_BOOTSTRAP = require __DIR__ . '/../data/iconos_bootstrap.php';

// Reglas de validación del nombre visible del icono.
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => 2,
    'max'       => 60,
];

// Reglas del campo "Icono de Bootstrap": formato correcto + que exista en el catálogo.
// Acepta tanto 'house' como 'bi-house' (el prefijo se normaliza para la comprobación).
$REGLAS_VALOR_BOOTSTRAP = [
    'requerido'      => true,
    'max'            => 60,
    'patron'         => '/^[a-z0-9-]+$/',
    'mensaje_patron' => 'Usa solo minúsculas, números y guiones (ej: <b>house</b> o <b>bi-house</b>).',
    'verificacion_externa' => [
        'mensaje'  => 'Ese icono no existe en Bootstrap Icons.',
        'callback' => function ($val) use ($ICONOS_BOOTSTRAP) {
            $nombre = preg_replace('/^bi-/', '', trim($val));
            // Devuelve true cuando el icono NO existe (condición de error).
            return !in_array($nombre, $ICONOS_BOOTSTRAP, true);
        }
    ]
];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'validar_campo':

        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        $errores = [];

        if ($campo === 'nombre') {
            $errores['nombre'] = validarCampoTexto($valor, 'Nombre', $REGLAS_NOMBRE);
        }
        else if ($campo === 'valor_bootstrap') {
            $errores['valor_bootstrap'] = validarCampoTexto($valor, 'Icono', $REGLAS_VALOR_BOOTSTRAP);
        }

        // Descarta los campos sin error (validarCampoTexto devuelve null si pasa).
        $errores = array_filter($errores);

        if (!empty($errores)) {
            echo json_encode([
                'status' => 'error',
                'type'   => 'fields',
                'errors' => $errores
            ]);
            exit;
        }

        echo json_encode(['status' => 'success']);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
}
