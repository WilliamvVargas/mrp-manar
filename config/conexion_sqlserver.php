<?php
    /*
     * Conexión a SQL Server vía PDO (driver pdo_sqlsrv). Separada de la conexión MySQL
     * (config/conexion.php): expone $pdoSqlsrv.
     *
     * Los datos de conexión viven como constantes en config/config_sqlserver.php.
     * Requiere la extensión pdo_sqlsrv + Microsoft ODBC Driver 18 for SQL Server.
     *
     * Nota: con ODBC Driver 18 el cifrado viene activo por defecto; en servidores con
     * certificado autofirmado (ej. instancia local) se usa TrustServerCertificate=1.
     */

    require_once __DIR__ . '/config_sqlserver.php';

    $sqlsrvOpciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $sqlsrvDsn = 'sqlsrv:Server=' . SQLSRV_HOST . ';Database=' . SQLSRV_DB . ';TrustServerCertificate=1';

    try {
        $pdoSqlsrv = new PDO($sqlsrvDsn, SQLSRV_USER, SQLSRV_PASS, $sqlsrvOpciones);
    } catch (PDOException $e) {
        error_log('[SQLSRV] ' . $e->getMessage());
        die('Error de conexión a SQL Server.');
    }
?>
