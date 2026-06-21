<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalIconoEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Icono</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-icono-editar"
                  class="form-validado-estatico form-validar-instantaneo"
                  action="controllers/iconos_controller.php"
                  novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token_editar_icono"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <input type="hidden"
                           id="id_icono_editar"
                           name="id_registro">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>

                    <!-- Vista previa del icono (se completa por JS) -->
                    <div class="d-flex justify-content-center mb-3">
                        <div id="preview-icono-editar"
                             class="border rounded d-flex align-items-center justify-content-center bg-light"
                             style="width: 25%; aspect-ratio: 1 / 1;">
                        </div>
                    </div>

                    <!-- Tipo (solo lectura) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_tipo_editar">Tipo</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-collection"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input_tipo_editar"
                                   disabled>
                        </div>
                    </div>

                    <!-- Valor (solo lectura) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_valor_editar">Valor</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input_valor_editar"
                                   disabled>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_nombre_editar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre_editar"
                                   name="nombre"
                                   placeholder="Ej: Casa"
                                   maxlength="60"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre-editar"></div>
                    </div>

                    <!-- Posición: se elige en la grilla de Asignar Posición -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_posicion_editar">Posición</label>
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input_posicion_editar"
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
                <div class="modal-footer bg-light py-2">
                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            data-bs-dismiss="modal">
                        Cerrar
                    </button>
                    <button type="submit"
                            class="btn btn-sm btn-primary"
                            id="btnActualizarIcono">
                        <i class="bi bi-save me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
