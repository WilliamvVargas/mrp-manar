<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalEmpresaEditar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Empresa</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-empresa-editar" class="form-validado-estatico form-validar-instantaneo" action="controllers/empresas_controller.php" enctype="multipart/form-data" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="id_empresa_editar" name="id_registro">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-editar"></div>
                    </div>

                    <!-- Nombre (máx 50) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="nombre_editar">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="nombre_editar"
                                   name="nombre"
                                   placeholder="Ej: Manar SpA"
                                   maxlength="<?php echo EMPRESA_NOMBRE_MAX_LENGTH; ?>"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
                    </div>

                    <!-- Empresa WMS (obligatoria; se puebla desde el maestro dbo.EMPRESA del WMS) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="empresa_wms_editar">Empresa WMS <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hdd-stack"></i></span>
                            <select class="form-select form-select-sm" id="empresa_wms_editar" name="empresa_wms" placeholder="Selecciona una empresa...">
                            </select>
                        </div>
                        <div class="form-text small">Empresa correspondiente en el WMS.</div>
                        <div class="invalid-feedback small" id="error-empresa_wms"></div>
                    </div>

                    <!-- Logo actual -->
                    <div class="mb-2 text-center">
                        <label class="form-label fw-bold small mb-1 d-block">Logo actual</label>
                        <img id="logo-actual-editar" src="" alt="Logo actual" class="img-thumbnail d-none" style="max-height: 100px;">
                        <div id="logo-actual-vacio" class="text-muted small d-none">Sin logo</div>
                    </div>

                    <!-- Cambiar logo (opcional) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_logo_editar">Cambiar logo (opcional)</label>
                        <input type="file"
                               class="form-control form-control-sm"
                               id="input_logo_editar"
                               name="logo"
                               accept="image/png, image/jpeg, image/webp">
                        <div class="form-text small">Déjalo vacío para conservar el actual. PNG/JPG/WEBP (máx <?php echo EMPRESA_LOGO_MAX_PESO_MB; ?> MB).</div>
                        <div class="invalid-feedback small" id="error-logo"></div>

                        <!-- Previsualización del logo nuevo -->
                        <div class="mt-2 text-center d-none" id="logo-nuevo-wrap">
                            <img id="logo-nuevo-editar" src="" alt="Nuevo logo" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                    </div>

                    <!-- Posición (mismo widget que el mantenedor de Menús) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_posicion_editar">Posición</label>
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_posicion_editar"
                                   placeholder="Sin cambios"
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
                            id="btnActualizar">
                        <i class="bi bi-save me-1"></i> Actualizar Empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
