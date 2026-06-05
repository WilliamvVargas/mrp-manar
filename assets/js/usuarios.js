$(document).ready(function() {

    listarUsuarios();
    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-mensajes');
    activarLimpiezaMensajeAlEscribir('#form-usuario-editar', '#modal-mensajes-editar');
    activarTogglePassword('#togglePassword', '#password', '#iconEye');
    activarTogglePassword('#togglePasswordConfirm', '#confirm_password', '#iconEyeConfirm');
    activarTogglePassword('#togglePasswordEdit', '#password-editar', '#iconEyeEdit');
    activarTogglePassword('#togglePasswordConfirmEdit', '#confirm-password-editar', '#iconEyeConfirmEdit');

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
                                    <button class="btn btn-sm btn-outline-dark btn-editar" data-id="${user.id}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-dark btn-password" data-id="${user.id}">
                                        <i class="bi bi-key"></i>
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
            beforeSend: function() {
                
            },
            success: function(res) {

                console.log(res)
                resetBtnLoading(btn);

                if (res.status === 'success') {

                    limpiarFormularioCompleto('#form-usuario', '#modal-mensajes', true);
                    listarUsuarios();
                    mostrarMensajeFormulario('#modal-mensajes', 'Éxito', res.message, 'success');
                } 
                else {

                    limpiarFormularioCompleto('#form-usuario', '#modal-mensajes', false);

                    if (res.type === 'fields') {
                        
                        $.each(res.errors, function(campo, mensaje) {
                            const input = $(`#form-usuario [name="${campo}"]`);
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


    $('#form-usuario-editar').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#btnActualizar');
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=editar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            beforeSend: function() {

            },
            success: function(res) {

                limpiarFormularioCompleto('#form-usuario-editar', '#modal-mensajes-editar', false);

                if (res.status === 'success') {
                    listarUsuarios();
                    mostrarMensajeFormulario('#modal-mensajes-editar', 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario('#modal-mensajes-editar', 'Atención', res.message, 'warning');
                }
                else {
                    
                    if (res.type === 'fields') {
                        // Resaltar errores específicos de validación con tus clases inválidas
                        $.each(res.errors, function(campo, mensaje) {
                            const input = $(`#form-usuario-editar [name="${campo}"]`);
                            input.addClass('is-invalid');
                            let feedback = input.closest('.mb-3').find('.invalid-feedback');
                            feedback.text(mensaje);
                            feedback.addClass('d-block');
                        });
                    } else {
                        mostrarMensajeFormulario('#modal-mensajes-editar', 'Atención', res.message, 'danger');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let mensajeError = "Ocurrió un error crítico en el servidor al actualizar.";
        
                if (textStatus === 'timeout') {
                    mensajeError = "El servidor está tardando demasiado en responder.";
                } else if (jqXHR.status === 403) {
                    mensajeError = "Su sesión expiró o la solicitud no es válida (Token CSRF inválido).";
                } else if (jqXHR.status === 500) {
                    mensajeError = "Error interno del servidor (500). Revisa los logs.";
                }

                mostrarMensajeFormulario('#modal-mensajes-editar', 'Error de Sistema', mensajeError, 'danger');
            },
            complete: function() {
                resetBtnLoading(btn); 
            }
        });

    });

    $('#form-usuario-password').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#btnEditarPassword');
        setBtnLoading(btn, 'Actualizando...');

        console.log($(this))

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=cambiar_password',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            beforeSend: function() {

            },
            success: function(res) {

                limpiarFormularioCompleto('#form-usuario-password', '#modal-mensajes-password', false);

                console.log(res)
                if (res.status === 'success') {

                    listarUsuarios();
                    mostrarMensajeFormulario('#modal-mensajes-password', 'Éxito', res.message, 'success');
                } 
                else {

                    if (res.type === 'fields') {
                        
                        $.each(res.errors, function(campo, mensaje) {
                            const input = $(`#form-usuario-password [name="${campo}"]`);
                            input.addClass('is-invalid');
                            let feedback = input.closest('.mb-3').find('.invalid-feedback');
                            feedback.text(mensaje);
                            feedback.addClass('d-block');
                        });
                    } 
                    else {
                       mostrarMensajeFormulario('#modal-mensajes-password', 'Atención', res.message, 'danger');
                    }
                }

            },
            complete: function() {
                resetBtnLoading(btn); 
            }

        });

    });

    $("#btn-generar-pass-editar").on("click", function(){

        const formulario = $("#form-usuario-password");

        console.log(formulario.serialize())


        $.ajax({
            url: 'controllers/usuarios_controller.php?action=generar_password',
            type: 'POST',
            data: formulario.serialize(),
            dataType: 'json',
            success: function(res) {

                limpiarFormularioCompleto("#form-usuario-password", '#modal-mensajes-password', false);

                if (res.status === 'success') {
                    mostrarMensajeFormulario('#modal-mensajes-password', 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario('#modal-mensajes-password', 'Atención', res.message, 'warning');
                }
                else {
                    
                    mostrarMensajeFormulario('#modal-mensajes-password', 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let mensajeError = "Ocurrió un error crítico en el servidor al actualizar.";
        
                if (textStatus === 'timeout') {
                    mensajeError = "El servidor está tardando demasiado en responder.";
                } else if (jqXHR.status === 403) {
                    mensajeError = "Su sesión expiró o la solicitud no es válida (Token CSRF inválido).";
                } else if (jqXHR.status === 500) {
                    mensajeError = "Error interno del servidor (500). Revisa los logs.";
                }

                mostrarMensajeFormulario('#modal-mensajes-password', 'Error de Sistema', mensajeError, 'danger');
            },
            complete: function() {
                resetBtnLoading(btn); 
            }

        });


    })

});

$(document).on('click', '.btn-editar', function() {

    const idUsuario = $(this).data('id');

    console.log(idUsuario)

    limpiarFormularioCompleto('#form-usuario-editar', '#modal-mensajes-editar', true);

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            console.log(response);

            if (response.status === 'success') {

                $('#id_usuario_editar').val(response.data.id);
                $('#usuario_editar').val(response.data.usuario);
                
            } 
            else {
                mostrarMensajeFormulario('#modal-mensajes-editar', 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario('#modal-mensajes-editar', 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioEditar').modal('show');
        }
    });

});


$(document).on('click', '.btn-password', function() {

    limpiarFormularioCompleto('#form-usuario-password', '#modal-mensajes-password', true);

    const idUsuario = $(this).data('id');
    

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            console.log(response);

            if (response.status === 'success') {

                $('#id_usuario_password_editar').val(response.data.id);
                $('#input-usuario-pass').val(response.data.usuario);
                
            } 
            else {
                mostrarMensajeFormulario('#modal-mensajes-password', 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario('#modal-mensajes-password', 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioPassword').modal('show');
        }
    });






















    $('#modalUsuarioPassword').modal('show');



});