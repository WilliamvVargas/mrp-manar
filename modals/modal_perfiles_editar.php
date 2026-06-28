<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalPerfilEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Perfil</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-perfil-editar" class="form-validado-estatico form-validar-instantaneo" action="controllers/perfiles_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_editar"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Id del perfil que se está editando (lo usa la validación de unicidad) -->
                    <input type="hidden" id="id_perfil_editar" name="id_registro">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_nombre_editar">Nombre <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre_editar"
                                   name="nombre"
                                   placeholder="Ej: Administrador"
                                   maxlength="<?php echo PERFIL_NOMBRE_MAX_LENGTH;?>"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre_editar"></div>
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
                            id="btnActualizarPerfil">
                        <i class="bi bi-save me-1"></i> Actualizar Perfil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
