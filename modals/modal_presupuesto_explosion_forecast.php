<div class="modal fade" id="modalExplosionForecast" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Explosión de Forecast</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body py-3">

                <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                    <div id="modal-mensajes-explosion"></div>
                </div>

                <p class="small text-muted mb-3">
                    Ejecuta el pipeline que pronostica la demanda por grupo (Prophet) y la reparte a
                    productos. <strong>Recalcula</strong> la tabla de forecast por producto.
                </p>

                <!-- Lista de pasos; el orden y la cantidad deben coincidir con el controlador. -->
                <ul class="list-group" id="explosion-pasos">
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="0">
                        <span><span class="badge bg-secondary me-2">1</span>Exportar series por grupo (SAP + presupuesto)</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="1">
                        <span><span class="badge bg-secondary me-2">2</span>Pronóstico Prophet por grupo</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="2">
                        <span><span class="badge bg-secondary me-2">3</span>Repartir el forecast del grupo a productos</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="3">
                        <span><span class="badge bg-secondary me-2">4</span>Exportar datos del backtest</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="4">
                        <span><span class="badge bg-secondary me-2">5</span>Prophet backtest (validación)</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="5">
                        <span><span class="badge bg-secondary me-2">6</span>Calcular factor y demanda corregida</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                </ul>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-primary" id="btn-explosion-ejecutar">
                    <i class="bi bi-play-fill"></i> Ejecutar
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
