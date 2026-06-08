<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalUsuarioEliminar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalTitleEliminar"><i class="bi bi-trash-fill me-2"></i>Eliminar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-usuario-eliminar" class="form-validado-estatico" novalidate>
                <div class="modal-body">
                    
                    <input type="hidden" name="csrf_token" id="csrf_token_elimnar" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id_usuario" id="id_usuario_eliminar">
                    
                    <div class="mensaje-wrapper" style="min-height: 10px; transition: all 0.3s ease;">
                        <div id="modal-mensajes-eliminar"></div>
                    </div>

                    <p>¿Estás seguro en eliminar este usuario?</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="input-usuario-eliminar">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="usuario" id="input-usuario-eliminar" disabled>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger btn-o" id="btnEliminar">
                        <i class="bi bi-trash me-1"></i> Eliminar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
