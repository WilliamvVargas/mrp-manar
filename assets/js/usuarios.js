$(document).ready(function() {

    // --- 1. INICIALIZACIONES ---

    // Tabla de consulta server-side (helper reutilizable definido en utils.js)
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url: 'controllers/usuarios_controller.php?action=listar',
        input: '#consulta',
        orden: [[3, 'desc']],   // Por fecha de creación, más recientes primero
        columnas: [
            { data: 'usuario',   render: $.fn.dataTable.render.text() },
            { data: 'nombres',   render: $.fn.dataTable.render.text() },
            { data: 'apellidos', render: $.fn.dataTable.render.text() },
            { data: 'fecha' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id) {
                    return `
                        <button class="btn btn-sm btn-outline-dark btn-editar" data-id="${id}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-dark btn-password" data-id="${id}">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${id}">
                            <i class="bi bi-trash"></i>
                        </button>`;
                }
            }
        ]
    });

    // Listeners de limpieza automática al escribir
    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-mensajes');
    activarLimpiezaMensajeAlEscribir('#form-usuario-editar', '#modal-mensajes-editar');
    activarLimpiezaMensajeAlEscribir('#form-usuario-password', '#modal-mensajes-password');

    // Configuración de toggles de contraseñas
    activarTogglePassword('#togglePassword', '#password', '#iconEye');
    activarTogglePassword('#togglePasswordConfirm', '#confirm_password', '#iconEyeConfirm');
    activarTogglePassword('#togglePasswordEdit', '#password-editar', '#iconEyeEdit');
    activarTogglePassword('#togglePasswordConfirmEdit', '#confirm-password-editar', '#iconEyeConfirmEdit');


    // --- 2. ACCIONES DE FORMULARIOS (SUBMITS) ---

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
                    tablaConsulta.ajax.reload(null, true);
                    mostrarMensajeFormulario(
                        modalMensaje, 
                        'Éxito', 
                        res.message, 
                        'success', 
                        400, 
                        res.credenciales
                    );
                } 
                else if (res.status === 'error') {

                    $(modalMensaje).slideUp(150); 
                    
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
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
                
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    tablaConsulta.ajax.reload(null, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } 
                else if (res.status === 'no_changes') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
                } 
                else {

                    $(modalMensaje).slideUp(150); 
                    
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
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

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    mostrarMensajeFormulario(
                        modalMensaje, 
                        'Éxito', 
                        res.message, 
                        'success', 
                        400, 
                        res.credenciales
                    );
                } 
                else {
                    $(modalMensaje).slideUp(150); 
                    
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
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
                    tablaConsulta.ajax.reload(function() {
                        // Si la página actual quedó vacía, retrocede a la última con registros.
                        const info = tablaConsulta.page.info();
                        if (info.pages > 0 && info.page >= info.pages) {
                            tablaConsulta.page(info.pages - 1).draw('page');
                        }
                    }, false);
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

});


// --- 5. DISPARADORES DE APERTURA Y CiERRE DE MODALES ---

$('#modalUsuarioCrear').on('hidden.bs.modal', function (e) {

    limpiarFormularioCompleto("#form-usuario","#modal-mensajes", true);

});

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


// Botón Copiar Credenciales
$(document).on('click', '.btn-copiar-credenciales-global', function() {
    const $boton = $(this);
    const textoACopiar = $boton.data('clipboard');

    navigator.clipboard.writeText(textoACopiar).then(function() {
        $boton.removeClass('btn-light border-secondary-subtle')
              .addClass('btn-success text-white')
              .html('<i class="bi bi-check-lg me-1"></i> ¡Copiado!');

        setTimeout(function() {
            $boton.removeClass('btn-success text-white')
                  .addClass('btn-light border-secondary-subtle')
                  .html('<i class="bi bi-clipboard me-1"></i> Copiar');
        }, 2000);
    });
});
