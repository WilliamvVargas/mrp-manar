<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalCargaMasivaPresupuesto" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title" id="modalTitlePresupuesto"><i class="bi bi-graph-up me-2"></i>Carga Masiva Presupuesto</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body py-2">

                <!-- Formulario de carga del archivo -->
                <form id="form-carga-masiva-presupuesto" class="form-validado-estatico" action="controllers/presupuesto_controller.php" novalidate>

                    <input type="hidden"
                           id="csrf_token_presupuesto"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-presupuesto"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="archivo-excel-presupuesto">Archivo Excel</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <input type="file"
                                           class="form-control form-control-sm"
                                           id="archivo-excel-presupuesto"
                                           name="archivo"
                                           accept=".xlsx">
                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="btnCargaMasivaPresupuesto">
                                        <i class="bi bi-save me-1"></i> Subir Registros
                                    </button>
                                </div>
                                <div class="form-text small">Formato permitido: .xlsx</div>
                                <div class="invalid-feedback small" id="error-archivo-presupuesto"></div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
