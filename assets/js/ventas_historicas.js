$(document).ready(function() {

    // ============================================================
    //  Formatos de celdas
    // ============================================================

    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // Número estilo chileno: miles con punto y decimales con coma.
    function formatearNumero(valor, decimales) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }
        const num = parseFloat(valor);
        if (isNaN(num)) {
            return '';
        }
        const partes = num.toFixed(decimales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    const renderTexto  = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderNumero = function(d) { return (d === null || d === undefined) ? '' : d; };
    const renderMoneda = function(d) { return formatearNumero(d, 0) === '' ? '' : '$' + formatearNumero(d, 0); };

    // Cantidad: entero si es exacto, con 2 decimales si es fraccionario.
    function renderCantidad(d) {
        if (d === null || d === undefined || d === '') {
            return '';
        }
        const n = parseFloat(d);
        if (isNaN(n)) {
            return '';
        }
        return formatearNumero(n, n % 1 === 0 ? 0 : 2);
    }

    // Fecha 'YYYY-MM-DD' -> 'DD-MM-YYYY'.
    function renderFecha(d) {
        if (!d) {
            return '';
        }
        const p = String(d).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : d;
    }

    // Mes (1-12) -> nombre.
    function renderMes(d) {
        const n = parseInt(d, 10);
        return (n >= 1 && n <= 12) ? MESES[n] : '';
    }

    // ============================================================
    //  Tabla principal (DataTable server-side)
    // ============================================================

    // Filtro de Fecha Documento (mes/año), lo setea el selector Flatpickr.
    let filtroFechaAnio = '';
    let filtroFechaMes  = '';

    const tablaVentas = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/ventas_historicas_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'desc']],   // por id descendente (lo más reciente primero)
        extra: function(d) {
            d.version    = $('#filtro-version').val();   // '' o versión exacta
            d.grupo      = $('#filtro-grupo').val();     // '' o grupo de artículo exacto
            d.familia    = $('#filtro-familia').val();   // '' o familia exacta
            d.fecha_anio = filtroFechaAnio;              // '' o año de fecha_docto
            d.fecha_mes  = filtroFechaMes;               // '' o mes de fecha_docto
        },
        columnas: [
            { data: 'id',                   className: 'text-center' },
            { data: 'version',              className: 'text-center', render: renderTexto },
            { data: 'fecha_docto',          className: 'text-center', render: renderFecha },
            { data: 'nro_docto',            className: 'text-center', render: renderNumero },
            { data: 'tipo_docto',           className: 'text-center', render: renderNumero },
            { data: 'rut_cliente',          render: renderTexto },
            { data: 'razon_social',         render: renderTexto },
            { data: 'cod_articulo',         render: renderTexto },
            { data: 'descripcion_articulo', render: renderTexto },
            { data: 'grupo_articulo',       render: renderTexto },
            { data: 'familia',              render: renderTexto },
            { data: 'cant_docto',           className: 'text-end', render: renderCantidad },
            { data: 'venta_bruta',          className: 'text-end', render: renderMoneda },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id) {
                    return '<button type="button" class="btn btn-sm btn-outline-secondary btn-ver-venta" '
                         + 'data-id="' + id + '" title="Ver detalle"><i class="bi bi-eye"></i></button>';
                }
            }
        ]
    });

    // Recargar la tabla al cambiar los filtros de Versión, Grupo o Familia.
    $('#filtro-version, #filtro-grupo, #filtro-familia').on('change', function() {
        tablaVentas.ajax.reload();
    });

    // Filtro Fecha Documento: selector de mes/año (Flatpickr + plugin monthSelect).
    const fpFecha = flatpickr('#filtro-fecha', {
        locale: 'es',
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
        onChange: function(fechas) {
            if (fechas.length) {
                filtroFechaAnio = fechas[0].getFullYear();
                filtroFechaMes  = fechas[0].getMonth() + 1;   // getMonth() es 0-11
            } else {
                filtroFechaAnio = '';
                filtroFechaMes  = '';
            }
            tablaVentas.ajax.reload();
        },
        onReady: function(selectedDates, dateStr, instance) {
            // Opción para quitar el filtro, al pie del propio calendario (sin botón X en el input).
            const $limpiar = $('<div class="text-center small text-primary border-top py-1" style="cursor: pointer;">Quitar filtro</div>');
            $limpiar.on('click', function() {
                instance.clear();   // dispara onChange -> limpia las variables y recarga
                instance.close();
            });
            $(instance.calendarContainer).append($limpiar);
        }
    });

    // Botón "Limpiar": deja todos los filtros por defecto y recarga (motor de utils.js).
    inicializarBotonLimpiar({
        boton:  '#btn-limpiar-filtros',
        tabla:  tablaVentas,
        campos: ['#consulta', '#filtro-version', '#filtro-grupo', '#filtro-familia'],
        delay:  250,   // un cuarto de segundo, con el botón en "cargando"
        alLimpiar: function() {
            fpFecha.clear(false);   // limpia el picker SIN disparar su onChange (evita recarga doble)
            filtroFechaAnio = '';
            filtroFechaMes  = '';
        }
    });

    // Llena el select de Versión con los valores disponibles (conserva la selección).
    function cargarFiltros() {
        $.ajax({
            url: 'controllers/ventas_historicas_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    return;
                }

                const verSel = $('#filtro-version').val();
                const opcVer = (res.versiones || []).map(function(v) {
                    return '<option value="' + v + '">' + v + '</option>';
                }).join('');
                $('#filtro-version').html('<option value="">Todas</option>' + opcVer).val(verSel);

                // Grupo y Familia: se arman con jQuery para escapar bien nombres con caracteres especiales.
                const $gru   = $('#filtro-grupo');
                const gruSel = $gru.val();
                $gru.empty().append('<option value="">Todos</option>');
                (res.grupos || []).forEach(function(g) {
                    $gru.append($('<option>').val(g).text(g));
                });
                $gru.val(gruSel);

                const $fam   = $('#filtro-familia');
                const famSel = $fam.val();
                $fam.empty().append('<option value="">Todas</option>');
                (res.familias || []).forEach(function(f) {
                    $fam.append($('<option>').val(f).text(f));
                });
                $fam.val(famSel);
            }
        });
    }

    // ============================================================
    //  Ver detalle (modal con todos los campos del registro)
    // ============================================================

    // Campos a mostrar en el detalle: [campo, etiqueta, tipo de formato].
    const CAMPOS_DETALLE = [
        ['id',                   'Id',                   'numero'],
        ['version',              'Versión',              'texto'],
        ['nro_docto',            'Nro. Documento',       'numero'],
        ['tipo_docto',           'Tipo Documento',       'numero'],
        ['nota_venta',           'Nota Venta',           'texto'],
        ['cond_venta',           'Condición de Venta',   'texto'],
        ['fecha_docto',          'Fecha Documento',      'fecha'],
        ['rut_cliente',          'RUT Cliente',          'texto'],
        ['razon_social',         'Razón Social',         'texto'],
        ['tipo_cliente',         'Tipo Cliente',         'texto'],
        ['lista_precio',         'Lista de Precio',      'texto'],
        ['cod_ejec',             'Cód. Ejecutivo',       'numero'],
        ['ejec_comercial',       'Ejecutivo Comercial',  'texto'],
        ['f_creacion',           'F. Creación',          'fecha'],
        ['f_primera_venta',      'F. 1ra Venta',         'fecha'],
        ['f_ultima_venta',       'F. Última Venta',      'fecha'],
        ['cod_articulo',         'Cód. Artículo',        'texto'],
        ['descripcion_articulo', 'Descripción Artículo', 'texto'],
        ['grupo_articulo',       'Grupo Artículo',       'texto'],
        ['familia',              'Familia',              'texto'],
        ['sub_familia',          'Sub-Familia',          'texto'],
        ['proveedor',            'Proveedor',            'texto'],
        ['cant_pedido',          'Cant. Pedido',         'cantidad'],
        ['pr_pedido',            'Pr. Pedido',           'moneda'],
        ['subtotal_pedido',      'Sub-Total Pedido',     'moneda'],
        ['cant_docto',           'Cant. Documento',      'cantidad'],
        ['pr_base',              'Pr. Base',             'moneda'],
        ['pct_descuento',        '% Descuento',          'porcentaje'],
        ['subtotal_docto',       'Sub-Total Documento',  'moneda'],
        ['pr_prom_pond',         'Pr. Prom. Pond.',      'moneda'],
        ['costo_pr_pp',          'Costo a Pr.P.P.',      'moneda'],
        ['ganancia_bruta',       'Ganancia Bruta',       'moneda'],
        ['pct_margen',           '% Margen',             'porcentaje'],
        ['gramaje',              'Gramaje',              'cantidad'],
        ['unid_x_caja',          'Unid. x Caja',         'numero'],
        ['sociedad',             'Sociedad',             'texto'],
        ['venta_bruta',          'Venta Bruta',          'moneda'],
        ['descuento',            'Descuento',            'moneda'],
        ['anio',                 'Año',                  'numero'],
        ['mes',                  'Mes',                  'mes'],
        ['pr_vta_prom',          'Pr. Vta. Prom.',       'moneda'],
        ['created_at',           'Fecha de carga',       'texto'],
    ];

    // Formatea un valor según su tipo (vacío -> guion).
    function valorDetalle(valor, tipo) {
        if (valor === null || valor === undefined || valor === '') {
            return '<span class="text-muted">—</span>';
        }
        switch (tipo) {
            case 'fecha':      return renderFecha(valor);
            case 'moneda':     return renderMoneda(valor);
            case 'cantidad':   return renderCantidad(valor);
            case 'porcentaje': return formatearNumero(valor, 2) + ' %';
            case 'mes':        return renderMes(valor) || $('<div>').text(valor).html();
            default:           return $('<div>').text(valor).html();
        }
    }

    // Arma las filas Campo | Valor del detalle.
    function filasDetalle(registro) {
        return CAMPOS_DETALLE.map(function(c) {
            return '<tr>'
                 + '<th class="fw-semibold small text-nowrap" style="width: 40%;">' + c[1] + '</th>'
                 + '<td class="small">' + valorDetalle(registro[c[0]], c[2]) + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Botón "Ver detalle": carga el registro y abre el modal con la tabla vertical.
    $('#tabla-consulta tbody').on('click', '.btn-ver-venta', function() {
        const id     = $(this).data('id');
        const $tbody = $('#tabla-detalle-venta');

        $tbody.html('<tr><td class="text-muted small">Cargando...</td></tr>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaDetalle')).show();

        $.ajax({
            url: 'controllers/ventas_historicas_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $tbody.html(filasDetalle(res.data));
                } else {
                    $tbody.html('<tr><td class="text-danger small">' + (res.message || 'No se pudo cargar el detalle.') + '</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td class="text-danger small">Error al cargar el detalle.</td></tr>');
            }
        });
    });

    // ============================================================
    //  Carga masiva (.xlsx)
    // ============================================================

    // Solo se aceptan .xlsx; al elegir un archivo válido se envía.
    $('#archivo-excel-ventas').on('change', function() {
        const $input  = $(this);
        const archivo = this.files[0];

        if (!archivo) {
            limpiarErrorCampo($input);
            return;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (extension !== 'xlsx') {
            $input.val('').addClass('is-invalid');
            $('#error-archivo-ventas').html('Solo se permiten archivos Excel (.xlsx).').addClass('d-block');
        } else {
            limpiarErrorCampo($input);
            $('#form-carga-masiva-ventas').trigger('submit');
        }
    });

    // Envía el archivo por AJAX y muestra el resultado de la carga.
    $('#form-carga-masiva-ventas').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaMasivaVentas');
        const modalMensaje = '#modal-mensajes-ventas';
        const $archivo     = $('#archivo-excel-ventas');

        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo-ventas').html('Debe ingresar un archivo .xlsx.').addClass('d-block');
            return;
        }

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/ventas_historicas_controller.php?action=procesar',
            type: 'POST',
            data: new FormData(this),   // incluye el archivo y el csrf_token
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaVentas.ajax.reload(null, false);   // muestra los nuevos registros
                    cargarFiltros();                        // aparece la nueva versión en el filtro
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading(btn);
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                $archivo.val('');
            }
        });
    });

    // Carga inicial de los filtros (Versión y Año).
    cargarFiltros();

});
