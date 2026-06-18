<?php 
    require_once 'includes/auth.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Forecast | MRP</title>
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
            <h5 class="mb-0 text-black"><i class="bi bi-graph-up me-2"></i>Forecast</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#modalCargaMasiva">
                    <i class="bi bi-file-arrow-up"></i> Carga Masiva Forecast
                </button>
                <button class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#modalCargaMasivaPresupuesto">
                    <i class="bi bi-file-arrow-up"></i> Carga Masiva Presupuesto
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-0 text-center py-3">
                <i class="bi bi-info-circle me-1"></i>Usa el botón <strong>Carga Masiva</strong> para cargar y consultar los registros de forecast.
            </p>
        </div>
    </div>
</div>

<?php 

    include 'modals/modal_forecast_carga_masiva.php';
    include 'modals/modal_presupuesto_carga_masiva.php';

?>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/forecast.js"></script>
<script src="assets/js/presupuesto.js"></script>
</body>
</html>