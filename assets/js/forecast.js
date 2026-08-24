$(document).ready(function() {

    // Redondea una cantidad a la unidad (0 decimales) con separador de miles (estilo chileno).
    function formatearEntero(valor) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return ''; }
        return String(Math.round(num)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Tabla principal de forecast AGRUPADA POR PRODUCTO (server-side, helper de utils.js).
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta-forecast',
        url:   'controllers/forecast_controller.php?action=listar',
        input: '#consulta-forecast',
        extra: function(d) {
            d.familia     = $('#filtro-familia').val();       // '' = todas
            d.sub_familia = $('#filtro-sub-familia').val();   // '' = todas
            d.calidad     = $('#filtro-calidad').val();       // '' = todas
        },
        // Orden por defecto: Código Producto ascendente.
        orden: [[0, 'asc']],
        columnas: [
            { data: 'producto_codigo',  render: $.fn.dataTable.render.text() },
            { data: 'producto_nombre',  render: $.fn.dataTable.render.text() },
            { data: 'familia',          render: $.fn.dataTable.render.text() },
            { data: 'sub_familia',      render: $.fn.dataTable.render.text() },
            { data: 'version',          className: 'text-center', render: $.fn.dataTable.render.text() },
            {
                // Cálculo Forecast: insumos aplicados en el cálculo del forecast del producto.
                // Extensible: hoy solo "Presupuesto"; a futuro se agregan más factores (uno por línea).
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-nowrap small',
                render: function(data, type, row) {
                    const icono = function(ok) {
                        return ok
                            ? '<i class="bi bi-check-lg text-success"></i>'
                            : '<i class="bi bi-x-lg text-danger"></i>';
                    };
                    return 'Presupuesto: ' + icono(Number(row.usa_presupuesto) === 1);
                }
            },
            {
                // Calidad del registro (Alta/Media/Baja): respaldo de datos + confiabilidad
                // del pronóstico (historia + MAPE + método). El tooltip muestra las semanas de historia.
                data: 'calidad',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(d, type, row) {
                    const cal = d || '';
                    if (!cal) { return '<span class="text-muted">—</span>'; }
                    const color = { 'Alta': 'success', 'Media': 'warning', 'Baja': 'danger' }[cal] || 'secondary';
                    const sem   = (row.semanas_historia !== null && row.semanas_historia !== undefined) ? row.semanas_historia : '';
                    const title = (sem !== '') ? ' title="' + sem + ' semanas de historia"' : '';
                    return '<span class="badge bg-' + color + '"' + title + '>' + cal + '</span>';
                }
            },
            { data: 'total_forecast',   className: 'text-end', render: formatearEntero },
            { data: 'forecast_sig_semana', className: 'text-end', render: formatearEntero },
            {
                // Acciones: botones "Gráfico producto" y "Parámetros MRP" (por producto).
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    const attr = function(v) {
                        const s = (v === null || v === undefined) ? '' : String(v);
                        return $('<div>').text(s).html().replace(/"/g, '&quot;');   // escapa <>& y comillas
                    };

                    const btnGrafico =
                          '<button type="button" class="btn btn-sm btn-outline-dark btn-grafico-producto"'
                        + ' data-codigo="'     + attr(row.producto_codigo)  + '"'
                        + ' data-nombre="'     + attr(row.producto_nombre)  + '"'
                        + ' data-familia="'    + attr(row.familia)          + '"'
                        + ' data-subfamilia="' + attr(row.sub_familia)      + '"'
                        + ' data-total="'      + attr(row.total_forecast)      + '"'
                        + ' data-sig="'        + attr(row.forecast_sig_semana) + '"'
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

    // Recargar la tabla al cambiar el filtro de Familia o Sub-Familia.
    $('#filtro-familia, #filtro-sub-familia, #filtro-calidad').on('change', function() {
        tablaConsulta.ajax.reload();
    });

    // Botón "Limpiar": deja todos los filtros por defecto y recarga (motor de utils.js).
    inicializarBotonLimpiar({
        boton:  '#btn-limpiar-filtros',
        tabla:  tablaConsulta,
        campos: ['#consulta-forecast', '#filtro-familia', '#filtro-sub-familia', '#filtro-calidad'],
        delay:  250
    });

    // Carga las opciones de los filtros de Familia y Sub-Familia (conservan la selección actual).
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
        MinOrdrQty: 'Cantidad Mínima de Pedido',
        OrdrMulti:  'Múltiplo de Pedido',
        MinStock:   'Stock Mínimo',
        MaxStock:   'Stock Máximo',
        MinOrder:   'Pedido Mínimo',
        OnHand:        'Stock Disponible WMS (En Mano)',
        CompVentas:    'Comprometido Ventas',
        CompProduccion:'Comprometido Producción',
        EnPedido:      'En Pedido (Compras)',
        EnProduccion:  'En Producción'
    };

    // Campos de negocio (UDF de OITM), en bloque aparte del modal. Los codificados
    // (Origen/Marca Propia/E-Commerce) ya vienen resueltos a su descripción desde el modelo.
    // Los dos que se solapan con un campo estándar llevan "(Negocio)" para diferenciarlos.
    const ETIQUETAS_MRP_NEGOCIO = {
        StatusArticulo:   'Status Artículo',
        Origen:           'Origen Artículo',
        MarcaPropia:      'Marca Propia',
        ArticuloNuevo:    'Artículo Nuevo',
        ECommerce:        'E-Commerce',
        Campana:          'Campaña',
        Gramaje:          'Gramaje',
        UnidCaja:         'Unidades por Caja',
        UnidEmbProv:      'Unid. Emb. Proveedor',
        Kilos:            'Kilos',
        Moneda:           'Moneda',
        ProveedorNegocio: 'Cód. Proveedor (Negocio)',
        ProveedorNombre:  'Proveedor (Negocio)',
        LeadTimeNegocio:  'Lead Time (Negocio)'
    };

    // Campos que son cantidades en unidades: se muestran como entero (sin los ".000000" de SAP).
    const CAMPOS_CANTIDAD_MRP = {
        MinOrdrQty: true, OrdrMulti: true, MinStock: true, MaxStock: true,
        MinOrder: true, OnHand: true, CompVentas: true, CompProduccion: true,
        EnPedido: true, EnProduccion: true,
        Gramaje: true, UnidCaja: true, Kilos: true, LeadTimeNegocio: true
    };

    // Sufijo de unidad que se agrega tras el valor formateado (solo si el campo tiene dato).
    const CAMPOS_SUFIJO_MRP = {
        LeadTimeNegocio: ' días'
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

    // Fila de encabezado de sección dentro de la tabla Campo | Valor.
    function seccionMrp(titulo) {
        return '<tr class="table-secondary">'
             + '<th colspan="2" class="small fw-bold text-uppercase">' + titulo + '</th>'
             + '</tr>';
    }

    // Arma las filas Campo | Valor de un grupo de campos (mismo estilo que "Ver detalle").
    function filasCampoValorMrp(etiquetas, registro) {
        return Object.keys(etiquetas).map(function(campo) {
            const valor = registro[campo];
            let texto;
            if (valor === null || valor === undefined || valor === '') {
                texto = '<span class="text-muted">—</span>';
            } else if (CAMPOS_CANTIDAD_MRP[campo]) {
                texto = formatearCantidad(valor) + (CAMPOS_SUFIJO_MRP[campo] || '');
            } else {
                texto = $('<div>').text(valor).html();
            }
            return '<tr>'
                 + '<th class="fw-semibold small text-nowrap" style="width: 45%;">' + etiquetas[campo] + '</th>'
                 + '<td class="small">' + texto + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Cuerpo del modal: parámetros estándar SAP + bloque de campos de negocio (UDF).
    function filasParametrosMrp(registro) {
        return seccionMrp('Parámetros MRP (SAP)')
             + filasCampoValorMrp(ETIQUETAS_MRP, registro)
             + seccionMrp('Campos de negocio (UDF)')
             + filasCampoValorMrp(ETIQUETAS_MRP_NEGOCIO, registro);
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

});
