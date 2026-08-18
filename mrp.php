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
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css">
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'MRP'); ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <div class="row g-2 mb-2 mx-0">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small mb-1" for="consulta-mrp">Consulta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta-mrp"
                                   name="consulta-mrp"
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
                    <div class="col-md-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <table class="table table-hover align-middle table-sm tabla-compacta" id="tabla-consulta-mrp" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 8%">Código Producto</th>
                            <th style="width: 16%">Nombre Producto</th>
                            <th style="width: 10%">Familia</th>
                            <th style="width: 10%">Sub-Familia</th>
                            <th style="width: 6%"  class="text-end" title="Lead Time del producto (U_LeadTime), en días">Lead Time (d)</th>
                            <th style="width: 8%"  class="text-end" title="Forecast sumado sobre las semanas del lead time">Demanda (Forecast)</th>
                            <th style="width: 11%" class="text-center" title="Semana(s) del forecast que cubre la demanda (ventana del lead time)">Semana(s)</th>
                            <th style="width: 7%"  class="text-end">Stock (WMS)</th>
                            <th style="width: 7%"  class="text-end" title="Stock vigente que vence dentro de 30 días">Stock ≤30d</th>
                            <th style="width: 6%"  class="text-center" title="Días hasta el lote más próximo a vencer">Próx. Venc. (d)</th>
                            <th style="width: 7%"  class="text-end">Comprometido</th>
                            <th style="width: 7%"  class="text-end">En Pedido</th>
                            <th style="width: 7%"  class="text-end">En Producción</th>
                            <th style="width: 8%"  class="text-end">Sugerido a Reponer</th>
                            <th style="width: 5%"  class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: detalle de un registro del MRP (columna Acciones). Lo llena el JS. -->
<div class="modal fade" id="modalMrpDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-clipboard-data me-2"></i>Detalle MRP
                    <span id="mrp-detalle-titulo" class="fw-normal"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">

                <!-- Resumen del registro (Campo | Valor) -->
                <table class="table table-sm table-striped align-middle mb-3">
                    <tbody id="tabla-mrp-detalle"></tbody>
                </table>

                <!-- Pestañas de detalle por caso (Stock; se irán agregando más) -->
                <ul class="nav nav-tabs" id="mrp-detalle-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-stock-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-stock" type="button" role="tab"
                                aria-controls="tab-stock" aria-selected="true">
                            <i class="bi bi-boxes me-1"></i> Stock
                        </button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-2">
                    <div class="tab-pane fade show active" id="tab-stock" role="tabpanel" aria-labelledby="tab-stock-btn">
                        <div id="mrp-stock-estado" class="text-center text-muted py-3">Cargando...</div>
                        <div class="table-responsive small" id="mrp-stock-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Lote</th>
                                        <th class="text-center">F. Ingreso</th>
                                        <th class="text-center">F. Vencimiento</th>
                                        <th class="text-end">Días p/Vencer</th>
                                        <th class="text-center">Ubicación</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Vencimiento</th>
                                        <th class="text-end">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-stock"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/mrp.js?v=<?php echo filemtime(__DIR__ . '/assets/js/mrp.js'); ?>"></script>
</body>
</html>
