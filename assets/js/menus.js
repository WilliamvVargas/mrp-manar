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
                data: 'total_items',
                className: 'text-center',
                render: function(total) {
                    return (total === null || total === undefined) ? 0 : total;
                }
            },
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

                    const btnEstado = '<button type="button" class="btn btn-sm ' + clase + ' btn-estado-menu me-1" '
                                    + 'data-id="' + id + '" title="' + titulo + '"><i class="bi bi-power"></i></button>';

                    // Reordenar las posiciones de los ítems del menú. Se muestra siempre, pero
                    // queda deshabilitado si el menú no tiene ítems (nada que reordenar).
                    const nombreAttr = $('<div>').text(fila.nombre == null ? '' : fila.nombre).html().replace(/"/g, '&quot;');
                    const sinItems   = Number(fila.total_items) === 0;
                    const btnAsignarItems = '<button type="button" class="btn btn-sm btn-outline-dark btn-asignar-items-menu me-1" '
                                          + 'data-id="' + id + '" data-nombre="' + nombreAttr + '" '
                                          + (sinItems ? 'disabled ' : '')
                                          + 'title="' + (sinItems ? 'El menú no tiene ítems menú' : 'Asignar posición ítem menús') + '">'
                                          + '<i class="bi bi-sort-numeric-down"></i></button>';

                    const btnEditar = '<button type="button" class="btn btn-sm btn-outline-dark btn-editar-menu me-1" '
                                    + 'data-id="' + id + '" title="Editar menú"><i class="bi bi-pencil"></i></button>';

                    const btnEliminar = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-menu" '
                                      + 'data-id="' + id + '" title="Eliminar menú"><i class="bi bi-trash"></i></button>';

                    return btnEstado + btnAsignarItems + btnEditar + btnEliminar;
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
                    // El nombre llega válido desde BD, pero NO se marca con el check verde
                    // (ese solo aparece tras la validación automática al escribir). Como ya
                    // es válido, se habilita "Asignar Posición" directamente más abajo.
                    $('#input_nombre_editar').val(res.data.nombre);
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
                    $('#input-items-eliminar').val(res.data.total_items);
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

    // --- Asignar Posición: contextos específicos del mantenedor de menús ---
    // El motor reutilizable vive en utils.js (inicializarAsignadorPosicion / itemPosicion).

    // Contexto de CREACIÓN: el ítem movible es uno sintético ("nuevo") que se inserta en la lista.
    const ctxCrear = {
        boton:        '#btn-asignar-posicion',
        modalPadre:   '#modalMenuCrear',
        inputVisible: '#input_posicion',
        inputHidden:  '#posicion',
        idMovible:    function() { return 'nuevo'; },
        construirItems: function(data) {
            const items = data.map(function(registro, i) {
                return itemPosicion(registro.id, registro.nombre, i + 1, 'fijo', null, registro.estado);
            });

            // Ítem del registro que se está creando; su posición original es la última.
            const nombreNuevo = $('#input_nombre').val().trim() || '(nuevo menú)';
            const estadoNuevo = $('#input_estado').is(':checked') ? 1 : 0;
            const itemNuevo   = itemPosicion('nuevo', nombreNuevo, items.length + 1, 'movible', 'Nuevo', estadoNuevo);

            // Si ya se había elegido una posición se respeta; si no, va al final.
            let pos = parseInt($('#input_posicion').val(), 10);
            if (isNaN(pos) || pos < 1 || pos > items.length + 1) {
                pos = items.length + 1;
            }
            items.splice(pos - 1, 0, itemNuevo);

            return items;
        },
        alCerrarReal: function() {
            // Cierre real del modal de creación: limpia el formulario y restablece el switch.
            limpiarFormularioCompleto('#form-menu', '#modal-mensajes', true);
            $('#label-estado').text('Activo');
        }
    };

    // Contexto de EDICIÓN: el ítem movible es el propio registro en edición, que ya
    // viene en el listado del backend (no se inserta nada, solo se marca como movible).
    const ctxEditar = {
        boton:        '#btn-asignar-posicion-editar',
        modalPadre:   '#modalMenuEditar',
        inputVisible: '#input_posicion_editar',
        inputHidden:  '#posicion_editar',
        idMovible:    function() { return String($('#id_menu_editar').val()); },
        construirItems: function(data) {
            const idEditado     = String($('#id_menu_editar').val());
            const estadoEdicion = $('#input_estado_editar').is(':checked') ? 1 : 0;
            let indiceMovible   = -1;

            const items = data.map(function(registro, i) {
                const esMovible = String(registro.id) === idEditado;
                if (esMovible) {
                    indiceMovible = i;
                }
                // El ítem en edición refleja el estado actual del switch; el resto, su estado de BD.
                const estado = esMovible ? estadoEdicion : registro.estado;
                return itemPosicion(registro.id, registro.nombre, i + 1, esMovible ? 'movible' : 'fijo', 'Editando', estado);
            });

            // Si en esta sesión ya se eligió una posición distinta, recoloca el ítem.
            const posElegida = parseInt($('#posicion_editar').val(), 10);
            if (!isNaN(posElegida) && posElegida >= 1 && posElegida <= items.length
                && indiceMovible !== -1 && (posElegida - 1) !== indiceMovible) {
                const movido = items.splice(indiceMovible, 1)[0];
                items.splice(posElegida - 1, 0, movido);
            }

            return items;
        },
        alCerrarReal: function() {
            // Cierre real del modal de edición: limpia el formulario y restablece el switch.
            limpiarFormularioCompleto('#form-menu-editar', '#modal-mensajes-editar', true);
            $('#label-estado-editar').text('Activo');
        }
    };

    // Contexto GLOBAL: TODOS los ítems son movibles. No hay modal padre ni input oculto;
    // el nuevo orden completo se guarda directo en el backend con el botón "Guardar orden".
    const ctxGlobal = {
        global:    true,
        boton:     '#btn-asignar-posiciones',
        idMovible: function() { return null; },
        construirItems: function(data) {
            return data.map(function(registro, i) {
                return itemPosicion(registro.id, registro.nombre, i + 1, 'libre', null, registro.estado);
            });
        }
    };

    // Activa el motor reutilizable de Asignar Posición con los contextos de menús.
    inicializarAsignadorPosicion({
        urlListar:    'controllers/menus_controller.php?action=listar_orden',
        urlReordenar: 'controllers/menus_controller.php?action=reordenar',
        tabla:        tablaConsulta,
        contextos:    [ctxCrear, ctxEditar, ctxGlobal]
    });

    // --- Asignar Posición de los ÍTEMS de un menú (botón por fila del DataTable) ---
    // Reusa el MISMO motor, pero sobre su PROPIO modal (selectores configurables) y apuntando
    // al backend de item_menus. El menú es fijo (el de la fila), por eso no hay combobox.
    const ctxItemsMenu = {
        global:    true,
        boton:     '.btn-asignar-items-menu',
        idMovible: function() { return null; },
        construirItems: function(data, $boton) {
            const menuId = $boton ? String($boton.data('id')) : '';

            // Refleja el nombre del menú en su campo de solo lectura.
            $('#input-menu-items').val($boton ? ($boton.data('nombre') || '') : '');

            // Solo los ítems del menú de la fila, todos movibles ('libre').
            return data
                .filter(function(r) { return String(r.menu_id) === menuId; })
                .map(function(r, i) {
                    return itemPosicion(r.id, r.nombre, i + 1, 'libre', null, r.estado);
                });
        }
    };

    inicializarAsignadorPosicion({
        urlListar:    'controllers/item_menus_controller.php?action=listar_orden',
        urlReordenar: 'controllers/item_menus_controller.php?action=reordenar',
        selectores: {
            modal:       '#modalAsignarPosicionItems',
            lista:       '#lista-posicion-items',
            mensajes:    '#modal-mensajes-posicion-items',
            btnVolver:   '#btn-volver-posicion-items',
            btnCancelar: '#btn-cancelar-posicion-items',
            btnGuardar:  '#btn-guardar-posicion-items',
            instrUno:    '#instruccion-posicion-uno-items',
            instrTodos:  '#instruccion-posicion-todos-items'
        },
        contextos: [ctxItemsMenu]
    });

});
