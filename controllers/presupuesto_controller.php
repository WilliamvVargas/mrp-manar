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

// Definición de columnas del presupuesto. La posición en el Excel YA NO es fija: cada
// columna se ubica por su TÍTULO en la fila 1 (así se aceptan archivos con las columnas
// corridas o en distinto orden). 'requerida' => la columna debe existir en el archivo
// (coincide con los campos obligatorios por fila); el resto puede faltar y queda en NULL.
$COLUMNAS_PRESUPUESTO = [
    // campo        => ['titulo' => 'Título en el Excel', 'requerida' => bool]
    'anio'          => ['titulo' => 'Año',         'requerida' => true],
    'mes'           => ['titulo' => 'Mes',         'requerida' => true],
    'canal'         => ['titulo' => 'Canal',       'requerida' => true],
    'sub_canal'     => ['titulo' => 'Sub-Canal',   'requerida' => true],
    'familia'       => ['titulo' => 'Familia',     'requerida' => true],
    'sub_familia'   => ['titulo' => 'Sub-Familia', 'requerida' => false],
    'venta'         => ['titulo' => 'Venta',       'requerida' => true],
    'mg_porcentaje' => ['titulo' => 'MG %',        'requerida' => false],
    'mg_neto'       => ['titulo' => 'MG Neto',     'requerida' => false],
    'pp'            => ['titulo' => 'PP',          'requerida' => false],
    'kg'            => ['titulo' => 'KG',          'requerida' => false],
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

        // Debe haber una empresa activa: el presupuesto se guarda asociado a ella.
        if (empty($_SESSION['empresa_id'])) {
            errorPresupuesto('No hay una empresa activa.');
        }

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
            errorPresupuesto('No se pudo leer la hoja "' . $HOJA_PRESUPUESTO . '".');
        }

        if (count($filas) < 2) {
            errorPresupuesto('La hoja "' . $HOJA_PRESUPUESTO . '" no contiene registros.');
        }

        // 3b. Límite de filas a procesar (sin contar la cabecera).
        $totalDatos = count($filas) - 1;
        if ($totalDatos > PRESUPUESTO_MAX_FILAS) {
            errorPresupuesto('La hoja tiene ' . $totalDatos . ' filas. El máximo permitido es ' . PRESUPUESTO_MAX_FILAS . '.');
        }

        // 4. Ubicar cada columna por su TÍTULO en la fila 1 (no por posición fija). Se arma
        //    un mapa "título normalizado -> letra de columna" y con él se resuelve en qué
        //    columna vive cada campo. Las columnas requeridas deben existir en el archivo.
        $cabecera    = $filas[0];
        $mapaTitulos = [];
        foreach ($cabecera as $col => $titulo) {
            $norm = normalizarCabecera($titulo);
            if ($norm !== '' && !isset($mapaTitulos[$norm])) {
                $mapaTitulos[$norm] = $col;   // ante títulos repetidos, gana la primera columna
            }
        }

        $columnaDeCampo = [];   // campo -> letra de columna (solo los campos presentes en el archivo)
        $faltantes      = [];   // títulos de columnas REQUERIDAS que no se encontraron
        foreach ($COLUMNAS_PRESUPUESTO as $campo => $meta) {
            $norm = normalizarCabecera($meta['titulo']);
            if (isset($mapaTitulos[$norm])) {
                $columnaDeCampo[$campo] = $mapaTitulos[$norm];
            } elseif ($meta['requerida']) {
                $faltantes[] = $meta['titulo'];
            }
        }

        if (!empty($faltantes)) {
            errorPresupuesto('El archivo no tiene el formato esperado: falta(n) la(s) columna(s) "'
                . implode('", "', $faltantes) . '".');
        }

        // 5. Extraer los datos (desde la fila 2). Vacío -> NULL; numéricos redondeados.
        //    Las columnas opcionales ausentes en el archivo quedan en NULL para toda fila.
        $registros   = [];
        $descartados = 0;
        $totalFilas  = count($filas);

        for ($i = 1; $i < $totalFilas; $i++) {
            $fila = $filas[$i];

            // Omite filas sin datos en ninguna de las columnas mapeadas.
            $tieneDatos = false;
            foreach ($columnaDeCampo as $campo => $col) {
                if (trim($fila[$col] ?? '') !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if (!$tieneDatos) {
                continue;
            }

            $registro = [];
            foreach ($COLUMNAS_PRESUPUESTO as $campo => $meta) {
                // Si la columna no existe en el archivo, el campo queda NULL.
                $valor = isset($columnaDeCampo[$campo]) ? trim($fila[$columnaDeCampo[$campo]] ?? '') : '';

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
            $presupuestoModel = new Presupuesto($pdo, $_SESSION['empresa_id'] ?? null);
            $resultado        = $presupuestoModel->insertarMasivo($registros, $_SESSION['usuario_id']);
            $insertados       = $resultado['insertados'];
            $version          = $resultado['version'];
        } catch (Throwable $e) {
            error_log('[PRESUPUESTO] ' . $e->getMessage());
            errorPresupuesto('Ocurrió un error al guardar los registros. Intente nuevamente.');
        }

        $mensaje = "Se cargaron {$insertados} registro(s) correctamente en la versión {$version}.";
        if ($descartados > 0) {
            $mensaje .= " Se omitieron {$descartados} fila(s) por campos obligatorios vacíos.";
        }

        echo json_encode([
            'status'      => 'success',
            'total'       => $insertados,
            'version'     => $version,
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
        $version    = trim($_GET['version'] ?? '');       // '' = todas

        // Columna y dirección de ordenamiento (índice -> nombre lógico).
        $columnas     = ['id', 'version', 'anio', 'mes', 'canal', 'sub_canal', 'familia', 'sub_familia', 'venta', 'mg_porcentaje', 'mg_neto', 'pp', 'kg'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'id';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'desc';

        try {
            $presupuestoModel = new Presupuesto($pdo, $_SESSION['empresa_id'] ?? null);
            $totalRegistros   = $presupuestoModel->contarTodos();
            $totalFiltrados   = $presupuestoModel->contarFiltrados($familia, $subFamilia, $anio, $mes, $version);
            $datos            = $presupuestoModel->listarPagina($familia, $subFamilia, $anio, $mes, $version, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
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
            $presupuestoModel = new Presupuesto($pdo, $_SESSION['empresa_id'] ?? null);
            echo json_encode([
                'status'       => 'success',
                'versiones'    => $presupuestoModel->versionesDisponibles(),
                'anios'        => $presupuestoModel->aniosDisponibles(),
                'familias'     => $presupuestoModel->familiasDisponibles(),
                'sub_familias' => $presupuestoModel->subFamiliasDisponibles()
            ]);
        } catch (PDOException $e) {
            error_log('[PRESUPUESTO][filtros] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'anios' => []]);
        }
        exit;

    case 'exportar':

        // Exporta a Excel TODAS las filas que cumplen los filtros actuales (no solo la
        // página visible, por ser DataTable server-side). Respeta versión, familia, etc.
        require_once __DIR__ . '/../assets/librerias/escritor_xlsx/escritor_xlsx.php';

        $familia    = trim($_GET['familia'] ?? '');
        $subFamilia = trim($_GET['sub_familia'] ?? '');
        $anio       = $_GET['anio'] ?? '';
        $mes        = $_GET['mes'] ?? '';
        $version    = trim($_GET['version'] ?? '');

        try {
            $presupuestoModel = new Presupuesto($pdo, $_SESSION['empresa_id'] ?? null);
            // longitud -1 = sin LIMIT (todas las filas filtradas), ordenadas por id.
            $datos = $presupuestoModel->listarPagina($familia, $subFamilia, $anio, $mes, $version, 'id', 'asc', 0, -1);

            $encabezados = ['ID', 'Versión', 'Año', 'Mes', 'Canal', 'Sub-Canal', 'Familia',
                            'Sub-Familia', 'Venta', 'MG %', 'MG Neto', 'PP', 'KG'];

            $xlsx = new EscritorXlsx('Presupuesto');
            $xlsx->encabezados($encabezados);
            foreach ($datos as $r) {
                $xlsx->fila([
                    $r['id'],
                    $r['version'],
                    $r['anio'],
                    $r['mes'],
                    $r['canal'],
                    $r['sub_canal'],
                    $r['familia'],
                    $r['sub_familia'],
                    $r['venta'],
                    $r['mg_porcentaje'],
                    $r['mg_neto'],
                    $r['pp'],
                    $r['kg'],
                ]);
            }

            $xlsx->descargar('Presupuesto');
        } catch (Throwable $e) {
            error_log('[PRESUPUESTO][exportar] ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'No se pudo generar el archivo Excel.']);
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
