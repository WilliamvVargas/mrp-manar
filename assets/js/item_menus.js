$(document).ready(function() {

    const MAX_RESULTADOS = 50;   // tope de coincidencias mostradas en el combobox.

    // ============================================================
    //  Combobox de Menú padre (con búsqueda + badge de estado)
    // ============================================================

    let MENUS = [];                       // catálogo de menús {id, nombre, estado, posicion}
    const $inputMenu  = $('#input_menu');
    const $listaMenus = $('#lista-menus');

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

    // Muestra (o limpia) el badge de estado del menú seleccionado, junto a la etiqueta.
    function mostrarEstadoMenu(estado) {
        const $badge = $('#badge-estado-menu');

        if (estado === null || estado === undefined) {
            $badge.addClass('d-none').removeClass('bg-success bg-secondary').text('');
            return;
        }
        $badge.removeClass('d-none bg-success bg-secondary')
              .addClass(Number(estado) === 1 ? 'bg-success' : 'bg-secondary')
              .text(Number(estado) === 1 ? 'Activo' : 'Inactivo');
    }

    // Filtra los menús por la consulta (por nombre).
    function filtrarMenus(consulta) {
        const q = consulta.trim().toLowerCase();
        if (!q) {
            return MENUS;
        }
        return MENUS.filter(function(m) {
            return m.nombre.toLowerCase().indexOf(q) !== -1;
        });
    }

    // Dibuja el desplegable. Con `todos` muestra el catálogo completo (al enfocar / chevron);
    // si no, filtra por el texto del input (al escribir).
    function abrirListaMenus(todos) {
        const coincidencias = todos ? MENUS : filtrarMenus($inputMenu.val());
        const mostradas     = coincidencias.slice(0, MAX_RESULTADOS);
        let html = '';

        if (!mostradas.length) {
            html = '<li class="list-group-item small text-muted">Sin coincidencias</li>';
        } else {
            html = mostradas.map(function(m) {
                const nombreEsc = $('<div>').text(m.nombre).html();
                return '<li class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2 py-1 item-menu" '
                     + 'role="button" data-id="' + m.id + '">'
                     + '<span class="small text-truncate">' + nombreEsc + '</span>'
                     + badgeEstadoHtml(m.estado)
                     + '</li>';
            }).join('');

            if (coincidencias.length > MAX_RESULTADOS) {
                html += '<li class="list-group-item small text-muted text-center">'
                      + (coincidencias.length - MAX_RESULTADOS) + ' resultados más… afina tu búsqueda'
                      + '</li>';
            }
        }

        $listaMenus.html(html).removeClass('d-none');
    }

    function cerrarListaMenus() {
        $listaMenus.addClass('d-none').empty();
    }

    // Resalta un ítem del desplegable (navegación con teclado).
    function resaltarItemMenu($item) {
        $listaMenus.children('.item-menu').removeClass('active');
        if ($item && $item.length) {
            $item.addClass('active');
            $item[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Limpia la selección de menú (cuando se edita el texto sin elegir de la lista).
    function limpiarSeleccionMenu() {
        $('#menu_id').val('');
        $inputMenu.removeClass('is-valid');
        mostrarEstadoMenu(null);
        limpiarPosicionElegida();      // la posición es por menú: se invalida al cambiar de menú
        actualizarBotonPosicion();
    }

    // Selecciona un menú: refleja su nombre, guarda el id real y muestra su estado.
    function seleccionarMenu(id) {
        const menu = MENUS.find(function(m) { return String(m.id) === String(id); });
        if (!menu) {
            return;
        }

        $inputMenu.val(menu.nombre).addClass('is-valid').removeClass('is-invalid');
        $('#menu_id').val(menu.id);
        mostrarEstadoMenu(menu.estado);
        limpiarErrorCampo($inputMenu);
        limpiarPosicionElegida();      // cambió el menú: se descarta la posición previa
        actualizarBotonPosicion();
        cerrarListaMenus();
    }

    // Al enfocar: muestra TODAS las opciones y selecciona el texto (para sobrescribirlo
    // sin tener que borrarlo). El setTimeout evita que el clic del mouse deshaga la selección.
    $inputMenu.on('focus', function() {
        const el = this;
        setTimeout(function() { el.select(); }, 0);
        abrirListaMenus(true);
    });

    // Al escribir: invalida la selección previa y filtra el desplegable.
    $inputMenu.on('input', function() {
        limpiarSeleccionMenu();
        abrirListaMenus();
    });

    // Navegación con teclado dentro del desplegable.
    $inputMenu.on('keydown', function(e) {
        if ($listaMenus.hasClass('d-none')) {
            return;
        }
        const $items = $listaMenus.children('.item-menu');
        let idx = $items.index($items.filter('.active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = (idx + 1 >= $items.length) ? 0 : idx + 1;
            resaltarItemMenu($items.eq(idx));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = (idx <= 0) ? $items.length - 1 : idx - 1;
            resaltarItemMenu($items.eq(idx));
        } else if (e.key === 'Enter') {
            if (idx >= 0) {
                e.preventDefault();
                seleccionarMenu($items.eq(idx).attr('data-id'));
            }
        } else if (e.key === 'Escape') {
            cerrarListaMenus();
        }
    });

    // Selección con el mouse (mousedown para no perder el foco antes del click).
    $listaMenus.on('mousedown', '.item-menu', function(e) {
        e.preventDefault();
        seleccionarMenu($(this).attr('data-id'));
    });

    // La flecha de la derecha abre/cierra el listado (mousedown para no robar el foco).
    $('#btn-abrir-menus').on('mousedown', function(e) {
        e.preventDefault();
        if ($listaMenus.hasClass('d-none')) {
            $inputMenu.trigger('focus');
            abrirListaMenus(true);      // muestra todas las opciones
        } else {
            cerrarListaMenus();
        }
    });

    // Cierra el desplegable al hacer clic fuera del combobox.
    $(document).on('mousedown', function(e) {
        if (!$(e.target).closest('#combobox-menu').length) {
            cerrarListaMenus();
        }
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
                $('#input-menu-posicion').val(menuSel ? menuSel.nombre : '');
                mostrarEstadoMenuPos(menuSel ? menuSel.estado : null);
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
    const $inputMenuPos  = $('#input-menu-posicion');
    const $listaMenusPos = $('#lista-menus-posicion');

    function mostrarEstadoMenuPos(estado) {
        const $b = $('#badge-estado-menu-posicion');
        if (estado === null || estado === undefined) {
            $b.addClass('d-none').removeClass('bg-success bg-secondary').text('');
            return;
        }
        $b.removeClass('d-none bg-success bg-secondary')
          .addClass(Number(estado) === 1 ? 'bg-success' : 'bg-secondary')
          .text(Number(estado) === 1 ? 'Activo' : 'Inactivo');
    }

    function abrirListaMenusPos(todos) {
        const coincidencias = todos ? MENUS : filtrarMenus($inputMenuPos.val());
        const mostradas     = coincidencias.slice(0, MAX_RESULTADOS);
        let html = '';

        if (!mostradas.length) {
            html = '<li class="list-group-item small text-muted">Sin coincidencias</li>';
        } else {
            html = mostradas.map(function(m) {
                const nombreEsc = $('<div>').text(m.nombre).html();
                return '<li class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2 py-1 item-menu-pos" '
                     + 'role="button" data-id="' + m.id + '">'
                     + '<span class="small text-truncate">' + nombreEsc + '</span>'
                     + badgeEstadoHtml(m.estado)
                     + '</li>';
            }).join('');

            if (coincidencias.length > MAX_RESULTADOS) {
                html += '<li class="list-group-item small text-muted text-center">'
                      + (coincidencias.length - MAX_RESULTADOS) + ' resultados más… afina tu búsqueda'
                      + '</li>';
            }
        }
        $listaMenusPos.html(html).removeClass('d-none');
    }

    function cerrarListaMenusPos() {
        $listaMenusPos.addClass('d-none').empty();
    }

    function resaltarItemMenuPos($item) {
        $listaMenusPos.children('.item-menu-pos').removeClass('active');
        if ($item && $item.length) {
            $item.addClass('active');
            $item[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Cambia el menú desde el modal: sincroniza con el formulario, reinicia la posición
    // (el nuevo va al final) y recarga la lista con los ítems del nuevo menú.
    function seleccionarMenuPos(id) {
        const menu = MENUS.find(function(m) { return String(m.id) === String(id); });
        if (!menu) {
            return;
        }

        // Sincroniza con el formulario de creación.
        $inputMenu.val(menu.nombre).addClass('is-valid').removeClass('is-invalid');
        $('#menu_id').val(menu.id);
        mostrarEstadoMenu(menu.estado);
        limpiarErrorCampo($inputMenu);
        limpiarPosicionElegida();      // el ítem nuevo pasa a la última posición
        actualizarBotonPosicion();

        // Refleja en el combobox del modal.
        $inputMenuPos.val(menu.nombre).removeClass('is-invalid');
        mostrarEstadoMenuPos(menu.estado);
        cerrarListaMenusPos();

        // Recarga la lista con los ítems del nuevo menú.
        asignador.recargar();
    }

    // Al enfocar: muestra TODAS las opciones y selecciona el texto (para sobrescribirlo).
    $inputMenuPos.on('focus', function() {
        const el = this;
        setTimeout(function() { el.select(); }, 0);
        abrirListaMenusPos(true);
    });

    // Al escribir: filtra por el texto.
    $inputMenuPos.on('input', function() {
        abrirListaMenusPos();
    });

    $inputMenuPos.on('keydown', function(e) {
        if ($listaMenusPos.hasClass('d-none')) {
            return;
        }
        const $items = $listaMenusPos.children('.item-menu-pos');
        let idx = $items.index($items.filter('.active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = (idx + 1 >= $items.length) ? 0 : idx + 1;
            resaltarItemMenuPos($items.eq(idx));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = (idx <= 0) ? $items.length - 1 : idx - 1;
            resaltarItemMenuPos($items.eq(idx));
        } else if (e.key === 'Enter') {
            if (idx >= 0) {
                e.preventDefault();
                seleccionarMenuPos($items.eq(idx).attr('data-id'));
            }
        } else if (e.key === 'Escape') {
            cerrarListaMenusPos();
        }
    });

    $listaMenusPos.on('mousedown', '.item-menu-pos', function(e) {
        e.preventDefault();
        seleccionarMenuPos($(this).attr('data-id'));
    });

    $('#btn-abrir-menus-posicion').on('mousedown', function(e) {
        e.preventDefault();
        if ($listaMenusPos.hasClass('d-none')) {
            $inputMenuPos.trigger('focus');
            abrirListaMenusPos(true);      // muestra todas las opciones
        } else {
            cerrarListaMenusPos();
        }
    });

    $(document).on('mousedown', function(e) {
        if (!$(e.target).closest('#combobox-menu-posicion').length) {
            cerrarListaMenusPos();
        }
    });

    // Carga inicial: menús para el combobox e íconos para el selector de botones.
    cargarMenus();
    cargarIconos();

});
