$(document).ready(function() {

    $('#form-login').on('submit', function(e) {
        e.preventDefault();
        
        // Limpiar estados previos
        $('.form-control').removeClass('is-invalid is-valid');
        $('.invalid-feedback').text('');

        $.ajax({
            url: 'includes/auth',
            type: 'POST',
            data: $('#form-login').serialize(),
            dataType: 'json',
            success: function(res) {

                console.log(res)

                if (res.status === 'error') {
                    $.each(res.errors, function(campo, mensaje) {
                        
                        let input = $('#' + campo);

                        if (input.length > 0) {

                            input.addClass('is-invalid');
                            input.parent().find('.invalid-feedback').text(mensaje);
                        } 
                        else {

                            $('#alert-container').html(`
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    ${mensaje}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                        }
                    });
                } 
                else {
                    window.location.href = 'dashboard';
                }

            },
            error: function(xhr, status, error) {
                console.error("Error del servidor:", error);
                console.error("Respuesta cruda:", xhr.responseText);
                alert("Error crítico en el servidor.");
            }
        });
    });

});