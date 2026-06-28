$(document).ready(function() {

    // Tabla principal de perfiles (server-side, helper reutilizable de utils.js).
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/perfiles_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por id ascendente
        columnas: [
            { data: 'id', className: 'text-center' },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id) {
                    // El perfil Administrador (Id 1) es fijo: sus botones quedan deshabilitados.
                    const esAdmin = Number(id) === 1;

                    const btnEditar = '<button type="button" class="btn btn-sm btn-outline-dark btn-editar-perfil me-1" '
                                    + 'data-id="' + id + '" ' + (esAdmin ? 'disabled ' : '')
                                    + 'title="' + (esAdmin ? 'El perfil Administrador no se puede editar' : 'Editar perfil') + '">'
                                    + '<i class="bi bi-pencil"></i></button>';

                    const btnEliminar = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-perfil" '
                                      + 'data-id="' + id + '" ' + (esAdmin ? 'disabled ' : '')
                                      + 'title="' + (esAdmin ? 'El perfil Administrador no se puede eliminar' : 'Eliminar perfil') + '">'
                                      + '<i class="bi bi-trash"></i></button>';

                    return btnEditar + btnEliminar;
                }
            }
        ]
    });

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-perfil', '#modal-mensajes');

    // Crear Perfil.
    $('#form-perfil').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarPerfil');
        const formulario   = '#form-perfil';
        const modalMensaje = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, nombre
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    tablaConsulta.ajax.reload(null, false);   // muestra el nuevo perfil en la tabla
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
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

    // Abre el modal de edición y carga los datos del perfil seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-editar-perfil', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-perfil-editar';
        const modalMensaje = '#modal-mensajes-editar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id_perfil_editar').val(res.data.id);
                    $('#input_nombre_editar').val(res.data.nombre);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del perfil.', 'danger', 0);
            },
            complete: function() {
                $('#modalPerfilEditar').modal('show');
            }
        });
    });

    // Actualizar Perfil.
    $('#form-perfil-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarPerfil');
        const formulario   = '#form-perfil-editar';
        const modalMensaje = '#modal-mensajes-editar';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=actualizar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, id_registro, nombre
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(null, false);   // refleja el cambio en la tabla
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
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

    // Abre el modal de eliminación y carga los datos del perfil seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-perfil', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-perfil-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id-perfil-eliminar').val(res.data.id);
                    $('#input-nombre-perfil-eliminar').val(res.data.nombre);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del perfil.', 'danger', 0);
            },
            complete: function() {
                $('#modalPerfilEliminar').modal('show');
            }
        });
    });

    // Confirmar eliminación del perfil.
    $('#form-perfil-eliminar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnEliminarPerfil');
        const modalMensaje = '#modal-mensajes-eliminar';

        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=eliminar',
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
