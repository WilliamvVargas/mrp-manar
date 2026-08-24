<?php 
    require_once 'includes/auth.php';
    require_once 'includes/control_acceso_pagina.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MRP</title>
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Forecast'); ?></h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" type="button" id="btn-explosion-forecast"
                        data-bs-toggle="modal" data-bs-target="#modalExplosionForecast">
                    <i class="bi bi-diagram-3"></i> Explosión de Forecast
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <div class="row g-2 mb-2 mx-0">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="consulta-forecast">Consulta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta-forecast"
                                   name="consulta-forecast"
                                   placeholder="Nombre o código de producto">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-familia">Familia</label>
                        <select class="form-select form-select-sm" id="filtro-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-sub-familia">Sub-Familia</label>
                        <select class="form-select form-select-sm" id="filtro-sub-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-calidad">Calidad</label>
                        <select class="form-select form-select-sm" id="filtro-calidad">
                            <option value="">Todas</option>
                            <option value="Alta">Alta</option>
                            <option value="Media">Media</option>
                            <option value="Baja">Baja</option>
                        </select>
                    </div>
                    <div class="col-md-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta-forecast" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 9%">Código Producto</th>
                            <th style="width: 18%">Nombre Producto</th>
                            <th style="width: 11%">Familia</th>
                            <th style="width: 11%">Sub-Familia</th>
                            <th style="width: 8%">Versión</th>
                            <th style="width: 9%">Cálculo Forecast</th>
                            <th style="width: 7%" class="text-center">Calidad</th>
                            <th style="width: 11%" class="text-end">Total Forecast (52 semanas)</th>
                            <th style="width: 11%" class="text-end">Forecast Semana Siguiente</th>
                            <th style="width: 10%" class="text-center">Acciones</th>
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
    include 'modals/modal_forecast_grafico.php';
    include 'modals/modal_forecast_ajuste.php';
    include 'modals/modal_forecast_parametros_mrp.php';
    include 'modals/modal_forecast_explosion.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/forecast.js?v=<?php echo filemtime(__DIR__ . '/assets/js/forecast.js'); ?>"></script>
<script src="assets/js/forecast_grafico.js?v=<?php echo filemtime(__DIR__ . '/assets/js/forecast_grafico.js'); ?>"></script>
<script src="assets/js/explosion_forecast.js?v=<?php echo filemtime(__DIR__ . '/assets/js/explosion_forecast.js'); ?>"></script>
</body>
</html>