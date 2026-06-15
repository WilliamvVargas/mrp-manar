$(document).ready(function() {

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-menu', '#modal-mensajes');

    // Switch de Estado: alterna la etiqueta entre Activo / Inactivo según su valor.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // "Asignar Posición" se habilita solo cuando el Nombre es válido.
    // El framework de validación le pone la clase 'is-valid' al input al aprobarlo;
    // observamos ese cambio de clase para activar/desactivar el botón.
    const inputNombre = document.getElementById('input_nombre');
    if (inputNombre) {
        const observadorNombre = new MutationObserver(function() {
            const $nombre = $(inputNombre);
            const valido  = $nombre.hasClass('is-valid') && !$nombre.hasClass('is-invalid');
            $('#btn-asignar-posicion').prop('disabled', !valido);
        });
        observadorNombre.observe(inputNombre, { attributes: true, attributeFilter: ['class'] });
    }

    // Crear Menú
    $('#form-menu').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarMenu');
        const formulario   = '#form-menu';
        const modalMensaje = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/menus_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    $('#label-estado').text('Activo');   // el switch vuelve a su valor por defecto
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

    // --- Alternancia entre el modal de creación y el de Asignar Posición ---
    const modalCrear   = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMenuCrear'));
    const modalAsignar = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAsignarPosicion'));
    let volverACrear   = false;   // true mientras se "salta" a Asignar Posición (para no resetear)

    // El botón abre Asignar Posición: primero arma TODO el listado (con el modal
    // aún oculto) y solo cuando está listo hace el cambio de modal (evita el destello).
    $('#btn-asignar-posicion').on('click', function() {
        setBtnLoading($(this), 'Cargando...');

        cargarListaPosicion(function() {
            volverACrear = true;
            modalCrear.hide();
        });
    });

    // Al ocultarse el modal de creación.
    $('#modalMenuCrear').on('hidden.bs.modal', function() {
        if (volverACrear) {
            volverACrear = false;
            modalAsignar.show();   // conserva el formulario para poder volver luego
            return;
        }
        // Cierre real: limpia el formulario y restablece el switch.
        limpiarFormularioCompleto('#form-menu', '#modal-mensajes', true);
        $('#label-estado').text('Activo');
    });

    // Al cerrar Asignar Posición: guarda la posición elegida para el menú nuevo
    // en el input deshabilitado, y vuelve al formulario de creación.
    $('#modalAsignarPosicion').on('hidden.bs.modal', function() {
        const $lista = $('#lista-posicion');
        const $nuevo = $lista.children('li[data-id="nuevo"]');

        if ($nuevo.length) {
            const posicion = $lista.children('li').index($nuevo) + 1;
            $('#input_posicion').val(posicion);   // visible (solo muestra)
            $('#posicion').val(posicion);          // hidden: valor real que se envía
        }

        modalCrear.show();
    });

    // Al abrir Asignar Posición (ya cargado), el botón vuelve a su estado original.
    $('#modalAsignarPosicion').on('shown.bs.modal', function() {
        resetBtnLoading($('#btn-asignar-posicion'));
    });

    /**
     * Carga los menús (ordenados por posición ASC) desde el backend, agrega al
     * final el menú que se está creando, y habilita el drag & drop con jQuery UI.
     * El reordenamiento es solo visual: por ahora no se guarda.
     */
    function cargarListaPosicion(onReady) {
        const $lista = $('#lista-posicion');
        $lista.html('<li class="list-group-item text-muted small">Cargando...</li>');

        $.ajax({
            url: 'controllers/menus_controller.php?action=listar',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $lista.html('<li class="list-group-item text-danger small">No se pudo cargar el listado.</li>');
                    if (typeof onReady === 'function') onReady();
                    return;
                }

                // Ítems de los menús existentes (su posición original = orden por BD).
                const items = res.data.map(function(menu, i) {
                    return itemPosicion(menu.id, menu.nombre, i + 1, false);
                });

                // Ítem del menú que se está creando; su posición original es la última.
                const nombreNuevo = $('#input_nombre').val().trim() || '(nuevo menú)';
                const itemNuevo   = itemPosicion('nuevo', nombreNuevo, items.length + 1, true);

                // Si ya se había elegido una posición (input deshabilitado) se respeta;
                // si no, el nuevo menú va al final por defecto.
                let posNuevo = parseInt($('#input_posicion').val(), 10);
                if (isNaN(posNuevo) || posNuevo < 1 || posNuevo > items.length + 1) {
                    posNuevo = items.length + 1;
                }
                items.splice(posNuevo - 1, 0, itemNuevo);

                $lista.html(items.join(''));

                // (Re)inicializa el orden por arrastre.
                if ($lista.hasClass('ui-sortable')) {
                    $lista.sortable('destroy');
                }
                $lista.sortable({
                    axis: 'y',
                    placeholder: 'list-group-item lista-posicion-placeholder',
                    forcePlaceholderSize: true,
                    cancel: 'li:not([data-id="nuevo"])',   // solo el menú nuevo se puede tomar
                    change: function(event, ui) {
                        renumerarPosiciones(ui.item, ui.placeholder);   // en vivo, durante el arrastre
                    },
                    update: function() {
                        renumerarPosiciones();   // al soltar
                    }
                });

                // Ajusta números y etiquetas según la posición actual del nuevo menú.
                renumerarPosiciones();

                // Todo procesado: recién ahora se abre el modal (sin destello).
                if (typeof onReady === 'function') onReady();
            },
            error: function() {
                $lista.html('<li class="list-group-item text-danger small">Error al cargar el listado.</li>');
                if (typeof onReady === 'function') onReady();
            }
        });
    }

    /**
     * HTML de un ítem del listado ordenable.
     * @param ordenOriginal Número de orden con el que se cargó (1..N).
     */
    function itemPosicion(id, nombre, ordenOriginal, esNuevo) {
        const nombreEsc = $('<div>').text(nombre).html();   // escapa el nombre (evita XSS)
        const clase     = esNuevo ? ' list-group-item-primary' : '';

        // El nuevo menú lleva siempre "Nuevo" (azul). Los existentes una etiqueta
        // amarilla (oculta) que, al cambiar de posición, mostrará su posición original.
        const etiqueta = esNuevo
            ? ' <span class="badge bg-primary ms-auto">Nuevo</span>'
            : ' <span class="badge bg-warning text-dark ms-auto badge-cambio d-none"></span>';

        // Ícono según si el ítem se puede mover (nuevo) o está restringido (existente).
        const icono = esNuevo
            ? '<i class="bi bi-arrows-vertical me-2 text-muted"></i>'
            : '<i class="bi bi-dash-circle me-2 text-muted"></i>';

        return '<li class="list-group-item d-flex align-items-center' + clase + '" data-id="' + id + '" data-original="' + ordenOriginal + '">'
             + icono
             + '<span class="numero-orden fw-semibold me-1">' + ordenOriginal + '.</span>'
             + '<span class="nombre-menu">' + nombreEsc + '</span>'
             + etiqueta
             + '</li>';
    }

    /**
     * Recalcula el número de orden de cada ítem según su posición actual.
     * Durante el arrastre recibe el ítem arrastrado y el placeholder para ubicarlo
     * en su nueva posición antes de soltar.
     */
    function renumerarPosiciones($arrastrado, $placeholder) {
        let n = 0;

        $('#lista-posicion').children('li').each(function() {
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

    /**
     * Aplica el número de orden a un ítem y muestra "(Posición Original N)"
     * solo si su posición actual difiere de la original.
     */
    function fijarNumeroOrden($li, n) {
        const original = parseInt($li.attr('data-original'), 10);
        const esNuevo  = $li.attr('data-id') === 'nuevo';
        const cambio   = (n !== original);

        $li.find('.numero-orden').text(n + '.');

        // Los menús EXISTENTES que cambian de posición se marcan en amarillo y la
        // etiqueta muestra su posición original; el nuevo conserva su estilo "Nuevo".
        if (!esNuevo) {
            $li.toggleClass('list-group-item-warning', cambio);

            const $badge = $li.find('.badge-cambio');
            if (cambio) {
                $badge.text('Posición Original ' + original).removeClass('d-none');
            } else {
                $badge.text('').addClass('d-none');
            }
        }
    }

});