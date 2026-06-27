<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalItemMenuEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Ítem Menú</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-item-menu-editar" class="form-validado-estatico form-validar-instantaneo" action="controllers/item_menus_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_editar"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Id del ítem que se está editando -->
                    <input type="hidden" id="id_editar" name="id">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>

                    <!-- Fila superior: Nombre y Menú (izquierda) + vista previa del ícono (derecha) -->
                    <div class="row g-3 mb-3">

                        <!-- Columna izquierda: los dos primeros inputs -->
                        <div class="col-md-7">

                            <!-- Nombre -->
                            <div class="mb-2">
                                <label class="form-label fw-bold small mb-1" for="input_nombre_editar">Nombre <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-menu-app-fill"></i></span>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="input_nombre_editar"
                                           name="nombre"
                                           placeholder="Ej: Usuarios"
                                           maxlength="<?php echo ITEM_MENU_NOMBRE_MAX_LENGTH;?>"
                                           data-check='true'>
                                </div>
                                <div class="invalid-feedback small" id="error-nombre_editar"></div>
                            </div>

                            <!-- Menú padre (combobox con búsqueda) -->
                            <div class="mb-2">
                                <label class="form-label fw-bold small mb-1 d-flex align-items-center" for="input_menu_editar">
                                    <span>Menú <span class="text-danger">*</span></span>
                                    <!-- Cantidad de ítems + estado del menú seleccionado (los completa el JS) -->
                                    <span class="badge bg-secondary ms-2 d-none" id="badge-items-menu-editar"></span>
                                    <span class="badge bg-secondary ms-1 d-none" id="badge-estado-menu-editar"></span>
                                </label>

                                <div class="position-relative" id="combobox-menu-editar">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               id="input_menu_editar"
                                               placeholder="Selecciona un menú..."
                                               autocomplete="off"
                                               maxlength="<?php echo MENU_NOMBRE_MAX_LENGTH;?>">
                                        <span class="input-group-text" id="btn-abrir-menus-editar" style="cursor: pointer;" title="Ver menús">
                                            <i class="bi bi-chevron-down"></i>
                                        </span>
                                    </div>

                                    <!-- Resultados de la búsqueda (se completan por JS) -->
                                    <ul class="list-group position-absolute w-100 shadow-sm d-none"
                                        id="lista-menus-editar"
                                        style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                    </ul>

                                    <div class="invalid-feedback small" id="error-menu_id_editar"></div>
                                </div>

                                <!-- Id real del menú elegido (lo completa el JS al seleccionar) -->
                                <input type="hidden" id="menu_id_editar" name="menu_id">
                            </div>

                        </div>

                        <!-- Columna derecha: vista previa del ícono seleccionado -->
                        <div class="col-md-5 d-flex flex-column">
                            <label class="form-label fw-bold small mb-1 d-block">Vista Previa</label>
                            <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                <div id="preview-icono-item-editar"
                                     class="border rounded d-flex align-items-center justify-content-center bg-light"
                                     style="height: 7.5rem; aspect-ratio: 1 / 1;">
                                    <i class="bi bi-question-square text-muted" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Fila: Enlace (izquierda) + Ícono (derecha) -->
                    <div class="row g-3 mb-2">

                        <!-- Enlace / Ruta -->
                        <div class="col-md-7">
                            <label class="form-label fw-bold small mb-1" for="input_enlace_editar">Enlace <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="input_enlace_editar"
                                       name="enlace"
                                       placeholder="Ej: usuarios"
                                       maxlength="<?php echo ITEM_MENU_ENLACE_MAX_LENGTH;?>"
                                       data-comparar-con="nombre"
                                       data-check='true'>
                            </div>
                            <div class="invalid-feedback small" id="error-enlace_editar"></div>
                        </div>

                        <!-- Ícono (se elige desde los botones) -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold small mb-1" for="input_icono_editar">Ícono</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-grid-3x3-gap"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm bg-light"
                                       id="input_icono_editar"
                                       placeholder="Elige un ícono"
                                       disabled>
                            </div>

                            <!-- Id (FK) del ícono elegido del catálogo (lo completa el JS al seleccionar) -->
                            <input type="hidden" id="icono_id_editar" name="icono_id">
                            <div class="invalid-feedback small" id="error-icono_id_editar"></div>
                        </div>

                    </div>

                    <!-- Fila inferior: Estado + Posición (izquierda) + botones de íconos (derecha) -->
                    <div class="row g-3 mb-2">

                        <!-- Estado y Posición -->
                        <div class="col-md-7">

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

                            <!-- Posición -->
                            <div class="mb-2">
                                <label class="form-label fw-bold small mb-1" for="input_posicion_editar">Posición</label>
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="input_posicion_editar"
                                           placeholder="Al final"
                                           disabled>
                                    <button type="button"
                                            class="btn btn-primary"
                                            id="btn-asignar-posicion-editar"
                                            disabled>
                                        <i class="bi bi-sort-numeric-down me-1"></i> Asignar Posición
                                    </button>
                                </div>
                                <input type="hidden" id="posicion_editar" name="posicion">
                            </div>

                        </div>

                        <!-- Botones de íconos disponibles (10 por fila); los completa el JS -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold small mb-1 d-block">Íconos disponibles</label>
                            <div class="item-menu-iconos" id="lista-iconos-botones-editar"></div>
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
                            id="btnActualizarItemMenu">
                        <i class="bi bi-save me-1"></i> Actualizar Ítem Menú
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
