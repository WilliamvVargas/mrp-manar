<?php
    require_once __DIR__ . '/../includes/auth.php';                  // sesión + valida CSRF en POST
    require_once __DIR__ . '/../config/conexion.php';                // $pdo
    require_once __DIR__ . '/../config/config.php';                  // constantes EMPRESA_*
    require_once __DIR__ . '/../includes/funciones_validacion.php';  // validarCampoTexto, etc.
    require_once __DIR__ . '/../models/empresa_model.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $empresaModel = new Empresa($pdo);
    $action = $_REQUEST['action'] ?? '';

    // Defensa en profundidad: 403 si el perfil no tiene acceso a esta sección.
    require_once __DIR__ . '/../includes/control_acceso_controlador.php';
    exigirAccesoControlador('empresas', $action);

    /**
     * Valida el logo subido (OPCIONAL). Si es válido, deja la extensión en $ext.
     * Solo acepta imágenes raster PNG/JPG/WEBP (sin SVG, para evitar riesgos).
     *
     * @return string|null Error si falla; null si pasa o si no se subió archivo.
     */
    function validarLogoEmpresa(array $file, &$ext)
    {
        $ext = null;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;   // logo opcional: no se subió nada
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'Hubo un problema al subir el <b>Logo</b>.';
        }
        if ($file['size'] > EMPRESA_LOGO_MAX_PESO_MB * 1024 * 1024) {
            return 'El <b>Logo</b> supera el peso máximo de ' . EMPRESA_LOGO_MAX_PESO_MB . ' MB.';
        }

        $info  = @getimagesize($file['tmp_name']);
        $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
        if ($info === false || !isset($tipos[$info[2]])) {
            return 'El <b>Logo</b> debe ser una imagen PNG, JPG o WEBP.';
        }

        $ext = $tipos[$info[2]];
        return null;
    }

    /**
     * Valida la Empresa WMS (OBLIGATORIA). Debe ser un código numérico entero >= 1
     * (el value del <option>, tomado del maestro dbo.EMPRESA).
     *
     * @return string|null Error si falla; null si pasa.
     */
    function validarEmpresaWms($valor)
    {
        if ($valor === '' || $valor === null) {
            return 'Debes seleccionar la <b>Empresa WMS</b>.';
        }
        if (!ctype_digit((string) $valor) || (int) $valor < 1) {
            return 'La <b>Empresa WMS</b> seleccionada no es válida.';
        }
        return null;
    }

    switch ($action) {

        case 'listar':

            retrasar();

            // Parámetros de DataTables (server-side).
            $draw     = (int) ($_GET['draw'] ?? 0);
            $inicio   = (int) ($_GET['start'] ?? 0);
            $longitud = (int) ($_GET['length'] ?? 10);
            $consulta = trim($_GET['consulta'] ?? '');

            // Columna/dirección de orden (índice del DataTable -> nombre lógico).
            $columnas     = ['posicion', 'logo', 'nombre', 'fecha', 'acciones'];
            $idxOrden     = (int) ($_GET['order'][0]['column'] ?? 0);
            $columnaOrden = $columnas[$idxOrden] ?? 'posicion';
            $dirOrden     = $_GET['order'][0]['dir'] ?? 'asc';

            try {
                echo json_encode([
                    'draw'            => $draw,
                    'recordsTotal'    => $empresaModel->contarTodos(),
                    'recordsFiltered' => $empresaModel->contarFiltrados($consulta),
                    'data'            => $empresaModel->listarPagina($consulta, $columnaOrden, $dirOrden, $inicio, $longitud),
                ]);
            } catch (PDOException $e) {
                error_log('[BD] ' . $e->getMessage());
                echo json_encode([
                    'draw'            => $draw,
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => [],
                    'error'           => 'Ocurrió un error al cargar las empresas.',
                ]);
            }
            exit;

        case 'registrar':

            retrasar();

            $nombre     = trim($_POST['nombre'] ?? '');
            $posicion   = $_POST['posicion'] ?? '';   // vacío = al final (MAX + 1)
            $empresaWms = trim($_POST['empresa_wms'] ?? '');

            $errores = [];

            if ($err = validarCampoTexto($nombre, 'Nombre', ['requerido' => true,
                                                             'min' => EMPRESA_NOMBRE_MIN_LENGTH,
                                                             'max' => EMPRESA_NOMBRE_MAX_LENGTH])) {
                $errores['nombre'] = $err;
            }

            // Nombre único (solo si el nombre pasó las validaciones básicas).
            if (!isset($errores['nombre']) && $empresaModel->existePorNombre($nombre)) {
                $errores['nombre'] = 'Ya existe una empresa con ese <b>Nombre</b>.';
            }

            // Empresa WMS (obligatoria): debe ser un código numérico del maestro.
            if ($err = validarEmpresaWms($empresaWms)) {
                $errores['empresa_wms'] = $err;
            }

            // Logo (opcional).
            $ext      = null;
            $logoFile = $_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
            if ($err = validarLogoEmpresa($logoFile, $ext)) {
                $errores['logo'] = $err;
            }

            enviarErrorCamposFormulario($errores);

            $destino    = null;
            $logoNombre = null;
            try {
                // Guarda el logo (si se subió) en assets/img/empresas/.
                if ($ext !== null) {
                    $logoNombre = 'empresa_' . uniqid('', true) . '.' . $ext;
                    $destino    = __DIR__ . '/../assets/img/empresas/' . $logoNombre;
                    if (!move_uploaded_file($logoFile['tmp_name'], $destino)) {
                        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el logo.']);
                        exit;
                    }
                }

                if ($empresaModel->crear($nombre, $logoNombre, $posicion, $_SESSION['usuario_id'] ?? null, (int) $empresaWms)) {
                    echo json_encode(['status' => 'success', 'message' => 'Empresa creada con éxito']);
                }
            }
            catch (PDOException $e) {
                // Si el insert falló y el logo ya se movió, elimínalo para no dejar basura.
                if ($destino !== null && is_file($destino)) { @unlink($destino); }
                responderErrorServidor($e);
            }
            exit;

        case 'obtener':

            $id = $_GET['id'] ?? '';
            try {
                $empresa = $empresaModel->buscarPorId($id);
                if ($empresa) {
                    echo json_encode(['status' => 'success', 'data' => $empresa]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Empresa no encontrada.']);
                }
            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;

        case 'editar':

            retrasar();

            $id         = $_POST['id_registro'] ?? '';
            $nombre     = trim($_POST['nombre'] ?? '');
            $posicion   = $_POST['posicion'] ?? '';   // vacío = no reordena
            $empresaWms = trim($_POST['empresa_wms'] ?? '');

            $errores = [];

            if ($err = validarCampoTexto($nombre, 'Nombre', ['requerido' => true,
                                                             'min' => EMPRESA_NOMBRE_MIN_LENGTH,
                                                             'max' => EMPRESA_NOMBRE_MAX_LENGTH])) {
                $errores['nombre'] = $err;
            }

            // Nombre único, excluyendo la propia empresa.
            if (!isset($errores['nombre']) && $empresaModel->existePorNombre($nombre, $id)) {
                $errores['nombre'] = 'Ya existe una empresa con ese <b>Nombre</b>.';
            }

            // Empresa WMS (obligatoria).
            if ($err = validarEmpresaWms($empresaWms)) {
                $errores['empresa_wms'] = $err;
            }

            // Logo nuevo (opcional).
            $ext      = null;
            $logoFile = $_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
            if ($err = validarLogoEmpresa($logoFile, $ext)) {
                $errores['logo'] = $err;
            }

            enviarErrorCamposFormulario($errores);

            $destino = null;
            try {
                $actual = $empresaModel->buscarPorId($id);
                if (!$actual) {
                    echo json_encode(['status' => 'error', 'message' => 'La empresa no existe.']);
                    exit;
                }

                $hayLogoNuevo = ($ext !== null);
                $hayPosicion  = (is_numeric($posicion) && (int) $posicion >= 1
                                 && (int) $posicion !== (int) $actual['posicion']);
                $cambioWms    = ((int) $empresaWms !== (int) $actual['empresa_wms']);

                // Sin cambios: mismo nombre, misma Empresa WMS, sin logo nuevo y sin reordenar.
                if ($actual['nombre'] === $nombre && !$cambioWms && !$hayLogoNuevo && !$hayPosicion) {
                    echo json_encode(['status' => 'no_changes', 'message' => 'No se realizaron cambios.']);
                    exit;
                }

                // Guarda el logo nuevo (si lo hay); por defecto conserva el actual.
                $logoFinal = $actual['logo'];
                if ($hayLogoNuevo) {
                    $logoFinal = 'empresa_' . uniqid('', true) . '.' . $ext;
                    $destino   = __DIR__ . '/../assets/img/empresas/' . $logoFinal;
                    if (!move_uploaded_file($logoFile['tmp_name'], $destino)) {
                        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el logo.']);
                        exit;
                    }
                }

                $empresaModel->actualizar($id, $nombre, $logoFinal, $_SESSION['usuario_id'] ?? null, (int) $empresaWms);

                // Reordena si se eligió una posición distinta.
                if ($hayPosicion) {
                    $empresaModel->reposicionar($id, (int) $posicion);
                }

                // Reemplazó el logo: elimina el anterior para no dejar basura.
                if ($hayLogoNuevo && !empty($actual['logo'])) {
                    $viejo = __DIR__ . '/../assets/img/empresas/' . basename($actual['logo']);
                    if (is_file($viejo)) { @unlink($viejo); }
                }

                echo json_encode(['status' => 'success', 'message' => 'Empresa actualizada con éxito.']);
            }
            catch (PDOException $e) {
                if ($destino !== null && is_file($destino)) { @unlink($destino); }
                responderErrorServidor($e);
            }
            exit;

        case 'listar_orden':

            // Listado completo ordenado por posición (lo usa el modal de Asignar Posición).
            try {
                echo json_encode(['status' => 'success', 'data' => $empresaModel->listarOrden()]);
            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;

        case 'reordenar':

            retrasar();

            $orden = $_POST['orden'] ?? [];
            if (!is_array($orden) || count($orden) === 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Orden no válido.']);
                exit;
            }
            try {
                $empresaModel->reordenar($orden);
                echo json_encode(['status' => 'success', 'message' => 'Orden actualizado con éxito.']);
            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;

        case 'validar_campo':

            // Validación instantánea (client-side) de un campo. Hoy solo el 'nombre':
            // requerido, 2-50 y único (excluyendo la propia empresa al editar).
            $campo      = $_POST['campo'] ?? '';
            $valor      = trim($_POST['valor'] ?? '');
            $idRegistro = $_POST['id_registro'] ?? null;

            $errores = [];

            if ($campo === 'nombre') {
                $err = validarCampoTexto($valor, 'Nombre', ['requerido' => true,
                                                            'min' => EMPRESA_NOMBRE_MIN_LENGTH,
                                                            'max' => EMPRESA_NOMBRE_MAX_LENGTH]);
                if (!$err && $empresaModel->existePorNombre($valor, $idRegistro ?: null)) {
                    $err = 'Ya existe una empresa con ese <b>Nombre</b>.';
                }
                $errores['nombre'] = $err;
            }

            if (!empty($errores[$campo])) {
                echo json_encode(['status' => 'error', 'type' => 'fields', 'errors' => $errores]);
                exit;
            }

            echo json_encode(['status' => 'success']);
            exit;

        case 'listar_empresas_wms':

            // Maestro de empresas del WMS (dbo.EMPRESA) para poblar el selector
            // "Empresa WMS" de los modales de crear/editar. Solo lectura.
            try {
                require_once __DIR__ . '/../config/conexion_wms.php';   // $pdoWms
                $sql = 'SELECT Cod_Emp, LTRIM(RTRIM(EmpDsc)) AS EmpDsc
                        FROM EMPRESA
                        ORDER BY Cod_Emp';
                $empresasWms = $pdoWms->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $empresasWms]);
            } catch (PDOException $e) {
                error_log('[WMS] ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'No se pudieron cargar las empresas del WMS.']);
            }
            exit;

        case 'obtener_conexion':

            // Datos de conexión SAP de una empresa, para cargar el modal "Conexión SAP".
            $id = $_GET['id'] ?? '';
            try {
                $conexion = $empresaModel->obtenerConexionSap($id);
                if ($conexion) {
                    echo json_encode(['status' => 'success', 'data' => $conexion]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Empresa no encontrada.']);
                }
            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;

        case 'guardar_conexion':

            retrasar();

            $id       = $_POST['id_empresa'] ?? '';
            $servidor = trim($_POST['servidor'] ?? '');
            $base     = trim($_POST['base_datos'] ?? '');
            $usuario  = trim($_POST['usuario'] ?? '');

            $errores = [];

            if ($err = validarCampoTexto($servidor, 'Servidor / Host', ['requerido' => true, 'max' => 100])) {
                $errores['servidor'] = $err;
            }
            if ($err = validarCampoTexto($base, 'Base de datos', ['requerido' => true, 'max' => 100])) {
                $errores['base_datos'] = $err;
            }
            if ($err = validarCampoTexto($usuario, 'Usuario', ['requerido' => true, 'max' => 100])) {
                $errores['usuario'] = $err;
            }

            enviarErrorCamposFormulario($errores);

            try {
                if (!$empresaModel->obtenerConexionSap($id)) {
                    echo json_encode(['status' => 'error', 'message' => 'La empresa no existe.']);
                    exit;
                }
                $empresaModel->guardarConexionSap($id, $servidor, $base, $usuario, $_SESSION['usuario_id'] ?? null);
                echo json_encode(['status' => 'success', 'message' => 'Conexión SAP guardada con éxito.']);
            } catch (PDOException $e) {
                responderErrorServidor($e);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
?>
