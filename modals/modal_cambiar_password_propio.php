<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalCambiarPasswordPropio" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 400px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-key-fill me-2"></i>Cambiar Contraseña</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-password-propio" class="form-validado-estatico form-validar-instantaneo" action="controllers/usuarios_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_password_propio"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-password-propio"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-usuario-propio">Nombre de Usuario</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-muted bg-light"
                                   id="input-usuario-propio"
                                   name="usuario"
                                   value="<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? ''); ?>"
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="password-propio">Contraseña</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password"
                                   class="form-control form-control-sm"
                                   id="password-propio"
                                   name="password"
                                   placeholder="Min: <?php echo PASS_MIN_LENGTH;?> - Max: <?php echo PASS_MAX_LENGTH;?>"
                                   maxlength="<?php echo PASS_MAX_LENGTH;?>"
                                   autocomplete="new-password"
                                   data-check='true'>
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordPropio">
                                <i class="bi bi-eye" id="iconEyePropio"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="error-password-propio"></div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-bold small mb-1" for="confirm-password-propio">Repetir Contraseña</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password"
                                   class="form-control form-control-sm"
                                   id="confirm-password-propio"
                                   name="confirm_password"
                                   placeholder="Reingrese la contraseña"
                                   maxlength="<?php echo PASS_MAX_LENGTH;?>"
                                   data-comparar-con="password"
                                   autocomplete="new-password"
                                   data-check='true'>
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="togglePasswordConfirmPropio">
                                <i class="bi bi-eye" id="iconEyeConfirmPropio"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="error-confirm_password_propio"></div>
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
                            id="btn-guardar-password-propio">
                        <i class="bi bi-save me-1"></i> Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
