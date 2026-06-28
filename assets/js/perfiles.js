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
                render: function() {
                    // Acciones (editar / eliminar): pendientes. La columna queda lista.
                    return '';
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

});
