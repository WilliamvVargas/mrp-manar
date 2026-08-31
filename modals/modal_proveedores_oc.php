<div class="modal fade" id="modalProveedorOc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-receipt me-2"></i>Detalle de OC
                    <span id="prov-oc-titulo" class="fw-normal"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">

                <div id="prov-oc-estado" class="text-center text-muted py-3">Cargando...</div>

                <div class="table-responsive small" id="prov-oc-wrap" style="display:none;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">N° OC</th>
                                <th class="text-center">Fecha Creación</th>
                                <th class="text-center">Fecha Recepción</th>
                                <th class="text-end">Días</th>
                                <th class="text-center">Vía</th>
                                <th>Cód. Artículo</th>
                                <th>Artículo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-center">N° Entrada</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-prov-oc"></tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer bg-light py-2">
                <span class="me-auto small text-muted" id="prov-oc-resumen"></span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
