$(document).ready(function() {

    // Tabla principal de menús (server-side, helper reutilizable de utils.js).
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/menus_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por posición ascendente
        extra: function(d) {
            d.estado = $('#filtro-estado').val();   // '', '1' o '0'
        },
        columnas: [
            { data: 'posicion', className: 'text-center' },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            {
                data: 'estado',
                className: 'text-center',
                render: function(estado) {
                    return Number(estado) === 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id, type, fila) {
                    const activo = Number(fila.estado) === 1;
                    const titulo = activo ? 'Inactivar menú' : 'Activar menú';
                    const clase  = activo ? 'btn-success' : 'btn-secondary';

                    const btnEditar = '<button type="button" class="btn btn-sm btn-outline-dark btn-editar-menu me-1" '
                                    + 'data-id="' + id + '" title="Editar menú"><i class="bi bi-pencil"></i></button>';

                    const btnEstado = '<button type="button" class="btn btn-sm ' + clase + ' btn-estado-menu" '
                                    + 'data-id="' + id + '" title="' + titulo + '"><i class="bi bi-power"></i></button>';

                    const btnEliminar = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-menu ms-1" '
                                      + 'data-id="' + id + '" title="Eliminar menú"><i class="bi bi-trash"></i></button>';

                    return btnEditar + btnEstado + btnEliminar;
                }
            }
        ]
    });

    // Recargar la tabla al cambiar el filtro de estado.
    $('#filtro-estado').on('change', function() {
        tablaConsulta.ajax.reload();
    });

    // Botón de la columna Acciones: alterna el estado del menú (activo <-> inactivo).
    // Solo refresca la tabla ante un 'success'; no muestra mensajes en pantalla.
    $('#tabla-consulta tbody').on('click', '.btn-estado-menu', function() {
        const $btn = $(this);
        const id   = $btn.data('id');

        $btn.prop('disabled', true);

        $.ajax({
            url: 'controllers/menus_controller.php?action=cambiar_estado',
            type: 'POST',
            data: { id: id, csrf_token: $('#csrf_token').val() },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(null, false);   // el redibujo restituye el botón
                } else {
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Abre el modal de edición y carga los datos del menú seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-editar-menu', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-menu-editar';
        const modalMensaje = '#modal-mensajes-editar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/menus_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const activo = Number(res.data.estado) === 1;

                    $('#id_menu_editar').val(res.data.id);
                    // El nombre llega validado desde BD: lo marca válido (habilita Asignar Posición).
                    $('#input_nombre_editar').val(res.data.nombre).addClass('is-valid');
                    $('#input_estado_editar').prop('checked', activo);
                    $('#label-estado-editar').text(activo ? 'Activo' : 'Inactivo');
                    $('#input_posicion_editar').val(res.data.posicion);
                    $('#btn-asignar-posicion-editar').prop('disabled', false);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del menú.', 'danger', 0);
            },
            complete: function() {
                $('#modalMenuEditar').modal('show');
            }
        });
    });

    // Abre el modal de eliminación y carga los datos del menú seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-menu', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-menu-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/menus_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id_menu_eliminar').val(res.data.id);
                    $('#input-nombre-eliminar').val(res.data.nombre);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del menú.', 'danger', 0);
            },
            complete: function() {
                $('#modalMenuEliminar').modal('show');
            }
        });
    });

    // Editar Menú
    $('#form-menu-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarMenu');
        const formulario   = '#form-menu-editar';
        const modalMensaje = '#modal-mensajes-editar';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/menus_controller.php?action=editar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    tablaConsulta.ajax.reload(null, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
                }
                else {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
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

    // Confirmar Eliminación de Menú
    $('#form-menu-eliminar').on('submit', function(e) {
        e.preventDefault();
        const btn          = $('#btnEliminarMenu');
        const modalMensaje = '#modal-mensajes-eliminar';
        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/menus_controller.php?action=eliminar',
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

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-menu', '#modal-mensajes');
    activarLimpiezaMensajeAlEscribir('#form-menu-editar', '#modal-mensajes-editar');

    // Switch de Estado: alterna la etiqueta entre Activo / Inactivo según su valor.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });

    $('#input_estado_editar').on('change', function() {
        $('#label-estado-editar').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // "Asignar Posición" se habilita solo cuando el Nombre es válido. El framework de
    // validación le pone la clase 'is-valid' al input al aprobarlo; observamos ese
    // cambio de clase para activar/desactivar el botón (en creación y en edición).
    activarObservadorNombre('input_nombre', '#btn-asignar-posicion');
    activarObservadorNombre('input_nombre_editar', '#btn-asignar-posicion-editar');

    function activarObservadorNombre(idInput, selectorBoton) {
        const input = document.getElementById(idInput);
        if (!input) {
            return;
        }
        const observador = new MutationObserver(function() {
            const $nombre = $(input);
            const valido  = $nombre.hasClass('is-valid') && !$nombre.hasClass('is-invalid');
            $(selectorBoton).prop('disabled', !valido);
        });
        observador.observe(input, { attributes: true, attributeFilter: ['class'] });
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
                    tablaConsulta.ajax.reload(null, false);   // refresca la tabla con el nuevo menú
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

    // --- Alternancia entre los modales de menú y el de Asignar Posición ---
    const modalCrear   = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMenuCrear'));
    const modalEditar  = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMenuEditar'));
    const modalAsignar = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAsignarPosicion'));

    let ctxPosicion       = null;    // contexto activo del modal Asignar Posición (creación o edición)
    let saltandoAPosicion = false;   // true mientras se "salta" desde el modal padre a Asignar Posición

    // Contexto de CREACIÓN: el ítem movible es uno sintético ("nuevo") que se inserta en la lista.
    const ctxCrear = {
        modalPadre:   modalCrear,
        boton:        '#btn-asignar-posicion',
        inputVisible: '#input_posicion',
        inputHidden:  '#posicion',
        idMovible:    function() { return 'nuevo'; },
        construirItems: function(data) {
            const items = data.map(function(menu, i) {
                return itemPosicion(menu.id, menu.nombre, i + 1, false, null, menu.estado);
            });

            // Ítem del menú que se está creando; su posición original es la última.
            const nombreNuevo = $('#input_nombre').val().trim() || '(nuevo menú)';
            const estadoNuevo = $('#input_estado').is(':checked') ? 1 : 0;
            const itemNuevo   = itemPosicion('nuevo', nombreNuevo, items.length + 1, true, 'Nuevo', estadoNuevo);

            // Si ya se había elegido una posición se respeta; si no, va al final.
            let pos = parseInt($('#input_posicion').val(), 10);
            if (isNaN(pos) || pos < 1 || pos > items.length + 1) {
                pos = items.length + 1;
            }
            items.splice(pos - 1, 0, itemNuevo);

            return items;
        }
    };

    // Contexto de EDICIÓN: el ítem movible es el propio registro en edición, que ya
    // viene en el listado del backend (no se inserta nada, solo se marca como movible).
    const ctxEditar = {
        modalPadre:   modalEditar,
        boton:        '#btn-asignar-posicion-editar',
        inputVisible: '#input_posicion_editar',
        inputHidden:  '#posicion_editar',
        idMovible:    function() { return String($('#id_menu_editar').val()); },
        construirItems: function(data) {
            const idEditado     = String($('#id_menu_editar').val());
            const estadoEdicion = $('#input_estado_editar').is(':checked') ? 1 : 0;
            let indiceMovible   = -1;

            const items = data.map(function(menu, i) {
                const esMovible = String(menu.id) === idEditado;
                if (esMovible) {
                    indiceMovible = i;
                }
                // El ítem en edición refleja el estado actual del switch; el resto, su estado de BD.
                const estado = esMovible ? estadoEdicion : menu.estado;
                return itemPosicion(menu.id, menu.nombre, i + 1, esMovible, 'Editando', estado);
            });

            // Si en esta sesión ya se eligió una posición distinta, recoloca el ítem.
            const posElegida = parseInt($('#posicion_editar').val(), 10);
            if (!isNaN(posElegida) && posElegida >= 1 && posElegida <= items.length
                && indiceMovible !== -1 && (posElegida - 1) !== indiceMovible) {
                const movido = items.splice(indiceMovible, 1)[0];
                items.splice(posElegida - 1, 0, movido);
            }

            return items;
        }
    };

    // Abre Asignar Posición desde un contexto: arma TODO el listado con el modal padre
    // aún visible y, solo cuando está listo, hace el cambio de modal (evita el destello).
    function abrirAsignarPosicion(ctx) {
        ctxPosicion = ctx;
        setBtnLoading($(ctx.boton), 'Cargando...');

        cargarListaPosicion(ctx, function() {
            saltandoAPosicion = true;
            ctx.modalPadre.hide();
        });
    }

    $('#btn-asignar-posicion').on('click', function() {
        abrirAsignarPosicion(ctxCrear);
    });

    $('#btn-asignar-posicion-editar').on('click', function() {
        abrirAsignarPosicion(ctxEditar);
    });

    // Al ocultarse el modal de creación.
    $('#modalMenuCrear').on('hidden.bs.modal', function() {
        if (saltandoAPosicion && ctxPosicion === ctxCrear) {
            saltandoAPosicion = false;
            modalAsignar.show();   // conserva el formulario para poder volver luego
            return;
        }
        // Cierre real: limpia el formulario y restablece el switch.
        limpiarFormularioCompleto('#form-menu', '#modal-mensajes', true);
        $('#label-estado').text('Activo');
    });

    // Al ocultarse el modal de edición.
    $('#modalMenuEditar').on('hidden.bs.modal', function() {
        if (saltandoAPosicion && ctxPosicion === ctxEditar) {
            saltandoAPosicion = false;
            modalAsignar.show();   // conserva el formulario para poder volver luego
            return;
        }
        // Cierre real: limpia el formulario y restablece el switch.
        limpiarFormularioCompleto('#form-menu-editar', '#modal-mensajes-editar', true);
        $('#label-estado-editar').text('Activo');
    });

    // Al cerrar Asignar Posición: guarda la posición elegida (visible + hidden) para el
    // ítem movible del contexto activo, y vuelve a su modal padre.
    $('#modalAsignarPosicion').on('hidden.bs.modal', function() {
        if (!ctxPosicion) {
            return;
        }

        const $lista   = $('#lista-posicion');
        const $movible = $lista.children('li[data-id="' + ctxPosicion.idMovible() + '"]');

        if ($movible.length) {
            const posicion = $lista.children('li').index($movible) + 1;
            $(ctxPosicion.inputVisible).val(posicion);   // visible (solo muestra)
            $(ctxPosicion.inputHidden).val(posicion);    // hidden: valor real que se envía
        }

        ctxPosicion.modalPadre.show();
    });

    // Al abrir Asignar Posición (ya cargado), el botón del contexto vuelve a su estado original.
    $('#modalAsignarPosicion').on('shown.bs.modal', function() {
        if (ctxPosicion) {
            resetBtnLoading($(ctxPosicion.boton));
        }
    });

    /**
     * Carga los menús (ordenados por posición ASC) del backend y, según el contexto,
     * arma el listado ordenable con jQuery UI dejando movible solo el ítem indicado.
     * El reordenamiento se aplica al guardar el formulario del modal padre.
     */
    function cargarListaPosicion(ctx, onReady) {
        const $lista = $('#lista-posicion');
        $lista.html('<li class="list-group-item text-muted small">Cargando...</li>');

        $.ajax({
            url: 'controllers/menus_controller.php?action=listar_orden',
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

                $lista.html(items.join(''));

                // (Re)inicializa el orden por arrastre.
                if ($lista.hasClass('ui-sortable')) {
                    $lista.sortable('destroy');
                }
                $lista.sortable({
                    axis: 'y',
                    placeholder: 'list-group-item lista-posicion-placeholder',
                    forcePlaceholderSize: true,
                    cancel: 'li:not([data-id="' + idMovible + '"])',   // solo el ítem movible se puede tomar
                    change: function(event, ui) {
                        renumerarPosiciones(ui.item, ui.placeholder);   // en vivo, durante el arrastre
                    },
                    update: function() {
                        renumerarPosiciones();   // al soltar
                    }
                });

                // Ajusta números y etiquetas según la posición actual del ítem movible.
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
     * @param esMovible     Si el ítem es el que se puede arrastrar.
     * @param textoBadge    Texto de la insignia del ítem movible (ej: 'Nuevo', 'Editando').
     */
    function itemPosicion(id, nombre, ordenOriginal, esMovible, textoBadge, estado) {
        const nombreEsc = $('<div>').text(nombre).html();   // escapa el nombre (evita XSS)
        const clase     = esMovible ? ' list-group-item-primary' : '';

        // Etiqueta contextual: insignia azul del ítem movible, o amarilla (oculta) que
        // muestra la posición original cuando un menú existente cambia de lugar.
        const etiqueta = esMovible
            ? '<span class="badge bg-primary">' + (textoBadge || 'Movible') + '</span>'
            : '<span class="badge bg-warning text-dark badge-cambio d-none"></span>';

        // Estado actual del menú: verde (Activo) / gris (Inactivo).
        const estadoBadge = Number(estado) === 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';

        // Ambas etiquetas agrupadas a la DERECHA: contextual + estado (al extremo).
        const etiquetas = '<span class="ms-auto d-flex align-items-center gap-1">' + etiqueta + estadoBadge + '</span>';

        // Ícono según si el ítem se puede mover o está restringido.
        const icono = esMovible
            ? '<i class="bi bi-arrows-vertical me-2 text-muted"></i>'
            : '<i class="bi bi-dash-circle me-2 text-muted"></i>';

        return '<li class="list-group-item d-flex align-items-center' + clase + '" data-id="' + id + '" data-original="' + ordenOriginal + '" data-movible="' + (esMovible ? '1' : '0') + '">'
             + icono
             + '<span class="numero-orden fw-semibold me-1">' + ordenOriginal + '.</span>'
             + '<span class="nombre-menu">' + nombreEsc + '</span>'
             + etiquetas
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
     * Aplica el número de orden a un ítem y muestra "Posición Original N"
     * solo si su posición actual difiere de la original.
     */
    function fijarNumeroOrden($li, n) {
        const original  = parseInt($li.attr('data-original'), 10);
        const esMovible = $li.attr('data-movible') === '1';
        const cambio    = (n !== original);

        $li.find('.numero-orden').text(n + '.');

        // Los menús EXISTENTES que cambian de posición se marcan en amarillo y la
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

});
