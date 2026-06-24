$(document).ready(function() {

    // Render seguro para celdas que pueden venir en NULL.
    const renderTexto  = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderNumero = function(d) { return (d === null || d === undefined) ? '' : d; };

    // Formatea un número al estilo chileno: miles con punto y decimales con coma.
    // (Locale-independiente: no depende del soporte de Intl del navegador.)
    function formatearNumero(valor, decimales) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }
        const num = parseFloat(valor);
        if (isNaN(num)) {
            return '';
        }
        const partes = num.toFixed(decimales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');   // punto de miles
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    // Renders por tipo de medida.
    const renderMoneda     = function(d) { return formatearNumero(d, 0) === '' ? '' : '$' + formatearNumero(d, 0); };  // Venta / MG Neto / PP: a la unidad, con $
    const renderPorcentaje = function(d) { return formatearNumero(d, 1); };   // MG %: a la décima
    const renderDecimal4   = function(d) { return formatearNumero(d, 4); };   // KG: 4 decimales

    // Tabla de presupuesto (server-side). Vive en el archivo raíz del mantenedor (presupuesto.php).
    const tablaPresupuesto = inicializarTablaConsulta({
        tabla: '#tabla-consulta-presupuesto',
        url:   'controllers/presupuesto_controller.php?action=listar',
        input: '#consulta-presupuesto',
        orden: [[0, 'desc']],   // por id, descendente
        columnas: [
            { data: 'id',            className: 'text-center' },
            { data: 'anio',          className: 'text-center', render: renderNumero },
            { data: 'mes',           className: 'text-center', render: renderNumero },
            { data: 'canal',         render: renderTexto },
            { data: 'sub_canal',     render: renderTexto },
            { data: 'familia',       render: renderTexto },
            { data: 'sub_familia',   render: renderTexto },
            { data: 'venta',         className: 'text-end', render: renderMoneda },
            { data: 'mg_porcentaje', className: 'text-end', render: renderPorcentaje },
            { data: 'mg_neto',       className: 'text-end', render: renderMoneda },
            { data: 'pp',            className: 'text-end', render: renderMoneda },
            { data: 'kg',            className: 'text-end', render: renderDecimal4 },
            {
                // Acciones: vista previa de los productos de la familia de la fila.
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    const fam     = (row.familia === null || row.familia === undefined) ? '' : String(row.familia);
                    const famAttr = $('<div>').text(fam).html().replace(/"/g, '&quot;');
                    return '<button type="button" class="btn btn-sm btn-outline-secondary btn-productos-familia" '
                         + 'data-familia="' + famAttr + '" '
                         + 'title="Ver productos de la familia">'
                         + '<i class="bi bi-eye"></i></button>';
                }
            }
        ]
    });

    // Carga masiva: solo se aceptan archivos Excel .xlsx
    $('#archivo-excel-presupuesto').on('change', function() {
        const $input  = $(this);
        const archivo = this.files[0];

        if (!archivo) {
            limpiarErrorCampo($input);
            return;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (extension !== 'xlsx') {
            $input.val('');   // descarta el archivo no válido
            $input.addClass('is-invalid');
            $('#error-archivo-presupuesto')
                  .html('Solo se permiten archivos Excel (.xlsx).')
                  .addClass('d-block');
        } else {
            limpiarErrorCampo($input);
            $('#form-carga-masiva-presupuesto').trigger('submit');
        }
    });

    // Carga masiva: envía el archivo por AJAX y muestra el resultado de la extracción
    $('#form-carga-masiva-presupuesto').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaMasivaPresupuesto');
        const modalMensaje = '#modal-mensajes-presupuesto';
        const $archivo     = $('#archivo-excel-presupuesto');

        // Debe haber un archivo seleccionado
        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo-presupuesto')
                    .html('Debe ingresar un archivo .xlsx.')
                    .addClass('d-block');
            return;
        }

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/presupuesto_controller.php?action=procesar',
            type: 'POST',
            data: new FormData(this),   // incluye el archivo y el csrf_token
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaPresupuesto.ajax.reload(null, true);
                } else {
                    let msg = res.message;
                    if (res.errores && res.errores.length) {
                        msg += '<ul class="text-start small mb-0 mt-2">'
                             + res.errores.map(function(e) { return '<li>' + e + '</li>'; }).join('')
                             + '</ul>';
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', msg, 'danger');
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

    // Acciones -> vista previa: productos de la familia de la fila (origen SAP).
    $('#tabla-consulta-presupuesto tbody').on('click', '.btn-productos-familia', function() {
        const familia = $(this).data('familia') || '';
        const $tbody  = $('#tabla-productos-familia tbody');

        $('#productos-familia-nombre').text(familia);
        $('#productos-familia-mensaje').empty();
        $tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">'
                  + '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPresupuestoProductos')).show();

        $.ajax({
            url: 'controllers/presupuesto_controller.php?action=productos',
            type: 'GET',
            data: { familia: familia },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $tbody.empty();
                    mostrarMensajeFormulario('#productos-familia-mensaje', 'Atención', res.message || 'No se pudo cargar.', 'danger');
                    return;
                }

                const filas = res.data || [];

                if (!filas.length) {
                    $tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">'
                              + 'Sin productos para esta familia.</td></tr>');
                    return;
                }

                $('#productos-familia-mensaje').html(
                    '<div class="small text-muted mb-2">' + filas.length + ' producto(s).</div>'
                );
                $tbody.html(filas.map(function(p) {
                    return '<tr><td>' + renderTexto(p.producto_codigo) + '</td>'
                         + '<td>' + renderTexto(p.producto_nombre) + '</td></tr>';
                }).join(''));
            },
            error: function(jqXHR, textStatus) {
                $tbody.empty();
                manejarErrorAjax(jqXHR, textStatus, '#productos-familia-mensaje');
            }
        });
    });

});
