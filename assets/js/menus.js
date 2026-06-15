$(document).ready(function() {

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-menu', '#modal-mensajes');

    // Switch de Estado: alterna la etiqueta entre Activo / Inactivo según su valor.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // Crear Menú
    $('#form-menu').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarMenu');
        const formulario   = '#form-menu';
        const modalMensaje = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/menus_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    $('#label-estado').text('Activo');   // el switch vuelve a su valor por defecto
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading(btn);
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            }
        });
    });

    // Al cerrar el modal, limpia el formulario y restablece la etiqueta del switch.
    $('#modalMenuCrear').on('hidden.bs.modal', function() {
        limpiarFormularioCompleto('#form-menu', '#modal-mensajes', true);
        $('#label-estado').text('Activo');
    });

});