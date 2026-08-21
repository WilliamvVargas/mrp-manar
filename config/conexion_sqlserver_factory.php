<?php
    /*
     * Fábrica de conexión SAP (SQL Server) POR EMPRESA.
     *
     * Arma un PDO a la base SAP de una empresa combinando:
     *   - los datos guardados en la tabla `empresas` (sap_servidor / sap_base / sap_usuario),
     *   - la contraseña del mapa SQLSRV_PASS_POR_BASE (config/config_sqlserver.php).
     *
     * La contraseña NUNCA vive en la base de datos.
     *
     * Uso:
     *   require_once __DIR__ . '/conexion.php';                  // $pdo (MySQL)
     *   require_once __DIR__ . '/conexion_sqlserver_factory.php';
     *   $pdoSqlsrv = conectarSap($pdo);                          // empresa activa / por defecto
     *   $pdoSqlsrv = conectarSap($pdo, $idEmpresa);              // una empresa concreta
     */

    require_once __DIR__ . '/config_sqlserver.php';   // SQLSRV_PASS_POR_BASE

    /**
     * Resuelve el id de la empresa a usar para la conexión SAP.
     * Prioridad: parámetro explícito > $_SESSION['empresa_id'] > empresa por defecto.
     *
     * La empresa por defecto es, provisionalmente, la de menor posición (hasta que exista
     * el selector de empresa activa).
     *
     * @return string|null Id de empresa, o null si no hay ninguna.
     */
    function resolverEmpresaSap(PDO $pdo, $empresaId = null)
    {
        if (!empty($empresaId)) {
            return $empresaId;
        }
        if (!empty($_SESSION['empresa_id'])) {
            return $_SESSION['empresa_id'];
        }
        $id = $pdo->query("SELECT id FROM empresas ORDER BY posicion ASC LIMIT 1")->fetchColumn();
        return $id !== false ? $id : null;
    }

    /**
     * Obtiene los parámetros de conexión SAP de una empresa (sin conectar todavía).
     * Valida que la empresa exista, tenga la conexión configurada y que su base tenga
     * contraseña en la configuración.
     *
     * @return array ['servidor', 'base', 'usuario', 'password', 'empresa']
     * @throws RuntimeException con un mensaje claro si algo falta.
     */
    function obtenerParametrosSap(PDO $pdo, $empresaId = null)
    {
        $empresaId = resolverEmpresaSap($pdo, $empresaId);
        if (empty($empresaId)) {
            throw new RuntimeException('No hay una empresa disponible para la conexión SAP.');
        }

        $stmt = $pdo->prepare(
            "SELECT nombre, sap_servidor, sap_base, sap_usuario FROM empresas WHERE id = ?"
        );
        $stmt->execute([$empresaId]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$empresa) {
            throw new RuntimeException('La empresa indicada para la conexión SAP no existe.');
        }

        $servidor = trim((string) $empresa['sap_servidor']);
        $base     = trim((string) $empresa['sap_base']);
        $usuario  = trim((string) $empresa['sap_usuario']);

        if ($servidor === '' || $base === '' || $usuario === '') {
            throw new RuntimeException(
                'La empresa "' . $empresa['nombre'] . '" no tiene la conexión SAP configurada.'
            );
        }

        $mapa = defined('SQLSRV_PASS_POR_BASE') ? SQLSRV_PASS_POR_BASE : [];
        if (!array_key_exists($base, $mapa)) {
            throw new RuntimeException(
                'Falta la contraseña SAP de la base "' . $base . '" en la configuración del servidor.'
            );
        }

        return [
            'servidor' => $servidor,
            'base'     => $base,
            'usuario'  => $usuario,
            'password' => $mapa[$base],
            'empresa'  => $empresa['nombre'],
        ];
    }

    /**
     * Construye y devuelve un PDO conectado a la base SAP de una empresa.
     *
     * @throws RuntimeException si la configuración está incompleta (ver obtenerParametrosSap).
     * @throws PDOException     si la conexión en sí falla (credenciales, red, etc.).
     */
    function conectarSap(PDO $pdo, $empresaId = null)
    {
        $p = obtenerParametrosSap($pdo, $empresaId);

        $dsn = 'sqlsrv:Server=' . $p['servidor'] . ';Database=' . $p['base']
             . ';TrustServerCertificate=1;LoginTimeout=10';

        $pdoSap = new PDO($dsn, $p['usuario'], $p['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Año-mes-día para las fechas de esta sesión (evita el problema de DATEFORMAT dmy
        // en logins con idioma Español). Blinda todas las consultas.
        $pdoSap->exec('SET DATEFORMAT ymd');

        return $pdoSap;
    }
?>
