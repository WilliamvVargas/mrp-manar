<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../assets/librerias/lector_xlsx/lector_xlsx.php';
require_once __DIR__ . '/../models/presupuesto_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Hoja del Excel que contiene los datos.
$HOJA_PRESUPUESTO = 'base';

// Mapeo de columnas del Excel (hoja "base", C a M) -> campos de la tabla.
$COLUMNAS_PRESUPUESTO = [
    'C' => 'anio',
    'D' => 'mes',
    'E' => 'canal',
    'F' => 'sub_canal',
    'G' => 'familia',
    'H' => 'sub_familia',
    'I' => 'venta',
    'J' => 'mg_porcentaje',
    'K' => 'mg_neto',
    'L' => 'pp',
    'M' => 'kg',
];

// Cabeceras esperadas en la fila 1 (para validar el formato del archivo).
$CABECERAS_ESPERADAS = [
    'C' => 'Año',
    'D' => 'Mes',
    'E' => 'Canal',
    'F' => 'Sub-Canal',
    'G' => 'Familia',
    'H' => 'Sub-Familia',
    'I' => 'Venta',
    'J' => 'MG %',
    'K' => 'MG Neto',
    'L' => 'PP',
    'M' => 'KG',
];

// Escala decimal de cada numérico: redondea y elimina el ruido de coma flotante.
// (mg_porcentaje se trata aparte: viene como fracción y se guarda como punto porcentual.)
$DECIMALES = [
    'venta'   => 2,
    'mg_neto' => 2,
    'pp'      => 4,
    'kg'      => 4,
];

// Campos obligatorios: si a la fila le falta alguno, NO se guarda (descarta la fila de totales).
$OBLIGATORIOS = ['anio', 'mes', 'canal', 'sub_canal', 'familia', 'venta'];

/**
 * Responde un error en JSON y termina la ejecución.
 */
function errorPresupuesto($mensaje)
{
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
    exit;
}

/**
 * Normaliza un encabezado para compararlo (sin distinguir mayúsculas ni espacios extra).
 */
function normalizarCabecera($s)
{
    return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
}

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('presupuesto', $action);

