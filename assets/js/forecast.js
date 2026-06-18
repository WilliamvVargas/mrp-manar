$(document).ready(function() {

    // Tabla principal de forecast (server-side, helper reutilizable de utils.js)
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta-forecast',
        url:   'controllers/forecast_controller.php?action=listar',
        input: '#consulta-forecast',
        orden: [[0, 'desc']],   // por id, descendente (más recientes primero)
        columnas: [
            { data: 'id', className: 'text-center' },
            { data: 'empresa',         render: $.fn.dataTable.render.text() },
            { data: 'version',         render: $.fn.dataTable.render.text() },
            { data: 'codigo_cliente',  render: $.fn.dataTable.render.text() },
            { data: 'nombre_cliente',  render: $.fn.dataTable.render.text() },
            { data: 'codigo_producto', render: $.fn.dataTable.render.text() },
            { data: 'nombre_producto', render: $.fn.dataTable.render.text() },
            { data: 'cantidad', className: 'text-end' },
            { data: null, orderable: false, searchable: false, className: 'text-center', render: function() { return ''; } }
        ]
    });

    // La tabla vive dentro del modal: al abrirlo recalcula el ancho de columnas
    // (DataTables no puede medirlas mientras el modal está oculto).
    $('#modalCargaMasiva').on('shown.bs.modal', function() {
        tablaConsulta.columns.adjust();
    });

    // Carga masiva: solo se aceptan archivos Excel .xlsx
    $('#archivo-excel').on('change', function() {
        const $input = $(this);
        const archivo = this.files[0];

        if (!archivo) {
            limpiarErrorCampo($input);
            return;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (extension !== 'xlsx') {
            $input.val('');   // descarta el archivo no válido
            $input.addClass('is-invalid');
            $('#error-archivo')
                  .html('Solo se permiten archivos Excel (.xlsx).')
                  .addClass('d-block');
        } else {
            limpiarErrorCampo($input);
            $('#form-carga-masiva').trigger('submit');
        }
    });

    // Carga masiva: envía el archivo por AJAX y muestra el resultado de la extracción
    $('#form-carga-masiva').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaMasiva');
        const modalMensaje = '#modal-mensajes';
        const $archivo     = $('#archivo-excel');

        // Debe haber un archivo seleccionado
        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo')
                    .html('Debe ingresar un archivo .xlsx.')
                    .addClass('d-block');
            return;
        }

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/forecast_controller.php?action=procesar',
            type: 'POST',
            data: new FormData(this),   // incluye el archivo y el csrf_token
            processData: false,         // no serializar (es FormData)
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaConsulta.ajax.reload(null, true);   // refresca la tabla con los nuevos registros
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

});