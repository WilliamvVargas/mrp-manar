<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard">Panel de Control</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'usuarios.php') ? 'active' : ''; ?>" 
                       href="#" id="navbarAdmin" role="button" data-bs-toggle="dropdown">
                        Administración
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'usuarios.php') ? 'active' : ''; ?>" href="usuarios">
                                <i class="bi bi-people-fill me-2"></i>Usuarios
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'perfiles.php') ? 'active' : ''; ?>" href="perfiles">
                                <i class="bi bi-person-badge me-2"></i>Perfiles
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'menus.php') ? 'active' : ''; ?>" href="menus">
                                <i class="bi bi-segmented-nav me-2"></i>Menús
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'item_menus.php') ? 'active' : ''; ?>" href="item_menus">
                                <i class="bi bi-menu-app-fill me-2"></i>Ítem Menús
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'iconos.php') ? 'active' : ''; ?>" href="iconos">
                                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Íconos
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item disabled" href="#">Configuración</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'usuarios.php') ? 'active' : ''; ?>" 
                       href="#" id="navbarAdmin" role="button" data-bs-toggle="dropdown">
                        Procesos
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'forecast.php') ? 'active' : ''; ?>" href="forecast">
                                <i class="bi bi-graph-up me-2"></i>MRP
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'presupuesto.php') ? 'active' : ''; ?>" href="presupuesto">
                                <i class="bi bi-cash-coin me-2"></i>Presupuesto
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'ventas_historicas.php') ? 'active' : ''; ?>" href="ventas_historicas">
                                <i class="bi bi-currency-dollar me-2"></i>Ventas Historicas
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'consultas_sap.php') ? 'active' : ''; ?>" href="consultas_sap">
                                <i class="bi bi-database-fill-gear me-2"></i>Consultas SAP
                            </a>
                        </li>
                    </ul>
                </li>
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