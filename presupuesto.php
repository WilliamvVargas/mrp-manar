<?php
    require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto | MRP</title>
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
            <h5 class="mb-0 text-black"><i class="bi bi-cash-coin me-2"></i>Presupuesto</h5>
            <button class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#modalCargaMasivaPresupuesto">
                <i class="bi bi-file-arrow-up"></i> Carga Masiva Presupuesto
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="consulta-presupuesto">Consulta</label>
                    <div class="col-md-4 px-0">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta-presupuesto"
                                   name="consulta-presupuesto"
                                   placeholder="Ej: Canal, Familia, Sub-Familia...">
                        </div>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="tabla-consulta-presupuesto" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 4%">ID</th>
                            <th style="width: 6%">Año</th>
                            <th style="width: 5%">Mes</th>
                            <th style="width: 11%">Canal</th>
                            <th style="width: 11%">Sub-Canal</th>
                            <th style="width: 11%">Familia</th>
                            <th style="width: 11%">Sub-Familia</th>
                            <th style="width: 9%" class="text-end">Venta</th>
                            <th style="width: 7%" class="text-end">MG %</th>
                            <th style="width: 9%" class="text-end">MG Neto</th>
                            <th style="width: 8%" class="text-end">PP</th>
                            <th style="width: 8%" class="text-end">KG</th>
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
    include 'modals/modal_presupuesto_carga_masiva.php';
?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/presupuesto.js"></script>
</body>
</html>
