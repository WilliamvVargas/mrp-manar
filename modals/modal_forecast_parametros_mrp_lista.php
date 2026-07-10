<div class="modal fade" id="modalForecastParametrosMrpLista" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-sliders me-2"></i>Parámetros para MRP — Bodega 010</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-3">
                <!-- Estado: se muestra mientras carga / si hay error; el JS lo alterna con la tabla. -->
                <div id="fc-mrp-lista-estado" class="text-center text-muted py-5">Cargando...</div>

                <div id="fc-mrp-lista-wrap" style="display:none;">
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="consulta-mrp-lista">Consulta</label>
                        <div class="col-md-4 px-0">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="consulta-mrp-lista"
                                       placeholder="Nombre o código de producto">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive small">
                    <table class="table table-hover table-sm align-middle" id="tabla-parametros-mrp-lista" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th title="Código Producto">Cód. Prod.</th>
                                <th title="Nombre Producto">Producto</th>
                                <th>Familia</th>
                                <th title="Sub-Familia">Sub-Fam.</th>
                                <th class="text-end" title="Lead Time (días)">L. Time</th>
                                <th class="text-end" title="Cantidad Mínima de Pedido">Cant. Mín.</th>
                                <th class="text-end" title="Múltiplo de Pedido">Múlt.</th>
                                <th class="text-end" title="Stock Mínimo">Stk. Mín.</th>
                                <th class="text-end" title="Stock Máximo">Stk. Máx.</th>
                                <th class="text-end" title="Pedido Mínimo">Ped. Mín.</th>
                                <th class="text-end" title="Stock Disponible (En Mano)">En Mano</th>
                                <th class="text-end" title="Comprometido">Comprom.</th>
                                <th class="text-end" title="En Pedido (Solicitado)">En Pedido</th>
                                <th title="Código Proveedor">Cód. Prov.</th>
                                <th>Proveedor</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    </div>
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
