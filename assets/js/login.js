$(document).ready(function() {

    //configuracion de formulario
    activarLimpiezaMensajeAlEscribir('#form-login', '#modal-mensajes');
    activarTogglePassword('#togglePassword', '#password', '#iconEye');

    //validar si se retorno al login porque la session se cerro
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('error') === 'session_expired') {
        
        setTimeout(function() {
            mostrarMensajeFormulario(
                '#modal-mensajes', 
                'Sesión Finalizada', 
                'Su sesión ha expirado por inactividad. Por favor, ingrese sus credenciales nuevamente.', 
                'warning' 
            );
            
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 200);
    }


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