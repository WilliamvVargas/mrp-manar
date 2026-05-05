$(document).ready(function() {

    activarLimpiezaMensajeAlEscribir('#form-login', '#modal-mensajes');

    $('#form-login').on('submit', function(e) {

        e.preventDefault();

        const btnSubmit = $('#btn-login')
        const btnOriginalText = btnSubmit.html();
        
        limpiarFormularioCompleto('#form-login', '#alert-container', false);

        setBtnLoading(btnSubmit, 'Validando...');

        $.ajax({
            url: 'controllers/login_controller',
            type: 'POST',
            data: $('#form-login').serialize(),
            dataType: 'json',
            success: function(res) {

                console.log(res)

                if (res.status === 'error') {

                    let erroresGenerales = [];

                    $.each(res.errors, function(campo, mensaje) {
                        
                        let input = $('#' + campo);

                        if (input.length > 0) {
                            input.addClass('is-invalid');
                            input.parent().find('.invalid-feedback').text(mensaje);
                        } 
                        else {
                            erroresGenerales.push(mensaje);
                        }
                    });

                    if (erroresGenerales.length > 0) {
                        mostrarMensajeFormulario('#modal-mensajes', 'Error de Acceso', erroresGenerales, 'danger');
                    }

                    resetBtnLoading(btnSubmit);
                } 
                else {
                    window.location.href = res.redirect;
                }

            },
            error: function(xhr, status, error) {
                resetBtnLoading(btnSubmit);
                mostrarMensajeFormulario('#modal-mensajes', 'Error Crítico', "No se pudo establecer conexión con el servidor. Inténtelo más tarde.", 'danger');
            }
        });
    });


});