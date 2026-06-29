<?php
    require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consultas SAP</title>
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
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'Consultas SAP'); ?></h5>
            <div class="d-flex gap-2">
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
                </div>

                <!-- Título de la consulta cargada; lo setea el JS al hacer clic en un botón. -->
                <h6 id="consulta-sap-titulo" class="fw-bold text-primary border-bottom pb-2 mb-3 d-none"></h6>

                <table class="table table-hover align-middle table-sm tabla-compacta" id="tabla-consulta" style="width:100%">
                    <thead class="table-dark"></thead>
                    <tbody></tbody>
                </table>

                <!-- Mensaje inicial: se oculta al cargar una consulta. -->
                <div id="consulta-sap-placeholder" class="text-center text-muted py-4">
                    <i class="bi bi-arrow-up-circle me-1"></i>
                    Selecciona una consulta (ODV, OC o Stock) para cargar los datos.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include 'modals/modal_consultas_sap_detalle.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/consultas_sap.js"></script>
</body>
</html>
