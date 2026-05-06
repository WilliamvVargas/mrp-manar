$(document).ready(function() {

    listarUsuarios();
    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-mensajes');
    activarTogglePassword('#togglePassword', '#password', '#iconEye');
    activarTogglePassword('#togglePasswordConfirm', '#confirm_password', '#iconEyeConfirm');

    function listarUsuarios() {
        $.ajax({
            url: 'controllers/usuarios_controller.php?action=listar',
            type: 'GET',
            dataType: 'json',
            success: function(response) {

                console.log(response)

                if (response.status === 'success') {

                    // 1. Destruir la instancia previa si existe
                    if ($.fn.DataTable.isDataTable('#tabla-usuarios')) {
                        $('#tabla-usuarios').DataTable().destroy();
                    }

                    let filas = '';
                    response.data.forEach(user => {
                        filas += `
                            <tr>
                                <td>${user.id}</td>
                                <td><strong>${user.usuario}</strong></td>
                                <td>${user.fecha}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning btn-editar" data-id="${user.id}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${user.id}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                                        
                    $('#lista-usuarios').html(filas);

                    // 2. Inicializar DataTables
                    $('#tabla-usuarios').DataTable({
                        autoWidth: false,
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                        },
                        "columnDefs": [
                            { "orderable": false, "targets": 3 }
                        ],
                        "order": [[0, "desc"]]
                    });

                } 
                else {
                    mostrarMensajeFormulario('#alert-container', 'Error de Sistema', 'critico', 'danger');
                }
            }
        });
    }

    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-messages');

    $('#form-usuario').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#btnGuardar');
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {

                console.log(res)
                resetBtnLoading(btn);
                limpiarFormularioCompleto('#form-usuario', '#modal-mensajes', false);
            

                if (res.status === 'success') {

                    limpiarFormularioCompleto('#form-usuario', '#modal-mensajes', true);
                    listarUsuarios();
                    mostrarMensajeFormulario('#modal-mensajes', 'Trabajo realizado', res.message, 'success');
                } 
                else {

                    if (res.type === 'fields') {
                        
                        $.each(res.errors, function(campo, mensaje) {
                            const input = $(`[name="${campo}"]`);
                            input.addClass('is-invalid');
                            let feedback = input.closest('.mb-3').find('.invalid-feedback');
                            feedback.text(mensaje);
                            feedback.addClass('d-block');
                        });
                    } 
                    else {
                       mostrarMensajeFormulario('#modal-mensajes', 'Atención', res.message, 'danger');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resetBtnLoading(btn);
                let mensajeError = "Ocurrió un error crítico en el servidor.";
        
                if (textStatus === 'timeout') {
                    mensajeError = "El servidor está tardando demasiado en responder.";
                } else if (jqXHR.status === 404) {
                    mensajeError = "No se encontró el controlador del servidor.";
                } else if (jqXHR.status === 500) {
                    mensajeError = "Error interno del servidor (500). Revisa los logs.";
                }

                mostrarMensajeFormulario('#modal-mensajes', 'Error de Sistema', mensajeError, 'danger');
            }
        });
    });

});