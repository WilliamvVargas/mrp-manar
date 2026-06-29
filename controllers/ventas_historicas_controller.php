<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../assets/librerias/lector_xlsx/lector_xlsx.php';
require_once __DIR__ . '/../models/ventas_historicas_model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Mapeo letra de columna del Excel -> [campo, tipo].
// Tipos: int | fecha (serial Excel) | mes (texto->1-12) | dec:N (decimal escala N) | txt:N (texto máx N).
$COLUMNAS = [
    'A'  => ['nro_docto',            'int'],
    'B'  => ['tipo_docto',           'int'],
    'C'  => ['nota_venta',           'txt:30'],
    'D'  => ['cond_venta',           'txt:60'],
    'E'  => ['fecha_docto',          'fecha'],
    'F'  => ['rut_cliente',          'txt:12'],
    'G'  => ['razon_social',         'txt:150'],
    'H'  => ['tipo_cliente',         'txt:40'],
    'I'  => ['lista_precio',         'txt:40'],
    'J'  => ['cod_ejec',             'int'],
    'K'  => ['ejec_comercial',       'txt:80'],
    'L'  => ['f_creacion',           'fecha'],
    'M'  => ['f_primera_venta',      'fecha'],
    'N'  => ['f_ultima_venta',       'fecha'],
    'O'  => ['cod_articulo',         'txt:20'],
    'P'  => ['descripcion_articulo', 'txt:150'],
    'Q'  => ['grupo_articulo',       'txt:60'],
    'R'  => ['familia',              'txt:60'],
    'S'  => ['sub_familia',          'txt:60'],
    'T'  => ['proveedor',            'txt:80'],
    'U'  => ['cant_pedido',          'dec:3'],
    'V'  => ['pr_pedido',            'dec:2'],
    'W'  => ['subtotal_pedido',      'dec:2'],
    'X'  => ['cant_docto',           'dec:3'],
    'Y'  => ['pr_base',              'dec:2'],
    'Z'  => ['pct_descuento',        'dec:2'],
    'AA' => ['subtotal_docto',       'dec:2'],
    'AB' => ['pr_prom_pond',         'dec:2'],
    'AC' => ['costo_pr_pp',          'dec:2'],
    'AD' => ['ganancia_bruta',       'dec:2'],
    'AE' => ['pct_margen',           'dec:2'],
    'AF' => ['gramaje',              'dec:3'],
    'AG' => ['unid_x_caja',          'int'],
    'AH' => ['sociedad',             'txt:40'],
    'AI' => ['venta_bruta',          'dec:2'],
    'AJ' => ['descuento',            'dec:2'],
    'AK' => ['anio',                 'int'],
    'AL' => ['mes',                  'mes'],
    'AM' => ['pr_vta_prom',          'dec:2'],
];

// Cabeceras esperadas (fila 1) para validar el formato del archivo. Se comparan
// normalizadas (sin distinguir mayúsculas ni espacios extra; tolera trailing spaces).
$CABECERAS = [
    'A'  => 'Nro.Docto.',         'B'  => 'Tipo Docto.',      'C'  => 'Nota Venta',
    'D'  => 'Cond.Venta',         'E'  => 'Fecha Docto.',     'F'  => 'R.U.T. Cliente',
    'G'  => 'Razón Social Cliente','H' => 'Tipo Cliente',     'I'  => 'Lista de Precio',
    'J'  => 'Cód.Ejec.',          'K'  => 'Ejec.Comercial',   'L'  => 'F.Creación',
    'M'  => 'F.1ra.Venta',        'N'  => 'F.Ult.Venta',      'O'  => 'Cód.Artículo',
    'P'  => 'Descripción Artículo','Q' => 'Grupo Artículo',   'R'  => 'Familia',
    'S'  => 'Sub-Familia',        'T'  => 'Proveedor',        'U'  => 'Cant.Ped.',
    'V'  => 'Pr.Pedido',          'W'  => 'Sub-Total Pedido', 'X'  => 'Cant.Docto.',
    'Y'  => 'Pr.Base',            'Z'  => '% Descuento',      'AA' => 'Sub-Total Docto.',
    'AB' => 'Pr.Prom.Pond.',      'AC' => 'Costo a Pr.P.P.',  'AD' => 'Ganan.Bruta',
    'AE' => '%Margen',            'AF' => 'Gramaje',          'AG' => 'Unid. x Caja',
    'AH' => 'Sociedad',           'AI' => 'Venta Bruta',      'AJ' => 'Descuento',
    'AK' => 'Año',                'AL' => 'Mes',              'AM' => 'Pr Vta prom',
];

