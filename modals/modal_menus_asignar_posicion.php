<div class="modal fade" id="modalAsignarPosicion" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-sort-numeric-down me-2"></i>Asignar Posición</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-asignar-posicion" novalidate>
                <div class="modal-body py-2">

                    <p class="small text-muted mb-2">
                        <i class="bi bi-info-circle me-1"></i>Arrastra el <span class="badge bg-primary ms-auto">Nuevo</span> menú para asignar su posición.
                    </p>

                    <ul class="list-group" id="lista-posicion"></ul>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            data-bs-dismiss="modal">
                        Volver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
