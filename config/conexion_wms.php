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
    } catch (PDOException $e) {
        error_log('[WMS] ' . $e->getMessage());
        die('Error de conexión al WMS.');
    }
?>
