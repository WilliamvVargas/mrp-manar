<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalEmpresaCrear" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-building-add me-2"></i>Nueva Empresa</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <!-- enctype multipart: el formulario incluye un archivo (logo). -->
            <form id="form-empresa" class="form-validado-estatico form-validar-instantaneo" action="controllers/empresas_controller.php" enctype="multipart/form-data" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>

                    <!-- Nombre (máx 100) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_nombre">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre"
                                   name="nombre"
                                   placeholder="Ej: Manar SpA"
                                   maxlength="<?php echo EMPRESA_NOMBRE_MAX_LENGTH; ?>"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
                    </div>

                    <!-- Empresa WMS (obligatoria; se puebla desde el maestro dbo.EMPRESA del WMS) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="empresa_wms">Empresa WMS <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hdd-stack"></i></span>
                            <select class="form-select form-select-sm" id="empresa_wms" name="empresa_wms" placeholder="Selecciona una empresa...">
                            </select>
                        </div>
                        <div class="form-text small">Empresa correspondiente en el WMS.</div>
                        <div class="invalid-feedback small" id="error-empresa_wms"></div>
                    </div>

                    <!-- Logo (imagen) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_logo">Logo</label>
                        <input type="file"
                               class="form-control form-control-sm"
                               id="input_logo"
                               name="logo"
                               accept="image/png, image/jpeg, image/webp">
                        <div class="form-text small">Formatos: PNG, JPG o WEBP (máx <?php echo EMPRESA_LOGO_MAX_PESO_MB; ?> MB).</div>
                        <div class="invalid-feedback small" id="error-logo"></div>

                        <!-- Previsualización del logo seleccionado -->
                        <div class="mt-2 text-center d-none" id="logo-preview-wrap">
                            <img id="logo-preview" src="" alt="Vista previa del logo"
                                 class="img-thumbnail" style="max-height: 120px;">
                        </div>
                    </div>

                    <!-- Posición (mismo widget que el mantenedor de Menús) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_posicion">Posición</label>
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_posicion"
                                   placeholder="Al final"
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
                            id="btnGuardar">
                        <i class="bi bi-save me-1"></i> Guardar Empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
