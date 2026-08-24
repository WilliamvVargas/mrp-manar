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

    let tabla = null;

    function mostrarAlerta(msg) {
        $('#alert-container').html(
            '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
            $('<div>').text(msg || 'No se pudo cargar el MRP.').html() +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        );
    }

    // Carga los datos del MRP (una sola vez; DataTable pagina/busca/ordena client-side).
    function cargarMrp() {
        $.ajax({
            url: 'controllers/mrp_controller.php?action=listar',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    mostrarAlerta(res.message);
                    return;
                }
                const filas = res.data || [];

                if (tabla) {
                    tabla.clear().rows.add(filas).draw();
                    return;
                }

                tabla = $('#tabla-consulta-mrp').DataTable({
                    data: filas,
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row'<'col-sm-12'p>>",
                    autoWidth: false,
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    // Mantiene la fila de encabezados visible al desplazarse hacia abajo.
                    fixedHeader: true,
                    // Por defecto: mayor "Sugerido a Reponer" primero (lo más urgente arriba).
                    order: [[13, 'desc']],
                    columns: [
                        { data: 'producto_codigo',  render: escaparTexto },
                        { data: 'producto_nombre',  render: escaparTexto },
                        { data: 'familia',          render: escaparTexto },
                        { data: 'sub_familia',      render: escaparTexto },
                        { data: 'lead_time',        className: 'text-end',    render: renderNumero },
                        { data: 'demanda_forecast', className: 'text-end',    render: renderNumero },
                        { data: 'semana_desde',     className: 'text-center', render: renderSemanas },
                        { data: 'stock_wms',        className: 'text-end',    render: renderNumero },
                        { data: 'stock_por_vencer', className: 'text-end',    render: renderNumero },
                        { data: 'dias_prox_venc',   className: 'text-center', render: renderDiasVenc },
                        { data: 'comprometido',     className: 'text-end',    render: renderNumero },
                        { data: 'en_pedido',        className: 'text-end',    render: renderNumero },
                        { data: 'en_produccion',    className: 'text-end',    render: renderNumero },
                        { data: 'sugerido',         className: 'text-end',    render: renderSugerido },
                        {
                            data: null, orderable: false, searchable: false, className: 'text-center',
                            render: function() {
                                return '<button type="button" class="btn btn-sm btn-outline-dark btn-mrp-detalle" '
                                     + 'title="Ver detalle"><i class="bi bi-eye"></i></button>';
                            }
                        }
                    ]
                });
            },
            error: function() {
                mostrarAlerta('Error al cargar el MRP.');
            }
        });
    }

    // Filtro client-side por Familia / Sub-Familia (búsqueda exacta por columna).
    function aplicarFiltros() {
        if (!tabla) { return; }
        const fam = $('#filtro-familia').val();
        const sub = $('#filtro-sub-familia').val();
        const rx  = function(v) { return v ? '^' + $.fn.dataTable.util.escapeRegex(v) + '$' : ''; };
        tabla.column(COL_FAMILIA).search(rx(fam), true, false);
        tabla.column(COL_SUBFAMILIA).search(rx(sub), true, false);
        tabla.draw();
    }

    // Buscador propio -> búsqueda global de DataTables.
    $('#consulta-mrp').on('input', function() {
        if (tabla) { tabla.search(this.value).draw(); }
    });

    $('#filtro-familia, #filtro-sub-familia').on('change', aplicarFiltros);

    // Botón "Limpiar": vacía filtros y buscador, y redibuja sin filtros.
    $('#btn-limpiar-filtros').on('click', function() {
        $('#consulta-mrp, #filtro-familia, #filtro-sub-familia').val('');
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
             + seccionDet('Planificación')
             + filaDet('Lead Time (días)',    numDet(f.lead_time))
             + filaDet('Demanda (Forecast)',  numDet(f.demanda_forecast))
             + filaDet('Semana(s)',           semanas)
             + seccionDet('Disponibilidad')
             + filaDet('Stock (WMS)',                 numDet(f.stock_wms))
             + filaDet('Stock que vence en ≤30 días', numDet(f.stock_por_vencer))
             + filaDet('Próximo vencimiento (días)',  renderDiasVenc(f.dias_prox_venc, 'display'))
             + seccionDet('Compromisos y entradas')
             + filaDet('Comprometido',   numDet(f.comprometido))
             + filaDet('En Pedido',      numDet(f.en_pedido))
             + filaDet('En Producción',  numDet(f.en_produccion))
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
                    // IMP01 = importación en tránsito; se resalta para distinguirla de la bodega local.
                    const almacen = (String(r.Almacen).toUpperCase() === 'IMP01')
                        ? '<span class="badge bg-info text-dark">IMP01</span>'
                        : textoDet(r.Almacen);
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenCompra) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td class="text-center">' + almacen + '</td>'
                         + '<td>' + proveedor + '</td>'
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
