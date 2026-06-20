<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalIconoEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 420px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Icono</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-icono-eliminar" class="form-validado-estatico" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_eliminar_icono"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id_icono_eliminar"
                           name="id_registro">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Estás seguro de que deseas eliminar permanentemente este icono?</p>

                    <div class="d-flex justify-content-center mb-3">
                        <div id="preview-icono-eliminar"
                             class="border rounded d-flex align-items-center justify-content-center bg-light"
                             style="width: 25%; aspect-ratio: 1 / 1;">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-nombre-eliminar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm fw-bold text-danger bg-light"
                                   id="input-nombre-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <!-- Tipo (solo lectura) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-tipo-eliminar">Tipo</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-collection"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input-tipo-eliminar"
                                   disabled>
                        </div>
                    </div>

                    <!-- Valor (solo lectura) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input-valor-eliminar">Valor</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input-valor-eliminar"
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
                            id="btnEliminarIcono">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
