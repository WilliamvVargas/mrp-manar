<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Panel de Control</span>
        <a href="logout" class="btn btn-outline-light btn-sm">Salir</a>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body d-flex justify-content-center">
                    <img src="assets/img/perfil-placeholder.png" class="card-img-top img-fluid w-50" alt="Usuario">
                </div>
                <div class="card-body text-center">
                    <h5><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></h5>
                    <button type="button" class="btn btn-info mt-3" data-bs-toggle="modal" data-bs-target="#miModal">
                        Ver Detalles
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="miModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Información del Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Este es un ejemplo de modal cargado dinámicamente.</p>
                <div class="text-center">
                    <img src="assets/img/logo.png" class="img-thumbnail" style="width: 150px;" alt="Logo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnGuardar">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>