switch ($action) {

    case 'procesar':

        retrasar();

        // 1. Validar que llegó un archivo y que la subida no tuvo errores.
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            errorPresupuesto('No se recibió el archivo o hubo un error en la subida.');
        }

        $archivo = $_FILES['archivo'];

        // 2. Validación server-side de la extensión (.xlsx).
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            errorPresupuesto('El archivo debe ser un Excel .xlsx.');
        }

        // 2b. Tope de peso del archivo (protege la memoria ANTES de leerlo).
        if ($archivo['size'] > PRESUPUESTO_MAX_PESO_MB * 1024 * 1024) {
            errorPresupuesto('El archivo supera el tamaño máximo permitido (' . PRESUPUESTO_MAX_PESO_MB . ' MB).');
        }

        // 3. Leer la hoja "base" con la librería nativa (por nombre de pestaña).
        try {
            $lector = new LectorXlsx($archivo['tmp_name']);
            $filas  = $lector->leerFilasPorNombre($HOJA_PRESUPUESTO);
        } catch (Throwable $e) {
            error_log('[PRESUPUESTO] ' . $e->getMessage());
            errorPresupuesto('No se pudo leer la hoja "' . $HOJA_PRESUPUESTO . '". ¿El archivo es correcto y la contiene?');
        }

        if (count($filas) < 2) {
            errorPresupuesto('La hoja "' . $HOJA_PRESUPUESTO . '" no contiene registros (solo cabecera o está vacía).');
        }

        // 3b. Límite de filas a procesar (sin contar la cabecera).
        $totalDatos = count($filas) - 1;
        if ($totalDatos > PRESUPUESTO_MAX_FILAS) {
            errorPresupuesto('La hoja tiene ' . $totalDatos . ' filas. El máximo permitido es ' . PRESUPUESTO_MAX_FILAS . '.');
        }

        // 4. Validar que la cabecera (fila 1) coincida con el formato esperado.
        $cabecera = $filas[0];
        foreach ($CABECERAS_ESPERADAS as $col => $titulo) {
            $valor = $cabecera[$col] ?? '';
            if (normalizarCabecera($valor) !== normalizarCabecera($titulo)) {
                errorPresupuesto("El archivo no tiene el formato esperado: falta o no coincide la columna \"{$titulo}\".");
            }
        }

        // 5. Extraer los datos (desde la fila 2). Vacío -> NULL; numéricos redondeados.
        $registros   = [];
        $descartados = 0;
        $totalFilas  = count($filas);

        for ($i = 1; $i < $totalFilas; $i++) {
            $fila = $filas[$i];

            // Omite filas sin datos en las columnas C-M.
            $tieneDatos = false;
            foreach ($COLUMNAS_PRESUPUESTO as $col => $campo) {
                if (trim($fila[$col] ?? '') !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if (!$tieneDatos) {
                continue;
            }

            $registro = [];
            foreach ($COLUMNAS_PRESUPUESTO as $col => $campo) {
                $valor = trim($fila[$col] ?? '');

                if ($campo === 'anio' || $campo === 'mes') {
                    $registro[$campo] = is_numeric($valor) ? (int) $valor : null;
                } elseif ($campo === 'mg_porcentaje') {
                    // En el Excel viene como fracción (0.296 = 29.6%); se guarda como punto porcentual.
                    $registro[$campo] = is_numeric($valor) ? round((float) $valor * 100, 2) : null;
                } elseif (isset($DECIMALES[$campo])) {
                    $registro[$campo] = is_numeric($valor) ? round((float) $valor, $DECIMALES[$campo]) : null;
                } else {
                    // Texto: vacío -> NULL; se acota a 100 (varchar de la tabla).
                    $registro[$campo] = ($valor === '') ? null : mb_substr($valor, 0, 100);
                }
            }

            // Regla: la fila solo se guarda si trae todos los campos obligatorios.
            foreach ($OBLIGATORIOS as $campo) {
                if ($registro[$campo] === null) {
                    $descartados++;
                    continue 2;   // pasa a la siguiente fila del Excel
                }
            }

            $registros[] = $registro;
        }

        if (empty($registros)) {
            errorPresupuesto('La hoja no contiene registros para procesar.');
        }

        // 6. Insertar en la base de datos dentro de una transacción (todo o nada).
        try {
            $presupuestoModel = new Presupuesto($pdo);
            $insertados       = $presupuestoModel->insertarMasivo($registros, $_SESSION['usuario_id']);
        } catch (Throwable $e) {
            error_log('[PRESUPUESTO] ' . $e->getMessage());
            errorPresupuesto('Ocurrió un error al guardar los registros. Intente nuevamente.');
        }

        $mensaje = "Se cargaron {$insertados} registro(s) correctamente.";
        if ($descartados > 0) {
            $mensaje .= " Se omitieron {$descartados} fila(s) por campos obligatorios vacíos.";
        }

        echo json_encode([
            'status'      => 'success',
            'total'       => $insertados,
            'descartados' => $descartados,
            'message'     => $mensaje
        ]);
        exit;

    case 'listar':

        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Filtros de la tabla.
        $familia    = trim($_GET['familia'] ?? '');       // '' = todas
        $subFamilia = trim($_GET['sub_familia'] ?? '');   // '' = todas
        $anio       = $_GET['anio'] ?? '';                // '' = todos
        $mes        = $_GET['mes'] ?? '';                 // '' = todos

        // Columna y dirección de ordenamiento (índice -> nombre lógico).
        $columnas     = ['id', 'anio', 'mes', 'canal', 'sub_canal', 'familia', 'sub_familia', 'venta', 'mg_porcentaje', 'mg_neto', 'pp', 'kg'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'id';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'desc';

        try {
            $presupuestoModel = new Presupuesto($pdo);
            $totalRegistros   = $presupuestoModel->contarTodos();
            $totalFiltrados   = $presupuestoModel->contarFiltrados($familia, $subFamilia, $anio, $mes);
            $datos            = $presupuestoModel->listarPagina($familia, $subFamilia, $anio, $mes, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos,
                // Total de la columna Venta sobre TODO el set filtrado (para el pie de la tabla).
                'suma_venta'      => $presupuestoModel->sumaVenta($familia, $subFamilia, $anio, $mes)
            ]);
        } catch (PDOException $e) {
            error_log('[PRESUPUESTO] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los registros.'
            ]);
        }
        exit;

    case 'filtros':

        // Valores para el filtro de Año (los meses son estáticos 1-12 en el front).
        try {
            $presupuestoModel = new Presupuesto($pdo);
            echo json_encode([
                'status'       => 'success',
                'anios'        => $presupuestoModel->aniosDisponibles(),
                'familias'     => $presupuestoModel->familiasDisponibles(),
                'sub_familias' => $presupuestoModel->subFamiliasDisponibles()
            ]);
        } catch (PDOException $e) {
            error_log('[PRESUPUESTO][filtros] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'anios' => []]);
        }
        exit;

    case 'productos':

        // Vista previa (botón de la columna Acciones): productos de la familia de la fila.
        // Origen SAP, leído desde los espejos MySQL (se refrescan si vencieron).
        require_once __DIR__ . '/../config/conexion_sqlserver.php';
        require_once __DIR__ . '/../models/sap_sync_model.php';
        require_once __DIR__ . '/../models/sap_producto_model.php';

        $familia = trim($_GET['familia'] ?? '');
        if ($familia === '') {
            errorPresupuesto('No se indicó la familia.');
        }

        try {
            // Asegura espejos frescos (refresca desde SAP solo si pasó el TTL).
            $sap = new SapSync($pdoSqlsrv, $pdo);
            $sap->asegurar('sap_familias');
            $sap->asegurar('sap_productos_maestros');

            $productos = (new SapProducto($pdo))->porFamiliaNombre($familia);

            echo json_encode([
                'status'  => 'success',
                'familia' => $familia,
                'data'    => $productos
            ]);
        } catch (Throwable $e) {
            error_log('[PRESUPUESTO][productos] ' . $e->getMessage());
            errorPresupuesto('No se pudieron obtener los productos de la familia.');
        }
        exit;

    default:
        http_response_code(400);
        errorPresupuesto('Acción no válida.');
}
