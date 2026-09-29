$(document).ready(function() {

    let tablaCustom = null;

    // Inicializa (una vez) y devuelve el DataTable del listado de forecast personalizado cargado.
    function tablaPersonalizado() {
        if (tablaCustom) { return tablaCustom; }
        tablaCustom = $('#tabla-forecast-personalizado').DataTable({
            data: [],
            // Mismo layout que el datatable principal: arriba [cantidad por página | info],
            // abajo la paginación (sin buscador global).
            dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12'p>>",
            autoWidth: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: [
                { data: 'producto_codigo', render: $.fn.dataTable.render.text() },
                { data: 'producto_nombre', render: function(v) {
                    return v ? $('<div>').text(v).html() : '<span class="text-muted">—</span>';
                } },
                { data: 'cantidad', className: 'text-center', searchable: false },
                {
                    data: 'producto_codigo', orderable: false, searchable: false, className: 'text-center',
                    render: function(cod) {
                        const c = $('<div>').text(cod == null ? '' : String(cod)).html();
                        return '<div class="btn-group btn-group-sm" role="group">'
                             + '<button type="button" class="btn btn-outline-dark btn-detalle-custom" '
                             +   'data-codigo="' + c + '" title="Ver detalle (semana a semana)">'
                             +   '<i class="bi bi-eye"></i></button>'
                             + '<button type="button" class="btn btn-outline-danger btn-eliminar-custom" '
                             +   'data-codigo="' + c + '" title="Eliminar forecast de este producto">'
                             +   '<i class="bi bi-trash"></i></button>'
                             + '</div>';
                    }
                }
            ],
            order: [[0, 'asc']]
        });
        return tablaCustom;
    }

    // Carga/recarga los datos del listado desde el servidor.
    function cargarTablaCustom() {
        $.ajax({
            url: 'controllers/forecast_controller.php?action=listar_personalizado',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                const t = tablaPersonalizado();
                t.clear().rows.add((res && res.data) ? res.data : []).draw();
            }
        });
    }

    // Cargar el listado cada vez que se abre el modal.
    $('#modalCargaForecastPersonalizado').on('shown.bs.modal', cargarTablaCustom);

    // Buscador: filtra el listado por código o nombre (la columna Cantidad no es buscable).
    $('#consulta-forecast-personalizado').on('input', function() {
        tablaPersonalizado().search(this.value).draw();
    });

    // Envío del formulario de Carga Forecast Personalizado (FormData: archivo + csrf_token).
    $('#form-carga-forecast-personalizado').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaForecastPersonalizado');
        const modalMensaje = '#modal-mensajes-forecast-personalizado';
        const $archivo     = $('#archivo-forecast-personalizado');

        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo-forecast-personalizado')
                .html('Debe ingresar un archivo .xlsx.')
                .addClass('d-block');
            return;
        }
        $archivo.removeClass('is-invalid');
        $('#error-archivo-forecast-personalizado').removeClass('d-block');

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/forecast_controller.php?action=cargar_personalizado',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    $archivo.val('');
                    cargarTablaCustom();   // refresca el listado con lo recién cargado
                } else {
                    let msg = res.message || 'No se pudo procesar el archivo.';
                    if (res.errores && res.errores.length) {
                        msg += '<ul class="text-start small mb-0 mt-2">'
                             + res.errores.map(function(x) { return '<li>' + x + '</li>'; }).join('')
                             + '</ul>';
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', msg, 'danger');
                }
            },
            error: function() {
                resetBtnLoading(btn);
                mostrarMensajeFormulario(modalMensaje, 'Atención', 'Error de comunicación con el servidor.', 'danger');
            }
        });
    });

    // yyyy-mm-dd -> dd-mm-yyyy
    function fmtFecha(s) {
        if (!s) { return ''; }
        const p = String(s).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : s;
    }

    // Entero con separador de miles (estilo chileno). Local: formatearEntero NO es global.
    function fmtNum(valor) {
        const n = parseFloat(valor);
        if (isNaN(n)) { return ''; }
        return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Modales apilados (BS5): el modal de detalle debe quedar POR ENCIMA del de carga y su backdrop.
    $('#modalDetalleForecastPersonalizado').on('shown.bs.modal', function() {
        $(this).css('z-index', 1065);
        $('.modal-backdrop').last().css('z-index', 1060);
    });
    // Al cerrar el detalle, el modal de carga sigue abierto: reponer el estado de scroll del body.
    $('#modalDetalleForecastPersonalizado').on('hidden.bs.modal', function() {
        if ($('#modalCargaForecastPersonalizado').hasClass('show')) { $('body').addClass('modal-open'); }
    });

    let codigoDetalleActual = null;
    let nombreDetalleActual = '';

    // Carga (o recarga) las líneas del detalle del producto en curso.
    function cargarDetalle(codigo) {
        $('#detalle-fp-estado').text('Cargando...').show();
        $('#detalle-fp-wrap').hide();
        $('#tabla-detalle-forecast-personalizado').empty();
        $('#detalle-fp-total').text('—');

        $.ajax({
            url: 'controllers/forecast_controller.php?action=detalle_personalizado',
            type: 'GET',
            data: { producto_codigo: codigo },
            dataType: 'json',
            success: function(res) {
                const filas = (res && res.data) ? res.data : [];
                if (!filas.length) {
                    $('#detalle-fp-estado').text('Este producto no tiene registros cargados.').show();
                    return;
                }
                const cEsc = $('<div>').text(codigo).html();
                let html = '', total = 0;
                filas.forEach(function(f) {
                    total += Number(f.demanda_forecast) || 0;
                    html += '<tr><td>' + fmtFecha(f.semana_inicio) + '</td>'
                          + '<td class="text-end">' + fmtNum(f.demanda_forecast) + '</td>'
                          + '<td class="text-center">'
                          +   '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-linea-fp" '
                          +     'data-codigo="' + cEsc + '" data-semana="' + f.semana_inicio + '" '
                          +     'data-demanda="' + (Number(f.demanda_forecast) || 0) + '" title="Eliminar este registro">'
                          +     '<i class="bi bi-trash"></i></button>'
                          + '</td></tr>';
                });
                $('#tabla-detalle-forecast-personalizado').html(html);
                $('#detalle-fp-total').text(fmtNum(total));
                $('#detalle-fp-estado').hide();
                $('#detalle-fp-wrap').show();
            },
            error: function() {
                $('#detalle-fp-estado').text('No se pudo cargar el detalle.').show();
            }
        });
    }

    // Ver detalle: abre el modal, pinta la cabecera del producto y carga sus semanas.
    $('#tabla-forecast-personalizado tbody').on('click', '.btn-detalle-custom', function() {
        const codigo = $(this).data('codigo');
        if (!codigo) { return; }
        codigoDetalleActual = codigo;

        const t    = tablaPersonalizado();
        const fila = t.row($(this).closest('tr')).data() || {};
        nombreDetalleActual = fila.producto_nombre || '';
        const esc  = function(v) { return $('<div>').text(v == null ? '' : String(v)).html(); };

        // Cabecera con la información del producto (código y nombre).
        $('#tabla-detalle-fp-info').html(
            '<tr>'
          +   '<td>' + esc(codigo) + '</td>'
          +   '<td>' + (fila.producto_nombre ? esc(fila.producto_nombre) : '<span class="text-muted">—</span>') + '</td>'
          + '</tr>'
        );

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleForecastPersonalizado')).show();
        cargarDetalle(codigo);
    });

    // Modal de confirmación de borrado de línea, apilado ENCIMA del modal de detalle.
    $('#modalEliminarLineaFp').on('shown.bs.modal', function() {
        $(this).css('z-index', 1075);
        $('.modal-backdrop').last().css('z-index', 1070);
    });
    $('#modalEliminarLineaFp').on('hidden.bs.modal', function() {
        // Siguen abiertos detalle y carga: repone el estado de scroll del body.
        if ($('#modalDetalleForecastPersonalizado').hasClass('show') || $('#modalCargaForecastPersonalizado').hasClass('show')) {
            $('body').addClass('modal-open');
        }
    });

    // Clic en el botón eliminar de una línea: abre el modal de confirmación (como Perfiles).
    $('#tabla-detalle-forecast-personalizado').on('click', '.btn-eliminar-linea-fp', function() {
        const codigo = $(this).data('codigo');
        const semana = $(this).data('semana');
        if (!codigo || !semana) { return; }

        $('#modal-mensajes-eliminar-linea-fp').empty();
        $('#fp-el-codigo').val(codigo);
        $('#fp-el-semana').val(semana);
        $('#fp-el-codigo-txt').val(codigo);
        $('#fp-el-nombre-txt').val(nombreDetalleActual || '—');
        $('#fp-el-semana-txt').val(fmtFecha(semana));
        $('#fp-el-demanda-txt').val(fmtNum($(this).data('demanda')));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEliminarLineaFp')).show();
    });

    // Confirmar el borrado de la línea. Cierra el modal, recarga el detalle y el listado principal.
    $('#form-eliminar-linea-fp').on('submit', function(e) {
        e.preventDefault();
        const btn          = $('#btnEliminarLineaFp');
        const modalMensaje = '#modal-mensajes-eliminar-linea-fp';

        setBtnLoading(btn, 'Eliminando...');
        $.ajax({
            url: 'controllers/forecast_controller.php?action=eliminar_linea_personalizado',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    cargarDetalle(codigoDetalleActual);   // refresca el detalle de fondo
                    cargarTablaCustom();                  // el conteo del listado cambió
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message || 'No se pudo eliminar.', 'danger');
                }
            },
            error: function() {
                resetBtnLoading(btn);
                mostrarMensajeFormulario(modalMensaje, 'Atención', 'Error de comunicación con el servidor.', 'danger');
            }
        });
    });

    // Modal de confirmación del borrado masivo (todo el producto), apilado sobre el modal de carga.
    $('#modalEliminarProductoFp').on('shown.bs.modal', function() {
        $(this).css('z-index', 1065);
        $('.modal-backdrop').last().css('z-index', 1060);
    });
    $('#modalEliminarProductoFp').on('hidden.bs.modal', function() {
        if ($('#modalCargaForecastPersonalizado').hasClass('show')) { $('body').addClass('modal-open'); }
    });

    // Clic en eliminar de un producto (listado): abre el modal de confirmación con sus datos.
    $('#tabla-forecast-personalizado tbody').on('click', '.btn-eliminar-custom', function() {
        const codigo = $(this).data('codigo');
        if (!codigo) { return; }
        const fila = tablaPersonalizado().row($(this).closest('tr')).data() || {};

        $('#modal-mensajes-eliminar-producto-fp').empty();
        $('#fp-ep-codigo').val(codigo);
        $('#fp-ep-codigo-txt').val(codigo);
        $('#fp-ep-nombre-txt').val(fila.producto_nombre || '—');
        $('#fp-ep-cantidad-txt').val(fila.cantidad != null ? fila.cantidad : '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEliminarProductoFp')).show();
    });

    // Confirmar el borrado masivo del producto. Cierra el modal y recarga el listado.
    $('#form-eliminar-producto-fp').on('submit', function(e) {
        e.preventDefault();
        const btn          = $('#btnEliminarProductoFp');
        const modalMensaje = '#modal-mensajes-eliminar-producto-fp';

        setBtnLoading(btn, 'Eliminando...');
        $.ajax({
            url: 'controllers/forecast_controller.php?action=eliminar_personalizado',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    cargarTablaCustom();   // recarga el listado de fondo
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message || 'No se pudo eliminar.', 'danger');
                }
            },
            error: function() {
                resetBtnLoading(btn);
                mostrarMensajeFormulario(modalMensaje, 'Atención', 'Error de comunicación con el servidor.', 'danger');
            }
        });
    });

});
