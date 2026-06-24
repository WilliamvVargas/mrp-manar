<div class="modal fade" id="modalPresupuestoProductos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-box-seam me-2"></i>Productos de la familia &mdash; <span id="productos-familia-nombre" class="fw-bold"></span>
                </h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body py-2">
                <div id="productos-familia-mensaje"></div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tabla-productos-familia" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 30%">Código</th>
                                <th>Producto</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
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
