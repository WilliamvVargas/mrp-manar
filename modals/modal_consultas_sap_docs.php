<div class="modal fade" id="modalConsultaSapDocs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-files me-2"></i>Documentos del artículo
                    <span id="docs-titulo" class="fw-normal"></span>
                </h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">
                <!-- Resumen: misma información y formato que el DataTable de la v3; lo completa el JS. -->
                <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-collection me-1"></i>Resumen</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-striped align-middle mb-0 small" id="tabla-resumen-docs-sap">
                        <thead class="table-dark"></thead>
                        <tbody></tbody>
                    </table>
                </div>

                <h6 class="fw-bold small text-muted mb-2"><i class="bi bi-files me-1"></i>Documentos</h6>
                <div class="table-responsive">
                    <!-- Encabezado fijo; el cuerpo lo completa el JS. -->
                    <table class="table table-sm table-striped align-middle mb-0 small">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">DocEntry (SAP)</th>
                                <th class="text-center">Tipo Doc.</th>
                                <th class="text-center">N° Doc.</th>
                                <th class="text-center">Fecha Documento</th>
                                <th>Cliente</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Total Neto</th>
                                <th class="text-end">IVA ($)</th>
                                <th class="text-end">Total Bruto</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-docs-consulta-sap"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="5" class="text-end">Total</td>
                                <td class="text-end" id="docs-total-cant"></td>
                                <td class="text-end" id="docs-total-neto"></td>
                                <td class="text-end" id="docs-total-iva"></td>
                                <td class="text-end" id="docs-total-bruto"></td>
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
