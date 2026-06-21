<?php require_once __DIR__ . '/../config/config.php'; ?>

<div class="modal fade" id="modalIconoCrear" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Icono</h6>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>
            </div>
            <form id="form-icono"
                  class="form-validado-estatico form-validar-instantaneo"
                  action="controllers/iconos_controller.php"
                  enctype="multipart/form-data"
                  novalidate>
                <div class="modal-body py-2">

                    <input type="hidden"
                           id="csrf_token"
                           name="csrf_token"
                           value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
                        <div id="modal-mensajes"></div>
                    </div>

                    <!-- Vista previa del icono (Bootstrap o SVG personalizado) -->
                    <div class="d-flex justify-content-center mb-3">
                        <div class="border rounded d-flex align-items-center justify-content-center bg-light"
                             style="width: 25%; aspect-ratio: 1 / 1;">
                            <i class="bi bi-image text-muted" id="preview-bootstrap" style="font-size: 3rem;"></i>
                            <img id="preview-personalizado" src="" alt="Vista previa" class="d-none"
                                 style="max-width: 72%; max-height: 72%;">
                        </div>
                    </div>

                    <!-- Tipo de icono: Bootstrap (nativo) o Personalizado (archivo SVG) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1 d-block">Tipo de icono</label>
                        <div class="btn-group btn-group-sm w-100" role="group" aria-label="Tipo de icono">
                            <input type="radio" class="btn-check" name="tipo" id="tipo-bootstrap" value="bootstrap" checked>
                            <label class="btn btn-outline-primary" for="tipo-bootstrap">
                                <i class="bi bi-bootstrap-fill me-1"></i> Bootstrap
                            </label>

                            <input type="radio" class="btn-check" name="tipo" id="tipo-personalizado" value="personalizado">
                            <label class="btn btn-outline-primary" for="tipo-personalizado">
                                <i class="bi bi-tools me-1"></i> Personalizado
                            </label>
                        </div>
                    </div>

                    <!-- Bloque BOOTSTRAP: combobox con búsqueda + iconos -->
                    <div class="mb-2" id="bloque-bootstrap">
                        <label class="form-label fw-bold small mb-1" for="input_valor_bootstrap">Icono de Bootstrap</label>

                        <div class="position-relative" id="combobox-bootstrap">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-bootstrap"></i></span>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="input_valor_bootstrap"
                                       name="valor_bootstrap"
                                       placeholder="Busca: house, gear, trash..."
                                       autocomplete="off"
                                       maxlength="60"
                                       data-check='true'>
                                <span class="input-group-text" id="btn-abrir-iconos" style="cursor: pointer;" title="Ver iconos">
                                    <i class="bi bi-chevron-down"></i>
                                </span>
                            </div>

                            <!-- Resultados de la búsqueda (se completan por JS) -->
                            <ul class="list-group position-absolute w-100 shadow-sm d-none"
                                id="lista-iconos-bootstrap"
                                style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                            </ul>

                            <div class="invalid-feedback small" id="error-valor_bootstrap"></div>
                        </div>

                        <div class="form-text small">
                            Escribe para buscar o consulta el catálogo en
                            <a href="https://icons.getbootstrap.com/"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="fw-semibold text-decoration-none">
                                icons.getbootstrap.com <i class="bi bi-box-arrow-up-right small"></i>
                            </a>.
                        </div>
                    </div>

                    <!-- Bloque PERSONALIZADO: archivo SVG + vista previa -->
                    <div class="mb-2 d-none" id="bloque-personalizado">
                        <label class="form-label fw-bold small mb-1" for="input_archivo">Archivo SVG</label>
                        <input type="file"
                               class="form-control form-control-sm"
                               id="input_archivo"
                               name="archivo"
                               accept=".svg,image/svg+xml">
                        <div class="form-text small">
                            Solo SVG. Los monocromáticos se normalizan para poder colorearlos;
                            los multicolor conservan sus colores.
                        </div>
                        <div class="invalid-feedback small" id="error-archivo"></div>
                    </div>

                    <!-- Nombre visible del icono -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_nombre">Nombre</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="input_nombre"
                                   name="nombre"
                                   placeholder="Ej: Casa"
                                   maxlength="60"
                                   data-check='true'>
                        </div>
                        <div class="invalid-feedback small" id="error-nombre"></div>
                    </div>

                    <!-- Valor (solo lectura): refleja lo que se guardará -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_valor_crear">Valor</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input_valor_crear"
                                   disabled>
                        </div>
                    </div>

                    <!-- Posición: se elige en la grilla de Asignar Posición -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1" for="input_posicion">Posición</label>
                        <div class="input-group input-group-sm">
                            <input type="text"
                                   class="form-control form-control-sm bg-light"
                                   id="input_posicion"
                                   placeholder="Al final"
                                   disabled>
                            <button type="button"
                                    class="btn btn-primary"
                                    id="btn-asignar-posicion"
                                    disabled>
                                <i class="bi bi-grid-3x3-gap me-1"></i> Asignar Posición
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
                            id="btnGuardarIcono">
                        <i class="bi bi-save me-1"></i> Guardar Icono
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
