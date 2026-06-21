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

                    <div id="modal-mensajes-posicion"></div>

                    <!-- Menú (opcional): lo usa solo Ítem Menús; oculto por defecto. Combobox
                         con búsqueda; al cambiarlo se recargan los ítems de ese menú. -->
                    <div class="mb-2 d-none" id="campo-menu-posicion">
                        <label class="form-label fw-bold small mb-1 d-flex align-items-center" for="input-menu-posicion">
                            <span>Menú</span>
                            <span class="badge bg-secondary ms-2 d-none" id="badge-items-menu-posicion"></span>
                            <span class="badge bg-secondary ms-1 d-none" id="badge-estado-menu-posicion"></span>
                        </label>
                        <div class="position-relative" id="combobox-menu-posicion">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="input-menu-posicion"
                                       placeholder="Busca un menú..."
                                       autocomplete="off">
                                <span class="input-group-text" id="btn-abrir-menus-posicion" style="cursor: pointer;" title="Ver menús">
                                    <i class="bi bi-chevron-down"></i>
                                </span>
                            </div>

                            <!-- Resultados de la búsqueda (los completa el JS) -->
                            <ul class="list-group position-absolute w-100 shadow-sm d-none"
                                id="lista-menus-posicion"
                                style="z-index: 1080; max-height: 220px; overflow-y: auto;">
                            </ul>
                        </div>
                    </div>

                    <p class="small text-muted mb-2" id="instruccion-posicion-uno">
                        <i class="bi bi-info-circle me-1"></i>Arrastra el registro <span class="badge bg-primary">resaltado</span> para asignar su posición.
                    </p>

                    <p class="small text-muted mb-2 d-none" id="instruccion-posicion-todos">
                        <i class="bi bi-info-circle me-1"></i>Arrastra cualquier registro para reordenar la lista completa.
                    </p>

                    <ul class="list-group" id="lista-posicion"></ul>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            id="btn-volver-posicion"
                            data-bs-dismiss="modal">
                        Volver
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-secondary d-none"
                            id="btn-cancelar-posicion"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-primary d-none"
                            id="btn-guardar-posicion">
                        <i class="bi bi-save me-1"></i> Guardar orden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
