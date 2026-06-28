<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/perfiles_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$perfilModel = new Perfil($pdo);

// Reglas de validación del nombre del perfil (compartidas por registrar y validar_campo):
// obligatorio, entre 2 y 50 caracteres, y único (case-insensitive).
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => PERFIL_NOMBRE_MIN_LENGTH,
    'max'       => PERFIL_NOMBRE_MAX_LENGTH,
    'verificacion_externa' => [
        'mensaje'  => 'Ya existe un <b>Perfil</b> con ese nombre. Utilice otro.',
        'callback' => function ($val) use ($perfilModel) {
            $idRegistro = $_POST['id_registro'] ?? null;   // null en creación
            return $perfilModel->existeNombre($val, $idRegistro);
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

    case 'registrar':

        retrasar();

        $nombre = trim($_POST['nombre'] ?? '');

        // Validación de campos (mismas reglas que la validación instantánea).
        $errores = [];
        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }
        enviarErrorCamposFormulario($errores);

        try {
            $perfilModel->crear($nombre, $_SESSION['usuario_id']);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Perfil creado con éxito.'
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;
}
