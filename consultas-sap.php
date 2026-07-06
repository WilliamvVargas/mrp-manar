<?php
    require_once 'includes/auth.php';
    require_once 'includes/control_acceso_pagina.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consultas SAP</title>
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Consultas SAP'); ?></h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-facs-ncs">
                    <i class="bi bi-search"></i> Consulta Facs. y NCs
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-facs-ncs-v2">
                    <i class="bi bi-search"></i> Consulta Facs. y NCs v2
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-facs-ncs-v3">
                    <i class="bi bi-search"></i> Consulta Facs. y NCs v3
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-facs-ncs-v4">
                    <i class="bi bi-search"></i> Consulta Facs. y NCs v4
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-oc">
                    <i class="bi bi-search"></i> Consulta OC
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-stock">
                    <i class="bi bi-search"></i> Consulta Stock
                </button>
                <button class="btn btn-primary btn-sm" type="button" id="btn-consulta-odv">
                    <i class="bi bi-search"></i> Consulta ODV
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

                    <!-- Filtro por fecha del documento (mes/año); el JS lo muestra solo en la consulta Facs. y NCs. -->
                    <div class="col-md-2 filtro-fecha-facs d-none">
                        <label class="form-label fw-bold small mb-1" for="facs-fecha-desde">Fecha Doc. desde (mes/año)</label>
                        <input type="text" class="form-control form-control-sm bg-white" id="facs-fecha-desde" placeholder="Sin límite" readonly>
                    </div>
                    <div class="col-md-2 filtro-fecha-facs d-none">
                        <label class="form-label fw-bold small mb-1" for="facs-fecha-hasta">Fecha Doc. hasta (mes/año)</label>
                        <input type="text" class="form-control form-control-sm bg-white" id="facs-fecha-hasta" placeholder="Sin límite" readonly>
                    </div>

                    <!-- Filtros client-side (líneas); el JS muestra cada uno según la consulta activa. -->
                    <div class="col-md-2 filtro-item-tipo d-none">
                        <label class="form-label fw-bold small mb-1" for="filtro-v2-tipo">Tipo Doc.</label>
                        <select class="form-select form-select-sm" id="filtro-v2-tipo">
                            <option value="">Todos</option>
                            <option value="Factura">Factura</option>
                            <option value="Nota de Crédito">Nota de Crédito</option>
                        </select>
                    </div>
                    <div class="col-md-2 filtro-item-familia d-none">
                        <label class="form-label fw-bold small mb-1" for="filtro-v2-familia">Familia</label>
                        <select class="form-select form-select-sm" id="filtro-v2-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2 filtro-item-subfamilia d-none">
                        <label class="form-label fw-bold small mb-1" for="filtro-v2-subfamilia">Sub-Familia</label>
                        <select class="form-select form-select-sm" id="filtro-v2-subfamilia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-auto d-flex align-items-end filtro-item-limpiar d-none">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros-sap">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Título de la consulta cargada; lo setea el JS al hacer clic en un botón. -->
                <h6 id="consulta-sap-titulo" class="fw-bold text-primary border-bottom pb-2 mb-3 d-none"></h6>

                <table class="table table-hover align-middle table-sm tabla-compacta" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark"></thead>
                    <tbody></tbody>
                    <tfoot class="table-light fw-bold"></tfoot>
                </table>

                <!-- Mensaje inicial: se oculta al cargar una consulta. -->
                <div id="consulta-sap-placeholder" class="text-center text-muted py-4">
                    <i class="bi bi-arrow-up-circle me-1"></i>
                    Selecciona una consulta (Facs. y NCs, ODV, OC o Stock) para cargar los datos.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include 'modals/modal_consultas_sap_detalle.php';
    include 'modals/modal_consultas_sap_lineas.php';
    include 'modals/modal_consultas_sap_docs.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/consultas_sap.js"></script>
</body>
</html>
