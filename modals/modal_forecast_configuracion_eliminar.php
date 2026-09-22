<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalForecastConfiguracionEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm-custom" style="max-width: 400px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Configuración</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-forecast-config-eliminar" class="form-validado-estatico" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_forecast_config_eliminar"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id-forecast-config-eliminar"
                           name="id">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-forecast-config-mensajes-eliminar"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Estás seguro de que deseas <span class="text-danger fw-bold">eliminar permanentemente</span> esta configuración?</p>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-nombre-forecast-config-eliminar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-danger bg-light"
                                   id="input-nombre-forecast-config-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-bold small mb-1">Opciones</label>
                        <ul class="list-group list-group-flush small border rounded">
                            <li class="list-group-item d-flex align-items-center py-1 px-2">
                                <span style="flex: 0 0 60%;">Imputar demanda censurada</span>
                                <span class="text-start" style="flex: 1;" id="del-imputar"></span>
                            </li>
                            <li class="list-group-item d-flex align-items-center py-1 px-2">
                                <span style="flex: 0 0 60%;">Suavizar outliers</span>
                                <span class="text-start" style="flex: 1;" id="del-capar"></span>
                            </li>
                            <li class="list-group-item d-flex align-items-center py-1 px-2">
                                <span style="flex: 0 0 60%;">Ensamble</span>
                                <span class="text-start" style="flex: 1;" id="del-ensamble"></span>
                            </li>
                            <li class="list-group-item d-flex align-items-center py-1 px-2">
                                <span style="flex: 0 0 60%;">Estabilizar poco histórico</span>
                                <span class="text-start" style="flex: 1;" id="del-estabilizar"></span>
                            </li>
                        </ul>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            data-bs-dismiss="modal">
                        Cerrar
                    </button>
                    <button type="submit"
                            class="btn btn-sm btn-danger"
                            id="btnEliminarForecastConfig">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
