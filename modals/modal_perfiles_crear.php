<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalPerfilCrear" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Perfil</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-perfil" class="form-validado-estatico form-validar-instantaneo" action="controllers/perfiles_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_nombre">Nombre <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre"
                                   name="nombre"
                                   placeholder="Ej: Administrador"
                                   maxlength="<?php echo PERFIL_NOMBRE_MAX_LENGTH;?>"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
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
                            id="btnGuardarPerfil">
                        <i class="bi bi-save me-1"></i> Guardar Perfil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
