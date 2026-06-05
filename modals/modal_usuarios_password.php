<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioPassword" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitlePassword"><i class="bi bi-key-fill me-2"></i>Cambiar Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-usuario-password" class="form-validado-estatico" novalidate>
                <div class="modal-body">
                    
                    <input type="hidden" name="csrf_token" id="csrf_token_password" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id_usuario" id="id_usuario_password_editar">
                    
                    <div class="mensaje-wrapper" style="min-height: 10px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-password"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="input-usuario-editar">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="usuario" id="input-usuario-pass" disabled>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="password-editar">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control " name="password" id="password-editar" placeholder="Mínimo <?php echo PASS_MIN_LENGTH;?> caracteres y máximo de <?php echo PASS_MAX_LENGTH;?> caracteres" maxlength="<?php echo PASS_MAX_LENGTH;?>" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordEdit">
                                <i class="bi bi-eye" id="iconEyeEdit"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-password-editar"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="confirm_password_editar">Repetir Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control" name="confirm-password" id="confirm-password-editar" placeholder="Reingrese la contraseña" maxlength="<?php echo PASS_MAX_LENGTH;?>" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirmEdit">
                                <i class="bi bi-eye" id="iconEyeConfirmEdit"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-confirm_password_editar"></div>
                    </div>

                    <div class="mb-3 d-flex justify-content-end">
                        <button class="btn btn-primary" type="button" id="btn-generar-pass-editar">
                            <i class="bi bi-robot"></i> Generar Contraseña Aleatoria
                        </button>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnEditarPassword">
                        <i class="bi bi-save me-1"></i> Cambiar Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>