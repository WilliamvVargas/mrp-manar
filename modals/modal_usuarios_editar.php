<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"> <div class="modal-content border-0 shadow-sm">
        <div class="modal-header bg-dark text-white py-2"> <h6 class="modal-title" id="modalTitleEdit"><i class="bi bi-pencil-fill me-2"></i>Editar Usuario</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
            <form id="form-usuario-editar" class="form-validado-estatico form-validar-instantaneo" action="controllers/usuarios_controller.php" novalidate>
                <div class="modal-body py-2"> <input type="hidden" name="csrf_token" id="csrf_token_editar" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id_registro" id="id_usuario_editar">
                    
                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>
                    
                    <div class="mb-2"> <label class="form-label fw-bold small mb-1" for="usuario_editar">Usuario</label>
                        <div class="input-group input-group-sm"> <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control form-control-sm" name="usuario" id="usuario_editar" placeholder="Ej: jperez" maxlength="<?php echo USER_MAX_LENGTH;?>" autocomplete="off" data-check='true' required>     
                        </div>
                        <div class="invalid-feedback" id="error-edit-usuario"></div>
                    </div>

                    <div class="row g-2 mb-2"> <div class="col-md-6">
                            <label for="nombres_editar" class="form-label fw-bold small mb-1">Nombres</label>
                            <input type="text" name="nombres" id="nombres_editar" class="form-control form-control-sm" placeholder="Ej: Juan Carlos" maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>" data-check='true'>
                            <div class="invalid-feedback" id="error-edit-nombres"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos_editar" class="form-label fw-bold small mb-1">Apellidos</label>
                            <input type="text" name="apellidos" id="apellidos_editar" class="form-control form-control-sm" placeholder="Ej: Pérez Rossi" maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>" data-check='true'>
                            <div class="invalid-feedback" id="error-edit-apellidos"></div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnActualizar">
                        <i class="bi bi-save me-1"></i> Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>