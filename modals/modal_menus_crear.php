<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalMenuCrear" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Menú</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-menu" class="form-validado-estatico form-validar-instantaneo" action="controllers/menus_controller.php" novalidate>
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
                        <label class="form-label fw-bold small mb-1" for="input_nombre">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre"
                                   name="nombre"
                                   placeholder="Ej: Clientes"
                                   maxlength="<?php echo MENU_NOMBRE_MAX_LENGTH;?>"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
                    </div>

                    <!-- Estado -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1 d-block">Estado</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="input_estado"
                                   name="estado"
                                   value="1"
                                   checked>
                            <label class="form-check-label small" for="input_estado" id="label-estado">Activo</label>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_posicion">Posición</label>
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_posicion"
                                   disabled>
                            <button type="button"
                                    class="btn btn-primary"
                                    id="btn-asignar-posicion"
                                    disabled>
                                <i class="bi bi-sort-numeric-down me-1"></i> Asignar Posición
                            </button>
                        </div>
                        <input type="hidden" id="posicion" name="posicion">
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
                            id="btnGuardarMenu">
                        <i class="bi bi-save me-1"></i> Guardar Menú
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
