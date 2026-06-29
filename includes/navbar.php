<?php
    // Navbar dinámico: los menús, ítems, enlaces y estado activo dependen del perfil del usuario
    // en sesión y de sus accesos activos. Solo se muestran menús e ítems activos con acceso.
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../models/accesos_model.php';
    require_once __DIR__ . '/../models/usuario_model.php';
    require_once __DIR__ . '/funciones_mantenedor.php';   // encabezadoMantenedor() para el card de cada mantenedor

    $menuNavegacion = (new Acceso($pdo))->menuNavegacion($_SESSION['usuario_id'] ?? null);

    // Perfil del usuario en sesión, para mostrarlo bajo su nombre (junto al botón Salir).
    $usuarioSesion = (new Usuario($pdo))->buscarPorId($_SESSION['usuario_id'] ?? null);
    $perfilUsuario = $usuarioSesion['perfil'] ?? null;

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
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle d-flex align-items-center py-1"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <span class="small text-end lh-sm me-2">
                        <strong class="d-block"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
                        <?php if (!empty($perfilUsuario)): ?>
                            <span class="text-white-50" style="font-size: 0.85em;"><?= htmlspecialchars($perfilUsuario) ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="bi bi-person-circle fs-3 text-white-50"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <li>
                        <button class="dropdown-item" type="button" id="btn-cambiar-password-propio">
                            <i class="bi bi-key me-2"></i>Cambiar Contraseña
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li >
                        <a class="dropdown-item bg-danger text-white" href="logout">
                            <i class="bi bi-box-arrow-right me-2"></i>Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<?php
    // Modal "Cambiar Contraseña" del usuario en sesión (se carga junto al navbar en cada página).
    include __DIR__ . '/../modals/modal_cambiar_password_propio.php';
?>
