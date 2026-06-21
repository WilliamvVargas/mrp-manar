$(document).ready(function() {

    const MAX_RESULTADOS = 50;   // tope de coincidencias mostradas en el combobox.

    // ============================================================
    //  Combobox de Menú padre (con búsqueda + badge de estado)
    // ============================================================

    let MENUS = [];                       // catálogo de menús {id, nombre, estado, posicion}
    const $inputMenu = $('#input_menu');

    // Carga los menús para el combobox (id, nombre, estado).
    function cargarMenus() {
        $.ajax({
            url: 'controllers/item_menus_controller.php?action=menus',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    MENUS = res.data || [];
                }
            }
        });
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
                    cargarMenus();   // refresca la cantidad de ítems por menú del combobox
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
    // Mismo enfoque que el formulario de creación de menús: el ítem nuevo es el único
    // movible. La lista se acota al MENÚ seleccionado, porque la posición es por menú.
    const asignador = inicializarAsignadorPosicion({
        urlListar: 'controllers/item_menus_controller.php?action=listar_orden',
        // Sin contexto global ni reordenamiento masivo: solo se usa la creación.
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
        }]
    });

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
        // Sincroniza con el formulario de creación.
        $inputMenu.val(nombreMenu(menu)).addClass('is-valid').removeClass('is-invalid');
        $('#menu_id').val(menu.id);
        mostrarEstadoMenu(menu);
        limpiarErrorCampo($inputMenu);
        limpiarPosicionElegida();      // el ítem nuevo pasa a la última posición
        actualizarBotonPosicion();

        // Refleja en el combobox del modal.
        $inputMenuPos.val(nombreMenu(menu)).removeClass('is-invalid');
        mostrarEstadoMenuPos(menu);

        // Recarga la lista con los ítems del nuevo menú.
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

    // Carga inicial: menús para el combobox e íconos para el selector de botones.
    cargarMenus();
    cargarIconos();

});
