<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/funciones_validacion.php';
require_once __DIR__ . '/../models/iconos_model.php';
require_once __DIR__ . '/../assets/librerias/procesador_svg/procesador_svg.php';

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

/**
 * Convierte un nombre en un slug apto para id de símbolo: minúsculas, sin acentos,
 * solo [a-z0-9-]. Ej: "Engranaje Ñoño" -> "engranaje-nono".
 */
function slugIcono($nombre)
{
    $s = mb_strtolower(trim($nombre), 'UTF-8');
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');

    return $s === '' ? 'icono' : $s;
}

/**
 * Regenera el sprite combinado (.svg) a partir de TODOS los iconos personalizados.
 * El archivo original suelto es la fuente de verdad; el sprite es derivado/regenerable.
 */
function regenerarSpriteIconos(Icono $iconoModel, $carpeta, $rutaSprite)
{
    $simbolos = '';

    foreach ($iconoModel->listarPersonalizados() as $ico) {
        $ruta = $carpeta . '/' . $ico['archivo'];
        if (!is_readable($ruta)) {
            continue;
        }
        try {
            $proc = new ProcesadorSvg(file_get_contents($ruta));
            $simbolos .= $proc->comoSimbolo($ico['valor']) . "\n";
        } catch (Throwable $e) {
            error_log('[ICONOS] sprite: ' . $e->getMessage());
        }
    }

    $sprite = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">' . "\n"
            . $simbolos
            . '</svg>' . "\n";

    file_put_contents($rutaSprite, $sprite);
}

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

    case 'registrar':

        retrasar();

        $tipo   = $_POST['tipo'] ?? '';
        $nombre = trim($_POST['nombre'] ?? '');

        if ($tipo === 'bootstrap') {

            $valor = trim($_POST['valor_bootstrap'] ?? '');

            // Validación de campos (mismas reglas que la validación instantánea).
            $errores = [];
            if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
                $errores['nombre'] = $err;
            }
            if ($err = validarCampoTexto($valor, 'Icono', $REGLAS_VALOR_BOOTSTRAP)) {
                $errores['valor_bootstrap'] = $err;
            }
            enviarErrorCamposFormulario($errores);

            // Se guarda el nombre del icono sin el prefijo 'bi-'.
            $valorNormalizado = preg_replace('/^bi-/', '', $valor);

            try {
                $iconoModel = new Icono($pdo);

                // No se permiten iconos duplicados (la columna `valor` es UNIQUE).
                if ($iconoModel->existePorValor($valorNormalizado)) {
                    enviarErrorCamposFormulario(['valor_bootstrap' => 'Ese icono ya está registrado.']);
                }

                $iconoModel->crear([
                    'nombre'     => $nombre,
                    'tipo'       => 'bootstrap',
                    'valor'      => $valorNormalizado,
                    'archivo'    => null,
                    'coloreable' => 1,   // los iconos de Bootstrap son monocromáticos
                ], $_SESSION['usuario_id']);

                echo json_encode([
                    'status'  => 'success',
                    'message' => 'Icono creado con éxito.'
                ]);

            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;
        }

        // ----- Tipo 'personalizado' (archivo SVG) -----

        // Valida Nombre y Archivo juntos (igual que el caso Bootstrap): ambos errores
        // se acumulan y se devuelven con un mensaje general, marcando cada campo.
        $errores = [];

        if ($err = validarCampoTexto($nombre, 'Nombre', $REGLAS_NOMBRE)) {
            $errores['nombre'] = $err;
        }

        $sub = $_FILES['archivo'] ?? null;
        if (!$sub || $sub['error'] === UPLOAD_ERR_NO_FILE) {
            $errores['archivo'] = 'Debe seleccionar un archivo SVG.';
        } elseif ($sub['error'] !== UPLOAD_ERR_OK) {
            $errores['archivo'] = 'Hubo un error al subir el archivo.';
        } elseif (strtolower(pathinfo($sub['name'], PATHINFO_EXTENSION)) !== 'svg') {
            $errores['archivo'] = 'Solo se permiten archivos .svg.';
        } elseif ($sub['size'] > ICONO_SVG_MAX_PESO_KB * 1024) {
            $errores['archivo'] = 'El SVG supera el tamaño máximo (' . ICONO_SVG_MAX_PESO_KB . ' KB).';
        }

        enviarErrorCamposFormulario($errores);

        // Procesa el SVG: saneo + detección de color (+ currentColor si es monocromático).
        try {
            $proc = new ProcesadorSvg(file_get_contents($sub['tmp_name']));
            $proc->sanear();
            $coloreable = $proc->esMonocromatico() ? 1 : 0;
            if ($coloreable) {
                $proc->aplicarCurrentColor();
            }
            $svgFinal = $proc->svgComoString();
        } catch (Throwable $e) {
            error_log('[ICONOS] ' . $e->getMessage());
            enviarErrorCamposFormulario(['archivo' => 'El archivo no es un SVG válido.']);
        }

        try {
            $iconoModel = new Icono($pdo);

            // El valor (id de símbolo) deriva del Nombre. No se permiten duplicados
            // (mismo criterio que los iconos Bootstrap): si ya existe, se rechaza.
            $valor = 'custom-' . slugIcono($nombre);

            if ($iconoModel->existePorValor($valor)) {
                enviarErrorCamposFormulario(['nombre' => 'Ya existe un icono con ese nombre.']);
            }

            $archivo = $valor . '.svg';

            $carpeta = __DIR__ . '/../assets/icons/personalizados';
            if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
                throw new RuntimeException('No se pudo crear la carpeta de iconos.');
            }

            if (file_put_contents($carpeta . '/' . $archivo, $svgFinal) === false) {
                throw new RuntimeException('No se pudo guardar el archivo SVG.');
            }

            try {
                $iconoModel->crear([
                    'nombre'     => $nombre,
                    'tipo'       => 'personalizado',
                    'valor'      => $valor,
                    'archivo'    => $archivo,
                    'coloreable' => $coloreable,
                ], $_SESSION['usuario_id']);
            } catch (PDOException $e) {
                @unlink($carpeta . '/' . $archivo);   // no dejar el archivo huérfano
                throw $e;
            }

            // Regenera el sprite combinado con todos los personalizados.
            regenerarSpriteIconos($iconoModel, $carpeta, __DIR__ . '/../assets/icons/sprite.svg');

        } catch (PDOException $e) {
            responderErrorServidor($e);
        } catch (Throwable $e) {
            error_log('[ICONOS] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el icono. Intente nuevamente.']);
            exit;
        }

        $mensaje = 'Icono creado con éxito.';
        if (!$coloreable) {
            $mensaje .= ' Se detectó multicolor: conserva sus colores originales.';
        }

        echo json_encode(['status' => 'success', 'message' => $mensaje]);
        exit;

    case 'listar':

        $draw     = (int) ($_GET['draw'] ?? 0);
        $inicio   = (int) ($_GET['start'] ?? 0);
        $longitud = (int) ($_GET['length'] ?? 10);

        // Buscador (#consulta): por nombre o valor. Filtro de tipo (#filtro-tipo).
        $consulta = trim($_GET['consulta'] ?? '');
        $tipo     = $_GET['tipo'] ?? '';

        // Índice de columna -> nombre lógico (solo las ordenables: posición, nombre, valor, tipo).
        $columnas     = [0 => 'posicion', 1 => 'nombre', 2 => 'valor', 3 => 'tipo'];
        $idxOrden     = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 0;
        $columnaOrden = $columnas[$idxOrden] ?? 'posicion';
        $dirOrden     = $_GET['order'][0]['dir'] ?? 'asc';

        try {
            $iconoModel     = new Icono($pdo);
            $totalRegistros = $iconoModel->contarTodos();
            $totalFiltrados = $iconoModel->contarFiltrados($consulta, $tipo);
            $datos          = $iconoModel->listarPagina($consulta, $tipo, $columnaOrden, $dirOrden, $inicio, $longitud);

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRegistros,
                'recordsFiltered' => $totalFiltrados,
                'data'            => $datos
            ]);
        } catch (PDOException $e) {
            error_log('[ICONOS] ' . $e->getMessage());
            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Ocurrió un error al cargar los iconos.'
            ]);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
}
