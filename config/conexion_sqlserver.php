<?php
    /*
     * Conexión a SQL Server (SAP) vía PDO. Expone $pdoSqlsrv.
     *
     * Modelo multi-empresa: la conexión se arma DINÁMICAMENTE con la fábrica conectarSap()
     * (config/conexion_sqlserver_factory.php), que toma servidor/base/usuario de la empresa
     * (tabla `empresas`) y la contraseña del mapa SQLSRV_PASS_POR_BASE (config_sqlserver.php).
     *
     * Por compatibilidad, este archivo sigue exponiendo el global $pdoSqlsrv, armado para la
     * empresa activa ($_SESSION['empresa_id']) o, en su defecto, la empresa por defecto.
     * Para conectar a una empresa concreta, usa conectarSap($pdo, $idEmpresa) directamente.
     */

    require_once __DIR__ . '/conexion.php';                    // $pdo (MySQL) — lo necesita la fábrica
    require_once __DIR__ . '/conexion_sqlserver_factory.php';  // conectarSap()

    try {
        $pdoSqlsrv = conectarSap($pdo);
    } catch (Throwable $e) {
        // El detalle (empresa sin configurar, falta password, credenciales, red...) queda en el log.
        error_log('[SQLSRV] ' . $e->getMessage());
        die('Error de conexión a SQL Server.');
    }
?>
