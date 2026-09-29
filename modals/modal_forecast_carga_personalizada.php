<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalCargaForecastPersonalizado" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-file-arrow-up me-2"></i>Carga Forecast Personalizado</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body py-2">

                <!-- Formulario de carga del archivo -->
                <form id="form-carga-forecast-personalizado" class="form-validado-estatico" action="controllers/forecast_controller.php" novalidate>

                    <input type="hidden"
                           id="csrf_token_forecast_personalizado"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-forecast-personalizado"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="archivo-forecast-personalizado">Archivo Excel</label>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="input-group input-group-sm">
                                    <input type="file"
                                           class="form-control form-control-sm"
                                           id="archivo-forecast-personalizado"
                                           name="archivo"
                                           accept=".xlsx">
                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="btnCargaForecastPersonalizado">
                                        <i class="bi bi-save me-1"></i> Subir Registros
                                    </button>
                                </div>
                                <div class="form-text small">
                                    Formato permitido: .xlsx ·
                                    <a href="controllers/forecast_controller.php?action=plantilla_forecast_personalizado">
                                        <i class="bi bi-download"></i> Descargar plantilla
                                    </a>
                                </div>
                                <div class="invalid-feedback small" id="error-archivo-forecast-personalizado"></div>
                            </div>
                        </div>
                    </div>
                </form>

                <hr class="my-2">
                <h6 class="small fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Forecast personalizado cargado</h6>
                <div class="row g-2 mb-2 mx-0">
                    <div class="col-md-6 px-0">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control form-control-sm"
                                   id="consulta-forecast-personalizado"
                                   placeholder="Filtrar por código o producto">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="tabla-forecast-personalizado" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Código Producto</th>
                                <th>Nombre Producto</th>
                                <th class="text-center" style="width: 18%">Cantidad Registros</th>
                                <th class="text-center" style="width: 12%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

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
