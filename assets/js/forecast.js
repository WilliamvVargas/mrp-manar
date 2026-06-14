$(document).ready(function() {

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
            $input.siblings('.invalid-feedback')
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
            $archivo.siblings('.invalid-feedback')
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
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', 'Se procesaron ' + res.total + ' registro(s).', 'success');
                    console.log('Registros extraídos:', res.data);   // temporal: ver el resultado
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

});