// Meses en español -> número.
$MESES = [
    'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
    'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
    'noviembre' => 11, 'diciembre' => 12,
];

/**
 * Responde un error en JSON y termina.
 */
function errorVentas($mensaje)
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

/**
 * Convierte un número serial de Excel a fecha 'Y-m-d'. La época de Excel es el
 * 1899-12-30 (por el bug del año 1900). Devuelve null si el valor no es válido.
 */
function serialAFecha($valor)
{
    if ($valor === '' || !is_numeric($valor)) {
        return null;
    }
    $dias = (int) floor((float) $valor);
    if ($dias <= 0) {
        return null;
    }
    $fecha = new DateTime('1899-12-30');
    $fecha->modify('+' . $dias . ' days');
    return $fecha->format('Y-m-d');
}

$action = $_REQUEST['action'] ?? '';

// Defensa en profundidad: corta con 403 si el perfil del usuario no tiene acceso a esta sección.
require_once __DIR__ . '/../includes/control_acceso_controlador.php';
exigirAccesoControlador('ventas-historicas', $action);

switch ($action) {

    case 'procesar':

        retrasar();

        // La carga es grande (decenas de miles de filas): más memoria y tiempo.
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // 1. Validar que llegó un archivo y que la subida no tuvo errores.
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            errorVentas('No se recibió el archivo o hubo un error en la subida.');
        }

        $archivo = $_FILES['archivo'];

        // 2. Extensión y peso (protege la memoria ANTES de leer).
        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            errorVentas('El archivo debe ser un Excel .xlsx.');
        }
        if ($archivo['size'] > VENTAS_MAX_PESO_MB * 1024 * 1024) {
            errorVentas('El archivo supera el tamaño máximo permitido (' . VENTAS_MAX_PESO_MB . ' MB).');
        }

        // 3. Leer la primera hoja con el lector nativo.
        try {
            $lector = new LectorXlsx($archivo['tmp_name']);
            $filas  = $lector->leerFilas(1);
        } catch (Throwable $e) {
            error_log('[VENTAS] ' . $e->getMessage());
            errorVentas('No se pudo leer el archivo. ¿El formato es correcto?');
        }

        if (count($filas) < 2) {
            errorVentas('El archivo no contiene registros (solo cabecera o está vacío).');
        }

        // 3b. Límite de filas a procesar (sin contar la cabecera).
        $totalDatos = count($filas) - 1;
        if ($totalDatos > VENTAS_MAX_FILAS) {
            errorVentas('El archivo tiene ' . $totalDatos . ' filas. El máximo permitido es ' . VENTAS_MAX_FILAS . '.');
        }

        // 4. Validar la cabecera (fila 1) contra el formato esperado.
        $cabecera = $filas[0];
        foreach ($CABECERAS as $col => $titulo) {
            if (normalizarCabecera($cabecera[$col] ?? '') !== normalizarCabecera($titulo)) {
                errorVentas("El archivo no tiene el formato esperado: falta o no coincide la columna \"{$titulo}\".");
            }
        }

        // 5. Extraer y convertir los datos (desde la fila 2).
        $registros   = [];
        $descartados = 0;
        $totalFilas  = count($filas);

        for ($i = 1; $i < $totalFilas; $i++) {
            $fila = $filas[$i];

            // Omitir filas totalmente vacías (p. ej. una fila de totales al final).
            $tieneDatos = false;
            foreach ($COLUMNAS as $col => $def) {
                if (trim($fila[$col] ?? '') !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if (!$tieneDatos) {
                $descartados++;
                continue;
            }

            $registro = [];
            foreach ($COLUMNAS as $col => $def) {
                list($campo, $tipo) = $def;
                $valor = trim($fila[$col] ?? '');

                if ($valor === '') {
                    $registro[$campo] = null;
                } elseif ($tipo === 'int') {
                    $registro[$campo] = is_numeric($valor) ? (int) $valor : null;
                } elseif ($tipo === 'fecha') {
                    $registro[$campo] = serialAFecha($valor);
                } elseif ($tipo === 'mes') {
                    $clave = mb_strtolower($valor);
                    $registro[$campo] = $MESES[$clave] ?? (is_numeric($valor) ? (int) $valor : null);
                } elseif (strpos($tipo, 'dec:') === 0) {
                    $escala = (int) substr($tipo, 4);
                    $registro[$campo] = is_numeric($valor) ? round((float) $valor, $escala) : null;
                } else { // txt:N
                    $max = (int) substr($tipo, 4);
                    $registro[$campo] = mb_substr($valor, 0, $max);
                }
            }

            $registros[] = $registro;
        }

        if (empty($registros)) {
            errorVentas('El archivo no contiene registros para procesar.');
        }

        // 6. Generar la versión (YYYYV000, año actual) e insertar en una transacción.
        try {
            $model      = new VentaHistorica($pdo);
            $version    = $model->siguienteVersion((int) date('Y'));
            $insertados = $model->insertarMasivo($registros, $version, $_SESSION['usuario_id']);
        } catch (Throwable $e) {
            error_log('[VENTAS] ' . $e->getMessage());
            errorVentas('Ocurrió un error al guardar los registros. Intente nuevamente.');
        }

        $mensaje = "Se cargaron {$insertados} registro(s) con la versión {$version}.";
        if ($descartados > 0) {
            $mensaje .= " Se omitieron {$descartados} fila(s) vacías.";
        }

        echo json_encode([
            'status'  => 'success',
            'total'   => $insertados,
            'version' => $version,
            'message' => $mensaje
        ]);
        exit;

    case 'listar':

        // Listado paginado para la tabla principal (DataTables server-side).
        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        $consulta  = trim($_GET['consulta'] ?? '');
        $version   = $_GET['version'] ?? '';      // '' = todas
        $grupo     = $_GET['grupo'] ?? '';        // '' = todos
        $familia   = $_GET['familia'] ?? '';      // '' = todas
        $fechaAnio = $_GET['fecha_anio'] ?? '';   // año de fecha_docto ('' = todos)
        $fechaMes  = $_GET['fecha_mes'] ?? '';    // mes de fecha_docto ('' = todos)

        // Columna y dirección de ordenamiento (índice -> nombre lógico).
        $columnas     = [0 => 'id', 1 => 'version', 2 => 'fecha_docto', 3 => 'nro_docto',
                         4 => 'tipo_docto', 5 => 'rut_cliente', 6 => 'razon_social', 7 => 'cod_articulo',
                         8 => 'descripcion_articulo', 9 => 'grupo_articulo', 10 => 'familia',
                         11 => 'cant_docto', 12 => 'venta_bruta'];   // 13 = Acciones (no ordenable)
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : null;
        $columnaOrden = ($idxOrden !== null && isset($columnas[$idxOrden])) ? $columnas[$idxOrden] : 'id';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'desc';

        try {
            $model          = new VentaHistorica($pdo);
            $totalRegistros = $model->contarTodos();
            $totalFiltrados = $model->contarFiltrados($consulta, $version, $grupo, $familia, $fechaAnio, $fechaMes);
            $datos          = $model->listarPagina($consulta, $version, $grupo, $familia, $fechaAnio, $fechaMes, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[VENTAS] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los registros.'
            ]);
        }
        exit;

    case 'obtener':

        // Registro completo para el modal de "Ver detalle".
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            errorVentas('Identificador no válido.');
        }
        try {
            $model    = new VentaHistorica($pdo);
            $registro = $model->buscarPorId($id);

            if (!$registro) {
                echo json_encode(['status' => 'error', 'message' => 'El registro no existe.']);
                exit;
            }
            echo json_encode(['status' => 'success', 'data' => $registro]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    case 'filtros':

        // Valores disponibles para los selects de Versión y Año.
        try {
            $model = new VentaHistorica($pdo);
            echo json_encode([
                'status'    => 'success',
                'versiones' => $model->versionesDisponibles(),
                'anios'     => $model->aniosDisponibles(),
                'grupos'    => $model->gruposDisponibles(),
                'familias'  => $model->familiasDisponibles()
            ]);
        } catch (PDOException $e) {
            responderErrorServidor($e);
        }
        exit;

    default:
        http_response_code(400);
        errorVentas('Acción no válida.');
}
