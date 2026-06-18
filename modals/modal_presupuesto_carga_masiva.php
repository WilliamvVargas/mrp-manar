<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalCargaMasivaPresupuesto" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title" id="modalTitlePresupuesto"><i class="bi bi-graph-up me-2"></i>Carga Masiva Presupuesto</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>

            <div class="modal-body py-2">

                <!-- Formulario de carga del archivo -->
                <form id="form-carga-masiva-presupuesto" class="form-validado-estatico" action="controllers/presupuesto_controller.php" novalidate>

                    <input type="hidden"
                           id="csrf_token_presupuesto"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-presupuesto"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="archivo-excel-presupuesto">Archivo Excel</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <input type="file"
                                           class="form-control form-control-sm"
                                           id="archivo-excel-presupuesto"
                                           name="archivo"
                                           accept=".xlsx">
                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="btnCargaMasivaPresupuesto">
                                        <i class="bi bi-save me-1"></i> Subir Registros
                                    </button>
                                </div>
                                <div class="form-text small">Formato permitido: .xlsx</div>
                                <div class="invalid-feedback small" id="error-archivo-presupuesto"></div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Consulta principal (listado de presupuesto) -->
                <div class="table-responsive">

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="consulta-presupuesto">Consulta</label>
                        <div class="col-md-4 px-0">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="consulta-presupuesto"
                                       name="consulta-presupuesto"
                                       placeholder="Ej: Canal, Familia, Sub-Familia...">
                            </div>
                        </div>
                    </div>

                    <table class="table table-hover align-middle" id="tabla-consulta-presupuesto" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 4%">ID</th>
                                <th style="width: 6%">Año</th>
                                <th style="width: 5%">Mes</th>
                                <th style="width: 11%">Canal</th>
                                <th style="width: 11%">Sub-Canal</th>
                                <th style="width: 11%">Familia</th>
                                <th style="width: 11%">Sub-Familia</th>
                                <th style="width: 9%" class="text-end">Venta</th>
                                <th style="width: 7%" class="text-end">MG %</th>
                                <th style="width: 9%" class="text-end">MG Neto</th>
                                <th style="width: 8%" class="text-end">PP</th>
                                <th style="width: 8%" class="text-end">KG</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
