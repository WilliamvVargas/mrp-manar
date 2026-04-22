$(document).ready(function() {
    $('#formLogin').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#btnEnviar');
        const mensajeDiv = $('#mensaje');
        const datos = $(this).serialize();

        $.ajax({
            url: 'includes/auth', // Ruta actualizada
            type: 'POST',
            data: datos,
            dataType: 'json',
            beforeSend: function() {
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Verificando...');
                mensajeDiv.addClass('d-none');
            },
            success: function(res) {
                if(res.status === 'success') {
                    window.location.href = 'dashboard';
                } else {
                    mensajeDiv.removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                    btn.prop('disabled', false).text('Entrar');
                }
            },
            error: function() {
                mensajeDiv.removeClass('d-none').addClass('alert-danger').text('Error de comunicación con el servidor.');
                btn.prop('disabled', false).text('Entrar');
            }
        });
    });
});