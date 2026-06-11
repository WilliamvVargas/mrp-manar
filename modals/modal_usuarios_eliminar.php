<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm-custom" style="max-width: 400px; margin: 1.75rem auto;"> <div class="modal-content border-0 shadow-sm">
        <div class="modal-header bg-danger text-white py-2"> <h6 class="modal-title" id="modalTitleEliminar"><i class="bi bi-trash-fill me-2"></i>Eliminar Usuario</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="form-usuario-eliminar" class="form-validado-estatico" novalidate>
            <div class="modal-body py-2"> <input type="hidden" name="csrf_token" id="csrf_token_elimnar" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id_usuario" id="id_usuario_eliminar">
                
                <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                    <div id="modal-mensajes-eliminar"></div>
                </div>

                <p class="small text-muted mb-2">¿Estás seguro de que deseas eliminar permanentemente este usuario?</p>

                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-usuario-eliminar">Nombre de Usuario</label>
                    <div class="input-group input-group-sm"> <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control form-control-sm fw-bold text-danger bg-light" name="usuario" id="input-usuario-eliminar" disabled>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light py-2"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-sm btn-danger" id="btnEliminar">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
            </div>
        </form>
        </div>
    </div>
</div>