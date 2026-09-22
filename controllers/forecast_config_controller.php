<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/forecast_config_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configModel = new ForecastConfig($pdo);

// Empresa activa: cada configuración se aísla por empresa (mismo criterio que forecast/MRP).
$empresaId = $_SESSION['empresa_id'] ?? null;

// Reglas del nombre: obligatorio, 2–100 caracteres, único POR EMPRESA (case-insensitive).
$REGLAS_NOMBRE = [
    'requerido' => true,
    'min'       => 2,
    'max'       => 100,
    'verificacion_externa' => [
        'mensaje'  => 'Ya existe una <b>Configuración</b> con ese nombre. Utilice otro.',
        'callback' => function ($val) use ($configModel, $empresaId) {
            $idRegistro = $_POST['id_registro'] ?? null;   // null en creación
            return $configModel->existeNombre($empresaId, $val, $idRegistro);
        }
    ]
];

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('configuracion-forecast', $action);

switch ($action) {

    case 'registrar':

        retrasar();

        $nombre = trim($_POST['nombre'] ?? '');
        // Los interruptores llegan solo si están marcados (checkbox value="1"); ausente => 0.
        $imputar     = isset($_POST['imputar_censura']) ? 1 : 0;
        $capar       = isset($_POST['capar_outliers'])  ? 1 : 0;
        $ensamble    = isset($_POST['ensamble'])        ? 1 : 0;
        $estabilizar = isset($_POST['estabilizar_poco_historico']) ? 1 : 0;

        // Parámetros: solo aplican si su opción está activa; si no, se guarda el valor por defecto.
        $caparK    = 10.0;   // por defecto
        $pesoProph = 50;     // por defecto (Prophet 50% / Estacional 50%)
        $estabN    = 52;     // por defecto (semanas de referencia del suavizado)

        $errores = [];
        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }

        // Umbral K del suavizado (1–50, admite un decimal). Se valida solo si la opción está activa.
        if ($capar) {
            $kRaw = str_replace(',', '.', trim($_POST['capar_k'] ?? ''));
            if ($kRaw === '' || !is_numeric($kRaw) || (float) $kRaw < 1 || (float) $kRaw > 50) {
                $errores['capar_k'] = 'Ingrese un umbral entre <b>1</b> y <b>50</b>.';
            } else {
                $caparK = round((float) $kRaw, 1);
            }
        }

        // Peso del ensamble (% de Prophet, entero 0–100). Se valida solo si la opción está activa.
        if ($ensamble) {
            $pRaw = trim($_POST['ensamble_peso_prophet'] ?? '');
            if ($pRaw === '' || !ctype_digit($pRaw) || (int) $pRaw < 0 || (int) $pRaw > 100) {
                $errores['ensamble_peso_prophet'] = 'Ingrese un peso entre <b>0</b> y <b>100</b>.';
            } else {
                $pesoProph = (int) $pRaw;
            }
        }

        // Semanas de referencia del suavizado (entero 4–104). Se valida solo si la opción está activa.
        if ($estabilizar) {
            $nRaw = trim($_POST['estabilizar_n_semanas'] ?? '');
            if ($nRaw === '' || !ctype_digit($nRaw) || (int) $nRaw < 4 || (int) $nRaw > 104) {
                $errores['estabilizar_n_semanas'] = 'Ingrese un valor entre <b>4</b> y <b>104</b> semanas.';
            } else {
                $estabN = (int) $nRaw;
            }
        }

        enviarErrorCamposFormulario($errores);

        try {
            $configModel->crear($empresaId, $nombre, $imputar, $capar, $caparK, $ensamble, $pesoProph, $estabilizar, $estabN, $_SESSION['usuario_id']);
            echo json_encode(['status' => 'success', 'message' => 'Configuración creada con éxito.']);
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
        $errores = array_filter($errores);

        if (!empty($errores)) {
            echo json_encode(['status' => 'error', 'type' => 'fields', 'errors' => $errores]);
            exit;
        }
        echo json_encode(['status' => 'success']);
        exit;

    case 'listar':

        // Listado paginado para la tabla principal (DataTables server-side), acotado a la empresa.
        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        $consulta = trim($_GET['consulta'] ?? '');   // buscador (#consulta) por nombre

        // Columna y dirección de ordenamiento (índice -> nombre lógico).
        $columnas     = [0 => 'nombre', 1 => 'imputar_censura', 2 => 'capar_outliers', 3 => 'ensamble', 4 => 'estabilizar_poco_historico'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'nombre';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'asc';

        try {
            $totalRegistros = $configModel->contarTodos($empresaId);
            $totalFiltrados = $configModel->contarFiltrados($empresaId, $consulta);
            $datos          = $configModel->listarPagina($empresaId, $consulta, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[FORECAST_CONFIG] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar las configuraciones.'
            ]);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
}
