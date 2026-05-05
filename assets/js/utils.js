/** Trigger para cerrar mensajes con el boton cerrar*/
$(document).on('click', '[id*="mensajes"] .btn-close', function(e) {
    e.preventDefault();
    
    const $contenedor = $(this).closest('[id*="mensajes"]');
    
    $contenedor.slideUp(400, function() {
        $(this).html('').show();
    });
});

/**
 * Inicializa la escucha de eventos en los inputs para ocultar la alerta general
 * @param {string} selectorForm - ID del formulario
 * @param {string} selectorMensajes - ID del contenedor de alertas
 */
function activarLimpiezaMensajeAlEscribir(selectorForm, selectorMensajes) {

    $(selectorForm).on('input', 'input, select, textarea', function() {
        const $contenedor = $(selectorMensajes);
        
        if ($contenedor.is(':visible')) {
            $contenedor.slideUp(300);
        }
    });
}


/**
 * Muestra un mensaje estructurado con icono, título y animación dentro de un contenedor
 * @param {string} contenedor - Selector del div (ej: '#modal-mensajes')
 * @param {string} titulo - Título corto
 * @param {string} mensaje - Descripción detallada
 * @param {string} tipo - 'success', 'danger', 'warning', 'info'
 */
function mostrarMensajeFormulario(contenedor, titulo, mensaje, tipo = 'danger') {
    const iconos = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-octagon-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill'
    };

    const icono = iconos[tipo] || iconos['danger'];

    const html = `
        <div class="alert alert-${tipo} alert-dismissible fade show p-4" role="alert">
            <div class="d-flex align-items-center justify-content-center mb-2">
                <i class="bi ${icono} fs-5 me-2"></i>
                <h6 class="alert-heading m-0 text-center fw-bold">${titulo}</h6>
            </div>
            <ul class="mb-0 small d-inline-block text-start">
                ${mensaje}
            </ul>
            <button type="button" class="btn-close" aria-label="Close"></button>
        </div>
    `;

    $(contenedor)
    .stop(true, true)
    .hide()
    .html(html)
    .fadeIn({
        duration: 400,
        queue: false
    }).css('display', 'none').slideDown(400);
}


/**
 * Limpia los estados de validación de Bootstrap en un formulario
 * @param {string} selectorForm - El ID o clase del formulario (ej: '#form-usuario')
 */
function limpiarErroresFormulario(selectorForm) {
    const form = $(selectorForm);
    
    form.find('.form-control, .form-select').removeClass('is-invalid');
    form.find('.invalid-feedback').text('');

    $('[id*="error-general"]').html(''); 
}




/**
 * Limpia validaciones, mensajes y opcionalmente resetea el formulario
 * @param {string} selectorForm - ID del formulario (ej: '#form-usuario')
 * @param {string} selectorMensajes - ID del contenedor de alertas (ej: '#modal-mensajes')
 * @param {boolean} resetInputs - Si es true, borra los valores de los inputs
 */
function limpiarFormularioCompleto(selectorForm, selectorMensajes, resetInputs = false) {
    const form = $(selectorForm);
    
    form.find('.form-control, .form-select').removeClass('is-invalid');
    form.find('.invalid-feedback').text('');
    
    const $mensajes = $(selectorMensajes);
    $mensajes.slideUp(300, function() {
        $(this).html('').show(); 
    });

    if (resetInputs) {
        form[0].reset();
        form.find('input[type="hidden"]').val('');
    }
}

/**
 * Deshabilita un botón y le pone un spinner
 * @param {jQuery} btn - El objeto jQuery del botón
 * @param {string} textoCarga - Texto a mostrar junto al spinner
 */
function setBtnLoading(btn, textoCarga = 'Cargando...') {
    btn.data('original-text', btn.html());
    btn.prop('disabled', true);
    btn.html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${textoCarga}`);
}

/**
 * Habilita el botón y restaura su texto original
 * @param {jQuery} btn - El objeto jQuery del botón
 */
function resetBtnLoading(btn) {
    const originalText = btn.data('original-text');
    btn.prop('disabled', false);
    if (originalText) {
        btn.html(originalText);
    }
}

