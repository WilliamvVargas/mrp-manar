$(document).ready(function() {

    // ============================================================
    //  Helpers de render de la tabla
    // ============================================================

    // Badge Sí/No para los interruptores sin parámetro.
    function badgeToggle(valor) {
        return Number(valor) === 1
            ? '<span class="badge bg-success">Sí</span>'
            : '<span class="badge bg-secondary">No</span>';
    }

    // Número "bonito": quita el .0 sobrante (15.0 -> 15) y conserva decimales reales (7.5).
    function num(v) { return String(parseFloat(v)); }

    // Badge "Sí" + parámetro (solo si la opción está activa); "No" si está apagada.
    function badgeConParametro(valor, texto) {
        return Number(valor) === 1
            ? '<span class="badge bg-success">Sí</span> <span class="text-muted small">' + texto + '</span>'
            : '<span class="badge bg-secondary">No</span>';
    }

    // ============================================================
    //  Tabla principal
    // ============================================================

    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/forecast_config_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por nombre ascendente
        columnas: [
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            { data: 'imputar_censura', className: 'text-center', render: badgeToggle },
            {
                data: 'capar_outliers', className: 'text-center',
                render: function(v, t, fila) { return badgeConParametro(v, 'K ' + num(fila.capar_k)); }
            },
            {
                data: 'ensamble', className: 'text-center',
                render: function(v, t, fila) { return badgeConParametro(v, 'Prophet ' + num(fila.ensamble_peso_prophet) + '%'); }
            },
            {
                data: 'estabilizar_poco_historico', className: 'text-center',
                render: function(v, t, fila) { return badgeConParametro(v, 'N ' + num(fila.estabilizar_n_semanas)); }
            },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(id) {
                    const btnEditar = '<button type="button" class="btn btn-outline-dark btn-editar-config" '
                                    + 'data-id="' + id + '" title="Editar configuración"><i class="bi bi-pencil"></i></button>';
                    const btnEliminar = '<button type="button" class="btn btn-outline-danger btn-eliminar-config" '
                                      + 'data-id="' + id + '" title="Eliminar configuración"><i class="bi bi-trash"></i></button>';
                    return '<div class="btn-group btn-group-sm" role="group">' + btnEditar + btnEliminar + '</div>';
                }
            }
        ]
    });

    // ============================================================
    //  Paneles plegables + parámetros (genérico, sirve a crear y editar)
    // ============================================================

    // Cada switch con parámetro lleva data-param="#input"; deshabilitado no se envía (el
    // controlador guarda el valor por defecto). Delegado, así vale para ambos modales.
    $(document).on('change', '.opcion-param-switch', function() {
        $($(this).data('param')).prop('disabled', !$(this).is(':checked'));
    });

    // Lectura viva del peso del ensamble. El slider lleva data-out="#span".
    function actualizarLecturaPeso($range) {
        const p = parseInt($range.val(), 10) || 0;
        $($range.data('out')).text('Prophet ' + p + '% · Estacional ' + (100 - p) + '%');
    }
    $(document).on('input', '.opcion-peso', function() { actualizarLecturaPeso($(this)); });

    // Revela/oculta la explicación al hacer clic en la cabecera (el switch vive fuera del botón).
    $(document).on('click', '.opcion-toggle', function() {
        const $btn  = $(this);
        const $body = $('#' + $btn.attr('aria-controls'));
        const abrir = $btn.attr('aria-expanded') !== 'true';
        $btn.attr('aria-expanded', abrir ? 'true' : 'false');
        $body.stop(true, true).slideToggle(180);
    });

    // Abre un panel por el selector de su cuerpo si estuviera cerrado (para mostrar un error).
    function abrirPanelOpcion(bodySel) {
        const $btn = $('.opcion-toggle[aria-controls="' + bodySel.replace('#', '') + '"]');
        if ($btn.attr('aria-expanded') !== 'true') {
            $btn.attr('aria-expanded', 'true');
            $(bodySel).stop(true, true).slideDown(180);
        }
    }
    // Despliega el panel del parámetro con error (suf: '' crear, '_ed' editar).
    function abrirPanelesConError(errores, suf) {
        if (errores.capar_k)               abrirPanelOpcion('#op-body-outliers' + suf);
        if (errores.ensamble_peso_prophet) abrirPanelOpcion('#op-body-ensamble' + suf);
        if (errores.estabilizar_n_semanas) abrirPanelOpcion('#op-body-estabilizar' + suf);
    }

    // Cierra todos los paneles de un modal.
    function cerrarPaneles($modal) {
        $modal.find('.opcion-toggle').attr('aria-expanded', 'false');
        $modal.find('.opcion-body').hide();
    }
    // Sincroniza el habilitado de cada parámetro con su switch, y la lectura del peso.
    function sincronizarParametros($modal) {
        $modal.find('.opcion-param-switch').each(function() {
            $($(this).data('param')).prop('disabled', !$(this).is(':checked'));
        });
        $modal.find('.opcion-peso').each(function() { actualizarLecturaPeso($(this)); });
    }
    // Deja un modal en su estado por defecto (para crear).
    function reiniciarModal($modal) {
        $modal.find('input[type="checkbox"][role="switch"]').prop('checked', false);
        $modal.find('[name="capar_k"]').val('10');
        $modal.find('[name="ensamble_peso_prophet"]').val(50);
        $modal.find('[name="estabilizar_n_semanas"]').val('52');
        cerrarPaneles($modal);
        sincronizarParametros($modal);
    }

    // ============================================================
    //  Crear
    // ============================================================

    const $modalCrear = $('#modalForecastConfiguracion');

    // Al abrir, paneles cerrados y parámetros al día.
    $modalCrear.on('show.bs.modal', function() { reiniciarModal($modalCrear); });

    if (typeof activarLimpiezaMensajeAlEscribir === 'function') {
        activarLimpiezaMensajeAlEscribir('#form-forecast-config', '#modal-forecast-config-mensajes');
    }

    $('#form-forecast-config').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarForecastConfig');
        const formulario   = '#form-forecast-config';
        const modalMensaje  = '#modal-forecast-config-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    reiniciarModal($modalCrear);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaConsulta.ajax.reload(null, false);
                } else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        abrirPanelesConError(res.errors, '');
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

    // ============================================================
    //  Editar
    // ============================================================

    const $modalEditar = $('#modalForecastConfiguracionEditar');

    if (typeof activarLimpiezaMensajeAlEscribir === 'function') {
        activarLimpiezaMensajeAlEscribir('#form-forecast-config-editar', '#modal-forecast-config-mensajes_ed');
    }

    // Abre el modal de edición y carga los datos de la configuración seleccionada.
    $('#tabla-consulta tbody').on('click', '.btn-editar-config', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-forecast-config-editar';
        const modalMensaje  = '#modal-forecast-config-mensajes_ed';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $('#id_forecast_config_editar').val(d.id);
                    $('#input_config_nombre_ed').val(d.nombre);
                    $('#cfg_imputar_censura_ed').prop('checked', Number(d.imputar_censura) === 1);
                    $('#cfg_capar_outliers_ed').prop('checked', Number(d.capar_outliers) === 1);
                    $('#cfg_ensamble_ed').prop('checked', Number(d.ensamble) === 1);
                    $('#cfg_estabilizar_ed').prop('checked', Number(d.estabilizar_poco_historico) === 1);
                    $('#cfg_capar_k_ed').val(num(d.capar_k));
                    $('#cfg_ensamble_peso_ed').val(parseInt(d.ensamble_peso_prophet, 10));
                    $('#cfg_estabilizar_n_ed').val(d.estabilizar_n_semanas);
                    cerrarPaneles($modalEditar);
                    sincronizarParametros($modalEditar);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos de la configuración.', 'danger', 0);
            },
            complete: function() {
                $modalEditar.modal('show');
            }
        });
    });

    // Actualizar configuración.
    $('#form-forecast-config-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarForecastConfig');
        const formulario   = '#form-forecast-config-editar';
        const modalMensaje  = '#modal-forecast-config-mensajes_ed';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=actualizar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(null, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
                } else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        abrirPanelesConError(res.errors, '_ed');
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

    // ============================================================
    //  Eliminar
    // ============================================================

    const $modalEliminar = $('#modalForecastConfiguracionEliminar');

    // Abre el modal de eliminación con el nombre de la configuración seleccionada.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-config', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-forecast-config-eliminar';
        const modalMensaje  = '#modal-forecast-config-mensajes-eliminar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $('#id-forecast-config-eliminar').val(d.id);
                    $('#input-nombre-forecast-config-eliminar').val(d.nombre);
                    // Resumen de opciones (mismo badge + parámetro que la tabla).
                    $('#del-imputar').html(badgeToggle(d.imputar_censura));
                    $('#del-capar').html(badgeConParametro(d.capar_outliers, 'K ' + num(d.capar_k)));
                    $('#del-ensamble').html(badgeConParametro(d.ensamble, 'Prophet ' + num(d.ensamble_peso_prophet) + '%'));
                    $('#del-estabilizar').html(badgeConParametro(d.estabilizar_poco_historico, 'N ' + num(d.estabilizar_n_semanas)));
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos de la configuración.', 'danger', 0);
            },
            complete: function() {
                $modalEliminar.modal('show');
            }
        });
    });

    // Confirmar eliminación.
    $('#form-forecast-config-eliminar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnEliminarForecastConfig');
        const modalMensaje  = '#modal-forecast-config-mensajes-eliminar';

        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=eliminar',
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
