<?php
    // Plantilla de la fecha simulada (SOLO DESARROLLO).
    // Copia este archivo como "config/fecha_simulada.php" (gitignored) y ajusta la fecha al
    // snapshot de tu BD de dev. config.php lo incluye automáticamente SI existe.
    //
    // En PRODUCCIÓN no crees este archivo: sin él, FECHA_SIMULADA no se define y el sistema usa
    // la fecha real del servidor.
    define('FECHA_SIMULADA', 'AAAA-MM-DD');
?>
