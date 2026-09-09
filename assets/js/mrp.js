$(document).ready(function() {

    // Redondea a entero con separador de miles (estilo chileno).
    function formatearEntero(valor) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return ''; }
        return String(Math.round(num)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Render numérico consciente del tipo: número crudo para ordenar/buscar, formateado para mostrar.
    function renderNumero(d, type) {
        if (type === 'sort' || type === 'type') {
            const n = parseFloat(d);
            return isNaN(n) ? 0 : n;
        }
        return formatearEntero(d);
    }

    // Sugerido a reponer: rojo si hay que reponer (>0), gris si 0. Ordena por el valor crudo.
    function renderSugerido(d, type) {
        if (type === 'sort' || type === 'type') {
            const n = parseFloat(d);
            return isNaN(n) ? 0 : n;
        }
        const n = parseFloat(d);
        if (isNaN(n) || n <= 0) { return '<span class="text-muted">0</span>'; }
        return '<span class="text-danger fw-bold">' + formatearEntero(n) + '</span>';
    }

    // Saldo proyectado: negativo (quiebre de stock) en rojo. Ordena por el valor crudo.
    function renderSaldo(d, type) {
        if (type === 'sort' || type === 'type') { const n = parseFloat(d); return isNaN(n) ? 0 : n; }
        const n = parseFloat(d);
        if (isNaN(n)) { return ''; }
        const txt = (n < 0 ? '-' : '') + formatearEntero(Math.abs(n));
        return (n < 0) ? '<span class="text-danger fw-bold">' + txt + '</span>' : txt;
    }

    // Color de barra según el estado de la semana (igual que los badges de Estado).
    const COLOR_ESTADO = { quiebre: '#dc3545', ajustado: '#ffc107', ok: '#198754' };

    // Tendencia de la demanda del horizonte: mini bar chart SVG. Cada barra = una semana
    // (desde la actual hacia el final): la ALTURA es la demanda y el COLOR es el estado de
    // esa semana (rojo=quiebre, amarillo=ajustado, verde=ok).
    function renderTendencia(d, type) {
        if (type !== 'display') { return ''; }
        const arr = Array.isArray(d) ? d : [];
        if (arr.length < 1) { return '<span class="text-muted">—</span>'; }
        const w = 58, h = 18, gap = 1;
        const n = arr.length;
        const bw = Math.max(1, (w - gap * (n - 1)) / n);   // ancho de barra ajustado para caber
        const max = arr.reduce(function(m, o) { const v = Number(o.d) || 0; return v > m ? v : m; }, 0) || 1;
        let bars = '';
        arr.forEach(function(o, i) {
            const v  = Number(o.d) || 0;
            const bh = Math.max(1, (v / max) * (h - 1));   // altura mínima 1px para que se vea
            const x  = i * (bw + gap);
            const y  = h - bh;
            const fill = COLOR_ESTADO[o.e] || '#0d6efd';
            bars += '<rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) + '" '
                  + 'width="' + bw.toFixed(1) + '" height="' + bh.toFixed(1) + '" '
                  + 'rx="0.4" fill="' + fill + '"/>';
        });
        return '<svg width="' + w + '" height="' + h + '" style="vertical-align:middle">'
             + bars + '</svg>';
    }

    // Estado de abastecimiento del producto: badge según quiebre / ajustado / ok.
    function renderEstado(d, type, row) {
        if (type !== 'display') { return d || ''; }
        if (d === 'quiebre') {
            const n = row.estado_sem || 0;
            return '<span class="badge bg-danger">Quiebre' + (n ? ' en ' + n + ' sem' : '') + '</span>';
        }
        if (d === 'ajustado') { return '<span class="badge bg-warning text-dark">Ajustado</span>'; }
        return '<span class="badge bg-success">OK</span>';
    }

    // Contenido de la celda "Producto" (info del producto apilada en vertical).
    function celdaProducto(row) {
        const esc = function(s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
        const linea = function(k, v) {
            return '<div class="mrp-pl"><span class="k">' + k + '</span>'
                 + '<span class="v">' + esc(v) + '</span></div>';
        };
        return '<div class="mrp-cod">' + esc(row.producto_codigo) + '</div>'
             + '<div class="mrp-nom">' + esc(row.producto_nombre) + '</div>'
             + linea('Familia', row.familia)
             + linea('Sub-Familia', row.sub_familia)
             + linea('Proveedor', row.proveedor)
             + linea('Lead Time', (row.lead_time || 0) + ' sem')
             + '<div class="mrp-est">' + renderEstado(row.estado, 'display', row) + '</div>';
    }

    // Fecha 'yyyy-mm-dd' -> 'dd-mm-yyyy'.
    function fmtFecha(s) {
        if (!s) { return ''; }
        const p = String(s).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : s;
    }

    // Rango de semanas cubierto por la demanda: "desde a hasta" (o una sola si coinciden).
    function renderSemanas(d, type, row) {
        if (type === 'sort' || type === 'type') { return row.semana_desde || ''; }
        const desde = fmtFecha(row.semana_desde);
        const hasta = fmtFecha(row.semana_hasta);
        if (!desde) { return ''; }
        return (desde === hasta) ? desde : (desde + ' a ' + hasta);
    }

    // Días al próximo vencimiento: número plano; "—" si el producto no tiene stock vigente.
    function renderDiasVenc(d, type) {
        if (type === 'sort' || type === 'type') {
            return (d === null || d === undefined || d === '') ? 999999 : parseFloat(d);
        }
        if (d === null || d === undefined || d === '') { return '—'; }
        return formatearEntero(d);
    }

    const escaparTexto = $.fn.dataTable.render.text();

    // Índices de columna Familia / Sub-Familia (para el filtro client-side).
    const COL_FAMILIA    = 2;
    const COL_SUBFAMILIA = 3;
    const COL_PROVEEDOR  = 4;

    let tabla = null;

    function mostrarAlerta(msg) {
        $('#alert-container').html(
            '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
            $('<div>').text(msg || 'No se pudo cargar el MRP.').html() +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        );
    }

    // Carga los datos del MRP (una sola vez; DataTable pagina/busca/ordena client-side).
    function cargarMrp(onDone) {
        const horizonte = $('#mrp-horizonte').val() || '4';
        const seguridad = $('#mrp-seguridad').val() || '2';
        $.ajax({
            url: 'controllers/mrp_controller.php?action=listar',
            type: 'GET',
            data: { horizonte: horizonte, semanas_seguridad: seguridad },
            dataType: 'json',
            complete: function() { if (typeof onDone === 'function') { onDone(); } },
            success: function(res) {
                if (res.status !== 'success') {
                    mostrarAlerta(res.message);
                    return;
                }
                const filas = res.data || [];
                poblarProveedores(filas);

                if (tabla) {
                    tabla.clear().rows.add(filas).draw();
                    aplicarFiltros();   // reaplica el filtro de proveedor si seguía seleccionado
                    return;
                }

                tabla = $('#tabla-consulta-mrp').DataTable({
                    data: filas,
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row'<'col-sm-12'p>>",
                    autoWidth: false,
                    // Por defecto muestra 100 registros por página.
                    pageLength: 100,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    // Mantiene la fila de encabezados visible al desplazarse hacia abajo.
                    fixedHeader: true,
                    // Orden por necesidad: mayor "Sugerido a Reponer" primero. Los desempates
                    // (nombre y semana) mantienen juntas las filas de un mismo producto y sus
                    // semanas en orden cronológico.
                    // Orden: por URGENCIA del producto (total a ordenar, col. oculta 18), luego
                    // nombre y semana → productos más urgentes arriba, con sus semanas contiguas
                    // y en orden cronológico.
                    order: [[18, 'desc'], [1, 'asc'], [6, 'asc']],
                    columns: [
                        {
                            data: 'producto_codigo', className: 'mrp-prod-cell', orderable: false,
                            render: function(d, type, row) { return (type === 'display') ? celdaProducto(row) : (d || ''); }
                        },
                        { data: 'producto_nombre',  visible: false, render: escaparTexto },
                        { data: 'familia',          visible: false, render: escaparTexto },
                        { data: 'sub_familia',      visible: false, render: escaparTexto },
                        { data: 'proveedor',        visible: false, render: escaparTexto },
                        { data: 'lead_time',        visible: false, render: renderNumero },
                        { data: 'semana',           className: 'text-center', render: function(d, type) { return (type === 'display') ? fmtFecha(d) : (d || ''); } },
                        { data: 'demanda_forecast', className: 'text-end',    render: renderNumero },
                        { data: 'tendencia',        className: 'text-center', orderable: false, render: renderTendencia },
                        { data: 'saldo_proyectado', className: 'text-end',    render: renderSaldo },
                        { data: 'dias_prox_venc',   className: 'text-center', render: renderDiasVenc },
                        { data: 'stock_wms',        className: 'text-end',    render: renderNumero },
                        { data: 'comprometido',     className: 'text-end',    render: renderNumero },
                        { data: 'en_pedido',        className: 'text-end',    render: renderNumero },
                        { data: 'en_produccion',    className: 'text-end',    render: renderNumero },
                        { data: 'stock_teorico',    className: 'text-end',    render: renderNumero },
                        { data: 'stock_seguridad',  className: 'text-end',    render: renderNumero },
                        { data: 'sugerido',         className: 'text-end',    render: renderSugerido },
                        { data: 'sugerido_total',   visible: false },   // clave de orden por producto (oculta)
                        { data: 'estado',           visible: false, render: renderEstado },   // se muestra en la celda Producto
                        {
                            data: null, orderable: false, searchable: false, className: 'text-center',
                            render: function() {
                                return '<button type="button" class="btn btn-sm btn-outline-dark btn-mrp-detalle" '
                                     + 'title="Ver detalle"><i class="bi bi-eye"></i></button>';
                            }
                        }
                    ],
                    // Camino 1 (robusto): celda "Producto" con apariencia fusionada SIN eliminar
                    // celdas (eso corrompía los nodos que DataTables reutiliza al paginar/ordenar).
                    // La info del producto se pinta solo en la 1ª fila de cada grupo; en las demás
                    // se VACÍA la celda (nunca se quita). Sin bordes internos → parece una sola celda.
                    // Idempotente: se recalcula en cada draw a partir del orden actual.
                    drawCallback: function() {
                        const api = this.api();
                        let prev = null;
                        api.rows({ page: 'current' }).every(function() {
                            const d     = this.data();
                            const $tr   = $(this.node());
                            const $cell = $tr.children('td').eq(0);
                            if (d.producto_codigo === prev) {
                                $cell.empty();
                                $tr.removeClass('mrp-fila-inicio');
                            } else {
                                $cell.html(celdaProducto(d));
                                $tr.addClass('mrp-fila-inicio');
                            }
                            prev = d.producto_codigo;
                        });
                    }
                });
            },
            error: function() {
                mostrarAlerta('Error al cargar el MRP.');
            }
        });
    }

    // Filtro client-side por Familia / Sub-Familia (búsqueda exacta por columna).
    // Puebla el combo de Proveedor con los proveedores DISTINTOS presentes en los datos
    // cargados (que son solo los productos con forecast). Conserva la selección si sigue.
    function poblarProveedores(filas) {
        const $sel = $('#filtro-proveedor');
        if (!$sel.length) { return; }
        const prev = $sel.val();
        const set = {};
        (filas || []).forEach(function(f) {
            const p = (f.proveedor == null ? '' : String(f.proveedor)).trim();
            if (p) { set[p] = true; }
        });
        const provs = Object.keys(set).sort(function(a, b) { return a.localeCompare(b, 'es'); });
        $sel.empty().append('<option value="">Todos</option>');
        provs.forEach(function(p) { $sel.append($('<option>').val(p).text(p)); });
        if (prev && set[prev]) { $sel.val(prev); }
    }

    function aplicarFiltros() {
        if (!tabla) { return; }
        const fam = $('#filtro-familia').val();
        const sub = $('#filtro-sub-familia').val();
        const prv = $('#filtro-proveedor').val();
        const rx  = function(v) { return v ? '^' + $.fn.dataTable.util.escapeRegex(v) + '$' : ''; };
        tabla.column(COL_FAMILIA).search(rx(fam), true, false);
        tabla.column(COL_SUBFAMILIA).search(rx(sub), true, false);
        tabla.column(COL_PROVEEDOR).search(rx(prv), true, false);
        tabla.draw();
    }

    // Buscador propio -> búsqueda global de DataTables.
    $('#consulta-mrp').on('input', function() {
        if (tabla) { tabla.search(this.value).draw(); }
    });

    $('#filtro-familia, #filtro-sub-familia, #filtro-proveedor').on('change', aplicarFiltros);

    // Cambiar el Horizonte recalcula la demanda/sugerido en el backend (recarga los datos).
    $('#mrp-horizonte').on('change', cargarMrp);

    // Recalcular Pronóstico: reconstruye el plan con los parámetros actuales (horizonte y
    // semanas de seguridad), con feedback en el botón.
    $('#btn-recalcular-pronostico').on('click', function() {
        const $btn = $(this);
        const original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Recalculando...');
        cargarMrp(function() { $btn.prop('disabled', false).html(original); });
    });

    // Botón "Limpiar": vacía filtros y buscador, y redibuja sin filtros.
    $('#btn-limpiar-filtros').on('click', function() {
        $('#consulta-mrp, #filtro-familia, #filtro-sub-familia, #filtro-proveedor').val('');
        if (tabla) {
            tabla.search('').columns().search('').draw();
        }
    });

    // ============================================================
    //  Detalle de un registro (columna Acciones): modal Campo | Valor.
    // ============================================================

    function seccionDet(titulo) {
        return '<tr class="table-secondary"><th colspan="2" class="small fw-bold text-uppercase">' + titulo + '</th></tr>';
    }
    function filaDet(label, valor) {
        const v = (valor === '' || valor === null || valor === undefined) ? '<span class="text-muted">—</span>' : valor;
        return '<tr><th class="fw-semibold small text-nowrap" style="width:55%;">' + label + '</th>'
             + '<td class="small">' + v + '</td></tr>';
    }
    const textoDet = function(v) { return (v === null || v === undefined) ? '' : $('<div>').text(v).html(); };
    const numDet   = function(v) { const s = formatearEntero(v); return s === '' ? '0' : s; };

    // Arma el cuerpo del modal de detalle a partir de la fila del DataTable.
    function filasDetalleMrp(f) {
        const semanas = renderSemanas(null, 'display', f);   // reutiliza el formato de la columna
        return seccionDet('Producto')
             + filaDet('Código',      textoDet(f.producto_codigo))
             + filaDet('Nombre',      textoDet(f.producto_nombre))
             + filaDet('Familia',     textoDet(f.familia))
             + filaDet('Sub-Familia', textoDet(f.sub_familia))
             + filaDet('Proveedor',   textoDet(f.proveedor))
             + seccionDet('Planificación')
             + filaDet('Lead Time (semanas)', numDet(f.lead_time))
             + filaDet('Demanda (Forecast)',  numDet(f.demanda_forecast))
             + filaDet('Semana(s)',           semanas)
             + seccionDet('Disponibilidad')
             + filaDet('Stock Físico',                numDet(f.stock_wms))
             + filaDet('Stock que vence en ≤30 días', numDet(f.stock_por_vencer))
             + filaDet('Próximo vencimiento (días)',  renderDiasVenc(f.dias_prox_venc, 'display'))
             + seccionDet('Compromisos y entradas')
             + filaDet('Comprometido',   numDet(f.comprometido))
             + filaDet('En Pedido',      numDet(f.en_pedido))
             + filaDet('En Producción',  numDet(f.en_produccion))
             + filaDet('Stock Teórico',  numDet(f.stock_teorico))
             + seccionDet('Resultado')
             + filaDet('Sugerido a Reponer', '<span class="fw-bold">' + numDet(f.sugerido) + '</span>');
    }

    // Pestaña "Stock": detalle de lotes/pallets del producto (WMS), ordenado por vencimiento.
    function cargarDetalleStock(cod) {
        const $estado = $('#mrp-stock-estado');
        const $wrap   = $('#mrp-stock-wrap');
        const $tbody  = $('#tabla-mrp-stock');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_stock',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el stock.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin stock en el WMS para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    html += '<tr>'
                         + '<td>' + textoDet(r.Lote) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FIngreso) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FVencimiento) + '</td>'
                         + '<td class="text-end">'    + formatearEntero(r.DiasParaVencer) + '</td>'
                         + '<td class="text-center">' + textoDet(r.Ubicacion) + '</td>'
                         + '<td class="text-center">' + textoDet(r.EstadoPallet) + '</td>'
                         + '<td class="text-center">' + textoDet(r.Vencimiento) + '</td>'
                         + '<td class="text-end">'    + formatearEntero(r.Cantidad) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar el stock.').show();
            }
        });
    }

    // Pestaña "Comprometido": líneas de ODV abiertas del producto (SAP, bodega 010).
    function cargarDetalleComprometido(cod) {
        const $estado = $('#mrp-comprometido-estado');
        const $wrap   = $('#mrp-comprometido-wrap');
        const $tbody  = $('#tabla-mrp-comprometido');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_comprometido',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el comprometido.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin ventas comprometidas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    const cliente = (r.CodCliente ? textoDet(r.CodCliente) + ' — ' : '') + textoDet(r.Cliente);
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenVenta) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td>' + cliente + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Cantidad) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar el comprometido.').show();
            }
        });
    }

    // Pestaña "En Pedido": líneas de OC abiertas del producto (SAP, bodega 010).
    function cargarDetalleEnPedido(cod) {
        const $estado = $('#mrp-en-pedido-estado');
        const $wrap   = $('#mrp-en-pedido-wrap');
        const $tbody  = $('#tabla-mrp-en-pedido');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_en_pedido',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar las órdenes de compra.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin órdenes de compra abiertas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    const proveedor = (r.CodProveedor ? textoDet(r.CodProveedor) + ' — ' : '') + textoDet(r.Proveedor);
                    // Origen: bandera (flag-icons por ISO2) + país del proveedor real del documento.
                    const codPais = String(r.PaisCod || '').toLowerCase();
                    const bandera = /^[a-z]{2}$/.test(codPais) ? '<span class="fi fi-' + codPais + ' me-1"></span>' : '';
                    const origen  = r.Pais ? (bandera + textoDet(r.Pais)) : '<span class="text-muted">—</span>';
                    // IMP01 = importación en tránsito; se resalta para distinguirla de la bodega local.
                    const almacen = (String(r.Almacen).toUpperCase() === 'IMP01')
                        ? '<span class="badge bg-info text-dark">IMP01</span>'
                        : textoDet(r.Almacen);
                    // Distingue Factura de Reserva (compra facturada por recibir) de la OC normal.
                    const tipoDoc = (r.TipoDoc === 'Factura de Reserva')
                        ? '<span class="badge bg-warning text-dark">Factura de Reserva</span>'
                        : textoDet(r.TipoDoc);
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenCompra) + '</td>'
                         + '<td class="text-center">' + tipoDoc + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td class="text-center">' + almacen + '</td>'
                         + '<td>' + proveedor + '</td>'
                         + '<td>' + origen + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Cantidad) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar las órdenes de compra.').show();
            }
        });
    }

    // Pestaña "En Producción": órdenes de producción liberadas del producto (SAP, OWOR).
    function cargarDetalleEnProduccion(cod) {
        const $estado = $('#mrp-en-produccion-estado');
        const $wrap   = $('#mrp-en-produccion-wrap');
        const $tbody  = $('#tabla-mrp-en-produccion');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_en_produccion',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar las órdenes de producción.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin órdenes de producción liberadas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenProduccion) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Planificada) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Completada) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar las órdenes de producción.').show();
            }
        });
    }

    // Pestaña "Forecast Semanal": serie semana a semana del producto (forecast_x_producto).
    function cargarDetalleForecast(cod) {
        const $estado = $('#mrp-forecast-estado');
        const $wrap   = $('#mrp-forecast-wrap');
        const $tbody  = $('#tabla-mrp-forecast');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();
        $('#mrp-forecast-total').text('—');

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_forecast',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el forecast.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin forecast para este producto.').show();
                    return;
                }
                let html = '', total = 0;
                filas.forEach(function(r) {
                    const dem = parseFloat(r.demanda) || 0;
                    total += dem;
                    const semanaIso = r.iso_year + '-W' + String(r.iso_week).padStart(2, '0');
                    html += '<tr>'
                         + '<td class="text-center">' + textoDet(semanaIso) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.semana_inicio) + '</td>'
                         + '<td class="text-end">' + formatearEntero(dem) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $('#mrp-forecast-total').text(formatearEntero(total));
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar el forecast.').show();
            }
        });
    }

    // Botón "Ver detalle": toma la fila del DataTable, llena el resumen y carga las pestañas.
    $('#tabla-consulta-mrp tbody').on('click', '.btn-mrp-detalle', function() {
        const f = tabla ? tabla.row($(this).closest('tr')).data() : null;
        if (!f) { return; }
        $('#mrp-detalle-titulo').text('— ' + (f.producto_codigo || '') + ' · ' + (f.producto_nombre || ''));
        $('#tabla-mrp-detalle').html(filasDetalleMrp(f));
        // Siempre inicia en la pestaña Stock al abrir un producto nuevo.
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-stock-btn')).show();
        cargarDetalleStock(f.producto_codigo);
        cargarDetalleComprometido(f.producto_codigo);
        cargarDetalleEnPedido(f.producto_codigo);
        cargarDetalleEnProduccion(f.producto_codigo);
        cargarDetalleForecast(f.producto_codigo);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMrpDetalle')).show();
    });

    // Carga las opciones de Familia / Sub-Familia (mismo contrato que Forecast).
    function cargarFiltros() {
        $.ajax({
            url: 'controllers/mrp_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                const $fam = $('#filtro-familia');
                $fam.empty().append('<option value="">Todas</option>');
                (res.familias || []).forEach(function(f) { $fam.append($('<option>').val(f).text(f)); });

                const $sub = $('#filtro-sub-familia');
                $sub.empty().append('<option value="">Todas</option>');
                (res.sub_familias || []).forEach(function(sf) { $sub.append($('<option>').val(sf).text(sf)); });
            }
        });
    }

    cargarFiltros();
    cargarMrp();
});
