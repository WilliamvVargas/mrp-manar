$(document).ready(function() {

    // Tabla principal de perfiles (server-side, helper reutilizable de utils.js).
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/perfiles_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por id ascendente
        columnas: [
            { data: 'id', className: 'text-center' },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            { data: 'total_usuarios', className: 'text-center' },
            {
                // Total Accesos: por cada menú -> "posición. nombre  activos/total" del perfil.
                data: 'resumen_accesos',
                orderable: false,
                searchable: false,
                className: 'small',
                render: function(resumen) {
                    if (!resumen || !resumen.length) {
                        return '<span class="text-muted">—</span>';
                    }
                    return resumen.map(function(mm) {
                        const nombreEsc = $('<div>').text(mm.posicion + '. ' + mm.nombre).html();
                        return '<div class="d-flex justify-content-between gap-2 mb-1">'
                             +     '<span>' + nombreEsc + '</span>'
                             +     '<span class="badge bg-secondary">Ítem menús: ' + mm.accesos_activos + '/' + mm.total_items + '</span>'
                             + '</div>';
                    }).join('');
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id, type, fila) {
                    // El perfil Administrador (Id 1) es fijo: sus botones de editar/eliminar
                    // quedan deshabilitados (Accesos sí está disponible para todos).
                    const esAdmin    = Number(id) === 1;
                    const nombreAttr = $('<div>').text(fila.nombre == null ? '' : fila.nombre).html().replace(/"/g, '&quot;');

                    const btnAccesos = '<button type="button" class="btn btn-outline-dark btn-accesos-perfil" '
                                     + 'data-id="' + id + '" data-nombre="' + nombreAttr + '" '
                                     + 'title="Accesos del perfil"><i class="bi bi-shield-lock"></i></button>';

                    const btnUsuarios = '<button type="button" class="btn btn-outline-dark btn-usuarios-perfil" '
                                      + 'data-id="' + id + '" data-nombre="' + nombreAttr + '" '
                                      + 'title="Usuarios del perfil"><i class="bi bi-people"></i></button>';

                    const btnEditar = '<button type="button" class="btn btn-outline-dark btn-editar-perfil" '
                                    + 'data-id="' + id + '" ' + (esAdmin ? 'disabled ' : '')
                                    + 'title="' + (esAdmin ? 'El perfil Administrador no se puede editar' : 'Editar perfil') + '">'
                                    + '<i class="bi bi-pencil"></i></button>';

                    const btnEliminar = '<button type="button" class="btn btn-outline-danger btn-eliminar-perfil" '
                                      + 'data-id="' + id + '" ' + (esAdmin ? 'disabled ' : '')
                                      + 'title="' + (esAdmin ? 'El perfil Administrador no se puede eliminar' : 'Eliminar perfil') + '">'
                                      + '<i class="bi bi-trash"></i></button>';

                    return '<div class="btn-group btn-group-sm" role="group">' + btnAccesos + btnUsuarios + btnEditar + btnEliminar + '</div>';
                }
            }
        ]
    });

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-perfil', '#modal-mensajes');

    // Crear Perfil.
    $('#form-perfil').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarPerfil');
        const formulario   = '#form-perfil';
        const modalMensaje = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, nombre
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    tablaConsulta.ajax.reload(null, false);   // muestra el nuevo perfil en la tabla
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

    // Abre el modal de edición y carga los datos del perfil seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-editar-perfil', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-perfil-editar';
        const modalMensaje = '#modal-mensajes-editar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id_perfil_editar').val(res.data.id);
                    $('#input_nombre_editar').val(res.data.nombre);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del perfil.', 'danger', 0);
            },
            complete: function() {
                $('#modalPerfilEditar').modal('show');
            }
        });
    });

    // Actualizar Perfil.
    $('#form-perfil-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarPerfil');
        const formulario   = '#form-perfil-editar';
        const modalMensaje = '#modal-mensajes-editar';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=actualizar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, id_registro, nombre
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(null, false);   // refleja el cambio en la tabla
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
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

    // Abre el modal de eliminación y carga los datos del perfil seleccionado.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-perfil', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-perfil-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id-perfil-eliminar').val(res.data.id);
                    $('#input-nombre-perfil-eliminar').val(res.data.nombre);
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del perfil.', 'danger', 0);
            },
            complete: function() {
                $('#modalPerfilEliminar').modal('show');
            }
        });
    });

    // Confirmar eliminación del perfil.
    $('#form-perfil-eliminar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnEliminarPerfil');
        const modalMensaje = '#modal-mensajes-eliminar';

        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=eliminar',
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

    // ============================================================
    //  Accesos del perfil (acordeón de menús)
    // ============================================================

    // Badge de estado (verde Activo / gris Inactivo). Reutilizado por menús e ítems.
    function badgeEstadoAccesos(estado) {
        return Number(estado) === 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';
    }

    // Ícono del ítem (Bootstrap con la fuente; personalizado con su archivo SVG). Cadena
    // vacía si el ítem no tiene ícono.
    function iconoItemAccesoHtml(it) {
        if (!it.icono_id) {
            return '';
        }
        if (it.icono_tipo === 'bootstrap') {
            return '<i class="bi bi-' + it.icono_valor + ' me-1"></i>';
        }
        if (it.icono_archivo) {
            return '<img class="me-1" src="assets/icons/personalizados/' + it.icono_archivo + '" alt="" '
                 + 'style="width: 1em; height: 1em; object-fit: contain; vertical-align: -0.125em;">';
        }
        return '';
    }

    // Cuerpo de una sección: los ítems menú del menú, ordenados por posición y con el mismo
    // formato "posición. nombre" que los menús (+ badge de estado). Cada ítem lleva un
    // checkbox de acceso, marcado si su id está en `concedidos` (accesos activos del perfil).
    function construirCuerpoItems(items, concedidos) {
        if (!items.length) {
            return '<div class="text-muted small">Este menú no tiene ítems menú.</div>';
        }
        return '<ul class="list-group list-group-flush">'
             + items.map(function(it) {
                   const nombreEsc = $('<div>').text(it.nombre).html();
                   const contenido = it.posicion + '. ' + iconoItemAccesoHtml(it) + nombreEsc;   // "1. [ícono] nombre"
                   const marcado   = concedidos.has(Number(it.id)) ? ' checked' : '';
                   return '<li class="list-group-item d-flex align-items-center py-1 px-2 small acceso-item-fila">'
                        +     '<input class="form-check-input acceso-item mt-0 me-2" type="checkbox" value="' + it.id + '"' + marcado + '>'
                        +     '<span class="flex-grow-1">' + contenido + '</span>'
                        +     badgeEstadoAccesos(it.estado)
                        + '</li>';
               }).join('')
             + '</ul>';
    }

    // Arma el acordeón de Bootstrap: una sección (cabecera) por cada menú. La cabecera muestra
    // "posición. nombre" + badges de estado y de cantidad de ítems; el cuerpo lista sus ítems.
    function construirAcordeonAccesos(menus, itemsPorMenu, concedidos) {
        return menus.map(function(m) {
            const collapseId = 'accordion-menu-' + m.id;
            const tituloEsc  = $('<div>').text(m.posicion + '. ' + m.nombre).html();

            // Badge "Ítem menús: X/Y" -> X = ítems de este menú con acceso activo del perfil,
            // Y = total de ítems del menú.
            const itemsDelMenu = itemsPorMenu[String(m.id)] || [];
            const totalItems   = itemsDelMenu.length;
            const activos      = itemsDelMenu.filter(function(it) { return concedidos.has(Number(it.id)); }).length;

            const itemsBadge = '<span class="badge bg-secondary badge-accesos-items">Ítem menús: ' + activos + '/' + totalItems + '</span>';
            const cuerpo     = construirCuerpoItems(itemsDelMenu, concedidos);

            // Al abrir el modal, los menús con al menos un acceso activo se muestran ABIERTOS.
            // (Sin data-bs-parent: varias secciones pueden quedar abiertas a la vez.)
            const abierto      = activos > 0;
            const btnClase     = 'accordion-button' + (abierto ? '' : ' collapsed');
            const colapsoClase = 'accordion-collapse collapse' + (abierto ? ' show' : '');

            return '<div class="accordion-item">'
                 +     '<h2 class="accordion-header">'
                 +         '<button class="' + btnClase + '" type="button" data-bs-toggle="collapse" '
                 +                 'data-bs-target="#' + collapseId + '" aria-expanded="' + (abierto ? 'true' : 'false') + '" aria-controls="' + collapseId + '">'
                 +             '<span class="flex-grow-1 fw-semibold">' + tituloEsc + '</span>'
                 +             '<span class="me-3 d-flex align-items-center gap-1">' + itemsBadge + badgeEstadoAccesos(m.estado) + '</span>'
                 +         '</button>'
                 +     '</h2>'
                 +     '<div id="' + collapseId + '" class="' + colapsoClase + '">'
                 +         '<div class="accordion-body">' + cuerpo + '</div>'
                 +     '</div>'
                 + '</div>';
        }).join('');
    }

    // Botón "Usuarios": abre el modal con los usuarios que pertenecen al perfil (usuario + estado).
    $('#tabla-consulta tbody').on('click', '.btn-usuarios-perfil', function() {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        $('#input-perfil-usuarios').val(nombre || '');
        $('#lista-usuarios-perfil').html('<div class="text-muted small p-2">Cargando...</div>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPerfilUsuarios')).show();

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=usuarios',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $('#lista-usuarios-perfil').html('<div class="text-danger small p-2">' + (res.message || 'No se pudo cargar.') + '</div>');
                    return;
                }

                const usuarios = res.data || [];
                if (!usuarios.length) {
                    $('#lista-usuarios-perfil').html('<div class="text-muted small p-2">Este perfil no tiene usuarios.</div>');
                    return;
                }

                const filas = usuarios.map(function(u) {
                    const badge = Number(u.estado) === 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>';
                    return '<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">'
                         +     '<span>' + $('<div>').text(u.usuario).html() + '</span>'
                         +     badge
                         + '</li>';
                }).join('');

                $('#lista-usuarios-perfil').html('<ul class="list-group list-group-flush">' + filas + '</ul>');
            },
            error: function() {
                $('#lista-usuarios-perfil').html('<div class="text-danger small p-2">Error al cargar los usuarios.</div>');
            }
        });
    });

    // Botón "Accesos": abre el modal y arma el acordeón con los menús de la tabla menus.
    $('#tabla-consulta tbody').on('click', '.btn-accesos-perfil', function() {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        $('#id-perfil-accesos').val(id);
        $('#input-perfil-accesos').val(nombre || '');
        $('#accesos-mensajes').empty();
        $('#accordion-accesos').html('<div class="text-muted small p-2">Cargando menús...</div>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPerfilAccesos')).show();

        // Menús (cabeceras) + ítems menú (cuerpos) + accesos del perfil (checkboxes marcados).
        $.when(
            $.ajax({ url: 'controllers/item_menus_controller.php?action=menus',        type: 'GET', dataType: 'json' }),
            $.ajax({ url: 'controllers/item_menus_controller.php?action=listar_orden', type: 'GET', dataType: 'json' }),
            $.ajax({ url: 'controllers/perfiles_controller.php?action=accesos', type: 'GET', dataType: 'json', data: { id: id } })
        ).done(function(menusResp, itemsResp, accesosResp) {
            const menusRes   = menusResp[0];
            const itemsRes   = itemsResp[0];
            const accesosRes = accesosResp[0];

            if (!menusRes || menusRes.status !== 'success') {
                $('#accordion-accesos').html('<div class="text-danger small p-2">No se pudieron cargar los menús.</div>');
                return;
            }

            const menus = menusRes.data || [];
            const items = (itemsRes && itemsRes.status === 'success') ? (itemsRes.data || []) : [];

            // Conjunto de ítems menú con acceso activo (para marcar los checkboxes).
            const concedidos = new Set(
                ((accesosRes && accesosRes.status === 'success') ? (accesosRes.data || []) : []).map(Number)
            );

            if (!menus.length) {
                $('#accordion-accesos').html('<div class="text-muted small p-2">No hay menús registrados.</div>');
                return;
            }

            // Agrupa los ítems por menú (ya vienen ordenados por menu_id y posición).
            const itemsPorMenu = {};
            items.forEach(function(it) {
                const k = String(it.menu_id);
                (itemsPorMenu[k] = itemsPorMenu[k] || []).push(it);
            });

            $('#accordion-accesos').html(construirAcordeonAccesos(menus, itemsPorMenu, concedidos));
        }).fail(function() {
            $('#accordion-accesos').html('<div class="text-danger small p-2">Error al cargar los datos.</div>');
        });
    });

    // Toda la fila es clickeable: un clic en cualquier parte (texto, badge, espacio vacío)
    // marca/desmarca el checkbox. Si el clic fue en el propio checkbox, se deja su toggle nativo.
    $('#accordion-accesos').on('click', '.acceso-item-fila', function(e) {
        if ($(e.target).is('.acceso-item')) {
            return;
        }
        const $chk = $(this).find('.acceso-item');
        $chk.prop('checked', !$chk.prop('checked')).trigger('change');
    });

    // En vivo: al marcar/desmarcar un ítem, recalcula el badge "X/Y" de su menú (X = marcados
    // ahora en ese menú). Delegado, porque el acordeón se re-renderiza en cada apertura.
    $('#accordion-accesos').on('change', '.acceso-item', function() {
        const $menu   = $(this).closest('.accordion-item');
        const total   = $menu.find('.acceso-item').length;
        const activos = $menu.find('.acceso-item:checked').length;
        $menu.find('.badge-accesos-items').text('Ítem menús: ' + activos + '/' + total);
    });

    // Actualizar accesos: envía los ítems MARCADOS; el backend concede (estado 1) los marcados
    // y revoca (estado 0) los que estaban activos y ya no vienen marcados (sin borrarlos).
    $('#btnActualizarAccesos').on('click', function() {
        const $btn     = $(this);
        const idPerfil = $('#id-perfil-accesos').val();

        const items = $('#accordion-accesos .acceso-item:checked').map(function() {
            return $(this).val();
        }).get();

        setBtnLoading($btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/perfiles_controller.php?action=actualizar_accesos',
            type: 'POST',
            data: { id_perfil: idPerfil, items: items, csrf_token: $('#csrf_token').val() },
            dataType: 'json',
            success: function(res) {
                resetBtnLoading($btn);

                if (res.status === 'success') {
                    mostrarMensajeFormulario('#accesos-mensajes', 'Éxito', res.message, 'success');
                } else {
                    mostrarMensajeFormulario('#accesos-mensajes', 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading($btn);
                manejarErrorAjax(jqXHR, textStatus, '#accesos-mensajes');
            }
        });
    });

});
