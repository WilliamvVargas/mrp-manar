<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioCrear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-usuario">
                <div class="modal-body">
                    
                    <input type="hidden" name="id" id="usuario_id">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mensaje-wrapper" style="min-height: 10px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="usuario" id="input_usuario" placeholder="Ej: jperez" maxlength="<?php echo USER_MAX_LENGTH;?>">
                            <div class="invalid-feedback" id="error-usuario"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Mínimo <?php echo PASS_MIN_LENGTH;?> caracteres y máximo de <?php echo PASS_MAX_LENGTH;?> caracteres" maxlength="<?php echo PASS_MAX_LENGTH;?>" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye" id="iconEye"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-password"></div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label fw-bold">Repetir Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Reingrese la contraseña" maxlength="<?php echo PASS_MAX_LENGTH;?>" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                <i class="bi bi-eye" id="iconEyeConfirm"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-confirm_password"></div>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">
                        <i class="bi bi-save me-1"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>