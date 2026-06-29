<?php
    // Control de acceso por página: el usuario en sesión solo puede ver la página si su perfil
    // tiene acceso ACTIVO al item_menu cuyo `enlace` coincide con la ruta solicitada. Si no lo
    // tiene (p. ej. entró escribiendo el link directo), se lo redirige al dashboard.
    //
    // Debe incluirse al INICIO de cada mantenedor, después de auth.php (que asegura la sesión) y
    // ANTES de cualquier salida HTML, porque usa header(). El dashboard NO lo incluye: es la
    // página de respaldo accesible para cualquier usuario con sesión.
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../models/accesos_model.php';

    $rutaSolicitada = basename($_SERVER['PHP_SELF'], '.php');

    if (!(new Acceso($pdo))->usuarioTieneAcceso($_SESSION['usuario_id'] ?? null, $rutaSolicitada)) {
        header('Location: dashboard');
        exit;
    }
?>
