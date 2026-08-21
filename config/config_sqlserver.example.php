<?php

    // Plantilla de configuración de conexión a SQL Server (SAP).
    // Copia este archivo como "config_sqlserver.php" y completa con tus credenciales reales.
    // (config_sqlserver.php está en .gitignore y NO se versiona.)
    //
    // Modelo multi-empresa:
    //   - Servidor, base y usuario de cada empresa viven en la tabla `empresas`
    //     (sap_servidor / sap_base / sap_usuario), editables desde el modal "Conexión SAP".
    //   - Aquí SOLO va la CONTRASEÑA, como un mapa "base de datos => contraseña".

    // === Contraseñas SAP por base de datos ===
    // La llave debe ser EXACTAMENTE el valor de sap_base de la empresa (ej. 'CLPRDMANAR').
    define('SQLSRV_PASS_POR_BASE', [
        'NOMBRE_DE_LA_BASE' => 'contraseña',
        // 'OTRA_BASE'      => 'otra_contraseña',
    ]);

?>
