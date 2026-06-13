<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php';

    /*
     * Funciones para frenar ataques de fuerza bruta contra el login.
     * El conteo se hace por combinación de IP + usuario sobre la tabla
     * `login_intentos`, con un bloqueo temporal configurable en config.php.
     */

    /**
     * Obtiene la IP del cliente que realiza la petición.
     *
     * @return string
     */
    function obtenerIpCliente() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Calcula los segundos de bloqueo restantes para una combinación IP + usuario.
     * Si la cantidad de intentos fallidos dentro de la ventana no alcanza el
     * límite, devuelve 0 (no está bloqueado).
     *
     * @param PDO    $pdo
     * @param string $ip
     * @param string $usuario
     * @return int Segundos restantes de bloqueo (0 si no aplica).
     */
    function segundosBloqueoLogin($pdo, $ip, $usuario) {

        $ventana = (int) LOGIN_VENTANA_MINUTOS;

        $sql = "SELECT COUNT(*) AS total,
                       TIMESTAMPDIFF(SECOND, NOW(), 
                       MIN(created_at) + INTERVAL $ventana MINUTE) AS restante
                FROM login_intentos
                WHERE ip = ?
                  AND usuario = ?
                  AND created_at > (NOW() - INTERVAL $ventana MINUTE)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $usuario]);
        $fila = $stmt->fetch();

        if ((int) $fila['total'] < LOGIN_MAX_INTENTOS) {
            return 0;
        }

        $restante = (int) $fila['restante'];

        return $restante > 0 ? $restante : 0;
    }

    /**
     * Registra un intento de inicio de sesión fallido.
     *
     * @param PDO    $pdo
     * @param string $ip
     * @param string $usuario
     * @return void
     */
    function registrarIntentoFallido($pdo, $ip, $usuario) {
        $stmt = $pdo->prepare("INSERT INTO login_intentos (ip, usuario) 
                               VALUES (?, ?)");
        $stmt->execute([$ip, $usuario]);
    }

    /**
     * Limpia los intentos fallidos de una combinación IP + usuario.
     * Se llama tras un inicio de sesión exitoso para reiniciar el contador.
     *
     * @param PDO    $pdo
     * @param string $ip
     * @param string $usuario
     * @return void
     */
    
    function limpiarIntentosFallidos($pdo, $ip, $usuario) {
        $stmt = $pdo->prepare("DELETE 
                               FROM login_intentos 
                               WHERE ip = ? AND usuario = ?");
        $stmt->execute([$ip, $usuario]);
    }

?>
