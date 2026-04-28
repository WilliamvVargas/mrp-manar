/**
 * Muestra una alerta de Bootstrap estandarizada
 * @param {string} titulo 
 * @param {string|array} mensajes 
 * @param {string} tipo (danger, success, warning, info)
 */
function mostrarAlertaGeneral(titulo, mensajes, tipo = 'danger') {
    let listaMensajes = Array.isArray(mensajes) ? mensajes : [mensajes];
    let cuerpoHTML = listaMensajes.map(m => `<li>${m}</li>`).join('');

    // Definir icono según el tipo
    let icono = 'bi-exclamation-octagon-fill';
    if(tipo === 'success') icono = 'bi-check-circle-fill';
    if(tipo === 'warning') icono = 'bi-exclamation-triangle-fill';

    const alertaHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show p-4" role="alert">
            <div class="d-flex align-items-center justify-content-center mb-2">
                <i class="bi ${icono} fs-5 me-2"></i>
                <h6 class="alert-heading m-0 text-center fw-bold">${titulo}</h6>
            </div>
            <ul class="mb-0 small d-inline-block text-start">
                ${cuerpoHTML}
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    $('#alert-container')
        .hide()
        .html(alertaHTML)
        .stop(true, true)
        .slideDown(400);
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