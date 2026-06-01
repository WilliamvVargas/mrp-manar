<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalEditTitle"><i class="bi bi-pencil-square me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-usuario-editar" novalidate>
                <div class="modal-body">
                    
                    <input type="hidden" name="csrf_token" id="csrf_token_editar" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id_usuario" id="id_usuario_editar">
                    
                    <div class="mensaje-wrapper" style="min-height: 10px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="usuario_editar">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="usuario" id="usuario_editar" placeholder="Ej: jperez" maxlength="<?php echo USER_MAX_LENGTH;?>" autocomplete="off" required>
                            <div class="invalid-feedback" id="error-edit-usuario"></div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnActualizar">
                        <i class="bi bi-save me-1"></i> Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
