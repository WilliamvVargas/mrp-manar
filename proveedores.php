<?php
    require_once 'includes/auth.php';
    require_once 'includes/control_acceso_pagina.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores | MRP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container-fluid">
    <div id="alert-container"></div>
</div>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Proveedores'); ?></h5>
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
                                   placeholder="Código o nombre del proveedor">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="filtro-pais-btn">País</label>
                        <!-- Dropdown de Bootstrap (en vez de <select>) para poder mostrar la bandera
                             en cada opción; el código seleccionado viaja en el input oculto. -->
                        <input type="hidden" id="filtro-pais" value="">
                        <div class="dropdown">
                            <button class="btn btn-sm border bg-white w-100 text-start dropdown-toggle d-flex align-items-center"
                                    type="button" id="filtro-pais-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="filtro-pais-label" class="flex-grow-1 text-truncate">Todos</span>
                            </button>
                            <ul class="dropdown-menu w-100" id="filtro-pais-menu" style="max-height: 320px; overflow-y: auto;">
                                <li><a class="dropdown-item" href="#" data-codigo="" data-nombre="Todos">Todos</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 12%">Código</th>
                            <th style="width: 30%">Nombre</th>
                            <th style="width: 12%" class="text-center">País</th>
                            <th style="width: 30%">Dirección</th>
                            <th style="width: 16%" class="text-center">Modo de Transporte</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/proveedores.js"></script>
</body>
</html>
