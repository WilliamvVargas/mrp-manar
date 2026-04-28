$(document).ready(function() {

    $('#form-login').on('submit', function(e) {

        e.preventDefault();

        const btnSubmit = $('#btn-login')
        const btnOriginalText = btnSubmit.html();
        
        $('.form-control').removeClass('is-invalid is-valid');
        $('.invalid-feedback').text('');
        $('#alert-container').empty();

        btnSubmit.prop('disabled', true);
        btnSubmit.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando...');

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

                    btnSubmit.prop('disabled', false);
                    btnSubmit.html(btnOriginalText);
                } 
                else {
                    window.location.href = 'dashboard';
                }

            },
            error: function(xhr, status, error) {
                btnSubmit.prop('disabled', false);
                btnSubmit.html(btnOriginalText);
                mostrarAlertaGeneral("Error Crítico", "No se pudo establecer conexión con el servidor. Inténtelo más tarde.");
            }
        });
    });

});