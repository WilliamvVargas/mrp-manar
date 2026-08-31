<div class="modal fade" id="modalConsultaSapDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-eye me-2"></i>Detalle del registro</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">
                <!-- Tabla vertical Campo | Valor; el cuerpo lo completa el JS. -->
                <table class="table table-sm table-striped align-middle mb-0">
                    <tbody id="tabla-detalle-consulta-sap"></tbody>
                </table>

                <!-- Líneas por pallet del producto (solo consultas Stock X Producto); lo maneja el JS. -->
                <div id="detalle-lineas-wrap" class="mt-3 d-none">
                    <h6 class="fw-bold small text-uppercase border-bottom pb-1 mb-2">
                        <i class="bi bi-list-ul me-1"></i> Líneas (detalle por pallet)
                    </h6>
                    <div id="detalle-lineas-estado" class="text-center text-muted small py-2">Cargando...</div>
                    <div class="table-responsive small" id="detalle-lineas-tabla-wrap" style="display:none;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center">Nro. Lote</th>
                                    <th class="text-center">F. Ingreso</th>
                                    <th class="text-center">F. Vencimiento</th>
                                    <th class="text-end">Días p/Vencer</th>
                                    <th class="text-center">Ubicación</th>
                                    <th class="text-center">Estado Pallet</th>
                                    <th class="text-center">Vencimiento</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-detalle-lineas"></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="7" class="text-end">Total</td>
                                    <td class="text-end" id="detalle-lineas-total"></td>
                                </tr>
                            </tfoot>
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
