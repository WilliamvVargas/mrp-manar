<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalPerfilAccesos" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Accesos</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body py-2">

                <!-- Id del perfil cuyos accesos se gestionan -->
                <input type="hidden" id="id-perfil-accesos">

                <!-- Mensajes del modal: arriba, por encima del campo Perfil -->
                <div id="accesos-mensajes"></div>

                <!-- Perfil cuyos accesos se gestionan (solo lectura) -->
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-perfil-accesos">Perfil</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input type="text"
                               class="form-control form-control-sm bg-light"
                               id="input-perfil-accesos"
                               disabled>
                    </div>
                </div>

                <!-- Acordeón de menús (una cabecera por menú); lo completa el JS -->
                <div class="accordion" id="accordion-accesos"></div>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
                <button type="button"
                        class="btn btn-sm btn-primary"
                        id="btnActualizarAccesos">
                    <i class="bi bi-save me-1"></i> Actualizar accesos
                </button>
            </div>
        </div>
    </div>
</div>
