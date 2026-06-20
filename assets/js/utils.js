//Configura todas las llamadas ajax con codigo 401 a retornar al login
$.ajaxSetup({
    statusCode: {
        401: function() {
            window.location.href = "index?error=session_expired";
        }
    }
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
            $feedback.html(mensajeError).addClass('d-block');
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

/**
 * Inicializa una tabla de DataTables en modo server-side, reutilizable por
 * cualquier mantenedor. Incluye:
 *   - Buscador propio (input externo) con debounce, que envía el parámetro 'consulta'.
 *   - Spinner de Bootstrap como indicador de carga.
 *   - Contador de registros que omite el redundante "de un total de 0" sin resultados.
 *
 * @param {Object} opciones
 * @param {string} opciones.tabla    - Selector de la tabla (ej: '#tabla-consulta').
 * @param {string} opciones.url      - URL del controlador (con action=listar).
 * @param {string} opciones.input    - Selector del input de búsqueda (ej: '#consulta').
 * @param {Array}  opciones.columnas - Definición de columnas de DataTables.
 * @param {Array}  opciones.orden    - Orden inicial (ej: [[3, 'desc']]).
 * @param {Function} [opciones.extra] - Opcional. Recibe el objeto de datos de la
 *                                      petición para agregar parámetros extra (filtros).
 * @returns {Object} La instancia de DataTable.
 */
function inicializarTablaConsulta(opciones) {

    // Indicador de carga: spinner de Bootstrap + texto
    $(opciones.tabla).on('processing.dt', function(e, settings, processing) {
        if (processing) {
            $('.dataTables_processing', settings.nTableWrapper)
                .html('<div class="spinner-border" role="status" aria-hidden="true"></div><span>Cargando . . .</span>');
        }
    });

    const tabla = $(opciones.tabla).DataTable({
        serverSide: true,   // El servidor pagina, busca y ordena
        processing: true,   // Activa el indicador de carga
        ajax: {
            url: opciones.url,
            type: 'GET',
            data: function(d) {
                d.consulta = $(opciones.input).val();   // Texto del buscador propio
                if (typeof opciones.extra === 'function') {
                    opciones.extra(d);   // parámetros adicionales (filtros extra)
                }
            }
        },
        // Layout: arriba [cantidad por página | info de registros], abajo la paginación.
        dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12'p>>",
        autoWidth: false,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        // Sin resultados: omite el redundante "de un total de 0 registros"
        infoCallback: function(settings, start, end, max, total, pre) {
            if (total === 0) {
                let texto = 'Mostrando registros del 0 al 0';
                if (max > 0 && max !== total) {
                    texto += ' (filtrado de un total de ' + max + ' registros)';
                }
                return texto;
            }
            return pre;
        },
        order: opciones.orden,
        columns: opciones.columnas
    });

    // Buscador propio con debounce → recarga la tabla
    $(opciones.input).on('input', debounce(function() {
        tabla.ajax.reload();
    }, 400));

    return tabla;
}

/**
 * Inicializa un "select con iconos": un dropdown de Bootstrap que se comporta como
 * un <select> pero permite icono + texto en cada opción (los <option> nativos no).
 * Reutilizable por cualquier mantenedor.
 *
 * Inyecta el dropdown dentro del contenedor indicado y guarda el valor elegido en un
 * <input type="hidden" id={idValor}>, de modo que se lea igual que un select normal
 * (p. ej. desde el `extra` de inicializarTablaConsulta: $('#idValor').val()).
 *
 * @param {Object}    config
 * @param {string}    config.contenedor    - Selector del contenedor donde se inyecta.
 * @param {string}    config.idValor       - Id del input hidden que guardará el valor.
 * @param {Array}     config.opciones      - [{ valor, texto, icono }] (icono = clase bi, opcional).
 * @param {string}   [config.valorInicial] - Valor seleccionado por defecto (por defecto, el primero).
 * @param {Function} [config.onCambio]     - Callback(valor) al cambiar la selección.
 */
function inicializarSelectIconos(config) {
    const $cont    = $(config.contenedor);
    const opciones = config.opciones || [];

    const iconoHtml = function(icono) {
        return icono ? '<i class="bi ' + icono + ' me-2"></i>' : '';
    };

    let itemsHtml = '';
    opciones.forEach(function(op) {
        itemsHtml += '<li><button type="button" class="dropdown-item d-flex align-items-center select-iconos-item" '
                   + 'data-valor="' + op.valor + '">' + iconoHtml(op.icono) + op.texto + '</button></li>';
    });

    $cont.html(
        '<div class="dropdown">'
        +   '<button class="btn btn-sm border bg-white dropdown-toggle w-100 d-flex align-items-center text-start" '
        +           'type="button" data-bs-toggle="dropdown" aria-expanded="false">'
        +       '<span class="select-iconos-texto flex-grow-1"></span>'
        +   '</button>'
        +   '<ul class="dropdown-menu w-100">' + itemsHtml + '</ul>'
        +   '<input type="hidden" id="' + config.idValor + '">'
        + '</div>'
    );

    const $texto  = $cont.find('.select-iconos-texto');
    const $hidden = $cont.find('#' + config.idValor);

    // Aplica una selección: actualiza la etiqueta visible y el valor; dispara el callback si corresponde.
    function seleccionar(valor, disparar) {
        const op = opciones.find(function(o) { return o.valor === valor; }) || opciones[0];
        if (!op) {
            return;
        }
        $texto.html(iconoHtml(op.icono) + op.texto);
        $hidden.val(op.valor);
        if (disparar && typeof config.onCambio === 'function') {
            config.onCambio(op.valor);
        }
    }

    $cont.on('click', '.select-iconos-item', function() {
        seleccionar($(this).attr('data-valor'), true);
    });

    const inicial = (config.valorInicial !== undefined)
        ? config.valorInicial
        : (opciones[0] ? opciones[0].valor : '');
    seleccionar(inicial, false);
}

/**
 * Construye el HTML de un ítem del listado ordenable de "Asignar Posición".
 * Reutilizable por cualquier mantenedor (lo invoca el `construirItems` del contexto).
 * @param {number|string} id     Identificador del registro.
 * @param {string} nombre        Nombre visible del registro.
 * @param {number} ordenOriginal Número de orden con el que se cargó (1..N).
 * @param {string} tipo          'movible' (único arrastrable, insignia azul),
 *                               'fijo' (restringido, no se arrastra) o
 *                               'libre' (arrastrable en modo global, sin insignia azul).
 * @param {string} textoBadge    Texto de la insignia del ítem movible (ej: 'Nuevo', 'Editando').
 * @param {number} estado        1 = activo, 0 = inactivo.
 * @returns {string} HTML del <li>.
 */
function itemPosicion(id, nombre, ordenOriginal, tipo, textoBadge, estado) {
    const nombreEsc = $('<div>').text(nombre).html();   // escapa el nombre (evita XSS)
    const esMovible = (tipo === 'movible');
    const clase     = esMovible ? ' list-group-item-primary' : '';

    // Etiqueta contextual: insignia azul del ítem movible, o amarilla (oculta) que
    // muestra la posición original cuando un registro cambia de lugar.
    const etiqueta = esMovible
        ? '<span class="badge bg-primary">' + (textoBadge || 'Movible') + '</span>'
        : '<span class="badge bg-warning text-dark badge-cambio d-none"></span>';

    // Estado actual del registro: verde (Activo) / gris (Inactivo). Opcional:
    // si no se pasa estado (null/undefined/''), no se muestra la insignia de estado.
    const estadoBadge = (estado === null || estado === undefined || estado === '')
        ? ''
        : (Number(estado) === 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>');

    // Ambas etiquetas agrupadas a la DERECHA: contextual + estado (al extremo).
    const etiquetas = '<span class="ms-auto d-flex align-items-center gap-1">' + etiqueta + estadoBadge + '</span>';

    // Ícono de flechas para los arrastrables ('movible' y 'libre'); círculo para los restringidos ('fijo').
    const icono = (tipo === 'fijo')
        ? '<i class="bi bi-dash-circle me-2 text-muted"></i>'
        : '<i class="bi bi-arrows-vertical me-2 text-muted"></i>';

    return '<li class="list-group-item d-flex align-items-center' + clase + '" data-id="' + id + '" data-original="' + ordenOriginal + '" data-movible="' + (esMovible ? '1' : '0') + '">'
         + icono
         + '<span class="numero-orden fw-semibold me-1">' + ordenOriginal + '.</span>'
         + '<span class="nombre-registro">' + nombreEsc + '</span>'
         + etiquetas
         + '</li>';
}

/**
 * Inicializa el modal "Asignar Posición" reutilizable (drag & drop con jQuery UI).
 * Sirve para cualquier mantenedor con una columna de orden; el comportamiento
 * específico de cada uno se define en los "contextos" que se le pasan.
 *
 * Markup requerido en la página (IDs fijos, ya genéricos):
 *   #modalAsignarPosicion, #lista-posicion, #modal-mensajes-posicion,
 *   #btn-volver-posicion, #btn-cancelar-posicion, #btn-guardar-posicion,
 *   #instruccion-posicion-uno, #instruccion-posicion-todos
 *
 * @param {Object} config
 * @param {string} config.urlListar    - URL GET que devuelve los registros ordenados ({status, data:[{id,nombre,estado,...}]}).
 * @param {string} config.urlReordenar - URL POST que persiste el nuevo orden global (recibe orden[] + csrf_token).
 * @param {Object} config.tabla        - Instancia de DataTable a recargar tras guardar el orden global.
 * @param {Array}  config.contextos    - Lista de contextos (creación, edición, global...).
 * @param {string} [config.csrf]       - Selector del input con el token CSRF (por defecto '#csrf_token').
 *
 * Forma de un contexto:
 *   {
 *     boton:          '#btn-...',                  // botón que dispara la apertura
 *     global:         true|false,                 // true = todos los ítems movibles, guardado directo
 *     // Solo contextos NO globales:
 *     modalPadre:     '#modal...',                 // modal del que se "salta" y al que se vuelve
 *     inputVisible:   '#...',                      // input visible donde se refleja la posición elegida
 *     inputHidden:    '#...',                      // input oculto que viaja con el form padre
 *     alCerrarReal:   function(){},                // limpieza al cerrar el modal padre de verdad (no salto)
 *     // Todos:
 *     idMovible:      function(){ return id|null },          // id del ítem arrastrable (null en global)
 *     construirItems: function(data){ return [htmlLi, ...] } // arma el listado (usa itemPosicion)
 *   }
 */
function inicializarAsignadorPosicion(config) {
    const SEL_MODAL       = '#modalAsignarPosicion';
    const SEL_LISTA       = '#lista-posicion';
    const SEL_MENSAJES    = '#modal-mensajes-posicion';
    const SEL_BTN_VOLVER  = '#btn-volver-posicion';
    const SEL_BTN_CANCEL  = '#btn-cancelar-posicion';
    const SEL_BTN_GUARDAR = '#btn-guardar-posicion';
    const SEL_INSTR_UNO   = '#instruccion-posicion-uno';
    const SEL_INSTR_TODOS = '#instruccion-posicion-todos';

    const selectorCsrf = config.csrf || '#csrf_token';
    const modalAsignar = bootstrap.Modal.getOrCreateInstance(document.querySelector(SEL_MODAL));

    let ctxActivo = null;    // contexto activo del modal (creación, edición o global)
    let saltando  = false;   // true mientras se "salta" desde el modal padre a Asignar Posición

    // Abre el modal desde un contexto: arma el listado y, según el modo, salta del modal
    // padre (evita el destello) o lo abre directo (global, sin modal padre).
    function abrir(ctx) {
        ctxActivo = ctx;
        setBtnLoading($(ctx.boton), 'Cargando...');

        const esGlobal = !!ctx.global;

        // Limpia un posible mensaje previo y ajusta footer + instrucción según el modo.
        $(SEL_MENSAJES).empty();
        $(SEL_BTN_VOLVER).toggleClass('d-none', esGlobal);
        $(SEL_BTN_CANCEL).toggleClass('d-none', !esGlobal);
        $(SEL_BTN_GUARDAR).toggleClass('d-none', !esGlobal);
        $(SEL_INSTR_UNO).toggleClass('d-none', esGlobal);
        $(SEL_INSTR_TODOS).toggleClass('d-none', !esGlobal);

        cargarLista(ctx, function() {
            if (esGlobal) {
                modalAsignar.show();   // sin modal padre: se abre directo
            } else {
                saltando = true;
                bootstrap.Modal.getOrCreateInstance(document.querySelector(ctx.modalPadre)).hide();
            }
        });
    }

    // Carga los registros (ordenados por posición ASC) y arma la lista ordenable.
    function cargarLista(ctx, onReady) {
        const $lista = $(SEL_LISTA);
        $lista.html('<li class="list-group-item text-muted small">Cargando...</li>');

        $.ajax({
            url: config.urlListar,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $lista.html('<li class="list-group-item text-danger small">No se pudo cargar el listado.</li>');
                    if (typeof onReady === 'function') onReady();
                    return;
                }

                const items     = ctx.construirItems(res.data);
                const idMovible = ctx.idMovible();

                // Destruye un sortable previo antes de re-pintar (mientras conserva 'ui-sortable').
                if ($lista.hasClass('ui-sortable')) {
                    $lista.sortable('destroy');
                }

                // Layout: lista vertical (por defecto) o grilla 2D (opt-in con config.grilla).
                // Menús no pasa config.grilla -> queda 'list-group' como siempre.
                $lista.attr('class', config.grilla ? 'lista-posicion-grilla' : 'list-group');
                $lista.html(items.join(''));

                const opcionesSortable = {
                    placeholder: config.grilla
                        ? 'lista-posicion-celda lista-posicion-celda-placeholder'
                        : 'list-group-item lista-posicion-placeholder',
                    forcePlaceholderSize: true,
                    change: function(event, ui) {
                        renumerar(ui.item, ui.placeholder);   // en vivo, durante el arrastre
                    },
                    update: function() {
                        renumerar();   // al soltar
                    }
                };

                // Sin grilla: movimiento solo vertical (lista). Con grilla: libre (2D).
                if (!config.grilla) {
                    opcionesSortable.axis = 'y';
                }

                // En creación/edición solo el ítem movible se puede tomar; en global, todos.
                if (!ctx.global) {
                    opcionesSortable.cancel = 'li:not([data-id="' + idMovible + '"])';
                }

                $lista.sortable(opcionesSortable);

                // Ajusta números y etiquetas según la posición actual de los ítems.
                renumerar();

                // Todo procesado: recién ahora se abre el modal (sin destello).
                if (typeof onReady === 'function') onReady();
            },
            error: function() {
                $lista.html('<li class="list-group-item text-danger small">Error al cargar el listado.</li>');
                if (typeof onReady === 'function') onReady();
            }
        });
    }

    // Recalcula el número de orden de cada ítem según su posición actual. Durante el
    // arrastre recibe el ítem arrastrado y el placeholder para ubicarlo antes de soltar.
    function renumerar($arrastrado, $placeholder) {
        let n = 0;

        $(SEL_LISTA).children('li').each(function() {
            const $li = $(this);

            // El ítem que se arrastra no cuenta en su posición antigua.
            if ($arrastrado && $li.is($arrastrado)) {
                return;
            }

            n++;

            // Donde está el placeholder irá el ítem arrastrado.
            if ($placeholder && $li.is($placeholder)) {
                if ($arrastrado) {
                    fijarNumeroOrden($arrastrado, n);
                }
            } else {
                fijarNumeroOrden($li, n);
            }
        });
    }

    // Aplica el número de orden a un ítem y muestra "Posición Original N" si difiere.
    function fijarNumeroOrden($li, n) {
        const original  = parseInt($li.attr('data-original'), 10);
        const esMovible = $li.attr('data-movible') === '1';
        const cambio    = (n !== original);

        $li.find('.numero-orden').text(n + '.');

        // Los registros EXISTENTES que cambian de posición se marcan en amarillo y la
        // etiqueta muestra su posición original; el ítem movible conserva su estilo.
        if (!esMovible) {
            $li.toggleClass('list-group-item-warning', cambio);

            const $badge = $li.find('.badge-cambio');
            if (cambio) {
                $badge.text('Posición Original ' + original).removeClass('d-none');
            } else {
                $badge.text('').addClass('d-none');
            }
        }
    }

    // --- Conexión de eventos ---

    config.contextos.forEach(function(ctx) {
        $(ctx.boton).on('click', function() {
            abrir(ctx);
        });

        // Contextos con modal padre: gestiona el "salto" a Asignar Posición o el cierre real.
        if (!ctx.global && ctx.modalPadre) {
            $(ctx.modalPadre).on('hidden.bs.modal', function() {
                if (saltando && ctxActivo === ctx) {
                    saltando = false;
                    modalAsignar.show();   // conserva el formulario para poder volver luego
                    return;
                }
                if (typeof ctx.alCerrarReal === 'function') {
                    ctx.alCerrarReal();
                }
            });
        }
    });

    // Guardar orden (modo global): envía la secuencia completa de ids al backend.
    $(SEL_BTN_GUARDAR).on('click', function() {
        const $btn  = $(this);
        const orden = $(SEL_LISTA).children('li').map(function() {
            return $(this).attr('data-id');
        }).get();

        if (!orden.length) {
            return;
        }

        setBtnLoading($btn, 'Guardando...');

        $.ajax({
            url: config.urlReordenar,
            type: 'POST',
            data: { orden: orden, csrf_token: $(selectorCsrf).val() },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    modalAsignar.hide();
                    if (config.tabla) {
                        config.tabla.ajax.reload(null, false);
                    }
                } else {
                    resetBtnLoading($btn);
                    mostrarMensajeFormulario(SEL_MENSAJES, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading($btn);
                manejarErrorAjax(jqXHR, textStatus, SEL_MENSAJES);
            }
        });
    });

    // Al cerrar Asignar Posición: en global no hace nada (guardado explícito); en
    // creación/edición guarda la posición elegida y vuelve al modal padre.
    $(SEL_MODAL).on('hidden.bs.modal', function() {
        if (!ctxActivo) {
            return;
        }

        if (ctxActivo.global) {
            resetBtnLoading($(SEL_BTN_GUARDAR));
            ctxActivo = null;
            return;
        }

        const $lista   = $(SEL_LISTA);
        const $movible = $lista.children('li[data-id="' + ctxActivo.idMovible() + '"]');

        if ($movible.length) {
            const posicion = $lista.children('li').index($movible) + 1;
            $(ctxActivo.inputVisible).val(posicion);   // visible (solo muestra)
            $(ctxActivo.inputHidden).val(posicion);    // hidden: valor real que se envía
        }

        bootstrap.Modal.getOrCreateInstance(document.querySelector(ctxActivo.modalPadre)).show();
    });

    // Al abrir Asignar Posición (ya cargado), el botón del contexto vuelve a su estado original.
    $(SEL_MODAL).on('shown.bs.modal', function() {
        if (ctxActivo) {
            resetBtnLoading($(ctxActivo.boton));
        }
    });
}

/** Trigger para cerrar mensajes con el boton cerrar*/
$(document).on('click', '[id*="mensajes"] .btn-close', function(e) {
    e.preventDefault();
    
    const $contenedor = $(this).closest('[id*="mensajes"]');
    
    $contenedor.slideUp(500, function() {
        $(this).html('').show();
    });
});

// Mantiene cualquier input de usuario con letra minuscula y sin espacios
$(document).on('input', '.form-validar-instantaneo input[name="usuario"]', function() {
    let valor = $(this).val();
    let valorLimpio = valor.toLowerCase().replace(/\s+/g, '');
    $(this).val(valorLimpio);
});


// 1. Al escribir: Valida el propio campo Y sus dependencias con DEBOUNCE
$(document).on('keyup', '.form-validar-instantaneo input', debounce(function() {
    const $inputModificado = $(this);
    
    ejecutarValidacionUniversal($inputModificado);
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
function ejecutarValidacionUniversal($input, aprobar_input = true) {
    const $form = $input.closest('form');
    const idFormulario = `#${$form.attr('id')}`;
    const fieldName = $input.attr('name');
    const value = $input.val();
    const csrf_token = $form.find('input[name="csrf_token"]').val();
    const idRegistro = $form.find('input[name="id_registro"]').val() || null;
    const usar_check = $input.data('check')
    const urlControlador = $form.attr('action'); 

    // Lógica de comparación dinámica (data-comparar-con)
    let extraValue = null;
    const inputCompaneroName = $input.data('comparar-con'); 
    if (inputCompaneroName) {
        extraValue = $form.find(`input[name="${inputCompaneroName}"]`).val();
    }

    // Si por alguna razón el formulario no tiene action
    if (!urlControlador) 
        return;

    $.ajax({
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

            // Respuesta obsoleta: si el valor del input cambió desde que se disparó esta
            // validación (p. ej. se autocompletó), no se aplica (evita re-marcar lo viejo).
            if ($input.val() !== value) {
                return;
            }

            if (response.status === 'error') {

                if(usar_check == true)
                    $input.removeClass('is-valid');

                if (response.type === 'fields') {
                    renderizarErroresCampos(idFormulario, response.errors);
                }

            } else {

                if(usar_check == true)
                    $input.addClass('is-valid');
                limpiarErrorCampo($input);
            }
        }
    });
}

// Listener delegado universal para la acción del robot generador de claves
$(document).on("click", ".btn-generar-password-global", function() {
    const $btn = $(this);
    
    // 1. Encontramos de forma dinámica el formulario ancestro más cercano
    const $form = $btn.closest('form');
    const idFormulario = '#' + $form.attr('id');
    
    // 2. Encontramos el ID del contenedor de mensajes dinámicamente buscando el div hijo de .mensaje-wrapper
    const idModalMensaje = '#' + $form.find('.mensaje-wrapper > div').attr('id');
    
    // 3. Mapeamos los inputs de contraseña por su atributo 'name' (común en ambos formularios)
    const $inputPass = $form.find('input[name="password"]');
    const $inputConfirm = $form.find('input[name="confirm_password"]');
    
    // 4. Mapeamos los botones de los ojos específicos de este formulario
    const $btnTogglePass = $form.find('#togglePassword, #togglePasswordEdit');
    const $btnToggleConfirm = $form.find('#togglePasswordConfirm, #togglePasswordConfirmEdit');

    setBtnLoading($btn, 'Generando...');
    
    $.ajax({
        url: 'controllers/usuarios_controller.php?action=generar_password',
        type: 'POST',
        data: $form.serialize(),
        dataType: 'json',
        success: function(res) {
            
            if (idFormulario === '#form-usuario-password') {
                limpiarFormularioCompleto(idFormulario, idModalMensaje, false);
            }

            if (res.status === 'success') {

                $inputPass.val(res.password);
                $inputConfirm.val(res.password);

                if ($inputPass.attr('type') === 'password') $btnTogglePass.trigger('click');
                if ($inputConfirm.attr('type') === 'password') $btnToggleConfirm.trigger('click');

                $inputPass.addClass('is-valid').removeClass('is-invalid');
                limpiarErrorCampo($inputPass);

                $inputConfirm.addClass('is-valid').removeClass('is-invalid');
                limpiarErrorCampo($inputConfirm);
            } 
            else {
                mostrarMensajeFormulario(idModalMensaje, 'Atención', res.message, 'danger');
            }
        },
        error: function(jqXHR, textStatus) {
            manejarErrorAjax(jqXHR, textStatus, idModalMensaje);
        },
        complete: function() {
            resetBtnLoading($btn);
        }
    });
});