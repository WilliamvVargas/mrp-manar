$(document).ready(function() {


    // --- 1. INICIALIZACIONES ---
    listarUsuarios();
    
    // Listeners de limpieza automática al escribir
    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-mensajes');
    activarLimpiezaMensajeAlEscribir('#form-usuario-editar', '#modal-mensajes-editar');
    activarLimpiezaMensajeAlEscribir('#form-usuario-password', '#modal-mensajes-password');

    // Configuración de toggles de contraseñas
    activarTogglePassword('#togglePassword', '#password', '#iconEye');
    activarTogglePassword('#togglePasswordConfirm', '#confirm_password', '#iconEyeConfirm');
    activarTogglePassword('#togglePasswordEdit', '#password-editar', '#iconEyeEdit');
    activarTogglePassword('#togglePasswordConfirmEdit', '#confirm-password-editar', '#iconEyeConfirmEdit');

    // --- 2. FLUJO PRINCIPAL: LISTAR ---
    function listarUsuarios() {
        $.ajax({
            url: 'controllers/usuarios_controller.php?action=listar',
            type: 'GET',
            dataType: 'json',
            success: function(response) {

                console.log(response)

                if (response.status === 'success') {
                    if ($.fn.DataTable.isDataTable('#tabla-usuarios')) {
                        $('#tabla-usuarios').DataTable().destroy();
                    }

                    let filas = '';
                    response.data.forEach(user => {
                        filas += `
                            <tr>
                                <td><strong>${user.usuario}</strong></td>
                                <td><strong>${user.nombres}</strong></td>
                                <td><strong>${user.apellidos}</strong></td>
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

                    $('#tabla-usuarios').DataTable({
                        autoWidth: false,
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                        },
                        "columnDefs": [ { "orderable": true, "targets": 3 }],
                        "order": [[0, "desc"]]
                    });
                } 
                else {
                    mostrarMensajeFormulario('#alert-container', 'Error de Sistema', 'No se pudieron procesar los usuarios.', 'danger');
                }
            }
        });
    }


    // --- 3. ACCIONES DE FORMULARIOS (SUBMITS) ---

    // Registrar Usuario
    $('#form-usuario').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnGuardar');
        const formulario = '#form-usuario';
        const modalMensaje = '#modal-mensajes';
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    listarUsuarios();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    } else {
                        mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                    }
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading(btn);
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            }
        });
    });


    // Editar Usuario
    $('#form-usuario-editar').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnActualizar');
        const formulario = '#form-usuario-editar';
        const modalMensaje = '#modal-mensajes-editar';
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=editar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                limpiarFormularioCompleto(formulario, modalMensaje, false);
                if (res.status === 'success') {
                    listarUsuarios();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } 
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
                } 
                else {
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    } 
                    else {
                        mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                    }
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });

    // Cambiar Contraseña desde el Modal Editar Password
    $('#form-usuario-password').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnEditarPassword');
        const formulario = '#form-usuario-password';
        const modalMensaje = '#modal-mensajes-password';
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=cambiar_password',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                limpiarFormularioCompleto(formulario, modalMensaje, false);
                if (res.status === 'success') {
                    listarUsuarios();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } 
                else {
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    } 
                    else {
                        mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                    }
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });

    // Confirmar Eliminación de Usuario
    $('#form-usuario-eliminar').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnEliminar');
        const formulario = '#form-usuario-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';
        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=eliminar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    listarUsuarios();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });

    $('#form-usuario input').on('keyup', debounce(function() {
        ejecutarValidacionInstantanea($(this));
    }));

    $('#form-usuario input').on('blur', function() {
        ejecutarValidacionInstantanea($(this));
    });

    function ejecutarValidacionInstantanea($input) {

        const formulario = '#form-usuario';
        const modalMensaje = '#modal-mensajes';
        const fieldName = $input.attr('name');
        const value = $input.val();
        const csrf_token = $input.closest('form').find('input[name="csrf_token"]').val()

        // Limpiamos el error visual previo de este campo específico usando tus estilos
        $input.removeClass('is-invalid');
        $input.next('.invalid-feedback').text('');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=validar_campo',
            type: 'POST',
            data: {
                campo: fieldName,
                valor: value,
                csrf_token: csrf_token
            },
            dataType: 'json',
            success: function(response) {

                console.log(response)

                if (response.status === 'error') {
                    if (response.type === 'fields') {
                        renderizarErroresCampos(formulario, response.errors);
                    } 
                } else {
                    $input.addClass('is-valid');
                    limpiarErrorCampo($input);
                }
            }
        });

    };


    // --- 4. GATILLOS DE GENERACIÓN DE PASSWORDS ---

    // Generar en Formulario de Registro
    $("#btn-generar-pass").on("click", function() {

        let btn = $(this);
        let formulario_serialize = $("#form-usuario").serialize() + "&es_actualizacion=0";
        const modalMensaje = '#modal-mensajes';
        setBtnLoading(btn, 'Generando...');
        
        $.ajax({
            url: 'controllers/usuarios_controller.php?action=generar_password',
            type: 'POST',
            data: formulario_serialize,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $("#password").val(res.password);
                    $("#confirm_password").val(res.password);

                    // Forzar apertura de los ojos si están cerrados de forma segura
                    if ($('#password').attr('type') === 'password') $('#togglePassword').trigger('click');
                    if ($('#confirm_password').attr('type') === 'password') $('#togglePasswordConfirm').trigger('click');
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });

    // Generar en Formulario de Modificar Password
    $("#btn-generar-pass-editar").on("click", function() {

        let btn = $(this);
        let formulario_serialize = $("#form-usuario-password").serialize() + "&es_actualizacion=1";
        const formulario = '#form-usuario-password';
        const modalMensaje = '#modal-mensajes-password';
        setBtnLoading(btn, 'Generando...');
        
        $.ajax({
            url: 'controllers/usuarios_controller.php?action=generar_password',
            type: 'POST',
            data: formulario_serialize,
            dataType: 'json',
            success: function(res) {
                limpiarFormularioCompleto(formulario, modalMensaje, false);
                if (res.status === 'success') {

                    // Inyectamos la clave generada en el input de cambio de contraseña
                    $("#password-editar").val(res.password);
                    $("#confirm-password-editar").val(res.password);
                    
                    if ($('#password-editar').attr('type') === 'password') $('#togglePasswordEdit').trigger('click');
                    if ($('#confirm-password-editar').attr('type') === 'password') $('#togglePasswordConfirmEdit').trigger('click');

                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } 
                else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });


});


// --- 5. DISPARADORES DE APERTURA DE MODALES ---

// Cargar datos en Modal de Edición
$(document).on('click', '.btn-editar', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-editar';
    const modalMensaje = '#modal-mensajes-editar';

    limpiarFormularioCompleto(formulario, modalMensaje, true);

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {          

            if (response.status === 'success') {

                $('#id_usuario_editar').val(response.data.id);
                $('#usuario_editar').val(response.data.usuario);
                $('#nombres_editar').val(response.data.nombres);
                $('#apellidos_editar').val(response.data.apellidos);
                
            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioEditar').modal('show');
        }
    });

});

// Cargar datos en Modal de Contraseña
$(document).on('click', '.btn-password', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-password';
    const modalMensaje = '#modal-mensajes-password';

    limpiarFormularioCompleto(formulario, modalMensaje, true);
    
    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            

            if (response.status === 'success') {

                $('#id_usuario_password_editar').val(response.data.id);
                $('#input-usuario-pass').val(response.data.usuario);
                
            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioPassword').modal('show');
        }
    });


});


// Cargar datos en Modal de Eliminación
$(document).on('click', '.btn-eliminar', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-eliminar';
    const modalMensaje = '#modal-mensajes-eliminar';

    limpiarFormularioCompleto(formulario, modalMensaje, true);
    

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            
            if (response.status === 'success') {

                $('#id_usuario_eliminar').val(response.data.id);
                $('#input-usuario-eliminar').val(response.data.usuario);
                
            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioEliminar').modal('show');
        }
    });

});