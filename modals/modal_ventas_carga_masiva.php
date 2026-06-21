<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalVentasCargaMasiva" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-currency-dollar me-2"></i>Carga Masiva Ventas Históricas</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body py-2">

                <!-- Formulario de carga del archivo -->
                <form id="form-carga-masiva-ventas" class="form-validado-estatico" action="controllers/ventas_historicas_controller.php" novalidate>

                    <input type="hidden"
                           id="csrf_token_ventas"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-ventas"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="archivo-excel-ventas">Archivo Excel</label>
                        <div class="row">
                            <div class="col-md-7">
                                <div class="input-group input-group-sm">
                                    <input type="file"
                                           class="form-control form-control-sm"
                                           id="archivo-excel-ventas"
                                           name="archivo"
                                           accept=".xlsx">
                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="btnCargaMasivaVentas">
                                        <i class="bi bi-save me-1"></i> Subir Registros
                                    </button>
                                </div>
                                <div class="form-text small">Formato permitido: .xlsx</div>
                                <div class="invalid-feedback small" id="error-archivo-ventas"></div>
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
