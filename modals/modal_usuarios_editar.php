<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"> 
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title" id="modalTitleEdit"><i class="bi bi-pencil-fill me-2"></i>Editar Usuario</h6>
                <button type="button" 
                        class="btn-close btn-close-white" 
                        data-bs-dismiss="modal" 
                        aria-label="Close">
                </button>
            </div>
            <form id="form-usuario-editar" class="form-validado-estatico form-validar-instantaneo" action="controllers/usuarios_controller.php" novalidate>
                <div class="modal-body py-2"> 

                    <input type="hidden"
                           id="csrf_token_editar"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id_usuario_editar"
                           name="id_registro" >
                    
                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>
                    
                    <div class="mb-2"> <label class="form-label fw-bold small mb-1" for="usuario_editar">Usuario</label>
                        <div class="input-group input-group-sm"> <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="usuario_editar"
                                   name="usuario"
                                   placeholder="Ej: jperez"
                                   maxlength="<?php echo USER_MAX_LENGTH;?>"
                                   autocomplete="off"
                                   data-check='true'>     
                        </div>
                        <div class="invalid-feedback" id="error-edit-usuario"></div>
                    </div>

                    <div class="mb-2">
                        <label for="nombres_editar" class="form-label fw-bold small mb-1">Nombres</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               id="nombres_editar"
                               name="nombres"
                               placeholder="Ej: Juan Carlos"
                               maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>"
                               data-check='true'>
                        <div class="invalid-feedback" id="error-edit-nombres"></div>
                    </div>

                    <div class="mb-2">
                        <label for="apellidos_editar" class="form-label fw-bold small mb-1">Apellidos</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               id="apellidos_editar"
                               name="apellidos"
                               placeholder="Ej: Pérez Rossi"
                               maxlength="<?php echo NOMBRE_APELLIDO_MAX_LENGTH;?>"
                               data-check='true'>
                        <div class="invalid-feedback" id="error-edit-apellidos"></div>
                    </div>

                    <!-- Perfil (combobox con búsqueda) + botón "Ver Accesos" (informativo) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_perfil_editar">Perfil</label>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="position-relative flex-grow-1" id="combobox-perfil-editar">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="input_perfil_editar"
                                           placeholder="Selecciona un perfil..."
                                           autocomplete="off"
                                           maxlength="<?php echo PERFIL_NOMBRE_MAX_LENGTH;?>">
                                    <span class="input-group-text" id="btn-abrir-perfiles-editar" style="cursor: pointer;" title="Ver perfiles">
                                        <i class="bi bi-chevron-down"></i>
                                    </span>
                                </div>

                                <!-- Resultados de la búsqueda (se completan por JS) -->
                                <ul class="list-group position-absolute w-100 shadow-sm d-none"
                                    id="lista-perfiles-editar"
                                    style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                </ul>

                                <div class="invalid-feedback small" id="error-id_perfil-editar"></div>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn-ver-perfiles-editar">
                                <i class="bi bi-eye me-1"></i>Ver Accesos
                            </button>
                        </div>

                        <!-- Id real del perfil elegido (lo completa el JS al seleccionar/poblar) -->
                        <input type="hidden" id="id_perfil_editar" name="id_perfil">
                    </div>

                    <!-- Empresas (N-a-N). El JS puebla la lista con checkboxes; el radio con
                         estrella marca la empresa por defecto (la que se carga al iniciar sesión). -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1 d-block">
                            Empresas <span class="text-danger">*</span>
                            <span class="text-muted fw-normal small">— marca la <i class="bi bi-star-fill text-warning"></i> por defecto</span>
                        </label>
                        <ul class="list-group list-empresas-usuario" id="lista-empresas-usuario-editar"
                            style="max-height: 170px; overflow-y: auto;">
                            <li class="list-group-item small text-muted">Cargando empresas...</li>
                        </ul>
                        <div class="invalid-feedback small" id="error-empresas-editar"></div>
                    </div>

                    <!-- Estado -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1 d-block">Estado</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="input_estado_editar"
                                   name="estado"
                                   value="1"
                                   checked>
                            <label class="form-check-label small" for="input_estado_editar" id="label-estado-editar">Activo</label>
                        </div>
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
                            id="btnActualizar">
                        <i class="bi bi-save me-1"></i> Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>