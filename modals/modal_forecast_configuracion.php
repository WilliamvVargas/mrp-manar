<?php require_once __DIR__ . '/../config/config.php'; ?>

<style>
    .opciones-forecast .opcion-toggle:focus { box-shadow: none; }
    .opciones-forecast .opcion-chevron { transition: transform .18s ease; }
    .opciones-forecast .opcion-toggle[aria-expanded="true"] .opcion-chevron { transform: rotate(90deg); }
</style>

<div class="modal fade" id="modalForecastConfiguracion" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-gear me-2"></i>Configuración del Forecast</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-forecast-config" class="form-validado-estatico" action="controllers/forecast_config_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_forecast_config"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-forecast-config-mensajes"></div>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small mb-1" for="input_config_nombre">Nombre <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_config_nombre"
                                   name="nombre"
                                   placeholder="Ej: Configuración estándar"
                                   maxlength="100">
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
                    </div>

                    <!-- Opciones de limpieza del forecast: paneles plegables (título + switch en la
                         cabecera, explicación oculta que se revela al hacer clic). Todos cerrados. -->
                    <label class="form-label fw-bold small mb-1">Opciones</label>
                    <div class="opciones-forecast border rounded">

                        <!-- Imputar demanda censurada -->
                        <div class="opcion-item border-bottom">
                            <div class="d-flex align-items-center gap-2 px-2 py-2">
                                <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                                        aria-expanded="false" aria-controls="op-body-censura">
                                    <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                                    <span class="small fw-semibold">Imputar demanda censurada por quiebre</span>
                                </button>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cfg_imputar_censura" name="imputar_censura" value="1">
                                </div>
                            </div>
                            <div class="opcion-body" id="op-body-censura" style="display: none;">
                                <p class="small text-secondary mb-0 px-2 pb-2 ps-4">
                                    Cuando hubo quiebre de stock, la venta registrada es menor que la demanda real: el quiebre la censura.
                                    Esta opción reconstruye el inventario histórico e imputa lo que se habría vendido, para que el modelo no
                                    aprenda a subestimar los productos que se quiebran seguido.
                                </p>
                            </div>
                        </div>

                        <!-- Capar outliers -->
                        <div class="opcion-item border-bottom">
                            <div class="d-flex align-items-center gap-2 px-2 py-2">
                                <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                                        aria-expanded="false" aria-controls="op-body-outliers">
                                    <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                                    <span class="small fw-semibold">Suavizar outliers (pedidos-lote)</span>
                                </button>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cfg_capar_outliers" name="capar_outliers" value="1">
                                </div>
                            </div>
                            <div class="opcion-body" id="op-body-outliers" style="display: none;">
                                <div class="px-2 pb-2 ps-4">
                                    <p class="small text-secondary mb-2">
                                        Pedidos puntuales muy grandes (compras por contrato o evento) inflan el histórico y el modelo los toma
                                        como demanda recurrente. Se recortan por sobre un múltiplo de la mediana; un K más alto suaviza menos.
                                    </p>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <label class="form-label fw-bold small mb-0" for="cfg_capar_k">Umbral (K)</label>
                                        <input type="number" step="0.5" min="1" max="50"
                                               class="form-control form-control-sm opcion-param"
                                               id="cfg_capar_k" name="capar_k" value="10"
                                               style="width: 90px;" disabled>
                                        <span class="small text-muted">× la mediana</span>
                                        <div class="invalid-feedback small w-100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ensamble -->
                        <div class="opcion-item">
                            <div class="d-flex align-items-center gap-2 px-2 py-2">
                                <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                                        aria-expanded="false" aria-controls="op-body-ensamble">
                                    <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                                    <span class="small fw-semibold">Ensamble Prophet + Pronóstico Estacional Ingenuo</span>
                                </button>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="cfg_ensamble" name="ensamble" value="1">
                                </div>
                            </div>
                            <div class="opcion-body" id="op-body-ensamble" style="display: none;">
                                <div class="px-2 pb-2 ps-4">
                                    <p class="small text-secondary mb-2">
                                        Promedia el pronóstico de Prophet con un pronóstico estacional ingenuo (repetir la temporada del año anterior).
                                        Ajusta cuánto pesa cada uno; es más robusto ante el ruido de timing semanal.
                                    </p>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <label class="form-label fw-bold small mb-0" for="cfg_ensamble_peso">Peso del ensamble</label>
                                        <span class="small fw-semibold text-primary" id="cfg_ensamble_peso_out">Prophet 50% · Estacional 50%</span>
                                    </div>
                                    <input type="range" min="0" max="100" step="5" value="50"
                                           class="form-range opcion-param"
                                           id="cfg_ensamble_peso" name="ensamble_peso_prophet" disabled>
                                </div>
                            </div>
                        </div>

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
                            id="btnGuardarForecastConfig">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
