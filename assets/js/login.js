$(document).ready(function() {

    $('#form-login').on('submit', function(e) {

        e.preventDefault();

        const btnSubmit = $('#btn-login')
        const btnOriginalText = btnSubmit.html();
        
        $('.form-control').removeClass('is-invalid is-valid');
        $('.invalid-feedback').text('');
        $('#alert-container').empty();

        setBtnLoading(btnSubmit, 'Validando...');

        $.ajax({
            url: 'includes/auth',
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
                        mostrarAlertaGeneral("Error de Acceso", erroresGenerales);
                    }

                    resetBtnLoading(btnSubmit);
                } 
                else {
                    window.location.href = 'dashboard';
                }

            },
            error: function(xhr, status, error) {
                resetBtnLoading(btnSubmit);
                mostrarAlertaGeneral("Error Crítico", "No se pudo establecer conexión con el servidor. Inténtelo más tarde.");
            }
        });
    });

});