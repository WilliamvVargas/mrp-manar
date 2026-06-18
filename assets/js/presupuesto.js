$(document).ready(function() {

    // Render seguro para celdas que pueden venir en NULL.
    const renderTexto  = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderNumero = function(d) { return (d === null || d === undefined) ? '' : d; };

    // Tabla de presupuesto (server-side). Vive dentro del modal de Carga Masiva Presupuesto.
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
            { data: 'venta',         className: 'text-end', render: renderNumero },
            { data: 'mg_porcentaje', className: 'text-end', render: renderNumero },
            { data: 'mg_neto',       className: 'text-end', render: renderNumero },
            { data: 'pp',            className: 'text-end', render: renderNumero },
            { data: 'kg',            className: 'text-end', render: renderNumero }
        ]
    });

    // La tabla vive dentro del modal: al abrirlo recalcula el ancho de columnas.
    $('#modalCargaMasivaPresupuesto').on('shown.bs.modal', function() {
        tablaPresupuesto.columns.adjust();
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

});
