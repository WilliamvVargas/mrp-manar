<?php
    /*
     * Exportación GENÉRICA a Excel (.xlsx). Reutilizable por cualquier mantenedor.
     *
     * Recibe por POST los datos YA cargados en el cliente (encabezados + filas) y devuelve
     * el archivo .xlsx armado con EscritorXlsx. Como solo da formato a datos que el cliente
     * ya tiene en pantalla, no consulta ninguna base ni expone información nueva: basta con
     * sesión válida + CSRF (los valida includes/auth.php). Respeta lo que el usuario ve
     * (filtros/búsqueda aplicados en el navegador).
     *
     * Parámetros POST:
     *   - csrf_token   : token CSRF (lo valida auth.php).
     *   - nombre       : nombre de la hoja y del archivo (opcional).
     *   - encabezados  : JSON con el arreglo de títulos de columna (opcional).
     *   - filas        : JSON con el arreglo de filas (cada fila = arreglo de valores).
     */
    require_once __DIR__ . '/../includes/auth.php';   // sesión + CSRF en POST
    require_once __DIR__ . '/../assets/librerias/escritor_xlsx/escritor_xlsx.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
        exit;
    }

    $nombre      = trim((string) ($_POST['nombre'] ?? 'consulta'));
    $encabezados = json_decode($_POST['encabezados'] ?? '[]', true);
    $filas       = json_decode($_POST['filas'] ?? '[]', true);

    if (!is_array($encabezados) || !is_array($filas)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Datos de exportación inválidos.']);
        exit;
    }

    if ($nombre === '') {
        $nombre = 'consulta';
    }

    try {
        $xlsx = new EscritorXlsx($nombre);
        if (!empty($encabezados)) {
            $xlsx->encabezados($encabezados);
        }
        $xlsx->filas($filas);
        $xlsx->descargar($nombre);
    } catch (Throwable $e) {
        error_log('[EXPORTAR_EXCEL] ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'No se pudo generar el archivo Excel.']);
    }
    exit;
