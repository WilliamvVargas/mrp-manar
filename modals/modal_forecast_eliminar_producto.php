<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalEliminarProductoFp" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 420px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar Forecast del Producto</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-eliminar-producto-fp" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden" id="csrf_token_eliminar_producto_fp" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="fp-ep-codigo" name="producto_codigo">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar-producto-fp"></div>
                    </div>

                    <p class="small text-muted mb-2">¿Eliminar <span class="text-danger fw-bold">permanentemente</span> TODO el forecast personalizado de este producto (todas sus semanas)?</p>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="fp-ep-codigo-txt">Producto</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fp-ep-codigo-txt" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="fp-ep-nombre-txt">Descripción</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fp-ep-nombre-txt" disabled>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-bold small mb-1" for="fp-ep-cantidad-txt">Cantidad Registros</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fp-ep-cantidad-txt" disabled>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-danger" id="btnEliminarProductoFp">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
