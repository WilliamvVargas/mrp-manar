<div class="modal fade" id="modalIconoVistaPrevia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2 border-0">
                <h6 class="modal-title"><i class="bi bi-eye me-2"></i>Vista Previa</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <div class="modal-body d-flex align-items-center justify-content-center p-4"
                 id="vista-previa-icono"
                 style="min-height: 340px;">
            </div>

            <div class="px-4 pt-2">
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-nombre-preview">Nombre</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-tag"></i></span>
                        <input type="text" class="form-control form-control-sm bg-light" id="input-nombre-preview" disabled>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-valor-preview">Valor</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-hash"></i></span>
                        <input type="text" class="form-control form-control-sm bg-light" id="input-valor-preview" disabled>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small mb-1" for="input-tipo-preview">Tipo</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-collection"></i></span>
                        <input type="text" class="form-control form-control-sm bg-light" id="input-tipo-preview" disabled>
                    </div>
                </div>
            </div>

            <div class="px-4 pb-2" id="contenedor-color-preview">
                <label class="form-label fw-bold small mb-1" for="input-color-preview">Icono Color</label>
                <input type="color"
                       class="form-control form-control-color form-control-sm"
                       id="input-color-preview"
                       value="#212529">
            </div>
            <div class="px-4 pb-2 d-none" id="mensaje-multicolor-preview">
                <div class="alert alert-secondary py-2 px-3 small mb-0">
                    <i class="bi bi-palette me-1"></i>Icono <strong>multicolor</strong>: conserva sus colores originales, por eso no se puede cambiar su color.
                </div>
            </div>
            <div class="px-4 pb-3">
                <label class="form-label fw-bold small mb-1" for="input-fondo-preview">Icono Fondo</label>
                <input type="color"
                       class="form-control form-control-color form-control-sm"
                       id="input-fondo-preview"
                       value="#ffffff">
            </div>
        </div>
    </div>
</div>
