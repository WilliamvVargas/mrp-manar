<div class="modal fade" id="modalVerPerfilUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-person-badge me-2"></i>Perfil del usuario</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">

                <p class="small text-muted mb-2">Perfil asignado al usuario y sus accesos.</p>

                <!-- Perfil del usuario (solo lectura) -->
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="perfil-usuario-nombre">Perfil</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input type="text"
                               class="form-control form-control-sm"
                               id="perfil-usuario-nombre"
                               readonly>
                    </div>
                </div>

                <!-- Accesos del perfil (cards de menús + ítems con acceso; lo completa el JS) -->
                <label class="form-label fw-bold small mb-1">Accesos</label>
                <div id="cards-perfil-usuario"></div>

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
