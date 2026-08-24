<?php
    // Versiones de presupuesto de la empresa activa, para el selector del modal. La empresa
    // activa la resuelve el navbar ($empresaActivaId), que se incluye antes que este modal.
    // El forecast SOLO puede correr sobre una empresa + versión concretas (no mezcla el resto).
    require_once __DIR__ . '/../models/presupuesto_model.php';
    $empresaExplosionId  = $empresaActivaId ?? ($_SESSION['empresa_id'] ?? '');
    $empresaExplosionNom = $empresaActiva['nombre'] ?? '';
    $versionesExplosion  = [];
    if ($empresaExplosionId !== '') {
        try {
            $versionesExplosion = (new Presupuesto($pdo, $empresaExplosionId))->versionesDisponibles();
        } catch (Throwable $e) {
            $versionesExplosion = [];
        }
    }
?>
<div class="modal fade" id="modalExplosionForecast" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Explosión de Forecast</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body py-3">

                <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                    <div id="modal-mensajes-explosion"></div>
                </div>

                <p class="small text-muted mb-3">
                    Ejecuta el pipeline que pronostica la demanda por grupo (Prophet) y la reparte a
                    productos. <strong>Recalcula</strong> la tabla de forecast por producto usando el
                    presupuesto de la <strong>empresa y versión</strong> seleccionadas.
                </p>

                <!-- Empresa activa + versión del presupuesto a usar -->
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small mb-1">Empresa activa</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?php echo htmlspecialchars($empresaExplosionNom !== '' ? $empresaExplosionNom : '(sin empresa activa)'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small mb-1" for="explosion-version">Versión del presupuesto</label>
                        <select class="form-select form-select-sm" id="explosion-version"
                                <?php echo empty($versionesExplosion) ? 'disabled' : ''; ?>>
                            <?php if (empty($versionesExplosion)): ?>
                                <option value="">(sin versiones)</option>
                            <?php else: ?>
                                <?php foreach ($versionesExplosion as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <?php if (empty($versionesExplosion)): ?>
                    <div class="alert alert-warning small py-2 mb-3">
                        La empresa activa no tiene presupuestos cargados. Carga un presupuesto antes de
                        ejecutar la explosión de forecast.
                    </div>
                <?php endif; ?>

                <!-- Lista de pasos; el orden y la cantidad deben coincidir con el controlador. -->
                <ul class="list-group" id="explosion-pasos">
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="0">
                        <span><span class="badge bg-secondary me-2">1</span>Exportar series por grupo (SAP + presupuesto)</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="1">
                        <span><span class="badge bg-secondary me-2">2</span>Entrenar Prophet</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="2">
                        <span><span class="badge bg-secondary me-2">3</span>Fabricar forecast por producto</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="3">
                        <span><span class="badge bg-secondary me-2">4</span>Exportar datos del backtest</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="4">
                        <span><span class="badge bg-secondary me-2">5</span>Prophet backtest (validación)</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between" data-paso="5">
                        <span><span class="badge bg-secondary me-2">6</span>Calcular factor y demanda corregida</span>
                        <span class="paso-estado"><i class="bi bi-dash-circle text-muted"></i></span>
                    </li>
                </ul>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-explosion-ejecutar"
                        <?php echo empty($versionesExplosion) ? 'disabled' : ''; ?>>
                    <i class="bi bi-play-fill"></i> Ejecutar
                </button>
            </div>
        </div>
    </div>
</div>
