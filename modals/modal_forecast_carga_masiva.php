<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalCargaMasiva" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title" id="modalTitle"><i class="bi bi-graph-up me-2"></i>Carga Masiva</h6>
                <button type="button" 
                        class="btn-close btn-close-white" 
                        data-bs-dismiss="modal" 
                        aria-label="Close">
                </button>
            </div>
            <form id="form-carga-masiva" class="form-validado-estatico" action="controllers/forecast_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="archivo-excel">Archivo Excel</label>
                        <input type="file"
                               class="form-control form-control-sm"
                               id="archivo-excel"
                               name="archivo"
                               accept=".xlsx">
                        <div class="form-text small">Formato permitido: .xlsx</div>
                        <div class="invalid-feedback small" id="error-archivo"></div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2"> 
                    <button type="button" 
                            class="btn btn-sm btn-secondary" 
                            data-bs-dismiss="modal">
                        Cerrar
                    </button>
                    <button type="submit" 
                            class="btn btn-sm btn-primary" 
                            id="btnCargaMasiva">
                        <i class="bi bi-save me-1"></i> Subir Registros
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>