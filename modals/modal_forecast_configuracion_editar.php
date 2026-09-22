<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalForecastConfiguracionEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Configuración del Forecast</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-forecast-config-editar" class="form-validado-estatico" action="controllers/forecast_config_controller.php" novalidate>
                <div class="modal-body py-2">

                    <!-- Id de la configuración que se está editando (lo usa la validación de unicidad) -->
                    <input type="hidden" id="id_forecast_config_editar" name="id_registro">

                    <?php $suf = '_ed'; include __DIR__ . '/_forecast_config_campos.php'; ?>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            data-bs-dismiss="modal">
                        Cerrar
                    </button>
                    <button type="submit"
                            class="btn btn-sm btn-primary"
                            id="btnActualizarForecastConfig">
                        <i class="bi bi-save me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
