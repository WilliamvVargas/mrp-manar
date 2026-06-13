<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioCrear" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2"> <h6 class="modal-title" id="modalTitleCreate"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-usuario" class="form-validado-estatico form-validar-instantaneo" action="controllers/usuarios_controller.php" novalidate>
                <div class="modal-body py-2"> <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>
                    
                    <div class="mb-2"> <label class="form-label fw-bold small mb-1" for="input_usuario">Usuario</label>
                        <div class="input-group input-group-sm"> <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control form-control-sm" name="usuario" id="input_usuario" placeholder="Ej: jperez" maxlength="<?php echo USER_MAX_LENGTH;?>" data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-usuario"></div>
                    </div>

                    <div class="row g-2 mb-2"> 
                        <div class="col-md-6">
                            <label for="nombres" class="form-label fw-bold small mb-1">Nombres</label>
                            <input type="text" name="nombres" id="nombres" class="form-control form-control-sm" placeholder="Ej: Juan Carlos" maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>" data-check='true'>
                            <div class="invalid-feedback small" id="error-nombres"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos" class="form-label fw-bold small mb-1">Apellidos</label>
                            <input type="text" name="apellidos" id="apellidos" class="form-control form-control-sm" placeholder="Ej: Pérez Rossi" maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>" data-check='true'>
                            <div class="invalid-feedback small" id="error-apellidos"></div>
                        </div>
                    </div>

                    <div class="row g-2 mb-1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small mb-1" for="password">Contraseña</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" 
                                       class="form-control form-control-sm" 
                                       name="password" 
                                       id="password" 
                                       placeholder="Min: <?php echo PASS_MIN_LENGTH;?> - Max: <?php echo PASS_MAX_LENGTH;?>" 
                                       maxlength="<?php echo PASS_MAX_LENGTH;?>" 
                                       autocomplete="new-password"
                                       data-check='true'>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="iconEye"></i>
                                </button>  
                            </div>
                            <div class="invalid-feedback small" id="error-password"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small mb-1" for="confirm_password">Repetir Contraseña</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" 
                                       class="form-control form-control-sm" 
                                       name="confirm_password" 
                                       id="confirm_password" 
                                       placeholder="Reingrese contraseña" 
                                       maxlength="<?php echo PASS_MAX_LENGTH;?>"
                                       data-comparar-con="password"
                                       autocomplete="new-password"
                                       data-check='true'>
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                    <i class="bi bi-eye" id="iconEyeConfirm"></i>
                                </button>  
                            </div>
                            <div class="invalid-feedback small" id="error-confirm_password"></div>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-end">
                        <button class="btn btn-xs btn-link text-decoration-none p-0 small btn-generar-password-global" type="button" id="btn-generar-pass" style="font-size: 0.82rem;">
                            <i class="bi bi-robot"></i> Generar clave aleatoria
                        </button>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardar">
                        <i class="bi bi-save me-1"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>