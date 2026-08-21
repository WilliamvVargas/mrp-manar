<?php
    require_once 'includes/auth.php';
    require_once 'includes/control_acceso_pagina.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Empresas | MRP</title>
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Empresas'); ?></h5>
            <button class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEmpresaCrear">
                <i class="bi bi-plus-lg"></i> Agregar Empresa
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
                                   placeholder="Ej: Manar">
                        </div>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 8%"  class="text-center">Posición</th>
                            <th style="width: 13%" class="text-center">Logo</th>
                            <th style="width: 40%">Nombre</th>
                            <th style="width: 21%">Fecha de Creación</th>
                            <th style="width: 18%" class="text-center">Acciones</th>
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
    include 'modals/modal_empresas_crear.php';
    include 'modals/modal_empresas_editar.php';
    include 'modals/modal_empresas_conexion_sap.php';
    include 'modals/modal_asignar_posicion.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/empresas.js?v=<?php echo filemtime(__DIR__ . '/assets/js/empresas.js'); ?>"></script>
</body>
</html>
