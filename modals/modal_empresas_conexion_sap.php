<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalEmpresaConexionSap" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-hdd-network me-2"></i>Conexión SAP</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-empresa-conexion" class="form-validado-estatico" action="controllers/empresas_controller.php" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="id_empresa_conexion" name="id_empresa">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-conexion"></div>
                    </div>

                    <!-- Empresa (solo lectura) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="conexion-empresa-nombre">Empresa</label>
                        <input type="text"
                               class="form-control form-control-sm bg-light"
                               id="conexion-empresa-nombre"
                               readonly>
                    </div>

                    <!-- Servidor / Host -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="conexion_servidor">Servidor / Host</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="conexion_servidor"
                                   name="servidor"
                                   placeholder="Ej: 192.168.1.20  o  SRVSAP\SQLEXPRESS"
                                   maxlength="100">
                        </div>
                        <div class="invalid-feedback small" id="error-servidor"></div>
                    </div>

                    <!-- Base de datos -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="conexion_base">Base de datos</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-database"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="conexion_base"
                                   name="base_datos"
                                   placeholder="Ej: CLPRDMANAR"
                                   maxlength="100">
                        </div>
                        <div class="invalid-feedback small" id="error-base_datos"></div>
                    </div>

                    <!-- Usuario (login de la base; NO la contraseña) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="conexion_usuario">Usuario</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="conexion_usuario"
                                   name="usuario"
                                   placeholder="Ej: app_manar"
                                   maxlength="100"
                                   autocomplete="off">
                        </div>
                        <div class="invalid-feedback small" id="error-usuario"></div>
                    </div>

                    <!-- Aviso de seguridad: la contraseña NO se guarda aquí -->
                    <div class="alert alert-warning small py-2 mb-0" role="alert">
                        <i class="bi bi-shield-lock me-1"></i>
                        Por seguridad, la <b>contraseña</b> no se guarda aquí — se mantiene en la
                        configuración del servidor, fuera de la base de datos.
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
                            id="btnGuardarConexion">
                        <i class="bi bi-save me-1"></i> Guardar Conexión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
