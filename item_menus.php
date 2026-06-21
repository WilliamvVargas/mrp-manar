<?php
    require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ítem Menús | MRP</title>
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
            <h5 class="mb-0 text-black"><i class="bi bi-menu-app-fill me-2"></i>Ítem Menús</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm"
                        type="button"
                        id="btn-asignar-posiciones">
                    <i class="bi bi-sort-numeric-down"></i> Asignar Posición
                </button>
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modalItemMenuCrear">
                    <i class="bi bi-plus-lg"></i> Agregar Ítem Menú
                </button>
            </div>
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
                                   placeholder="Ej: Usuarios">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="filtro-menu">Menú</label>
                        <select class="form-select form-select-sm" id="filtro-menu">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="filtro-estado">Estado</label>
                        <select class="form-select form-select-sm" id="filtro-estado">
                            <option value="">Todos</option>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 7%"  class="text-center">Id</th>
                            <th style="width: 18%">Nombre</th>
                            <th style="width: 18%">Enlace</th>
                            <th style="width: 16%">Menú</th>
                            <th style="width: 9%"  class="text-center">Posición</th>
                            <th style="width: 8%"  class="text-center">Ícono</th>
                            <th style="width: 10%" class="text-center">Estado</th>
                            <th style="width: 14%" class="text-center">Acciones</th>
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

    include 'modals/modal_item_menus_crear.php';
    include 'modals/modal_asignar_posicion.php';

?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/item_menus.js"></script>
</body>
</html>
