<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalEliminarLineaFp" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 400px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Registro</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-eliminar-linea-fp" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden" id="csrf_token_eliminar_linea_fp" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="fp-el-codigo" name="producto_codigo">
                    <input type="hidden" id="fp-el-semana" name="semana_inicio">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar-linea-fp"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Eliminar <span class="text-danger fw-bold">permanentemente</span> este registro del forecast personalizado?</p>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="fp-el-codigo-txt">Producto</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fp-el-codigo-txt" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="fp-el-nombre-txt">Descripción</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fp-el-nombre-txt" disabled>
                    </div>
                    <div class="row g-2">
                        <div class="col-7 mb-1">
                            <label class="form-label fw-bold small mb-1" for="fp-el-semana-txt">Semana</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="fp-el-semana-txt" disabled>
                        </div>
                        <div class="col-5 mb-1">
                            <label class="form-label fw-bold small mb-1" for="fp-el-demanda-txt">Demanda</label>
                            <input type="text" class="form-control form-control-sm text-end bg-light" id="fp-el-demanda-txt" disabled>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-danger" id="btnEliminarLineaFp">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
