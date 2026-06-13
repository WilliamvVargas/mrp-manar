<?php

    /*
     * Arranque de sesión centralizado con cookies endurecidas.
     */

    if (session_status() === PHP_SESSION_NONE) {

        // Detecta si la petición viaja por HTTPS para activar el flag 'secure'
        // sin romper el entorno local que aún funciona sobre HTTP.
        $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        session_set_cookie_params([
            'lifetime' => 0,          // La cookie expira al cerrar el navegador
            'path'     => '/',
            'domain'   => '',
            'secure'   => $esHttps,   // Solo se envía por HTTPS cuando está disponible
            'httponly' => true,       // Inaccesible desde JavaScript (mitiga robo por XSS)
            'samesite' => 'Lax'       // No viaja en peticiones entre sitios (mitiga CSRF)
        ]);

        session_start();
    }

?>
