<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioPassword" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 400px; margin: 1.75rem auto;"> <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title" id="modalTitlePassword"><i class="bi bi-key-fill me-2"></i>Cambiar Password</h6>
                <button type="button" 
                        class="btn-close btn-close-white" 
                        data-bs-dismiss="modal" 
                        aria-label="Close">
                </button>
            </div>
            <form id="form-usuario-password" class="form-validado-estatico form-validar-instantaneo" action="controllers/usuarios_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_password"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id_usuario_password_editar"
                           name="id_usuario" >
                    
                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-password"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-usuario-pass">Nombre de Usuario</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-muted bg-light"
                                   id="input-usuario-pass"
                                   name="usuario"
                                   data-check='true'
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="password-editar">Contraseña</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password"
                                   class="form-control form-control-sm"
                                   id="password-editar"
                                   name="password"
                                   placeholder="Min: <?php echo PASS_MIN_LENGTH;?> - Max: <?php echo PASS_MAX_LENGTH;?>" 
                                   maxlength="<?php echo PASS_MAX_LENGTH;?>" 
                                   autocomplete="new-password"
                                   data-check='true'>
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordEdit">
                                <i class="bi bi-eye" id="iconEyeEdit"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-password-editar"></div>
                    </div>

                    <div class="mb-1"> <label class="form-label fw-bold small mb-1" for="confirm-password-editar">Repetir Contraseña</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password"
                                   class="form-control form-control-sm"
                                   id="confirm-password-editar"
                                   name="confirm_password"
                                   placeholder="Reingrese la contraseña"
                                   maxlength="<?php echo PASS_MAX_LENGTH;?>"
                                   data-comparar-con="password"
                                   autocomplete="new-password"
                                   data-check='true'>
                            <button type="button"
                                    class="btn btn-outline-secondary" 
                                    id="togglePasswordConfirmEdit">
                                <i class="bi bi-eye" id="iconEyeConfirmEdit"></i>
                            </button>  
                        </div>
                        <div class="invalid-feedback" id="error-confirm_password_editar"></div>
                    </div>

                    <div class="mb-2 d-flex justify-content-end">
                        <button type="button"
                                id="btn-generar-pass-editar"
                                class="btn btn-xs btn-link text-decoration-none p-0 small btn-generar-password-global"
                                style="font-size: 0.82rem;">
                            <i class="bi bi-robot"></i> Generar clave aleatoria
                        </button>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" 
                            class="btn btn-sm btn-secondary" 
                            data-bs-dismiss="modal">
                        Cerrar
                    </button>
                    <button type="submit"
                            class="btn btn-sm btn-primary"
                            id="btnEditarPassword">
                        <i class="bi bi-save me-1"></i> Cambiar Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>