<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalItemMenuEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm-custom" style="max-width: 400px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Ítem Menú</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-item-menu-eliminar" class="form-validado-estatico" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_eliminar_item"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id-item-eliminar"
                           name="id">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Estás seguro de que deseas eliminar permanentemente este ítem menú?</p>

                    <!-- Vista previa del ícono del ítem (la completa el JS) -->
                    <div class="d-flex justify-content-center mb-3">
                        <div id="preview-icono-item-eliminar"
                             class="border rounded d-flex align-items-center justify-content-center bg-light"
                             style="width: 25%; aspect-ratio: 1 / 1;">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-nombre-item-eliminar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-menu-app-fill"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-danger bg-light"
                                   id="input-nombre-item-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-enlace-item-eliminar">Enlace</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input-enlace-item-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-menu-item-eliminar">Menú</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-segmented-nav"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input-menu-item-eliminar"
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
                            id="btnEliminarItemMenu">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
