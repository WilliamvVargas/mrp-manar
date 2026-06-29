<?php
    // Navbar dinámico: los menús, ítems, enlaces y estado activo dependen del perfil del usuario
    // en sesión y de sus accesos activos. Solo se muestran menús e ítems activos con acceso.
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../models/accesos_model.php';
    require_once __DIR__ . '/funciones_mantenedor.php';   // encabezadoMantenedor() para el card de cada mantenedor

    $menuNavegacion = (new Acceso($pdo))->menuNavegacion($_SESSION['usuario_id'] ?? null);

    // Ruta actual (archivo sin .php) para marcar el menú/ítem activo. Los archivos de página se
    // llaman igual que el `enlace` del item_menu, así que basta comparar el nombre del archivo.
    $rutaActual = basename($_SERVER['PHP_SELF'], '.php');

    // HTML del ícono de un ítem (Bootstrap por fuente o personalizado por archivo).
    if (!function_exists('iconoNavbar')) {
        function iconoNavbar(array $item): string
        {
            if (($item['icono_tipo'] ?? '') === 'bootstrap' && !empty($item['icono_valor'])) {
                return '<i class="bi bi-' . htmlspecialchars($item['icono_valor']) . ' me-2"></i>';
            }
            if (($item['icono_tipo'] ?? '') === 'personalizado' && !empty($item['icono_archivo'])) {
                // Monocromático (coloreable): clase `icono-mono` para forzarlo a blanco vía CSS.
                // Los multicolor no la llevan y conservan sus colores.
                $claseMono = !empty($item['icono_coloreable']) ? ' icono-mono' : '';
                return '<img src="assets/icons/personalizados/' . htmlspecialchars($item['icono_archivo'])
                     . '" alt="" class="me-2' . $claseMono . '" style="width:1em;height:1em;object-fit:contain;vertical-align:-0.125em;">';
            }
            return '';
        }
    }
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard">Panel de Control</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menuNavegacion as $menu): ?>
                    <?php
                        // El menú queda "activo" si la ruta actual coincide con alguno de sus ítems.
                        $menuActivo = false;
                        foreach ($menu['items'] as $it) {
                            if ($it['enlace'] === $rutaActual) {
                                $menuActivo = true;
                                break;
                            }
                        }
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $menuActivo ? 'active' : ''; ?>"
                           href="#" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($menu['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <?php foreach ($menu['items'] as $item): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($item['enlace'] === $rutaActual) ? 'active' : ''; ?>"
                                       href="<?php echo htmlspecialchars($item['enlace']); ?>">
                                        <?php echo iconoNavbar($item) . htmlspecialchars($item['nombre']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 small">
                    <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
                </span>
                <a href="logout" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </div>
</nav>
