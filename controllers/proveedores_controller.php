<?php
    /*
     * Controlador del mantenedor de Proveedores (solo consulta).
     *
     * Lista los socios de negocio proveedores (OCRD, CardType='S') de la empresa activa:
     * código, nombre, país y dirección. El modo de transporte todavía no se registra en
     * ningún lado, así que se muestra en blanco por ahora.
     *
     * La lista SAP es acotada (cientos de proveedores), así que el paginado/orden/búsqueda de
     * DataTables (server-side) se resuelve en memoria tras traer todo de SAP una vez.
     */
    require_once __DIR__ . '/../includes/auth.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $action = $_REQUEST['action'] ?? '';

    require_once __DIR__ . '/../includes/control_acceso_controlador.php';
    exigirAccesoControlador('proveedores', $action);

    switch ($action) {

        case 'listar':

            // DataTables (server-side): proveedores de SAP (OCRD), filtrado/ordenado en memoria.
            $draw     = (int) ($_GET['draw'] ?? 0);
            $inicio   = max(0, (int) ($_GET['start'] ?? 0));
            $longitud = (int) ($_GET['length'] ?? 10);
            $consulta = trim($_GET['consulta'] ?? '');
            $filtroPais = trim($_GET['pais'] ?? '');   // código ISO2 del país

            // Columna de orden (índice DataTables -> clave lógica).
            $columnas = [0 => 'codigo', 1 => 'nombre', 2 => 'pais', 3 => 'direccion'];
            $idxOrden = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 1;
            $colOrden = $columnas[$idxOrden] ?? 'nombre';
            $dirOrden = (strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc') ? -1 : 1;

            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv (SAP empresa activa)
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap

                // Dedupe por CÓDIGO NORMALIZADO (sin guiones): en SAP el mismo proveedor a veces
                // tiene dos CardCode que solo difieren en un guion (ej. 62379037P / 62379037-P).
                // Cuando ambas variantes están activas, aparecería dos veces; se consolida en una.
                $filas = [];
                $vistosNorm = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->proveedoresOcrd() as $b) {
                    $cod  = trim($b['codigo']);
                    $norm = str_replace('-', '', $cod);
                    if (isset($vistosNorm[$norm])) { continue; }
                    $vistosNorm[$norm] = true;

                    $filas[] = [
                        'codigo'          => $cod,
                        'nombre'          => $b['nombre'],
                        'pais_codigo'     => $b['pais_codigo'],
                        'pais'            => $b['pais'],
                        'direccion'       => $b['direccion'],
                        'modo_transporte' => '',   // aún no se registra; en blanco por ahora
                    ];
                }

                $totalRegistros = count($filas);

                // Filtro por texto (código o nombre).
                if ($consulta !== '') {
                    $q = mb_strtolower($consulta);
                    $filas = array_values(array_filter($filas, function ($f) use ($q) {
                        return mb_strpos(mb_strtolower($f['codigo'] . ' ' . $f['nombre']), $q) !== false;
                    }));
                }

                // Filtro por país (código ISO2).
                if ($filtroPais !== '') {
                    $filas = array_values(array_filter($filas, function ($f) use ($filtroPais) {
                        return $f['pais_codigo'] === $filtroPais;
                    }));
                }

                $totalFiltrados = count($filas);

                // Orden en memoria por la columna elegida.
                usort($filas, function ($a, $b) use ($colOrden, $dirOrden) {
                    return $dirOrden * strcasecmp((string) $a[$colOrden], (string) $b[$colOrden]);
                });

                // Paginado (length = -1 => todo).
                $pagina = ($longitud < 0) ? $filas : array_slice($filas, $inicio, $longitud);

                echo json_encode([
                    'draw'            => $draw,
                    'recordsTotal'    => $totalRegistros,
                    'recordsFiltered' => $totalFiltrados,
                    'data'            => $pagina,
                ]);
            } catch (Throwable $e) {
                error_log('[PROVEEDORES][listar] ' . $e->getMessage());
                echo json_encode([
                    'draw'            => $draw,
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => [],
                    'error'           => 'Ocurrió un error al cargar los proveedores.',
                ]);
            }
            exit;

        case 'paises':

            // Países presentes en la lista de proveedores (para las opciones del filtro).
            try {
                require_once __DIR__ . '/../config/conexion_sqlserver.php';   // $pdoSqlsrv
                require_once __DIR__ . '/../models/consultas_sap_model.php';  // ConsultaSap

                $vistos = [];
                foreach ((new ConsultaSap($pdoSqlsrv))->proveedoresOcrd() as $b) {
                    $cod = trim($b['pais_codigo']);
                    if ($cod === '' || isset($vistos[$cod])) { continue; }
                    $vistos[$cod] = ['codigo' => $cod, 'nombre' => $b['pais']];
                }
                $paises = array_values($vistos);
                usort($paises, function ($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });

                echo json_encode(['status' => 'success', 'data' => $paises]);
            } catch (Throwable $e) {
                error_log('[PROVEEDORES][paises] ' . $e->getMessage());
                echo json_encode(['status' => 'success', 'data' => []]);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
?>
