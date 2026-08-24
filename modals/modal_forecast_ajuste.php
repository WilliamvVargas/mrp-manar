<div class="modal fade" id="modalForecastAjuste" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Cantidad Ajustada</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form-forecast-ajuste">
                <div class="modal-body py-3">
                    <div id="modal-mensajes-ajuste"></div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="fc-ajuste-semana-txt">Semana</label>
                        <input type="text" class="form-control form-control-sm bg-white" id="fc-ajuste-semana-txt" readonly>
                    </div>
                    <div>
                        <label class="form-label fw-bold small mb-1" for="fc-ajuste-cantidad">Cantidad ajustada</label>
                        <input type="number"
                               class="form-control form-control-sm"
                               id="fc-ajuste-cantidad"
                               name="cantidad"
                               min="0" step="1" inputmode="numeric"
                               placeholder="0">
                    </div>

                    <!-- Datos ocultos que identifican la semana + CSRF. -->
                    <input type="hidden" name="csrf_token"    value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="itemcode"      id="fc-ajuste-itemcode">
                    <input type="hidden" name="iso_year"      id="fc-ajuste-iso-year">
                    <input type="hidden" name="iso_week"      id="fc-ajuste-iso-week">
                    <input type="hidden" name="semana_inicio" id="fc-ajuste-semana-inicio">
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btn-guardar-ajuste">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
