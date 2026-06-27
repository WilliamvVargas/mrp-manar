$(document).ready(function() {

    const MAX_RESULTADOS = 50;   // tope de coincidencias mostradas en el combobox.

    // ============================================================
    //  Tabla principal de ítems menú (DataTable server-side)
    // ============================================================

    // Ícono del ítem para su columna (Bootstrap con la fuente; personalizado con su archivo).
    function iconoTablaHtml(fila) {
        if (!fila.icono_id) {
            return '';
        }
        return fila.icono_tipo === 'bootstrap'
            ? '<i class="bi bi-' + fila.icono_valor + '"></i>'
            : '<img src="assets/icons/personalizados/' + fila.icono_archivo + '" alt="" '
              + 'style="width: 18px; height: 18px; object-fit: contain;">';
    }

    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/item_menus_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // ascendente por Id del ítem menú
        extra: function(d) {
            d.menu_id = $('#filtro-menu').val();    // '' o id del menú
            d.estado  = $('#filtro-estado').val();  // '', '1' o '0'
        },
        columnas: [
            { data: 'id', className: 'text-center' },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            { data: 'enlace', render: $.fn.dataTable.render.text() },
            { data: 'menu_nombre', render: $.fn.dataTable.render.text() },
            { data: 'posicion', className: 'text-center' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(d, type, fila) {
                    return iconoTablaHtml(fila);
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
                render: function(id) {
                    const btnEditar = '<button type="button" class="btn btn-sm btn-outline-dark btn-editar-item me-1" '
                                    + 'data-id="' + id + '" title="Editar ítem"><i class="bi bi-pencil"></i></button>';
                    const btnEliminar = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-item" '
                                      + 'data-id="' + id + '" title="Eliminar ítem"><i class="bi bi-trash"></i></button>';
                    return btnEditar + btnEliminar;
                }
            }
        ]
    });

    // Recargar la tabla al cambiar los filtros de menú o estado.
    $('#filtro-menu, #filtro-estado').on('change', function() {
        tablaConsulta.ajax.reload();
    });

    // ============================================================
    //  Combobox de Menú padre (con búsqueda + badge de estado)
    // ============================================================

    let MENUS = [];                       // catálogo de menús {id, nombre, estado, posicion}
    const $inputMenu = $('#input_menu');

    // Carga los menús para el combobox y el filtro (id, nombre, estado, total_items).
    function cargarMenus() {
        $.ajax({
            url: 'controllers/item_menus_controller.php?action=menus',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    MENUS = res.data || [];
                    poblarFiltroMenu();
                }
            }
        });
    }

    // Llena el <select> del filtro de Menú con el catálogo (conserva la selección actual).
    function poblarFiltroMenu() {
        const seleccion = $('#filtro-menu').val();
        const opciones  = MENUS.map(function(m) {
            return '<option value="' + m.id + '">' + $('<div>').text(nombreMenu(m)).html() + '</option>';
        }).join('');
        $('#filtro-menu').html('<option value="">Todos</option>' + opciones).val(seleccion);
    }

    // HTML del badge de estado (verde Activo / gris Inactivo).
    function badgeEstadoHtml(estado) {
        return Number(estado) === 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';
    }

    // HTML del badge (gris/secundario) con la cantidad de ítems asociados al menú.
    function badgeItemsHtml(total) {
        return '<span class="badge bg-secondary">Ítem menús: ' + (Number(total) || 0) + '</span>';
    }

    // Badges de un menú agrupados a la derecha: cantidad de ítems + estado.
    function badgesMenuHtml(menu) {
        return '<span class="d-flex align-items-center gap-1">'
             + badgeItemsHtml(menu.total_items) + badgeEstadoHtml(menu.estado)
             + '</span>';
    }

    // Nombre del menú con su posición delante. Ej: "1. Clientes".
    function nombreMenu(menu) {
        return menu.posicion + '. ' + menu.nombre;
    }

    // Muestra (o limpia) los badges del menú seleccionado junto a la etiqueta: estado +
    // cantidad de ítems. Recibe el menú (o null para limpiar).
    function mostrarEstadoMenu(menu) {
        pintarBadgesMenu(menu, '#badge-estado-menu', '#badge-items-menu');
    }

    // Rellena (o limpia) los badges de estado y de conteo de un menú en sus contenedores.
    function pintarBadgesMenu(menu, selEstado, selItems) {
        const $estado = $(selEstado);
        const $items  = $(selItems);

        if (!menu) {
            $estado.addClass('d-none').removeClass('bg-success bg-secondary').text('');
            $items.addClass('d-none').text('');
            return;
        }
        $estado.removeClass('d-none bg-success bg-secondary')
               .addClass(Number(menu.estado) === 1 ? 'bg-success' : 'bg-secondary')
               .text(Number(menu.estado) === 1 ? 'Activo' : 'Inactivo');
        $items.removeClass('d-none').text('Ítem menús: ' + (Number(menu.total_items) || 0));
    }

    // Limpia la selección de menú (cuando se edita el texto sin elegir de la lista).
    function limpiarSeleccionMenu() {
        $('#menu_id').val('');
        $inputMenu.removeClass('is-valid');
        mostrarEstadoMenu(null);
        limpiarPosicionElegida();      // la posición es por menú: se invalida al cambiar de menú
        actualizarBotonPosicion();
    }

    // Combobox reutilizable (utils.js). cerrarListaMenus se mantiene como envoltura porque
    // se usa también en el reseteo del formulario.
    const comboMenu = inicializarCombobox({
        input:      '#input_menu',
        lista:      '#lista-menus',
        chevron:    '#btn-abrir-menus',
        contenedor: '#combobox-menu',
        max:        MAX_RESULTADOS,
        campo:      'nombre',
        claseItem:  'd-flex align-items-center justify-content-between gap-2',
        opciones:   function() { return MENUS; },
        render: function(m) {
            const nombreEsc = $('<div>').text(nombreMenu(m)).html();
            return '<span class="small text-truncate">' + nombreEsc + '</span>' + badgesMenuHtml(m);
        },
        onInput:  limpiarSeleccionMenu,
        onSelect: function(menu) {
            $inputMenu.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
            $('#menu_id').val(menu.id);
            mostrarEstadoMenu(menu);
            limpiarErrorCampo($inputMenu);
            limpiarPosicionElegida();      // cambió el menú: se descarta la posición previa
            actualizarBotonPosicion();
        }
    });

    function cerrarListaMenus() {
        comboMenu.cerrar();
    }

    // Validación instantánea del Menú: el motor de utils.js valida por `name`, pero el menú
    // es un combobox cuyo valor real va en un hidden (#menu_id). Por eso se valida aparte:
    // al SALIR del combobox sin un menú elegido se muestra el error; si hay menú, se limpia.
    // (La selección usa mousedown+preventDefault en utils.js, así que elegir NO dispara blur.)
    function validarMenuAlSalir($combo, $hidden) {
        if ($hidden.val() !== '') {
            limpiarErrorCampo($combo);
            return;
        }
        $combo.addClass('is-invalid').removeClass('is-valid');
        ejecutarValidacionUniversal($hidden);   // muestra "Debe seleccionar un Menú" en su feedback
    }

    $inputMenu.on('blur', function() {
        validarMenuAlSalir($inputMenu, $('#menu_id'));
    });

    // ============================================================
    //  Selector de íconos (botones, 10 por fila)
    // ============================================================

    let ICONOS = [];                          // catálogo {id, nombre, tipo, valor, archivo}
    const $listaIconos = $('#lista-iconos-botones');

    // Escapa un texto para usarlo dentro de un atributo (title).
    function atributoEsc(texto) {
        return $('<div>').text(texto == null ? '' : texto).html().replace(/"/g, '&quot;');
    }

    // Placeholder de la vista previa cuando no hay ícono seleccionado.
    const PLACEHOLDER_ICONO = '<i class="bi bi-question-square text-muted" style="font-size: 3rem;"></i>';

    // Clase para los personalizados monocromáticos (coloreables): permite recolorearlos
    // (negro / blanco) con un filtro. Los multicolor no la llevan y conservan sus colores.
    function claseMonoImg(ico) {
        return Number(ico.coloreable) === 1 ? ' icono-mono' : '';
    }

    // HTML del ícono según su tipo (Bootstrap con la fuente; personalizado con su archivo).
    function iconoVisualHtml(ico) {
        return ico.tipo === 'bootstrap'
            ? '<i class="bi bi-' + ico.valor + '"></i>'
            : '<img class="icono-svg' + claseMonoImg(ico) + '" src="assets/icons/personalizados/' + ico.archivo + '" alt="">';
    }

    // HTML del ícono en grande para la caja de vista previa.
    function iconoPreviewHtml(ico) {
        return ico.tipo === 'bootstrap'
            ? '<i class="bi bi-' + ico.valor + '" style="font-size: 3rem;"></i>'
            : '<img class="icono-svg' + claseMonoImg(ico) + '" src="assets/icons/personalizados/' + ico.archivo + '" alt="" style="max-width: 72%; max-height: 72%;">';
    }

    // HTML del ícono de un ítem (por su icono_id) para la celda de Asignar Posición.
    // Si no tiene ícono o el catálogo aún no cargó, devuelve cadena vacía (sin ícono).
    function iconoCeldaHtml(iconoId) {
        if (!iconoId) {
            return '';
        }
        const ico = ICONOS.find(function(i) { return String(i.id) === String(iconoId); });
        if (!ico) {
            return '';
        }
        return '<span class="item-menu-icono-celda me-2 d-inline-flex align-items-center justify-content-center">'
             + iconoVisualHtml(ico)
             + '</span>';
    }

    // Carga el catálogo de iconos y pinta los botones.
    function cargarIconos() {
        $.ajax({
            url: 'controllers/item_menus_controller.php?action=iconos',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    ICONOS = res.data || [];
                    renderIconosBotones();
                }
            }
        });
    }

    // Dibuja un botón por cada ícono del catálogo (la grilla los acomoda de a 10 por fila).
    function renderIconosBotones() {
        if (!ICONOS.length) {
            $listaIconos.html('<div class="small text-muted p-1">No hay íconos en el catálogo.</div>');
            return;
        }
        const html = ICONOS.map(function(ico, i) {
            return '<button type="button" class="btn btn-outline-secondary btn-icono" '
                 + 'data-index="' + i + '" title="' + atributoEsc(ico.nombre) + '">'
                 + iconoVisualHtml(ico)
                 + '</button>';
        }).join('');
        $listaIconos.html(html);
    }

    // Quita la selección de ícono (input visible, id real y vista previa).
    function limpiarSeleccionIcono() {
        $('#input_icono').val('');
        $('#icono_id').val('');
        $('#preview-icono-item').html(PLACEHOLDER_ICONO);
        $listaIconos.children('.btn-icono')
            .removeClass('active btn-secondary')
            .addClass('btn-outline-secondary');
    }

    // Al hacer clic en un ícono: lo marca, carga su nombre en el input y guarda su valor.
    // Si ya estaba seleccionado, se deselecciona (el ícono es opcional).
    $listaIconos.on('click', '.btn-icono', function() {
        const $btn = $(this);

        if ($btn.hasClass('active')) {
            limpiarSeleccionIcono();
            return;
        }

        const ico = ICONOS[$btn.data('index')];
        if (!ico) {
            return;
        }

        $listaIconos.children('.btn-icono')
            .removeClass('active btn-secondary')
            .addClass('btn-outline-secondary');
        $btn.addClass('active btn-secondary').removeClass('btn-outline-secondary');

        $('#input_icono').val(ico.nombre);   // muestra el nombre en el input deshabilitado
        $('#icono_id').val(ico.id);          // guarda el id (FK) que se enviará
        $('#preview-icono-item').html(iconoPreviewHtml(ico));   // vista previa en grande
        limpiarErrorCampo($('#icono_id'));
    });

    // ============================================================
    //  Estado (switch) y creación del ítem menú
    // ============================================================

    // Refleja el texto del switch de estado.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // Normaliza un texto a un valor apto para ruta/URL (mismo criterio que el backend):
    // minúsculas, sin acentos, espacios a guiones y sin caracteres ajenos a una ruta.
    function normalizarEnlaceTexto(texto) {
        return (texto || '')
            .toLowerCase()
            .replace(/[áàä]/g, 'a').replace(/[éèë]/g, 'e').replace(/[íìï]/g, 'i')
            .replace(/[óòö]/g, 'o').replace(/[úùü]/g, 'u').replace(/ñ/g, 'n')
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9\/_.-]+/g, '');
    }

    // Bandera: pasa a true cuando el usuario edita el Enlace a mano (deja de seguir al Nombre).
    let enlaceEditadoManualmente = false;

    // Al escribir el Nombre, autocompleta el Enlace con su versión normalizada, salvo que
    // el usuario ya lo haya editado a mano. Se recortan los guiones de los extremos para que
    // un espacio al inicio/fin del Nombre no deje el Enlace empezando/terminando en guión.
    // La revalidación del Enlace la dispara el sistema vía data-comparar-con="nombre"
    // (procesarDependenciasCruzadas en utils.js), igual que las contraseñas en usuarios.
    $('#input_nombre').on('input', function() {
        if (!enlaceEditadoManualmente) {
            const enlace = normalizarEnlaceTexto($(this).val()).replace(/^-+|-+$/g, '');
            $('#input_enlace').val(enlace);
        }
    });

    // El Enlace se normaliza en vivo. Editarlo a mano lo "fija" (deja de autocompletarse);
    // si se deja en blanco, se libera y vuelve a seguir al Nombre.
    $('#input_enlace').on('input', function() {
        const limpio = normalizarEnlaceTexto($(this).val());
        if ($(this).val() !== limpio) {
            $(this).val(limpio);
        }
        enlaceEditadoManualmente = $(this).val().trim() !== '';
    });

    // ---------- Botón "Asignar Posición" ----------

    // Limpia la posición elegida (al final = vacío).
    function limpiarPosicionElegida() {
        $('#input_posicion').val('');
        $('#posicion').val('');
    }

    // El Nombre es válido cuando la validación instantánea lo marcó con el check verde.
    function nombreItemEsValido() {
        const $n = $('#input_nombre');
        return $n.hasClass('is-valid') && !$n.hasClass('is-invalid');
    }

    // "Asignar Posición" se habilita con un Nombre válido y un Menú seleccionado
    // (la posición de un ítem es relativa a su menú padre).
    function actualizarBotonPosicion() {
        const habilitar = nombreItemEsValido() && $('#menu_id').val() !== '';
        $('#btn-asignar-posicion').prop('disabled', !habilitar);
    }

    // Reevalúa el botón cuando cambia la validez del Nombre (clase is-valid/is-invalid).
    const inputNombreItemDom = document.getElementById('input_nombre');
    if (inputNombreItemDom) {
        const observadorNombreItem = new MutationObserver(actualizarBotonPosicion);
        observadorNombreItem.observe(inputNombreItemDom, { attributes: true, attributeFilter: ['class'] });
    }

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-item-menu', '#modal-mensajes');

    // Restablece el formulario a su estado inicial.
    function resetearFormularioItemMenu() {
        limpiarFormularioCompleto('#form-item-menu', '#modal-mensajes', true);
        cerrarListaMenus();
        limpiarSeleccionMenu();
        limpiarSeleccionIcono();
        enlaceEditadoManualmente = false;   // el Enlace vuelve a seguir al Nombre
        $('#label-estado').text('Activo');
    }

    // Guardar Ítem Menú.
    $('#form-item-menu').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarItemMenu');
        const formulario   = '#form-item-menu';
        const modalMensaje  = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/item_menus_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, menu_id, nombre, icono, enlace, estado, posicion
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    resetearFormularioItemMenu();
                    cargarMenus();                          // refresca la cantidad de ítems por menú
                    tablaConsulta.ajax.reload(null, false); // muestra el nuevo ítem en la tabla
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        // El error del menú apunta al hidden #menu_id: márcalo también en el combobox visible.
                        if (res.errors && res.errors.menu_id) {
                            $inputMenu.addClass('is-invalid').removeClass('is-valid');
                        }
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

    // ---------- Asignar Posición (motor reutilizable de utils.js) ----------
    // La posición de un ítem es relativa a su menú. Hay tres contextos: creación, edición
    // y global (botón de la raíz), este último acotado al menú elegido en el combobox.
    let modoPosicion = 'crear';   // quién abrió el modal: 'crear' | 'editar' | 'global'
    let menuGlobalId = null;      // menú cuyos ítems se reordenan en el modo global

    const asignador = inicializarAsignadorPosicion({
        urlListar:    'controllers/item_menus_controller.php?action=listar_orden',
        urlReordenar: 'controllers/item_menus_controller.php?action=reordenar',
        tabla:        tablaConsulta,
        contextos: [{
            boton:        '#btn-asignar-posicion',
            modalPadre:   '#modalItemMenuCrear',
            inputVisible: '#input_posicion',
            inputHidden:  '#posicion',
            idMovible:    function() { return 'nuevo'; },
            construirItems: function(data) {
                const menuId = String($('#menu_id').val());

                // Refleja el menú elegido en el combobox del modal (input + badge) y lo muestra.
                const menuSel = MENUS.find(function(m) { return String(m.id) === menuId; });
                $('#input-menu-posicion').val(menuSel ? nombreMenu(menuSel) : '');
                mostrarEstadoMenuPos(menuSel || null);
                $('#campo-menu-posicion').removeClass('d-none');

                // Solo los ítems del menú elegido (la posición es relativa a ese menú).
                const items = data
                    .filter(function(r) { return String(r.menu_id) === menuId; })
                    .map(function(r, i) {
                        return itemPosicion(r.id, r.nombre, i + 1, 'fijo', null, r.estado, iconoCeldaHtml(r.icono_id));
                    });

                // Ítem que se está creando; su posición original es la última. Su ícono es
                // el que se haya elegido en el formulario (o ninguno).
                const nombreNuevo = $('#input_nombre').val().trim() || '(nuevo ítem)';
                const estadoNuevo = $('#input_estado').is(':checked') ? 1 : 0;
                const iconoNuevo  = iconoCeldaHtml($('#icono_id').val());
                const itemNuevo   = itemPosicion('nuevo', nombreNuevo, items.length + 1, 'movible', 'Nuevo', estadoNuevo, iconoNuevo);

                // Si ya se había elegido una posición se respeta; si no, va al final.
                let pos = parseInt($('#input_posicion').val(), 10);
                if (isNaN(pos) || pos < 1 || pos > items.length + 1) {
                    pos = items.length + 1;
                }
                items.splice(pos - 1, 0, itemNuevo);

                return items;
            },
            alCerrarReal: resetearFormularioItemMenu
        }, {
            // --- Contexto de EDICIÓN ---
            // El ítem que se edita es el único movible; la lista se acota a su menú (el
            // elegido en el formulario de edición). Si cambió de menú, el ítem va al final
            // del nuevo menú. El menú NO se cambia desde este modal (combobox oculto).
            boton:        '#btn-asignar-posicion-editar',
            modalPadre:   '#modalItemMenuEditar',
            inputVisible: '#input_posicion_editar',
            inputHidden:  '#posicion_editar',
            idMovible:    function() { return String($('#id_editar').val()); },
            construirItems: function(data) {
                const menuId = String($('#menu_id_editar').val());
                const itemId = String($('#id_editar').val());

                // Muestra el combobox de menú y lo refleja con el menú de la edición (igual que
                // en creación): cambiarlo desde aquí mueve el ítem a ese menú.
                $('#campo-menu-posicion').removeClass('d-none');
                const menuSel = MENUS.find(function(m) { return String(m.id) === menuId; });
                $('#input-menu-posicion').val(menuSel ? nombreMenu(menuSel) : '');
                mostrarEstadoMenuPos(menuSel || null);

                // Ítems del menú elegido, EXCLUYENDO el que se edita (se reinserta como movible).
                const items = data
                    .filter(function(r) { return String(r.menu_id) === menuId && String(r.id) !== itemId; })
                    .map(function(r, i) {
                        return itemPosicion(r.id, r.nombre, i + 1, 'fijo', null, r.estado, iconoCeldaHtml(r.icono_id));
                    });

                // El ítem editado, como movible: nombre/estado/ícono según el formulario.
                const nombreEd = $('#input_nombre_editar').val().trim() || '(ítem)';
                const estadoEd = $('#input_estado_editar').is(':checked') ? 1 : 0;
                const iconoEd  = iconoCeldaHtml($('#icono_id_editar').val());
                const itemEd   = itemPosicion(itemId, nombreEd, items.length + 1, 'movible', 'Editando', estadoEd, iconoEd);

                // Posición: respeta la ya elegida (o la actual del ítem, que viene cargada en
                // el input); si no es válida para este menú, va al final.
                let pos = parseInt($('#input_posicion_editar').val(), 10);
                if (isNaN(pos) || pos < 1 || pos > items.length + 1) {
                    pos = items.length + 1;
                }
                items.splice(pos - 1, 0, itemEd);

                return items;
            }
        }, {
            // --- Contexto GLOBAL (botón de la raíz): reordenar los ítems de UN menú ---
            // Como la posición es por menú, el reordenamiento masivo se acota al menú
            // elegido en el combobox del modal; todos sus ítems son movibles.
            global:    true,
            boton:     '#btn-asignar-posiciones',
            idMovible: function() { return null; },
            construirItems: function(data) {
                // Muestra el combobox de menú; si aún no hay menú elegido, usa el del filtro
                // de la tabla o, si no, el primero del catálogo.
                $('#campo-menu-posicion').removeClass('d-none');
                if (menuGlobalId === null || menuGlobalId === '') {
                    const filtro = $('#filtro-menu').val();
                    menuGlobalId = (filtro !== '' ? filtro : (MENUS[0] ? String(MENUS[0].id) : ''));
                }
                const menuSel = MENUS.find(function(m) { return String(m.id) === String(menuGlobalId); });
                $('#input-menu-posicion').val(menuSel ? nombreMenu(menuSel) : '');
                mostrarEstadoMenuPos(menuSel || null);

                // Ítems del menú elegido, todos movibles ('libre').
                return data
                    .filter(function(r) { return String(r.menu_id) === String(menuGlobalId); })
                    .map(function(r, i) {
                        return itemPosicion(r.id, r.nombre, i + 1, 'libre', null, r.estado, iconoCeldaHtml(r.icono_id));
                    });
            }
        }]
    });

    // El combobox del modal de posición se comporta según quién abrió el modal.
    $('#btn-asignar-posicion').on('click', function() { modoPosicion = 'crear'; });
    $('#btn-asignar-posicion-editar').on('click', function() { modoPosicion = 'editar'; });
    $('#btn-asignar-posiciones').on('click', function() { modoPosicion = 'global'; menuGlobalId = null; });

    // ---------- Combobox de Menú dentro del modal Asignar Posición ----------
    // Igual que el del formulario: al cambiar el menú, sincroniza la selección, recarga
    // los ítems de ese menú y deja el nuevo en la última posición.
    const $inputMenuPos = $('#input-menu-posicion');

    // Badges (estado + conteo) del menú en la etiqueta del modal. Recibe el menú (o null).
    function mostrarEstadoMenuPos(menu) {
        pintarBadgesMenu(menu, '#badge-estado-menu-posicion', '#badge-items-menu-posicion');
    }

    // Cambia el menú desde el modal: sincroniza con el formulario, reinicia la posición
    // (el nuevo va al final) y recarga la lista con los ítems del nuevo menú.
    function seleccionarMenuPos(menu) {
        if (modoPosicion === 'crear') {
            // Sincroniza con el formulario de creación.
            $inputMenu.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
            $('#menu_id').val(menu.id);
            mostrarEstadoMenu(menu);
            limpiarErrorCampo($inputMenu);
            limpiarPosicionElegida();      // el ítem nuevo pasa a la última posición
            actualizarBotonPosicion();
        } else if (modoPosicion === 'editar') {
            // Sincroniza con el formulario de edición (mueve el ítem a ese menú).
            $inputMenuEditar.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
            $('#menu_id_editar').val(menu.id);
            mostrarEstadoMenuEditar(menu);
            limpiarErrorCampo($inputMenuEditar);
            limpiarPosicionEditar();       // el ítem pasa al final del nuevo menú
            actualizarBotonPosicionEditar();
        } else if (modoPosicion === 'global') {
            // Reordenamiento masivo: solo cambia el menú cuyos ítems se reordenan.
            menuGlobalId = String(menu.id);
        }

        // Refleja en el combobox del modal.
        $inputMenuPos.val(nombreMenu(menu)).removeClass('is-invalid');
        mostrarEstadoMenuPos(menu);

        // Recarga la lista con los ítems del menú elegido.
        asignador.recargar();
    }

    // Combobox reutilizable (utils.js) del menú dentro del modal Asignar Posición.
    inicializarCombobox({
        input:      '#input-menu-posicion',
        lista:      '#lista-menus-posicion',
        chevron:    '#btn-abrir-menus-posicion',
        contenedor: '#combobox-menu-posicion',
        max:        MAX_RESULTADOS,
        campo:      'nombre',
        claseItem:  'd-flex align-items-center justify-content-between gap-2',
        opciones:   function() { return MENUS; },
        render: function(m) {
            const nombreEsc = $('<div>').text(nombreMenu(m)).html();
            return '<span class="small text-truncate">' + nombreEsc + '</span>' + badgesMenuHtml(m);
        },
        onSelect: seleccionarMenuPos
    });

    // ============================================================
    //  Edición de ítem menú
    // ============================================================

    const $listaIconosEditar = $('#lista-iconos-botones-editar');
    const $inputMenuEditar   = $('#input_menu_editar');

    // --- Selector de íconos del modal de edición ---

    // Dibuja un botón por cada ícono del catálogo en el modal de edición.
    function renderIconosBotonesEditar() {
        if (!ICONOS.length) {
            $listaIconosEditar.html('<div class="small text-muted p-1">No hay íconos en el catálogo.</div>');
            return;
        }
        const html = ICONOS.map(function(ico, i) {
            return '<button type="button" class="btn btn-outline-secondary btn-icono" '
                 + 'data-index="' + i + '" title="' + atributoEsc(ico.nombre) + '">'
                 + iconoVisualHtml(ico)
                 + '</button>';
        }).join('');
        $listaIconosEditar.html(html);
    }

    // Quita la selección de ícono en edición (input, id real, vista previa y botones).
    function limpiarSeleccionIconoEditar() {
        $('#input_icono_editar').val('');
        $('#icono_id_editar').val('');
        $('#preview-icono-item-editar').html(PLACEHOLDER_ICONO);
        $listaIconosEditar.children('.btn-icono')
            .removeClass('active btn-secondary')
            .addClass('btn-outline-secondary');
    }

    // Marca como seleccionado el ícono indicado (por su id) en el modal de edición.
    function seleccionarIconoEditar(iconoId) {
        limpiarSeleccionIconoEditar();
        if (!iconoId) {
            return;
        }
        const idx = ICONOS.findIndex(function(i) { return String(i.id) === String(iconoId); });
        if (idx < 0) {
            return;
        }
        const ico  = ICONOS[idx];
        const $btn = $listaIconosEditar.children('.btn-icono').filter('[data-index="' + idx + '"]');
        $btn.addClass('active btn-secondary').removeClass('btn-outline-secondary');
        $('#input_icono_editar').val(ico.nombre);
        $('#icono_id_editar').val(ico.id);
        $('#preview-icono-item-editar').html(iconoPreviewHtml(ico));
    }

    // Click en un ícono del modal de edición: selecciona o (si ya estaba) deselecciona.
    $listaIconosEditar.on('click', '.btn-icono', function() {
        const $btn = $(this);
        if ($btn.hasClass('active')) {
            limpiarSeleccionIconoEditar();
            return;
        }
        const ico = ICONOS[$btn.data('index')];
        if (ico) {
            seleccionarIconoEditar(ico.id);
        }
    });

    // --- Combobox de Menú del modal de edición ---

    // Badges (estado + conteo) del menú en la etiqueta del modal de edición.
    function mostrarEstadoMenuEditar(menu) {
        pintarBadgesMenu(menu, '#badge-estado-menu-editar', '#badge-items-menu-editar');
    }

    // Limpia la posición elegida en edición (al final = vacío).
    function limpiarPosicionEditar() {
        $('#input_posicion_editar').val('');
        $('#posicion_editar').val('');
    }

    // "Asignar Posición" (edición) se habilita cuando hay un menú seleccionado
    // (la posición de un ítem es relativa a su menú padre).
    function actualizarBotonPosicionEditar() {
        $('#btn-asignar-posicion-editar').prop('disabled', $('#menu_id_editar').val() === '');
    }

    // Limpia la selección de menú en edición (al editar el texto sin elegir de la lista).
    function limpiarSeleccionMenuEditar() {
        $('#menu_id_editar').val('');
        $inputMenuEditar.removeClass('is-valid');
        mostrarEstadoMenuEditar(null);
        limpiarPosicionEditar();          // la posición es por menú: se invalida al cambiar
        actualizarBotonPosicionEditar();
    }

    inicializarCombobox({
        input:      '#input_menu_editar',
        lista:      '#lista-menus-editar',
        chevron:    '#btn-abrir-menus-editar',
        contenedor: '#combobox-menu-editar',
        max:        MAX_RESULTADOS,
        campo:      'nombre',
        claseItem:  'd-flex align-items-center justify-content-between gap-2',
        opciones:   function() { return MENUS; },
        render: function(m) {
            const nombreEsc = $('<div>').text(nombreMenu(m)).html();
            return '<span class="small text-truncate">' + nombreEsc + '</span>' + badgesMenuHtml(m);
        },
        onInput:  limpiarSeleccionMenuEditar,
        onSelect: function(menu) {
            $inputMenuEditar.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
            $('#menu_id_editar').val(menu.id);
            mostrarEstadoMenuEditar(menu);
            limpiarErrorCampo($inputMenuEditar);
            limpiarPosicionEditar();          // cambió el menú: se descarta la posición previa
            actualizarBotonPosicionEditar();
        }
    });

    // Validación instantánea del Menú en edición (mismo motivo que en creación).
    $inputMenuEditar.on('blur', function() {
        validarMenuAlSalir($inputMenuEditar, $('#menu_id_editar'));
    });

    // Refleja el texto del switch de estado en edición.
    $('#input_estado_editar').on('change', function() {
        $('#label-estado-editar').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // Limpia la alerta general al escribir en el formulario de edición.
    activarLimpiezaMensajeAlEscribir('#form-item-menu-editar', '#modal-mensajes-editar');

    // Puebla el formulario de edición con los datos del ítem (usa los catálogos MENUS e ICONOS).
    function poblarFormularioEdicion(item) {
        limpiarFormularioCompleto('#form-item-menu-editar', '#modal-mensajes-editar', true);

        $('#id_editar').val(item.id);
        $('#input_nombre_editar').val(item.nombre);
        $('#input_enlace_editar').val(item.enlace || '');

        // Menú (combobox): se resuelve con el catálogo MENUS.
        const menu = MENUS.find(function(m) { return String(m.id) === String(item.menu_id); });
        if (menu) {
            $inputMenuEditar.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
            $('#menu_id_editar').val(menu.id);
            mostrarEstadoMenuEditar(menu);
        } else {
            $inputMenuEditar.val('');
            limpiarSeleccionMenuEditar();
        }

        // Ícono (botones + vista previa): se resuelve con el catálogo ICONOS.
        renderIconosBotonesEditar();
        seleccionarIconoEditar(item.icono_id);

        // Estado.
        const activo = Number(item.estado) === 1;
        $('#input_estado_editar').prop('checked', activo);
        $('#label-estado-editar').text(activo ? 'Activo' : 'Inactivo');

        // Posición actual del ítem (se respeta como punto de partida en Asignar Posición).
        $('#input_posicion_editar').val(item.posicion);
        $('#posicion_editar').val(item.posicion);

        // Habilita Asignar Posición según el menú cargado.
        actualizarBotonPosicionEditar();
    }

    // Abrir edición: trae el ítem y, si llega bien, puebla el formulario y muestra el modal.
    $('#tabla-consulta tbody').on('click', '.btn-editar-item', function() {
        const id = $(this).data('id');

        $.ajax({
            url: 'controllers/item_menus_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    mostrarMensajeFormulario('#alert-container', 'Atención', res.message || 'No se pudo cargar el ítem menú.', 'danger');
                    return;
                }
                poblarFormularioEdicion(res.data);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItemMenuEditar')).show();
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, '#alert-container');
            }
        });
    });

    // Actualizar Ítem Menú.
    $('#form-item-menu-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarItemMenu');
        const formulario   = '#form-item-menu-editar';
        const modalMensaje = '#modal-mensajes-editar';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/item_menus_controller.php?action=actualizar',
            type: 'POST',
            data: $(this).serialize(),   // id, csrf_token, menu_id, nombre, icono_id, enlace, estado, posicion
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    cargarMenus();                          // refresca la cantidad de ítems por menú
                    tablaConsulta.ajax.reload(null, false); // refleja los cambios en la tabla
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        // El error del menú apunta al hidden #menu_id_editar: márcalo también en el combobox visible.
                        if (res.errors && res.errors.menu_id) {
                            $inputMenuEditar.addClass('is-invalid').removeClass('is-valid');
                        }
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
    //  Eliminación de ítem menú
    // ============================================================

    // Abre el modal de eliminación y carga los datos del ítem seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-item', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-item-menu-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);
        $('#preview-icono-item-eliminar').html(PLACEHOLDER_ICONO);   // reinicia la vista previa

        $.ajax({
            url: 'controllers/item_menus_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id-item-eliminar').val(res.data.id);
                    $('#input-nombre-item-eliminar').val(res.data.nombre);
                    $('#input-enlace-item-eliminar').val(res.data.enlace || '');

                    // El menú se resuelve con el catálogo MENUS (obtener solo trae menu_id).
                    const menu = MENUS.find(function(m) { return String(m.id) === String(res.data.menu_id); });
                    $('#input-menu-item-eliminar').val(menu ? nombreMenu(menu) : '');

                    // Vista previa del ícono (catálogo ICONOS); si no tiene, queda el placeholder.
                    const ico = ICONOS.find(function(i) { return String(i.id) === String(res.data.icono_id); });
                    $('#preview-icono-item-eliminar').html(ico ? iconoPreviewHtml(ico) : PLACEHOLDER_ICONO);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del ítem menú.', 'danger', 0);
            },
            complete: function() {
                $('#modalItemMenuEliminar').modal('show');
            }
        });
    });

    // Confirmar eliminación del ítem menú.
    $('#form-item-menu-eliminar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnEliminarItemMenu');
        const modalMensaje = '#modal-mensajes-eliminar';

        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/item_menus_controller.php?action=eliminar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    cargarMenus();   // refresca la cantidad de ítems por menú
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

    // Carga inicial: menús para el combobox e íconos para el selector de botones.
    cargarMenus();
    cargarIconos();

});
