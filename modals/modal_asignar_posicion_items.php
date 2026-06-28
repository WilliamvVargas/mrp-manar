<div class="modal fade" id="modalAsignarPosicionItems" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-sort-numeric-down me-2"></i>Asignar Posición de Ítems</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">

                <div id="modal-mensajes-posicion-items"></div>

                <!-- Menú con el que se está trabajando (solo lectura) -->
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-menu-items">Menú</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                        <input type="text"
                               class="form-control form-control-sm bg-light"
                               id="input-menu-items"
                               disabled>
                    </div>
                </div>

                <p class="small text-muted mb-2 d-none" id="instruccion-posicion-uno-items">
                    <i class="bi bi-info-circle me-1"></i>Arrastra el registro <span class="badge bg-primary">resaltado</span> para asignar su posición.
                </p>

                <p class="small text-muted mb-2" id="instruccion-posicion-todos-items">
                    <i class="bi bi-info-circle me-1"></i>Arrastra cualquier registro para reordenar la lista completa.
                </p>

                <ul class="list-group" id="lista-posicion-items"></ul>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button"
                        class="btn btn-sm btn-secondary d-none"
                        id="btn-volver-posicion-items"
                        data-bs-dismiss="modal">
                    Volver
                </button>
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        id="btn-cancelar-posicion-items"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button"
                        class="btn btn-sm btn-primary"
                        id="btn-guardar-posicion-items">
                    <i class="bi bi-save me-1"></i> Guardar orden
                </button>
            </div>
        </div>
    </div>
</div>
