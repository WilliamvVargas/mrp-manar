//configura todas las llamadas ajax con codigo 401 a retornar al login
$.ajaxSetup({
    statusCode: {
        401: function() {
            window.location.href = "index?error=session_expired";
        }
    }
});


/** Trigger para cerrar mensajes con el boton cerrar*/
$(document).on('click', '[id*="mensajes"] .btn-close', function(e) {
    e.preventDefault();
    
    const $contenedor = $(this).closest('[id*="mensajes"]');
    
    $contenedor.slideUp(500, function() {
        $(this).html('').show();
    });
});

/**
 * Activa la funcionalidad de mostrar/ocultar contraseña
 * @param {string} selectorBtn - ID del botón que hace el toggle (ej: '#togglePassword')
 * @param {string} selectorInput - ID del input de contraseña (ej: '#password')
 * @param {string} selectorIcon - ID del icono dentro del botón (ej: '#iconEye')
 */
function activarTogglePassword(selectorBtn, selectorInput, selectorIcon) {
    $(selectorBtn).on('click', function() {
        const input = $(selectorInput);
        const icon = $(selectorIcon);

        // Cambiamos el tipo de input
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
}

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
function mostrarMensajeFormulario(contenedor, titulo, mensaje, tipo = 'danger', duracion = 400, credenciales = null) {
    const iconos = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-octagon-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill'
    };

    const icono = iconos[tipo] || iconos['danger'];

    let HTMLCredenciales = '';

    //Caso especial en caso que venga las credenciales
    if (tipo === 'success' && credenciales) {
        HTMLCredenciales = `
        <div class="mt-2 pt-2 border-top border-success-subtle text-start">
            <div class="fw-bold text-center mb-1">
                <i class="bi bi-key-fill me-1 "></i> Credenciales de acceso
            </div>
            
            <div class="d-flex align-items-center justify-content-between" style="font-size: 0.82rem;">
                <div style="line-height: 1.4;">
                    <span class="d-block"><strong>Usuario:</strong> <span>${credenciales.usuario}</span></span>
                    <span class="d-block"><strong>Contraseña:</strong> <span>${credenciales.password}</span></span>
                </div>
                
                <button class="btn btn-sm btn-light border-secondary-subtle text-dark ms-3 py-1 px-2 d-flex align-items-center btn-copiar-credenciales-global" 
                        type="button" 
                        data-clipboard="Usuario: ${credenciales.usuario}&#10;Contraseña: ${credenciales.password}">
                    <i class="bi bi-clipboard me-1"></i> Copiar
                </button>
            </div>
        </div>`;
    }

    const html = `
        <div class="alert alert-${tipo} alert-dismissible fade show p-3" role="alert"> <div class="d-flex align-items-center justify-content-center mb-2">
                <i class="bi ${icono} fs-5 me-2"></i>
                <h6 class="alert-heading m-0 text-center fw-bold">${titulo}</h6>
            </div>
            <div class="mb-0 small text-center">
                ${mensaje}
            </div>
            ${HTMLCredenciales}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

    $(contenedor)
    .stop(true, true)
    .hide()
    .html(html)
    .fadeIn({
        duration: duracion,
        queue: false
    }).css('display', 'none').slideDown(duracion);
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
    form.find('.form-control, .form-select').removeClass('is-valid');

    form.find('.invalid-feedback').text('').removeClass('d-block');

    const $mensajes = $(selectorMensajes);
    $mensajes.slideUp(300, function() {
        $(this).html('').show(); 
    });

    if (resetInputs) {
        form[0].reset();
        form.find('input[type="hidden"]:not([name="csrf_token"])').val('');

        //Se restauran por defecto los iconos de los ojos
        form.find('input[name*="password"], input[type="text"][id*="password"]').attr('type', 'password');
        form.find('.bi-eye-slash').removeClass('bi-eye-slash').addClass('bi-eye');
    }
}

/**
 * Deshabilita un botón y le pone un spinner
 * @param {jQuery} btn - El objeto jQuery del botón
 * @param {string} textoCarga - Texto a mostrar junto al spinner
 */
function setBtnLoading(btn, textoCarga = 'Cargando...') {
    btn.data('original-text', btn.html());

    const anchoOriginal = btn.outerWidth();
    btn.css('min-width', `${anchoOriginal}px`);
    
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


/**
 * Procesa de forma unificada los errores de servidor (HTTP) en cualquier petición AJAX
 * @param {jQuery.jqXHR} jqXHR - Objeto nativo de error de jQuery
 * @param {string} textStatus - Estado textual del error ('timeout', 'error', etc.)
 * @param {string} contenedorAlerta - Selector del div de destino (ej: '#modal-mensajes')
 */
function manejarErrorAjax(jqXHR, textStatus, contenedorAlerta) {
    let mensajeError = "Ocurrió un error crítico en el servidor.";
    if (textStatus === 'timeout') {
        mensajeError = "El servidor está tardando demasiado en responder.";
    } else if (jqXHR.status === 403) {
        mensajeError = "Su sesión expiró o la solicitud no es válida (Token CSRF inválido).";
    } else if (jqXHR.status === 404) {
        mensajeError = "No se encontró el controlador o endpoint en el servidor.";
    } else if (jqXHR.status === 500) {
        mensajeError = "Error interno del servidor (500). Revisa los logs de PHP.";
    }
    mostrarMensajeFormulario(contenedorAlerta, 'Error de Sistema', mensajeError, 'danger');
}


/**
 * Renderiza los errores del backend en sus respectivos campos.
 * @param {string} idFormulario - ID del formulario actual (ej: '#form-usuario')
 * @param {Object} errores - Objeto clave-valor con los errores ({ usuario: 'Error...', nombres: 'Error...' })
 */
function renderizarErroresCampos(idFormulario, errores) {
    const $form = $(idFormulario);
    
    // Iteramos sobre cada error que mandó el servidor
    Object.keys(errores).forEach(function(nombreCampo) {
        const mensajeError = errores[nombreCampo];
        
        // 1. Buscamos el input exacto por su atributo NAME dentro de ESTE formulario
        const $input = $form.find(`input[name="${nombreCampo}"], select[name="${nombreCampo}"], textarea[name="${nombreCampo}"]`);
        
        if ($input.length) {
            // Marcamos el input como inválido
            $input.addClass('is-invalid');
            
            // 2. BÚSQUEDA QUIRÚRGICA: Buscamos el invalid-feedback que sea hermano 
            // del input o que esté dentro de su mismo grupo contenedor (mb-2 o col)
            let $feedback = $input.siblings('.invalid-feedback');
            
            // Si no lo encuentra al lado (caso de los input-group con botones), busca en su contenedor directo
            if (!$feedback.length) {
                $feedback = $input.closest('.input-group').siblings('.invalid-feedback');
            }
            if (!$feedback.length) {
                $feedback = $input.closest('div').find('.invalid-feedback').first();
            }

            // Inyectamos el mensaje y lo mostramos
            $feedback.text(mensajeError).addClass('d-block');
        }
    });
}
/**
 * Limpia el estado de error de un campo específico y colapsa su espacio vertical
 * @param {jQuery} $input - El elemento jQuery del input a limpiar
 */
function limpiarErrorCampo($input) {
    $input.removeClass('is-invalid');
    
    // Buscamos el feedback asociado solo a este input específico
    let $feedback = $input.siblings('.invalid-feedback');
    if (!$feedback.length) {
        $feedback = $input.closest('.input-group').siblings('.invalid-feedback');
    }
    if (!$feedback.length) {
        $feedback = $input.closest('div').find('.invalid-feedback').first();
    }
    
    $feedback.text('').removeClass('d-block');
}

/**
 * Crea una función con retraso (Debounce) para evitar ejecuciones masivas.
 * @param {Function} func - La función que se ejecutará al final.
 * @param {Number} delay - Tiempo de espera en milisegundos.
 */
function debounce(func, delay = 400) {
    let timeoutId;
    return function (...args) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}


$(document).on('input', '.form-validar-instantaneo input[name="usuario"]', function() {
    let valor = $(this).val();
    let valorLimpio = valor.toLowerCase().replace(/\s+/g, '');
    $(this).val(valorLimpio);
});


// 1. Al escribir: Valida el propio campo Y sus dependencias con DEBOUNCE
$(document).on('keyup', '.form-validar-instantaneo input', debounce(function() {
    const $inputModificado = $(this);
    
    // Primero validamos el campo en el que se está escribiendo
    ejecutarValidacionUniversal($inputModificado);
    
    // Luego disparamos las dependencias cruzadas de forma pausada
    procesarDependenciasCruzadas($inputModificado);
}));

// 2. Al perder el foco (Blur): Valida de inmediato (sin esperar al debounce)
$(document).on('blur', '.form-validar-instantaneo input', function() {
    const $inputModificado = $(this);
    
    ejecutarValidacionUniversal($inputModificado);
    procesarDependenciasCruzadas($inputModificado);
});

/**
 * Función auxiliar para evaluar y disparar la validación de campos hermanos
 * @param {jQuery} $inputModificado - El input que cambió su valor
 */
function procesarDependenciasCruzadas($inputModificado) {
    const nameModificado = $inputModificado.attr('name');
    const $form = $inputModificado.closest('form');
    
    // CASO A: Modificaste el "Padre" (password). Buscamos al "Hijo" (confirm_password).
    const $hijoDependiente = $form.find(`[data-comparar-con="${nameModificado}"]`);
    if ($hijoDependiente.length && $hijoDependiente.val() !== '') {
        ejecutarValidacionUniversal($hijoDependiente);
    }

    // CASO B: Modificaste el "Hijo" (confirm_password). Sincronizamos con el "Padre".
    const namePadre = $inputModificado.data('comparar-con');
    if (namePadre) {
        const $padre = $form.find(`input[name="${namePadre}"]`);
        if ($padre.length && $padre.val() !== '' && $padre.hasClass('is-invalid')) {
            ejecutarValidacionUniversal($padre);
        }
    }
}

/**
 * Procesa la validación asíncrona de un campo de manera agnóstica al módulo.
 * @param {jQuery} $input - Elemento que se está evaluando.
 */
function ejecutarValidacionUniversal($input) {
    const $form = $input.closest('form');
    const idFormulario = `#${$form.attr('id')}`;
    const fieldName = $input.attr('name');
    const value = $input.val();
    const csrf_token = $form.find('input[name="csrf_token"]').val();
    const idRegistro = $form.find('input[name="id_usuario"], input[name="id"], input[name="id_producto"]').val() || null;
    
    // CAPTURA CLAVE: Leemos a qué controlador debe ir este formulario específico
    const urlControlador = $form.attr('action'); 

    // Lógica de comparación dinámica (data-comparar-con)
    let extraValue = null;
    const inputCompaneroName = $input.data('comparar-con'); 
    if (inputCompaneroName) {
        extraValue = $form.find(`input[name="${inputCompaneroName}"]`).val();
    }

    // Si por alguna razón el formulario no tiene action, detenemos para evitar errores
    if (!urlControlador) return;

    $.ajax({
        // La URL ahora es 100% dinámica, concatenando la acción de validación de campo
        url: `${urlControlador}?action=validar_campo`,
        type: 'POST',
        data: {
            campo: fieldName,
            valor: value,
            extra: extraValue,
            id_registro: idRegistro,
            csrf_token: csrf_token
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'error') {
                $input.removeClass('is-valid');
                if (response.type === 'fields') {
                    renderizarErroresCampos(idFormulario, response.errors);
                } 
            } else {
                $input.addClass('is-valid');
                limpiarErrorCampo($input);
            }
        }
    });
}

