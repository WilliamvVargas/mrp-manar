<?php
/**
 * Controlador de la "Explosión de Forecast" (botón del mantenedor de Forecast).
 * Ejecuta, PASO A PASO, el pipeline que pronostica la demanda por grupo (Prophet) y la
 * reparte a productos, invocando por CLI los scripts existentes (php assets/librerias/forecast/*.php y el
 * venv de Python). Una llamada AJAX por paso; devuelve JSON con el resultado para que el
 * modal marque ✓ / ✗.
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_REQUEST['action'] ?? '';

require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('forecast', $action);

set_time_limit(0);

// Rutas absolutas (sin espacios en XAMPP -> no requieren comillas). El EJECUTABLE va con
// backslashes (cmd de Windows); los ARGUMENTOS (rutas de scripts) van con barras normales
// para evitar que secuencias como \f se interpreten como escape en el string PHP.
$rootB = dirname(__DIR__);                       // backslashes (para el .exe del venv)
$rootF = str_replace('\\', '/', $rootB);         // barras normales (para argumentos)

$php = 'C:\\xampp\\php\\php.exe';
if (!is_file($php)) {
    $alt = dirname(PHP_BINARY) . '\\php.exe';
    if (is_file($alt)) { $php = $alt; }
}
$py = $rootB . '\\assets\\librerias\\python\\venv\\Scripts\\python.exe';
$fp = $rootF . '/assets/librerias/python/forecast_prophet.py';
$pru = $rootF . '/assets/librerias/forecast/';

// Contexto del presupuesto para el forecast: empresa activa (de la sesión) + versión
// (elegida en el modal). Se inyecta SOLO a los dos scripts que leen el presupuesto, para
// que el pronóstico use esa empresa/versión y no mezcle el resto. escapeshellarg protege
// los argumentos en la línea de comandos.
$empresaId   = $_SESSION['empresa_id'] ?? '';
$versionPres = trim($_GET['version'] ?? '');
$argsPres    = ' ' . escapeshellarg($empresaId) . ' ' . escapeshellarg($versionPres);

// Pasos del pipeline (mismo orden que la lista del modal).
$pasos = [
    ['label' => 'Exportar series por grupo (SAP + presupuesto)', 'cmd' => $php . ' ' . $pru . 'forecast_export.php' . $argsPres . ' 2>&1'],
    ['label' => 'Entrenar Prophet',                              'cmd' => $py . ' ' . $fp . ' 2>&1'],
    ['label' => 'Fabricar forecast por producto',    'cmd' => $php . ' ' . $pru . 'forecast_cargar.php' . $argsPres . ' 2>&1'],
    ['label' => 'Exportar datos del backtest',                   'cmd' => $php . ' ' . $pru . 'forecast_backtest_export.php' . $argsPres . ' 2>&1'],
    ['label' => 'Prophet backtest (validación)',                 'cmd' => $py . ' ' . $fp . ' backtest 2>&1'],
    ['label' => 'Calcular factor y demanda corregida',           'cmd' => $php . ' ' . $pru . 'forecast_backtest_cargar.php' . $argsPres . ' 2>&1'],
];

switch ($action) {

    case 'explosion_paso':

        // El forecast se calcula para el presupuesto de una empresa + versión concretas.
        if ($empresaId === '') {
            echo json_encode(['status' => 'error', 'message' => 'No hay una empresa activa.']);
            exit;
        }
        if ($versionPres === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se indicó la versión del presupuesto a usar.']);
            exit;
        }

        $i = (int) ($_GET['paso'] ?? -1);
        if (!isset($pasos[$i])) {
            echo json_encode(['status' => 'error', 'message' => 'Paso inválido.']);
            exit;
        }

        // Si el entorno de Python (venv) no existe, se intenta crear automáticamente con
        // setup_python.bat (crea el venv e instala requirements.txt). Solo en el primer paso;
        // es idempotente y la primera vez puede tardar varios minutos (instala Prophet).
        $setOut = [];
        if ($i === 0 && !is_file($py)) {
            $setup = $rootB . '\\assets\\librerias\\python\\setup_python.bat';
            if (is_file($setup)) {
                exec('"' . $setup . '" 2>&1', $setOut, $setRet);
            }
        }

        if (!is_file($php) || !is_file($py) || !is_file($fp)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Falta PHP o el entorno de Python. Se intentó crear el venv con setup_python.bat; '
                           . 'si persiste, verifica que Python esté instalado en el servidor y córrelo manualmente '
                           . '(assets/librerias/python/setup_python.bat).',
                'salida'  => implode("\n", array_slice($setOut, -15)),
            ]);
            exit;
        }

        $salida = [];
        $ret    = 0;
        exec($pasos[$i]['cmd'], $salida, $ret);
        $txt = implode("\n", $salida);

        // Éxito: exit 0 y sin marcas de error de los scripts (ERROR:, Fatal error, Traceback).
        $ok = ($ret === 0)
            && (strpos($txt, 'ERROR:') === false)
            && (stripos($txt, 'Fatal error') === false)
            && (stripos($txt, 'Traceback') === false);

        echo json_encode([
            'status' => 'success',
            'ok'     => $ok,
            'paso'   => $i,
            'label'  => $pasos[$i]['label'],
            'salida' => implode("\n", array_slice($salida, -10)), // últimas líneas, para diagnóstico
        ]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
}
