<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalMenuEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm-custom" style="max-width: 400px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Menú</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-menu-eliminar" class="form-validado-estatico" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_eliminar_menu"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id_menu_eliminar"
                           name="id_menu">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Estás seguro de que deseas <span class="text-danger fw-bold">eliminar permanentemente</span> este menú?</p>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-nombre-eliminar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-danger bg-light"
                                   id="input-nombre-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-items-eliminar">N° de Ítem Menús</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-menu-app-fill"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input-items-eliminar"
                                   disabled>
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
                            class="btn btn-sm btn-danger"
                            id="btnEliminarMenu">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
