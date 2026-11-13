<?php require_once __DIR__ . '/../config/config.php'; ?>

<!-- Comprar: registra la compra del producto en la semana actual. Queda pendiente hasta que se
     marque "OC emitida" o se anule; mientras tanto el MRP la cuenta como pedido en camino. -->
<div class="modal fade" id="modalMrpComprar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 460px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title"><i class="bi bi-cart-plus me-2"></i>Comprar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-mrp-comprar" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden" id="csrf_token_mrp_comprar" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="mrp-c-codigo" name="producto_codigo">
                    <input type="hidden" id="mrp-c-nombre" name="producto_nombre">
                    <input type="hidden" id="mrp-c-proveedor" name="proveedor">
                    <input type="hidden" id="mrp-c-sugerida" name="cantidad_sugerida">
                    <input type="hidden" id="mrp-c-lead" name="lead_time">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-mrp-comprar"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="mrp-c-producto-txt">Producto</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="mrp-c-producto-txt" disabled>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <label class="form-label fw-bold small mb-1" for="mrp-c-proveedor-txt">Proveedor</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="mrp-c-proveedor-txt" disabled>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold small mb-1" for="mrp-c-lead-txt">Lead Time</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="mrp-c-lead-txt" disabled>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small mb-1" for="mrp-c-sugerido-txt">Sugerido de hoy</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="mrp-c-sugerido-txt" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small mb-1" for="mrp-c-llegada-txt">Llegada estimada</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="mrp-c-llegada-txt" disabled>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="mrp-c-cantidad">Cantidad a comprar <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" class="form-control form-control-sm" id="mrp-c-cantidad" name="cantidad">
                        <div class="invalid-feedback small" id="error-cantidad"></div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-bold small mb-1" for="mrp-c-observacion">Observación</label>
                        <textarea class="form-control form-control-sm" id="mrp-c-observacion" name="observacion" rows="2" maxlength="255"></textarea>
                        <div class="invalid-feedback small" id="error-observacion"></div>
                    </div>
                    <div class="form-text small mb-1">La compra queda pendiente hasta marcar <b>OC emitida</b> o anularla.</div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btnMrpComprar">
                        <i class="bi bi-cart-check me-1"></i> Registrar compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cierre manual de una compra pendiente: "OC emitida" (N° de OC opcional) o "Anular". -->
<div class="modal fade" id="modalMrpCompraCerrar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 420px; margin: 1.75rem auto;">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header text-white py-2" id="mrp-cc-header">
                <h6 class="modal-title" id="mrp-cc-titulo"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-mrp-compra-cerrar" novalidate>
                <div class="modal-body py-2">

                    <input type="hidden" id="csrf_token_mrp_cerrar" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="mrp-cc-id" name="id">
                    <input type="hidden" id="mrp-cc-accion">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-mrp-cerrar"></div>
                    </div>

                    <p class="small text-muted mb-2" id="mrp-cc-texto"></p>

                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="mrp-cc-producto-txt">Producto</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="mrp-cc-producto-txt" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="mrp-cc-compra-txt">Compra</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="mrp-cc-compra-txt" disabled>
                    </div>
                    <div class="mb-1" id="mrp-cc-oc-wrap">
                        <label class="form-label fw-bold small mb-1" for="mrp-cc-numero-oc">N° de OC (SAP)</label>
                        <input type="text" class="form-control form-control-sm" id="mrp-cc-numero-oc" name="numero_oc" maxlength="30" placeholder="Opcional">
                        <div class="invalid-feedback small" id="error-numero_oc"></div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm" id="btnMrpCompraCerrar"></button>
                </div>
            </form>
        </div>
    </div>
</div>
