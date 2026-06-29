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

    // Usuario sacado al login por estar bloqueado (estado inactivo): mensaje de error propio.
    if (urlParams.get('error') === 'usuario_bloqueado') {

        setTimeout(function() {
            mostrarMensajeFormulario(
                '#modal-mensajes',
                'Atención',
                'Usuario bloqueado.',
                'danger'
            );

            window.history.replaceState({}, document.title, window.location.pathname);
        }, 200);
    }


    $('#form-login').on('submit', function(e) {

        e.preventDefault();

        const btnSubmit = $('#btn-login')
        const formulario = '#form-login';
        const celdaMensaje = '#modal-mensajes';

        setBtnLoading(btnSubmit, 'Validando...');

        $.ajax({
            url: 'controllers/login_controller?action=iniciar_sesion',
            type: 'POST',
            data: $('#form-login').serialize(),
            dataType: 'json',
            success: function(res) {

                if (res.status === 'success') {
                    limpiarFormularioCompleto('#form-login', '#alert-container', false);
                    window.location.href = res.redirect;
                }
                else if (res.status === 'error') {

                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    } 
                    else {
                        renderizarErroresCampos(formulario, res.errors);
                    }

                    mostrarMensajeFormulario(celdaMensaje, 'Atención', res.message, 'danger');
                    resetBtnLoading(btnSubmit);
                }
                else{

                    mostrarMensajeFormulario(celdaMensaje, 'Atención', res.message, 'warning');
                    resetBtnLoading(btnSubmit);

                }
            },
            error: function(xhr, status, error) {
                resetBtnLoading(btnSubmit);
                limpiarFormularioCompleto('#form-login', '#alert-container', false);
                mostrarMensajeFormulario('#modal-mensajes', 'Error Crítico', "No se pudo establecer conexión con el servidor. Inténtelo más tarde.", 'danger');
            }
        });
    });


});