<?php
    require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Iconos | MRP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div id="alert-container"></div>
</div>

<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-black"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Iconos</h5>
            <button class="btn btn-primary btn-sm"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#modalIconoCrear">
                <i class="bi bi-plus-lg"></i> Agregar Icono
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <div class="row g-2 mb-2 mx-0">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small mb-1" for="consulta">Consulta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta"
                                   name="consulta"
                                   placeholder="Ej: house">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1">Tipo</label>
                        <div id="filtro-tipo-contenedor"></div>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 10%" class="text-center">Posición</th>
                            <th style="width: 26%">Nombre</th>
                            <th style="width: 26%">Valor</th>
                            <th style="width: 13%" class="text-center">Tipo</th>
                            <th style="width: 12%" class="text-center">Vista previa</th>
                            <th style="width: 13%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php

    include 'modals/modal_iconos_crear.php';

?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script>
    // Catálogo de nombres de Bootstrap Icons (mismo origen que valida el servidor),
    // embebido para el combobox de búsqueda del formulario de creación.
    const ICONOS_BOOTSTRAP = <?php echo json_encode(require __DIR__ . '/data/iconos_bootstrap.php'); ?>;
</script>
<script src="assets/js/iconos.js"></script>
</body>
</html>
