<?php
    require_once 'includes/auth.php';
    require_once 'includes/control_acceso_pagina.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MRP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
    <link href="assets/css/custom.css" rel="stylesheet">
    <style>
        /* MRP en PANELES: un panel por producto = ficha del producto (columna izquierda, fuera de
           la tabla, para no estirar filas) + tabla con sus semanas. La ficha queda fija arriba al
           desplazarse por un producto con muchas semanas. En pantallas angostas va arriba. */
        .mrp-panel { display: flex; background: #fff; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 12px; }
        /* Ancho EXACTO (min = max = 240px): el contenido no puede ensancharla, así todas las tablas
           quedan del mismo ancho y sus columnas alineadas entre paneles. El texto largo se corta. */
        .mrp-ficha { flex: 0 0 240px; width: 240px; min-width: 240px; max-width: 240px;
                     background: #f8f9fa; border-right: 2px solid #adb5bd; padding: 8px 10px; border-radius: 6px 0 0 6px;
                     overflow-wrap: anywhere; }
        .mrp-ficha .mrp-cod-row { flex-wrap: wrap; }
        .mrp-ficha .mrp-pl .v { text-align: right; min-width: 0; }
        .mrp-ficha-inner { position: sticky; top: 8px; }
        .mrp-panel-tabla { flex: 1 1 auto; min-width: 0; overflow-x: auto; }
        /* Anchos de columna FIJOS e iguales en todos los paneles (los define el <colgroup> del JS);
           min-width evita que se aprieten demasiado: bajo ese ancho aparece scroll horizontal. */
        .mrp-tabla { table-layout: fixed; width: 100%; min-width: 860px; }
        /* "thead th" para ganarle a .tabla-compacta thead th (custom.css), que fuerza los títulos
           en una línea: con columnas de ancho fijo y pantallas angostas (notebook con escala
           125-150%) los títulos se montaban sobre la columna vecina. Aquí bajan de línea. */
        .mrp-tabla thead th { white-space: normal; overflow-wrap: break-word; vertical-align: middle; line-height: 1.15; }
        .mrp-tabla td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mrp-tabla thead th { cursor: default; }
        @media (max-width: 991.98px) {
            .mrp-panel { flex-direction: column; }
            .mrp-ficha { flex-basis: auto; width: auto; min-width: 0; max-width: none;
                         border-right: 0; border-bottom: 2px solid #adb5bd; border-radius: 6px 6px 0 0; }
            .mrp-ficha-inner { position: static; }
        }
        /* Fila superior de la ficha: código a la izquierda y badge de Estado a la derecha. */
        .mrp-cod-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 4px; }
        /* Badge del código (nativo bg-secondary): cursor de mano, hover y un tamaño algo mayor. */
        .mrp-cod-badge { cursor: pointer; font-size: 0.82rem; padding: 0.32em 0.6em; }
        .mrp-cod-badge:hover { filter: brightness(0.9); }
        .mrp-nom { font-size: 13px; font-weight: 600; line-height: 1.25; margin: 1px 0 6px; }
        .mrp-pl { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; padding: 1px 0; }
        .mrp-pl .k { color: #6c757d; }
        /* Stock Mín (rojo) / Máx (verde) resaltados: etiqueta Y valor en negrita. */
        .mrp-pl.mrp-min .k, .mrp-pl.mrp-min .v { color: #dc3545; font-weight: 700; }
        .mrp-pl.mrp-max .k, .mrp-pl.mrp-max .v { color: #198754; font-weight: 700; }
        /* Stock Seguridad: etiqueta y valor en negrita (sin color). */
        .mrp-pl.mrp-seg .k, .mrp-pl.mrp-seg .v { font-weight: 700; }
        /* Badge de Estado (Quiebre/Ajustado/OK) más grande y legible; va en la fila del código. */
        .mrp-est .badge { font-size: 0.85rem; padding: 0.45em 0.7em; white-space: nowrap; }
        /* Celdas con tooltip de acumulado (Demanda Proyectada y Sugerido a Reponer): cursor de ayuda. */
        .mrp-tabla td.mrp-tip-cell { cursor: help; }
        /* Separador: divide lo COMPROMETIDO (En Pedido, Comprometido, Stock Teórico) de lo
           dependiente del forecast (Demanda Proyectada en adelante). */
        .mrp-tabla th.mrp-sep-left,
        .mrp-tabla td.mrp-sep-left { border-left: 2px solid #94a3b8; }
        /* Botón "Ver detalle" al pie de la ficha del producto. */
        .mrp-acciones { margin-top: 8px; }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container-fluid">
    <div id="alert-container"></div>
</div>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-black"><?php echo encabezadoMantenedor($pdo, 'MRP'); ?></h5>
            <button type="button" class="btn btn-success btn-sm" id="btn-exportar-mrp"
                    title="Exportar el pronóstico de compra (todas las semanas de todos los productos) a Excel">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </button>
        </div>
        <div class="card-body">
            <div>

                <div class="row g-2 mb-2 mx-0">
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="consulta-mrp">Consulta</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="consulta-mrp"
                                   name="consulta-mrp"
                                   placeholder="Nombre o código de producto">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-familia">Familia</label>
                        <select class="form-select form-select-sm" id="filtro-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-sub-familia">Sub-Familia</label>
                        <select class="form-select form-select-sm" id="filtro-sub-familia">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-proveedor">Proveedor</label>
                        <select class="form-select form-select-sm" id="filtro-proveedor" title="Solo proveedores de productos con forecast">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="filtro-quiebre">En Quiebre</label>
                        <select class="form-select form-select-sm" id="filtro-quiebre" title="Filtra los productos por su estado de quiebre en el horizonte de cálculo">
                            <option value="" selected>Ver Todo</option>
                            <option value="con">Con Quiebre</option>
                            <option value="sin">Sin Quiebre</option>
                            <option value="0">Quiebre Actual</option>
                            <option value="1">Quiebre en 1 semana</option>
                            <option value="2">Quiebre en 2 semanas</option>
                            <option value="3">Quiebre en 3 semanas</option>
                            <option value="4">Quiebre en 4 semanas</option>
                            <option value="5">Quiebre en 5 semanas</option>
                            <option value="6">Quiebre en 6 semanas</option>
                            <option value="7">Quiebre en 7 semanas</option>
                            <option value="8+">Quiebre en 8 semanas o más</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small mb-1" for="mrp-horizonte">Horizonte</label>
                        <select class="form-select form-select-sm" id="mrp-horizonte" title="Cuántas semanas mostrar por producto (no recalcula: el plan se calcula sobre una ventana fija)">
                            <option value="1">1 Semana</option>
                            <option value="2">2 Semanas</option>
                            <option value="3">3 Semanas</option>
                            <option value="4" selected>1 Mes</option>
                            <option value="8">2 Meses</option>
                            <option value="13">3 Meses</option>
                            <option value="26">6 Meses</option>
                            <option value="52">1 Año</option>
                        </select>
                    </div>
                    <div class="col-md-auto ms-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-limpiar-filtros">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <div id="mrp-loading" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Cargando pronóstico...
                </div>
                <!-- Vista en PANELES: un panel por producto (ficha a la izquierda + tabla de semanas),
                     armada por assets/js/mrp.js (renderPaneles), con paginación POR PRODUCTO. Usa la
                     misma estructura y clases que la paginación de DataTables del resto del sistema
                     (dataTables_wrapper / _length / _info / _paginate), así hereda su estética y el
                     tono oscuro de custom.css. Arriba: cantidad por página | info; abajo: paginación. -->
                <div id="mrp-vista" class="dataTables_wrapper dt-bootstrap5 d-none">
                    <div id="mrp-toolbar" class="row align-items-center">
                        <div class="col-sm-12 col-md-6">
                            <div class="dataTables_length">
                                <label>Mostrar
                                    <select id="mrp-por-pagina" class="form-select form-select-sm">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="-1">Todos</option>
                                    </select>
                                    productos</label>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 text-md-end">
                            <div class="dataTables_info" id="mrp-info" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                    <div class="row"><div class="col-sm-12"><div id="mrp-paneles"></div></div></div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="dataTables_paginate paging_simple_numbers">
                                <ul id="mrp-paginacion" class="pagination"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: detalle de un registro del MRP (columna Acciones). Lo llena el JS. -->
<div class="modal fade" id="modalMrpDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title">
                    <i class="bi bi-clipboard-data me-2"></i>Detalle MRP
                    <span id="mrp-detalle-titulo" class="fw-normal"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">
              <div class="row g-3">

                <!-- Izquierda: información del producto (Campo | Valor) -->
                <div class="col-12 col-lg-4">
                    <table class="table table-sm table-striped align-middle mb-0 small" style="font-size: .78rem;">
                        <tbody id="tabla-mrp-detalle"></tbody>
                    </table>
                </div>

                <!-- Derecha: pestañas de detalle + cuerpo -->
                <div class="col-12 col-lg-8" style="font-size: .78rem;">

                <!-- Pestañas de detalle por caso (Stock; se irán agregando más) -->
                <ul class="nav nav-tabs" id="mrp-detalle-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-stock-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-stock" type="button" role="tab"
                                aria-controls="tab-stock" aria-selected="true">
                            <i class="bi bi-boxes me-1"></i> Stock
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-comprometido-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-comprometido" type="button" role="tab"
                                aria-controls="tab-comprometido" aria-selected="false">
                            <i class="bi bi-cart-check me-1"></i> Comprometido
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-en-pedido-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-en-pedido" type="button" role="tab"
                                aria-controls="tab-en-pedido" aria-selected="false">
                            <i class="bi bi-truck me-1"></i> En Pedido
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-en-produccion-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-en-produccion" type="button" role="tab"
                                aria-controls="tab-en-produccion" aria-selected="false">
                            <i class="bi bi-gear-wide-connected me-1"></i> En Producción
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-forecast-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-forecast" type="button" role="tab"
                                aria-controls="tab-forecast" aria-selected="false">
                            <i class="bi bi-graph-up me-1"></i> Forecast Semanal
                        </button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-2">
                    <div class="tab-pane fade show active" id="tab-stock" role="tabpanel" aria-labelledby="tab-stock-btn">
                        <div id="mrp-stock-estado" class="text-center text-muted py-3">Cargando...</div>
                        <div class="table-responsive small" id="mrp-stock-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Lote</th>
                                        <th class="text-center">F. Ingreso</th>
                                        <th class="text-center">F. Vencimiento</th>
                                        <th class="text-end">Días p/Vencer</th>
                                        <th class="text-center">Ubicación</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Vencimiento</th>
                                        <th class="text-end">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-stock"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-comprometido" role="tabpanel" aria-labelledby="tab-comprometido-btn">
                        <div id="mrp-comprometido-estado" class="text-center text-muted py-3">Cargando...</div>
                        <div class="table-responsive small" id="mrp-comprometido-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Orden Venta</th>
                                        <th class="text-center">Fecha</th>
                                        <th class="text-center">Fecha Entrega</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-comprometido"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-en-pedido" role="tabpanel" aria-labelledby="tab-en-pedido-btn">
                        <div id="mrp-en-pedido-estado" class="text-center text-muted py-3">Cargando...</div>
                        <div class="table-responsive small" id="mrp-en-pedido-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N° Documento</th>
                                        <th class="text-center">Tipo Doc.</th>
                                        <th class="text-center">Fecha</th>
                                        <th class="text-center">Fecha Comprometida</th>
                                        <th class="text-center">Fecha Ingreso Bodega</th>
                                        <th class="text-center">Almacén</th>
                                        <th>Proveedor</th>
                                        <th>Origen</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-en-pedido"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-en-produccion" role="tabpanel" aria-labelledby="tab-en-produccion-btn">
                        <div id="mrp-en-produccion-estado" class="text-center text-muted py-3">Cargando...</div>
                        <div class="table-responsive small" id="mrp-en-produccion-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Orden Producción</th>
                                        <th class="text-center">Fecha</th>
                                        <th class="text-center">Fecha Entrega</th>
                                        <th class="text-end">Planificada</th>
                                        <th class="text-end">Completada</th>
                                        <th class="text-end">Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-en-produccion"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-forecast" role="tabpanel" aria-labelledby="tab-forecast-btn">
                        <div id="mrp-forecast-estado" class="text-center text-muted py-3">Cargando...</div>
                        <!-- Gráfico del forecast semanal (solo forecast, sin venta histórica ni filtros) -->
                        <div id="mrp-forecast-grafico-wrap" style="display:none;">
                            <div id="mrp-forecast-grafico" style="width:100%; height:300px;"></div>
                        </div>
                        <div class="table-responsive small" id="mrp-forecast-wrap" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-center">Semana ISO</th>
                                        <th class="text-center">Inicio semana</th>
                                        <th class="text-end">Demanda (Forecast)</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-mrp-forecast"></tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="2" class="text-end">Total (<span id="mrp-forecast-nsem">—</span> semanas):</td>
                                        <td class="text-end" id="mrp-forecast-total">—</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                </div><!-- /col derecha -->
              </div><!-- /row -->
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/mrp.js?v=<?php echo filemtime(__DIR__ . '/assets/js/mrp.js'); ?>"></script>
</body>
</html>
