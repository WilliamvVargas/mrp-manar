<div class="modal fade" id="modalUsuarioVerPerfiles" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-person-badge me-2"></i>Ver accesos por perfil</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">

                <p class="small text-muted mb-2">Selecciona un perfil para ver sus accesos.</p>

                <!-- Combobox de perfil -->
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input_perfil_ver">Perfil</label>
                    <div class="position-relative" id="combobox-perfil-ver">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_perfil_ver"
                                   placeholder="Selecciona un perfil..."
                                   autocomplete="off">
                            <span class="input-group-text" id="btn-abrir-perfiles-ver" style="cursor: pointer;" title="Ver perfiles">
                                <i class="bi bi-chevron-down"></i>
                            </span>
                        </div>

                        <!-- Resultados de la búsqueda (se completan por JS) -->
                        <ul class="list-group position-absolute w-100 shadow-sm d-none"
                            id="lista-perfiles-ver"
                            style="z-index: 1090; max-height: 220px; overflow-y: auto;">
                        </ul>
                    </div>

                    <!-- Id del perfil elegido (informativo, lo completa el JS) -->
                    <input type="hidden" id="id_perfil_ver">
                </div>

                <!-- Accesos del perfil (cards de menús + ítems con acceso; lo completa el JS) -->
                <label class="form-label fw-bold small mb-1">Accesos</label>
                <div id="accordion-ver-perfiles"></div>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">
                    Volver
                </button>
            </div>
        </div>
    </div>
</div>
