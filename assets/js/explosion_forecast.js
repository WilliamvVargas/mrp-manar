$(document).ready(function() {

    // ============================================================
    //  Explosión de Forecast — corre el pipeline paso a paso y marca ✓/✗.
    //  Usa el presupuesto de la empresa activa y la VERSIÓN elegida en el modal.
    // ============================================================

    const $lis  = $('#explosion-pasos li');
    const total = $lis.length;
    let corriendo = false;

    const ICONOS = {
        pend: '<i class="bi bi-dash-circle text-muted"></i>',
        run:  '<span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>',
        ok:   '<i class="bi bi-check-circle-fill text-success"></i>',
        err:  '<i class="bi bi-x-circle-fill text-danger"></i>'
    };

    // URL de un paso, con la versión de presupuesto elegida (el controlador toma la empresa
    // activa de la sesión).
    function urlPaso(i) {
        const version = $('#explosion-version').val() || '';
        return 'controllers/explosion_forecast_controller.php?action=explosion_paso'
             + '&paso='    + i
             + '&version=' + encodeURIComponent(version);
    }

    function setEstado($li, estado, detalle) {
        const $e = $li.find('.paso-estado');
        $e.html(ICONOS[estado] || ICONOS.pend);
        if (detalle) { $e.attr('title', detalle); } else { $e.removeAttr('title'); }
    }

    function finalizar(exito, labelFallo) {
        corriendo = false;
        $('#btn-explosion-ejecutar').prop('disabled', false).html('<i class="bi bi-play-fill"></i> Volver a ejecutar');
        if (exito) {
            // Refresca la tabla del Forecast para mostrar los registros recién calculados
            // (se recupera la instancia por su id; conserva la página actual con reload(null,false)).
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#tabla-consulta-forecast')) {
                $('#tabla-consulta-forecast').DataTable().ajax.reload(null, false);
            }
            mostrarMensajeFormulario('#modal-mensajes-explosion', 'Éxito', 'La explosión de forecast se completó correctamente.', 'success');
        } else {
            const msg = labelFallo
                ? 'Falló en el paso "' + labelFallo + '". Pasa el cursor sobre la ✗ para ver el detalle.'
                : 'La explosión de forecast falló. Revisa el paso marcado.';
            mostrarMensajeFormulario('#modal-mensajes-explosion', 'Atención', msg, 'danger');
        }
    }

    function correrPaso(i) {
        if (i >= total) { finalizar(true); return; }

        const $li = $lis.eq(i);
        setEstado($li, 'run');

        $.ajax({ url: urlPaso(i), type: 'GET', dataType: 'json' })
            .done(function(res) {
                if (res && res.status === 'success' && res.ok) {
                    setEstado($li, 'ok');
                    correrPaso(i + 1);
                } else {
                    setEstado($li, 'err', (res && (res.salida || res.message)) || 'Paso fallido.');
                    finalizar(false, (res && res.label) || '');
                }
            })
            .fail(function() {
                setEstado($li, 'err', 'Error de conexión con el servidor.');
                finalizar(false, '');
            });
    }

    function ejecutar() {
        if (corriendo) { return; }

        // Debe haber una versión de presupuesto elegida (la empresa la valida el servidor).
        const version = $('#explosion-version').val() || '';
        if (version === '') {
            mostrarMensajeFormulario('#modal-mensajes-explosion', 'Atención',
                'Selecciona una versión de presupuesto para ejecutar la explosión.', 'warning');
            return;
        }

        corriendo = true;
        $('#btn-explosion-ejecutar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Ejecutando...');
        mostrarMensajeFormulario('#modal-mensajes-explosion', 'Procesando',
            'Ejecutando el pipeline con la versión ' + version + ', puede tardar ~1 minuto...', 'info');
        $lis.each(function() { setEstado($(this), 'pend'); });
        correrPaso(0);
    }

    // Al abrir el modal se deja en estado inicial (no arranca solo: el usuario elige versión).
    $('#modalExplosionForecast').on('shown.bs.modal', function() {
        if (corriendo) { return; }
        $('#modal-mensajes-explosion').empty();
        $lis.each(function() { setEstado($(this), 'pend'); });
    });

    // Botón Ejecutar / Volver a ejecutar.
    $('#btn-explosion-ejecutar').on('click', ejecutar);
});
