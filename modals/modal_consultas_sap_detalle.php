<div class="modal fade" id="modalConsultaSapDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
