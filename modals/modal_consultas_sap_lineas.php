<div class="modal fade" id="modalConsultaSapLineas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-list-ul me-2"></i>Líneas del documento
                    <span id="lineas-doc-titulo" class="fw-normal"></span>
                </h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">
                <div class="table-responsive">
                    <!-- Encabezado fijo; el cuerpo lo completa el JS. -->
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">Línea</th>
                                <th>Cód. Artículo</th>
                                <th>Artículo</th>
                                <th class="text-center">Unidad</th>
                                <th class="text-center">Bodega</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">% Desc.</th>
                                <th class="text-end">% IVA</th>
                                <th class="text-end">Total Línea</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-lineas-consulta-sap"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="9" class="text-end">Total líneas</td>
                                <td class="text-end" id="lineas-total-suma"></td>
                            </tr>
                        </tfoot>
                    </table>
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
