$(document).ready(function() {

    // Badge Sí/No para los interruptores de limpieza del forecast.
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

    // Tabla principal de configuraciones (server-side, helper reutilizable de utils.js).
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
            }
        ]
    });

    // Cada parámetro se habilita solo si su opción está activa; deshabilitado no se envía y el
    // controlador guarda el valor por defecto. Actualiza también la lectura del peso del ensamble.
    function sincronizarParametro($switch, $input) {
        $input.prop('disabled', !$switch.is(':checked'));
    }
    function actualizarLecturaPeso() {
        const p = parseInt($('#cfg_ensamble_peso').val(), 10) || 0;
        $('#cfg_ensamble_peso_out').text('Prophet ' + p + '% · Estacional ' + (100 - p) + '%');
    }
    // Abre un panel por el selector de su cuerpo (id) si estuviera cerrado.
    function abrirPanelOpcion(bodySel) {
        const idBody = bodySel.replace('#', '');
        const $btn   = $('.opcion-toggle[aria-controls="' + idBody + '"]');
        if ($btn.attr('aria-expanded') !== 'true') {
            $btn.attr('aria-expanded', 'true');
            $(bodySel).stop(true, true).slideDown(180);
        }
    }
    function resetParametrosOpciones() {
        $('#cfg_capar_k').val('10');
        $('#cfg_ensamble_peso').val(50);
        $('#cfg_estabilizar_n').val('52');
        actualizarLecturaPeso();
        sincronizarParametro($('#cfg_capar_outliers'), $('#cfg_capar_k'));
        sincronizarParametro($('#cfg_ensamble'), $('#cfg_ensamble_peso'));
        sincronizarParametro($('#cfg_estabilizar'), $('#cfg_estabilizar_n'));
    }

    $('#cfg_capar_outliers').on('change', function() {
        sincronizarParametro($(this), $('#cfg_capar_k'));
    });
    $('#cfg_ensamble').on('change', function() {
        sincronizarParametro($(this), $('#cfg_ensamble_peso'));
    });
    $('#cfg_estabilizar').on('change', function() {
        sincronizarParametro($(this), $('#cfg_estabilizar_n'));
    });
    $('#cfg_ensamble_peso').on('input', actualizarLecturaPeso);

    // Al abrir el modal, todos los paneles de "Opciones" arrancan cerrados y los parámetros al día.
    $('#modalForecastConfiguracion').on('show.bs.modal', function() {
        $(this).find('.opcion-toggle').attr('aria-expanded', 'false');
        $(this).find('.opcion-body').hide();
        resetParametrosOpciones();
    });

    // Paneles plegables de "Opciones": revela/oculta la explicación al hacer clic en la cabecera.
    // El switch vive fuera del botón, así que accionarlo no colapsa el panel.
    $('#modalForecastConfiguracion').on('click', '.opcion-toggle', function() {
        const $btn  = $(this);
        const $body = $('#' + $btn.attr('aria-controls'));
        const abrir = $btn.attr('aria-expanded') !== 'true';

        $btn.attr('aria-expanded', abrir ? 'true' : 'false');
        $body.stop(true, true).slideToggle(180);
    });

    // Limpia el mensaje del modal al escribir en el formulario.
    if (typeof activarLimpiezaMensajeAlEscribir === 'function') {
        activarLimpiezaMensajeAlEscribir('#form-forecast-config', '#modal-forecast-config-mensajes');
    }

    // Crear configuración (nombre + interruptores). Mismo patrón que crear menú/perfil.
    $('#form-forecast-config').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarForecastConfig');
        const formulario   = '#form-forecast-config';
        const modalMensaje = '#modal-forecast-config-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/forecast_config_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, nombre, y los switches marcados
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    resetParametrosOpciones();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaConsulta.ajax.reload(null, false);
                } else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        // Despliega el panel del parámetro con error para que el mensaje sea visible.
                        if (res.errors.capar_k)               abrirPanelOpcion('#op-body-outliers');
                        if (res.errors.ensamble_peso_prophet) abrirPanelOpcion('#op-body-ensamble');
                        if (res.errors.estabilizar_n_semanas) abrirPanelOpcion('#op-body-estabilizar');
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
