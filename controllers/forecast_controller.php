<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../assets/librerias/lector_xlsx/lector_xlsx.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Mapeo de columnas del Excel -> campos del forecast.
$COLUMNAS_FORECAST = [
    'A' => 'empresa',
    'B' => 'version',
    'C' => 'cod_cliente',
    'D' => 'nombre_cliente',
    'E' => 'fecha',
    'F' => 'codigo_producto',
    'G' => 'nombre_producto',
    'H' => 'cantidad',
];

// Cabeceras esperadas en la fila 1 (para validar que el archivo tenga el formato correcto).
$CABECERAS_ESPERADAS = [
    'A' => 'EMPRESA',
    'B' => 'VERSION',
    'C' => 'COD CLIENTE',
    'D' => 'NOMBRE CLIENTE',
    'E' => 'FECHA',
    'F' => 'CODIGO PRODUCTO',
    'G' => 'NOMBRE PRODUCTO',
    'H' => 'CANTIDAD',
];

/**
 * Convierte un número de serie de fecha de Excel a 'Y-m-d'.
 * Excel cuenta los días desde 1900; el desfase 25569 lo lleva a la época Unix.
 * Devuelve null si el valor está vacío o no es numérico.
 */
function serialExcelAFecha($serial)
{
    if ($serial === '' || !is_numeric($serial)) {
        return null;
    }
    return gmdate('Y-m-d', (int) round(((float) $serial - 25569) * 86400));
}

/**
 * Responde un error en JSON y termina la ejecución.
 */
function errorForecast($mensaje)
{
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'procesar':

        retrasar();

        // 1. Validar que llegó un archivo y que la subida no tuvo errores.
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            errorForecast('No se recibió el archivo o hubo un error en la subida.');
        }

        $archivo = $_FILES['archivo'];

        // 2. Validación server-side de la extensión (.xlsx).
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            errorForecast('El archivo debe ser un Excel .xlsx.');
        }

        // 2b. Tope de peso del archivo (protege la memoria ANTES de leerlo).
        if ($archivo['size'] > FORECAST_MAX_PESO_MB * 1024 * 1024) {
            errorForecast('El archivo supera el tamaño máximo permitido (' . FORECAST_MAX_PESO_MB . ' MB).');
        }

        // 3. Leer el archivo con la librería nativa.
        try {
            $lector = new LectorXlsx($archivo['tmp_name']);
            $filas  = $lector->leerFilas();
        } catch (Throwable $e) {
            error_log('[FORECAST] ' . $e->getMessage());
            errorForecast('No se pudo leer el archivo. ¿Está dañado o no es un .xlsx válido?');
        }

        if (count($filas) < 2) {
            errorForecast('El archivo no contiene registros (solo cabecera o está vacío).');
        }

        // 3b. Límite de filas a procesar (sin contar la cabecera).
        $totalDatos = count($filas) - 1;
        if ($totalDatos > FORECAST_MAX_FILAS) {
            errorForecast('El archivo tiene ' . $totalDatos . ' filas. El máximo permitido es ' . FORECAST_MAX_FILAS . '.');
        }

        // 4. Validar que la cabecera (fila 1) coincida con el formato esperado.
        $cabecera = $filas[0];
        foreach ($CABECERAS_ESPERADAS as $col => $titulo) {
            $valor = trim($cabecera[$col] ?? '');
            if (strcasecmp($valor, $titulo) !== 0) {
                errorForecast("El archivo no tiene el formato esperado: falta o no coincide la columna \"{$titulo}\".");
            }
        }

        // 5. Extraer los datos (desde la fila 2): mapear columnas y convertir la fecha.
        $registros  = [];
        $totalFilas = count($filas);

        for ($i = 1; $i < $totalFilas; $i++) {
            $fila = $filas[$i];

            // Omite filas completamente vacías.
            $tieneDatos = false;
            foreach ($fila as $celda) {
                if (trim($celda) !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if (!$tieneDatos) {
                continue;
            }

            $registro = [];
            foreach ($COLUMNAS_FORECAST as $col => $campo) {
                $valor = trim($fila[$col] ?? '');

                if ($campo === 'fecha') {
                    $valor = serialExcelAFecha($valor);
                } elseif ($campo === 'cantidad') {
                    $valor = ($valor === '') ? null : (int) $valor;
                }

                $registro[$campo] = $valor;
            }

            $registros[] = $registro;
        }

        echo json_encode([
            'status' => 'success',
            'total'  => count($registros),
            'data'   => $registros
        ]);
        exit;

    default:
        http_response_code(400);
        errorForecast('Acción no válida.');
}
