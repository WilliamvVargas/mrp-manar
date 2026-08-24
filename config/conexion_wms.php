<?php
    /*
     * Conexión al WMS vía PDO (driver pdo_sqlsrv). Separada de la conexión MySQL
     * (config/conexion.php) y de la de SAP (config/conexion_sqlserver.php): expone $pdoWms,
     * para poder usar las tres en un mismo request (ej. un reporte que cruce SAP + WMS).
     *
     * Los datos de conexión viven como constantes en config/config_wms.php.
     * Requiere la extensión pdo_sqlsrv + Microsoft ODBC Driver for SQL Server.
     *
     * Nota: con ODBC Driver 18 el cifrado viene activo por defecto; en servidores con
     * certificado autofirmado (ej. instancia local) se usa TrustServerCertificate=1.
     */

    require_once __DIR__ . '/config_wms.php';

    $wmsOpciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $wmsDsn = 'sqlsrv:Server=' . WMS_HOST . ';Database=' . WMS_DB . ';TrustServerCertificate=1';

    try {
        $pdoWms = new PDO($wmsDsn, WMS_USER, WMS_PASS, $wmsOpciones);

        // Año-mes-día para las fechas de esta sesión (igual que la conexión SAP): blinda las
        // consultas ante logins con idioma Español (DATEFORMAT dmy) en el servidor.
        $pdoWms->exec('SET DATEFORMAT ymd');
    } catch (PDOException $e) {
        error_log('[WMS] ' . $e->getMessage());
        die('Error de conexión al WMS.');
    }

    /**
     * Código de empresa del WMS (Cod_Emp) de la empresa activa. El WMS separa empresas por
     * Cod_Emp (Manar=1, Molderil=2, ...), guardado en empresas.empresa_wms. Se pasa a
     * ConsultaWms para acotar las consultas a la empresa activa.
     *
     * @param PDO         $pdoMysql  Conexión MySQL (donde vive la tabla empresas).
     * @param string|null $empresaId Empresa a resolver ('' / null = la activa de la sesión).
     * @return int Cod_Emp (por defecto 1 = Manar si no se puede resolver).
     */
    if (!function_exists('codigoEmpresaWms')) {
        function codigoEmpresaWms(PDO $pdoMysql, $empresaId = null)
        {
            $empresaId = $empresaId ?: ($_SESSION['empresa_id'] ?? null);
            if (empty($empresaId)) {
                return 1;
            }
            try {
                $st = $pdoMysql->prepare("SELECT empresa_wms FROM empresas WHERE id = ?");
                $st->execute([$empresaId]);
                $v = $st->fetchColumn();
                return ($v !== false && $v !== null && $v !== '') ? (int) $v : 1;
            } catch (Throwable $e) {
                error_log('[WMS] codigoEmpresaWms: ' . $e->getMessage());
                return 1;
            }
        }
    }
?>
