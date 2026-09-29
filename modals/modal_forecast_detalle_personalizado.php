<div class="modal fade" id="modalDetalleForecastPersonalizado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-eye me-2"></i>Detalle</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">

                <!-- Cabecera: información del producto -->
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle mb-0" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Código Producto</th>
                                <th>Nombre Producto</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-detalle-fp-info"></tbody>
                    </table>
                </div>

                <div id="detalle-fp-estado" class="text-center text-muted py-3">Cargando...</div>
                <div class="table-responsive" id="detalle-fp-wrap" style="display:none;">
                    <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Semana</th>
                                <th class="text-end">Demanda</th>
                                <th class="text-center" style="width: 15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-detalle-forecast-personalizado"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td class="text-end">Total:</td>
                                <td class="text-end" id="detalle-fp-total">—</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
