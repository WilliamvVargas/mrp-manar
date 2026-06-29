<div class="modal fade" id="modalPerfilUsuarios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-people me-2"></i>Usuarios del perfil</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">

                <!-- Perfil (solo lectura) -->
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-perfil-usuarios">Perfil</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input type="text"
                               class="form-control form-control-sm bg-light"
                               id="input-perfil-usuarios"
                               readonly>
                    </div>
                </div>

                <!-- Listado de usuarios del perfil (usuario + estado; lo completa el JS) -->
                <label class="form-label fw-bold small mb-1">Usuarios</label>
                <div id="lista-usuarios-perfil"></div>

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
