<div class="modal fade" id="modalForecastGrafico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
            <div class="modal-body d-flex flex-column p-0" style="overflow:hidden;">
                <!-- Región FIJA (no hace scroll): tabla del producto, siempre visible arriba. -->
                <div class="px-3 pt-3 pb-2 border-bottom flex-shrink-0">
                    <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-collection me-1"></i>Registro seleccionado</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0 small" id="tabla-resumen-grafico-forecast">
                            <thead class="table-dark"></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Región con SCROLL: gráfico + detalle (se desplazan; la tabla de arriba queda fija). -->
                <div class="px-3 py-3 flex-grow-1" style="overflow-y:auto;">

                <!-- Título del gráfico (mismo ícono que el botón "Gráfico producto"). -->
                <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-graph-up me-1"></i>Gráfico demanda histórica y demanda forecast</h6>

                <!-- Filtros por rango año-mes (client-side); el JS fija el rango según el producto. -->
                <div class="row g-2 mb-2 align-items-end" id="fc-grafico-filtros" style="display:none;">
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1">Mostrar</label>
                        <!-- Multiselección de series (utils.js -> inicializarMultiselect). -->
                        <div id="fc-grafico-mostrar" style="min-width: 230px;"></div>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1" for="fc-grafico-desde">Desde (mes/año)</label>
                        <input type="text" class="form-control form-control-sm bg-white" id="fc-grafico-desde" placeholder="mes/año" readonly>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-bold small mb-1" for="fc-grafico-hasta">Hasta (mes/año)</label>
                        <input type="text" class="form-control form-control-sm bg-white" id="fc-grafico-hasta" placeholder="mes/año" readonly>
                    </div>
                </div>

                <!-- Estado: se muestra mientras carga / si no hay datos; el JS lo alterna con el gráfico. -->
                <div id="fc-grafico-estado" class="text-center text-muted py-5">Cargando...</div>
                <!-- Lienzo del gráfico (Google Charts). -->
                <div id="fc-grafico-canvas" style="width:100%; min-height:420px;"></div>

                <!-- Detalle semana a semana del forecast del producto (bajo el gráfico); lo llena el JS. -->
                <div id="fc-grafico-detalle-wrap" class="mt-4" style="display:none;">
                    <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-table me-1"></i>Detalle del forecast por semana</h6>
                    <div class="table-responsive small">
                        <table class="table table-sm table-striped table-hover align-middle mb-0" id="tabla-detalle-grafico-forecast" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 22%">Semana</th>
                                    <th style="width: 18%">Tipo</th>
                                    <th style="width: 30%" class="text-end">Demanda Forecast</th>
                                    <th style="width: 30%" class="text-end">Demanda Histórica</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                </div><!-- fin de la región con scroll -->
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
