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
                                <th class="text-end" title="Cantidad Mínima de Pedido">Cant. Mín.</th>
                                <th class="text-end" title="Múltiplo de Pedido">Múlt.</th>
                                <th class="text-end" title="Stock Mínimo">Stk. Mín.</th>
                                <th class="text-end" title="Stock Máximo">Stk. Máx.</th>
                                <th class="text-end" title="Pedido Mínimo">Ped. Mín.</th>
                                <th class="text-end" title="Stock Disponible WMS vigente (En Mano)">En Mano (WMS)</th>
                                <th class="text-end" title="Comprometido Ventas — órdenes de venta abiertas, bodega 010">Comp. Vta.</th>
                                <th class="text-end" title="Comprometido Producción — consumo de órdenes de producción, bodega 010">Comp. Prod.</th>
                                <th class="text-end" title="En Pedido — pendiente de OC (compras), bodega 010">En Pedido (Cmp.)</th>
                                <th class="text-end" title="En Producción — órdenes de producción liberadas, bodega 010">En Prod.</th>

                                <!-- Campos de negocio (UDF de OITM) -->
                                <th title="Status Artículo (negocio)">Status Art.</th>
                                <th title="Origen Artículo">Origen</th>
                                <th title="Marca Propia">M. Propia</th>
                                <th title="Artículo Nuevo">Art. Nuevo</th>
                                <th title="E-Commerce">E-Comm.</th>
                                <th title="Campaña">Campaña</th>
                                <th class="text-end" title="Gramaje">Gramaje</th>
                                <th class="text-end" title="Unidades por Caja">Unid. Caja</th>
                                <th title="Unidad Emb. Proveedor">U. Emb. Prov.</th>
                                <th class="text-end" title="Kilos">Kilos</th>
                                <th title="Moneda">Moneda</th>
                                <th title="Código Proveedor (negocio, UDF U_NX_Proveedor)">Cód. Prov. (Neg.)</th>
                                <th title="Proveedor (negocio, resuelto en @PROVEEDORES)">Proveedor (Neg.)</th>
                                <th class="text-end" title="Lead Time (negocio, UDF)">L.Time (Neg.)</th>
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
