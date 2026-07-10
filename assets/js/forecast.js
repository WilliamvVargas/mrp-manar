$(document).ready(function() {

    // Redondea una cantidad a la unidad (0 decimales) con separador de miles (estilo chileno).
    function formatearEntero(valor) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return ''; }
        return String(Math.round(num)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Tabla principal de forecast por producto (server-side, helper reutilizable de utils.js)
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta-forecast',
        url:   'controllers/forecast_controller.php?action=listar',
        input: '#consulta-forecast',
        extra: function(d) {
            d.familia     = $('#filtro-familia').val();       // '' = todas
            d.sub_familia = $('#filtro-sub-familia').val();   // '' = todas
            d.anio        = $('#filtro-anio').val();          // '' = todos
            d.mes         = $('#filtro-mes').val();           // '' = todos
        },
        // Orden por defecto (multi-columna): Código Producto, Año y Mes ascendente.
        orden: [[2, 'asc'], [0, 'asc'], [1, 'asc']],
        columnas: [
            { data: 'anio', className: 'text-center' },
            { data: 'mes',  className: 'text-center' },
            { data: 'producto_codigo',  render: $.fn.dataTable.render.text() },
            { data: 'producto_nombre',  render: $.fn.dataTable.render.text() },
            { data: 'familia',          render: $.fn.dataTable.render.text() },
            { data: 'sub_familia',      render: $.fn.dataTable.render.text() },
            { data: 'demanda_forecast', className: 'text-end', render: formatearEntero },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: function() { return ''; } },
            { data: 'created_at', className: 'text-center', render: $.fn.dataTable.render.text() },
            {
                // Acciones: botón "Gráfico producto" (historia real + Cantidad Forecast del producto).
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    const attr = function(v) {
                        const s = (v === null || v === undefined) ? '' : String(v);
                        return $('<div>').text(s).html().replace(/"/g, '&quot;');   // escapa <>& y comillas
                    };
                    const ym = String(row.anio) + '-' + String(row.mes).padStart(2, '0');

                    const btnGrafico =
                          '<button type="button" class="btn btn-sm btn-outline-dark btn-grafico-producto"'
                        + ' data-codigo="'     + attr(row.producto_codigo)  + '"'
                        + ' data-nombre="'     + attr(row.producto_nombre)  + '"'
                        + ' data-familia="'    + attr(row.familia)          + '"'
                        + ' data-subfamilia="' + attr(row.sub_familia)      + '"'
                        + ' data-anio="'       + attr(row.anio)             + '"'
                        + ' data-mes="'        + attr(row.mes)              + '"'
                        + ' data-demanda="'    + attr(row.demanda_forecast) + '"'
                        + ' data-ym="'         + ym + '"'
                        + ' title="Gráfico producto"><i class="bi bi-graph-up"></i></button>';

                    const btnMrp =
                          '<button type="button" class="btn btn-sm btn-outline-danger btn-parametros-mrp"'
                        + ' data-codigo="' + attr(row.producto_codigo) + '"'
                        + ' data-nombre="' + attr(row.producto_nombre) + '"'
                        + ' title="Parámetros para MRP"><i class="bi bi-sliders"></i></button>';

                    return btnGrafico + ' ' + btnMrp;
                }
            }
        ]
    });

    // Recargar la tabla al cambiar el filtro de Familia, Sub-Familia, Año o Mes.
    $('#filtro-familia, #filtro-sub-familia, #filtro-anio, #filtro-mes').on('change', function() {
        tablaConsulta.ajax.reload();
    });

    // Botón "Limpiar": deja todos los filtros por defecto y recarga (motor de utils.js).
    inicializarBotonLimpiar({
        boton:  '#btn-limpiar-filtros',
        tabla:  tablaConsulta,
        campos: ['#consulta-forecast', '#filtro-familia', '#filtro-sub-familia', '#filtro-anio', '#filtro-mes'],
        delay:  250
    });

    // Carga las opciones de los filtros de Año, Familia y Sub-Familia (conservan la selección actual).
    function cargarFiltros() {
        $.ajax({
            url: 'controllers/forecast_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                // Familia (texto escapado vía .text()).
                const $fam   = $('#filtro-familia');
                const famSel = $fam.val();
                $fam.empty().append('<option value="">Todas</option>');
                (res.familias || []).forEach(function(f) {
                    $fam.append($('<option>').val(f).text(f));
                });
                $fam.val(famSel);

                // Sub-Familia (texto escapado).
                const $sub   = $('#filtro-sub-familia');
                const subSel = $sub.val();
                $sub.empty().append('<option value="">Todas</option>');
                (res.sub_familias || []).forEach(function(sf) {
                    $sub.append($('<option>').val(sf).text(sf));
                });
                $sub.val(subSel);

                // Año.
                const $anio   = $('#filtro-anio');
                const anioSel = $anio.val();
                const optsAnio = (res.anios || []).map(function(a) {
                    return '<option value="' + a + '">' + a + '</option>';
                }).join('');
                $anio.html('<option value="">Todos</option>' + optsAnio).val(anioSel);
            }
        });
    }
    cargarFiltros();

    // ============================================================
    //  Parámetros para MRP (columna Acciones): parámetros SAP del producto (bodega 010).
    // ============================================================

    // Etiquetas legibles de los campos de la consulta, en el orden de despliegue.
    const ETIQUETAS_MRP = {
        ItemCode:   'Código Producto',
        ItemName:   'Nombre Producto',
        Familia:    'Familia',
        SubFamilia: 'Sub-Familia',
        LeadTime:   'Lead Time (días)',
        MinOrdrQty: 'Cantidad Mínima de Pedido',
        OrdrMulti:  'Múltiplo de Pedido',
        MinStock:   'Stock Mínimo',
        MaxStock:   'Stock Máximo',
        MinOrder:   'Pedido Mínimo',
        OnHand:     'Stock Disponible (En Mano)',
        IsCommited: 'Comprometido',
        OnOrder:    'En Pedido (Solicitado)',
        CardCode:   'Cód. Proveedor',
        CardName:   'Proveedor'
    };

    // Campos que son cantidades en unidades: se muestran como entero (sin los ".000000" de SAP).
    const CAMPOS_CANTIDAD_MRP = {
        LeadTime: true, MinOrdrQty: true, OrdrMulti: true, MinStock: true, MaxStock: true,
        MinOrder: true, OnHand: true, IsCommited: true, OnOrder: true
    };

    // Cantidad en unidades: entero si es exacto; si no, hasta 2 decimales sin ceros de relleno.
    // Miles con punto y decimales con coma (estilo chileno).
    function formatearCantidad(valor) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return $('<div>').text(valor).html(); }
        const redondeado = (num % 1 === 0) ? num.toFixed(0) : String(parseFloat(num.toFixed(2)));
        const partes = redondeado.split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    // Arma las filas Campo | Valor de los parámetros MRP (mismo estilo que "Ver detalle").
    function filasParametrosMrp(registro) {
        return Object.keys(ETIQUETAS_MRP).map(function(campo) {
            const valor = registro[campo];
            let texto;
            if (valor === null || valor === undefined || valor === '') {
                texto = '<span class="text-muted">—</span>';
            } else if (CAMPOS_CANTIDAD_MRP[campo]) {
                texto = formatearCantidad(valor);
            } else {
                texto = $('<div>').text(valor).html();
            }
            return '<tr>'
                 + '<th class="fw-semibold small text-nowrap" style="width: 45%;">' + ETIQUETAS_MRP[campo] + '</th>'
                 + '<td class="small">' + texto + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Botón "Parámetros para MRP": pide la consulta al controlador y la muestra en el modal.
    $('#tabla-consulta-forecast tbody').on('click', '.btn-parametros-mrp', function() {
        const cod    = $(this).data('codigo');
        const $tbody = $('#tabla-parametros-mrp');

        $('#fc-mrp-titulo').text('');
        $tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">'
                  + '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalForecastParametrosMrp')).show();

        $.ajax({
            url: 'controllers/forecast_controller.php?action=parametros_mrp',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $tbody.html('<tr><td colspan="2" class="text-danger small py-3">'
                              + (res.message || 'No se pudo cargar.') + '</td></tr>');
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">'
                              + 'Sin parámetros MRP para este producto (bodega 010).</td></tr>');
                    return;
                }
                $tbody.html(filasParametrosMrp(filas[0]));
            },
            error: function() {
                $tbody.html('<tr><td colspan="2" class="text-danger small py-3">'
                          + 'Error al cargar los parámetros MRP.</td></tr>');
            }
        });
    });

    // ============================================================
    //  Listado "Parámetros MRP" (botón del header): DataTable dentro de un modal con todos
    //  los productos de la bodega 010 (misma consulta, sin filtro por producto).
    // ============================================================

    let tablaMrpLista = null; // instancia DataTable (se reconstruye en cada apertura)

    const renderTxt = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderCant = function(d) { return formatearCantidad(d); };   // cantidades en unidades (entero)

    // Al terminar de abrirse el modal (ancho final ya disponible), carga y arma el DataTable.
    $('#modalForecastParametrosMrpLista').on('shown.bs.modal', function() {
        const $estado = $('#fc-mrp-lista-estado');
        const $wrap   = $('#fc-mrp-lista-wrap');

        // Usa el mismo filtro que la consulta principal (código o nombre de producto).
        const filtroConsulta = $('#consulta-forecast').val() || '';

        $estado.text('Cargando...').show();
        $wrap.hide();

        $.ajax({
            url: 'controllers/forecast_controller.php?action=parametros_mrp_todos',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar la consulta.').show();
                    return;
                }

                const filas = res.data || [];

                // Reinicia la tabla previa antes de recargar los datos.
                if (tablaMrpLista) {
                    tablaMrpLista.destroy();
                    $('#tabla-parametros-mrp-lista tbody').empty();
                }

                $estado.hide();
                $wrap.show();

                tablaMrpLista = $('#tabla-parametros-mrp-lista').DataTable({
                    data: filas,
                    // Sin el buscador por defecto ('f'): se usa el input propio #consulta-mrp-lista.
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row'<'col-sm-12'p>>",
                    autoWidth: false,
                    scrollX: true,
                    search: { search: filtroConsulta },   // hereda el filtro de la consulta principal
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    columns: [
                        { data: 'ItemCode',   render: renderTxt },
                        { data: 'ItemName',   render: renderTxt },
                        { data: 'Familia',    render: renderTxt },
                        { data: 'SubFamilia', render: renderTxt },
                        { data: 'LeadTime',   className: 'text-end', render: renderCant },
                        { data: 'MinOrdrQty', className: 'text-end', render: renderCant },
                        { data: 'OrdrMulti',  className: 'text-end', render: renderCant },
                        { data: 'MinStock',   className: 'text-end', render: renderCant },
                        { data: 'MaxStock',   className: 'text-end', render: renderCant },
                        { data: 'MinOrder',   className: 'text-end', render: renderCant },
                        { data: 'OnHand',     className: 'text-end', render: renderCant },
                        { data: 'IsCommited', className: 'text-end', render: renderCant },
                        { data: 'OnOrder',    className: 'text-end', render: renderCant },
                        { data: 'CardCode',   render: renderTxt },
                        { data: 'CardName',   render: renderTxt }
                    ]
                });

                // Refleja en el input propio el filtro heredado de la consulta principal.
                $('#consulta-mrp-lista').val(filtroConsulta);

                // La tabla se construyó dentro del modal: recalcula el ancho de columnas.
                tablaMrpLista.columns.adjust();
            },
            error: function() {
                $estado.text('Error al cargar la consulta.').show();
            }
        });
    });

    // Buscador propio del modal (reemplaza al buscador por defecto del DataTable).
    $('#consulta-mrp-lista').on('keyup input', function() {
        if (tablaMrpLista) {
            tablaMrpLista.search(this.value).draw();
        }
    });

});
