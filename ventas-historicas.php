<?php
    require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas Históricas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Ventas Históricas'); ?></h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modalVentasCargaMasiva">
                    <i class="bi bi-file-arrow-up"></i> Carga Masiva Ventas Históricas
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <div class="row g-2 mb-2 mx-0 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="consulta">Consulta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta"
                                   name="consulta"
                                   placeholder="Ej: cliente, artículo, documento...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-version">Versión</label>
                        <select class="form-select form-select-sm" id="filtro-version">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-grupo">Grupo Artículo</label>
                        <select class="form-select form-select-sm" id="filtro-grupo">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-familia">Familia</label>
                        <select class="form-select form-select-sm" id="filtro-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-fecha">Fecha Documento (mes/año)</label>
                        <input type="text"
                               class="form-control form-control-sm bg-white"
                               id="filtro-fecha"
                               placeholder="Todas"
                               readonly>
                    </div>
                    <div class="col-md-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <table class="table table-hover align-middle table-sm tabla-compacta" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">Id</th>
                            <th class="text-center">Versión</th>
                            <th class="text-center">Fecha Docto.</th>
                            <th class="text-center">Nro Docto.</th>
                            <th class="text-center">Tipo Docto.</th>
                            <th>RUT Cliente</th>
                            <th>Cliente</th>
                            <th>Cód. Artículo</th>
                            <th>Artículo</th>
                            <th>Grupo Artículo</th>
                            <th>Familia</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Venta Bruta</th>
                            <th class="text-center">Acciones</th>
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
    include 'modals/modal_ventas_carga_masiva.php';
    include 'modals/modal_ventas_detalle.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/ventas_historicas.js"></script>
</body>
</html>
