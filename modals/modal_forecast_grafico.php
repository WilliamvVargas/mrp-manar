<div class="modal fade" id="modalForecastGrafico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-graph-up me-2"></i>Gráfico producto
                    <span id="fc-grafico-titulo" class="fw-normal"></span>
                </h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-3">
                <!-- Resumen: el registro seleccionado en el mantenedor de Forecast; lo llena el JS. -->
                <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-collection me-1"></i>Registro seleccionado</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-striped align-middle mb-0 small" id="tabla-resumen-grafico-forecast">
                        <thead class="table-dark"></thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Filtros por año (client-side); el JS los llena con los años del producto. -->
                <div class="row g-2 mb-2 align-items-end" id="fc-grafico-filtros" style="display:none;">
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1" for="fc-grafico-mostrar">Mostrar</label>
                        <select class="form-select form-select-sm" id="fc-grafico-mostrar">
                            <option value="ambos" selected>Demanda y Venta Neta</option>
                            <option value="demanda">Demanda</option>
                            <option value="venta">Venta Neta</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1" for="fc-grafico-anio-desde">Año desde</label>
                        <select class="form-select form-select-sm" id="fc-grafico-anio-desde"></select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1" for="fc-grafico-anio-hasta">Año hasta</label>
                        <select class="form-select form-select-sm" id="fc-grafico-anio-hasta"></select>
                    </div>
                </div>

                <!-- Estado: se muestra mientras carga / si no hay datos; el JS lo alterna con el gráfico. -->
                <div id="fc-grafico-estado" class="text-center text-muted py-5">Cargando...</div>
                <!-- Lienzo del gráfico (Google Charts). -->
                <div id="fc-grafico-canvas" style="width:100%; min-height:420px;"></div>
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
