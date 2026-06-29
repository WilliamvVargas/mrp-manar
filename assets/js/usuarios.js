// Flag: true mientras se "salta" del formulario (creación o edición) al de "Ver accesos por
// perfil". Sirve para NO resetear el formulario al ocultarlo y poder volver a él al cerrarlo.
let saltandoVerPerfiles = false;
// A qué formulario vuelve "Ver accesos" al cerrarse: 'crear' | 'editar'.
let origenVerAccesos = 'crear';

$(document).ready(function() {

    // --- 1. INICIALIZACIONES ---

    // Tabla de consulta server-side (helper reutilizable definido en utils.js)
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url: 'controllers/usuarios_controller.php?action=listar',
        input: '#consulta',
        extra: function(d) {
            d.id_perfil = $('#filtro-perfil').val();   // '' = todos
            d.estado    = $('#filtro-estado').val();   // '', '1' o '0'
        },
        orden: [[4, 'desc']],   // Por fecha de creación, más recientes primero
        columnas: [
            { data: 'usuario',   render: $.fn.dataTable.render.text() },
            { data: 'nombres',   render: $.fn.dataTable.render.text() },
            { data: 'apellidos', render: $.fn.dataTable.render.text() },
            {
                data: 'perfil',
                render: function(perfil) {
                    return perfil
                        ? $('<div>').text(perfil).html()
                        : '<span class="text-muted">—</span>';
                }
            },
            { data: 'fecha' },
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
                    // El usuario "admin" (primer registro) no se puede eliminar.
                    const esAdmin = fila.usuario === 'admin';
                    // Botón de estado (igual que menús): verde si activo, gris si inactivo.
                    const activo       = Number(fila.estado) === 1;
                    const claseEstado  = activo ? 'btn-success' : 'btn-secondary';
                    const tituloEstado = activo ? 'Inactivar usuario' : 'Activar usuario';
                    return `
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn ${claseEstado} btn-estado-usuario" data-id="${id}" ${esAdmin ? 'disabled' : ''} title="${esAdmin ? 'No se puede cambiar el estado del usuario admin' : tituloEstado}">
                                <i class="bi bi-power"></i>
                            </button>
                            <button class="btn btn-outline-dark btn-ver-perfil" data-id="${id}" title="Ver Perfil">
                                <i class="bi bi-person-badge"></i>
                            </button>
                            <button class="btn btn-outline-dark btn-editar" data-id="${id}" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-dark btn-password" data-id="${id}" title="Contraseña">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-eliminar" data-id="${id}" ${esAdmin ? 'disabled' : ''} title="${esAdmin ? 'No se puede eliminar al usuario admin' : 'Eliminar'}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`;
                }
            }
        ]
    });

    // Recargar la tabla al cambiar el filtro de perfil o estado.
    $('#filtro-perfil, #filtro-estado').on('change', function() {
        tablaConsulta.ajax.reload();
    });

    // Listeners de limpieza automática al escribir
    activarLimpiezaMensajeAlEscribir('#form-usuario', '#modal-mensajes');
    activarLimpiezaMensajeAlEscribir('#form-usuario-editar', '#modal-mensajes-editar');
    activarLimpiezaMensajeAlEscribir('#form-usuario-password', '#modal-mensajes-password');

    // Configuración de toggles de contraseñas
    activarTogglePassword('#togglePassword', '#password', '#iconEye');
    activarTogglePassword('#togglePasswordConfirm', '#confirm_password', '#iconEyeConfirm');
    activarTogglePassword('#togglePasswordEdit', '#password-editar', '#iconEyeEdit');
    activarTogglePassword('#togglePasswordConfirmEdit', '#confirm-password-editar', '#iconEyeConfirmEdit');

    // Switch de Estado: actualiza su etiqueta (Activo/Inactivo) en creación y edición.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });
    $('#input_estado_editar').on('change', function() {
        $('#label-estado-editar').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // --- Combobox de Perfil (modal de creación) ---
    let PERFILES = [];                       // catálogo de perfiles {id, nombre}
    const $inputPerfil = $('#input_perfil');

    function cargarPerfiles() {
        $.ajax({
            url: 'controllers/perfiles_controller.php?action=combo',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    PERFILES = res.data || [];
                }
            }
        });
    }

    // Limpia la selección de perfil (cuando se edita el texto sin elegir de la lista).
    function limpiarSeleccionPerfil() {
        $('#id_perfil').val('');
        $inputPerfil.removeClass('is-valid');
    }

    inicializarCombobox({
        input:      '#input_perfil',
        lista:      '#lista-perfiles',
        chevron:    '#btn-abrir-perfiles',
        contenedor: '#combobox-perfil',
        max:        50,
        campo:      'nombre',
        opciones:   function() { return PERFILES; },
        render: function(p) {
            return '<span class="small">' + $('<div>').text(p.nombre).html() + '</span>';
        },
        onInput:  limpiarSeleccionPerfil,
        onSelect: function(p) {
            $inputPerfil.val(p.nombre).addClass('is-valid').removeClass('is-invalid');
            $('#id_perfil').val(p.id);
            limpiarErrorCampo($inputPerfil);
        }
    });

    // Validación del Perfil al SALIR del combobox: el motor valida por `name` y el valor real
    // va en el hidden #id_perfil, así que se valida aparte (igual que el menú en Ítem Menús).
    $inputPerfil.on('blur', function() {
        if ($('#id_perfil').val() !== '') {
            limpiarErrorCampo($inputPerfil);
            return;
        }
        $inputPerfil.addClass('is-invalid').removeClass('is-valid');
        ejecutarValidacionUniversal($('#id_perfil'));   // muestra "Debe seleccionar un Perfil"
    });

    // --- Combobox de Perfil (modal de edición) ---
    const $inputPerfilEditar = $('#input_perfil_editar');

    function limpiarSeleccionPerfilEditar() {
        $('#id_perfil_editar').val('');
        $inputPerfilEditar.removeClass('is-valid');
    }

    inicializarCombobox({
        input:      '#input_perfil_editar',
        lista:      '#lista-perfiles-editar',
        chevron:    '#btn-abrir-perfiles-editar',
        contenedor: '#combobox-perfil-editar',
        max:        50,
        campo:      'nombre',
        opciones:   function() { return PERFILES; },
        render: function(p) {
            return '<span class="small">' + $('<div>').text(p.nombre).html() + '</span>';
        },
        onInput:  limpiarSeleccionPerfilEditar,
        onSelect: function(p) {
            $inputPerfilEditar.val(p.nombre).addClass('is-valid').removeClass('is-invalid');
            $('#id_perfil_editar').val(p.id);
            limpiarErrorCampo($inputPerfilEditar);
        }
    });

    $inputPerfilEditar.on('blur', function() {
        if ($('#id_perfil_editar').val() !== '') {
            limpiarErrorCampo($inputPerfilEditar);
            return;
        }
        $inputPerfilEditar.addClass('is-invalid').removeClass('is-valid');
        ejecutarValidacionUniversal($('#id_perfil_editar'));
    });

    cargarPerfiles();

    // ============================================================
    //  Modal "Ver perfiles" (informativo): combobox + cards de menús/accesos
    // ============================================================

    // Ícono del ítem (Bootstrap con la fuente; personalizado con su archivo). Vacío si no tiene.
    function iconoItemVer(it) {
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

    // Cards (con list-group): SOLO los menús e ítems menú que el perfil tiene con acceso
    // activo. La cabecera de la card es el menú ("posición. nombre"); la lista, sus ítems
    // concedidos ("posición. nombre" + ícono). Los menús/ítems sin acceso NO se muestran.
    function construirCardsVer(menus, itemsPorMenu, concedidos) {
        const cards = menus.map(function(m) {
            const itemsConcedidos = (itemsPorMenu[String(m.id)] || []).filter(function(it) {
                return concedidos.has(Number(it.id));
            });
            if (!itemsConcedidos.length) {
                return '';
            }

            const tituloEsc = $('<div>').text(m.nombre).html();
            const lista = itemsConcedidos.map(function(it) {
                const nombreEsc   = $('<div>').text(it.nombre).html();
                const estadoBadge = Number(it.estado) === 1
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
                return '<li class="list-group-item d-flex align-items-center py-1 px-2 small">'
                     +     '<span class="flex-grow-1">' + iconoItemVer(it) + nombreEsc + '</span>'
                     +     estadoBadge
                     + '</li>';
            }).join('');

            return '<div class="card mb-2">'
                 +     '<div class="card-header py-1 px-2 fw-semibold small">' + tituloEsc + '</div>'
                 +     '<ul class="list-group list-group-flush">' + lista + '</ul>'
                 + '</div>';
        }).filter(Boolean).join('');

        return cards || '<div class="text-muted small p-2">Este perfil no tiene accesos asignados.</div>';
    }

    // Carga menús + ítems + accesos del perfil y arma las cards informativas en el contenedor
    // indicado (por defecto, el del modal "Ver accesos por perfil" de creación/edición).
    function cargarAccesosPerfilVer(idPerfil, contenedor) {
        const $cont = $(contenedor || '#accordion-ver-perfiles');
        $cont.html('<div class="text-muted small p-2">Cargando...</div>');

        $.when(
            $.ajax({ url: 'controllers/item_menus_controller.php?action=menus',        type: 'GET', dataType: 'json' }),
            $.ajax({ url: 'controllers/item_menus_controller.php?action=listar_orden', type: 'GET', dataType: 'json' }),
            $.ajax({ url: 'controllers/perfiles_controller.php?action=accesos', type: 'GET', dataType: 'json', data: { id: idPerfil } })
        ).done(function(menusResp, itemsResp, accesosResp) {
            const menusRes   = menusResp[0];
            const itemsRes   = itemsResp[0];
            const accesosRes = accesosResp[0];

            if (!menusRes || menusRes.status !== 'success') {
                $cont.html('<div class="text-danger small p-2">No se pudieron cargar los menús.</div>');
                return;
            }

            const menus = menusRes.data || [];
            const items = (itemsRes && itemsRes.status === 'success') ? (itemsRes.data || []) : [];
            const concedidos = new Set(
                ((accesosRes && accesosRes.status === 'success') ? (accesosRes.data || []) : []).map(Number)
            );

            if (!menus.length) {
                $cont.html('<div class="text-muted small p-2">No hay menús registrados.</div>');
                return;
            }

            const itemsPorMenu = {};
            items.forEach(function(it) {
                const k = String(it.menu_id);
                (itemsPorMenu[k] = itemsPorMenu[k] || []).push(it);
            });

            $cont.html(construirCardsVer(menus, itemsPorMenu, concedidos));
        }).fail(function() {
            $cont.html('<div class="text-danger small p-2">Error al cargar los datos.</div>');
        });
    }

    // Botón "Ver Accesos": "salta" al modal informativo. Oculta el formulario de origen (sin
    // resetearlo, por el flag) y su evento 'hidden' se encarga de mostrar el informativo. El
    // informativo se abre con el MISMO perfil elegido en el formulario; si hay, carga sus accesos.
    function abrirVerAccesos(origen, idHidden, inputVisible) {
        origenVerAccesos = origen;

        const idActual     = $(idHidden).val();
        const nombreActual = $(inputVisible).val();

        if (idActual) {
            $('#id_perfil_ver').val(idActual);
            $('#input_perfil_ver').val(nombreActual);
            cargarAccesosPerfilVer(idActual);
        } else {
            $('#id_perfil_ver').val('');
            $('#input_perfil_ver').val('');
            $('#accordion-ver-perfiles').html('<div class="text-muted small p-2">Selecciona un perfil para ver sus accesos.</div>');
        }

        saltandoVerPerfiles = true;
        const modalId = (origen === 'editar') ? 'modalUsuarioEditar' : 'modalUsuarioCrear';
        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).hide();
    }

    $('#btn-ver-perfiles').on('click', function() {
        abrirVerAccesos('crear', '#id_perfil', '#input_perfil');
    });

    $('#btn-ver-perfiles-editar').on('click', function() {
        abrirVerAccesos('editar', '#id_perfil_editar', '#input_perfil_editar');
    });

    // Combobox de perfil dentro del modal "Ver perfiles".
    inicializarCombobox({
        input:      '#input_perfil_ver',
        lista:      '#lista-perfiles-ver',
        chevron:    '#btn-abrir-perfiles-ver',
        contenedor: '#combobox-perfil-ver',
        max:        50,
        campo:      'nombre',
        opciones:   function() { return PERFILES; },
        render: function(p) {
            return '<span class="small">' + $('<div>').text(p.nombre).html() + '</span>';
        },
        onSelect: function(p) {
            $('#input_perfil_ver').val(p.nombre);
            $('#id_perfil_ver').val(p.id);
            cargarAccesosPerfilVer(p.id);
        }
    });

    // Botón "Ver Perfil" (columna Acciones): modal de solo lectura con el perfil del usuario y
    // sus accesos. Reusa el render de cards (cargarAccesosPerfilVer) en su propio contenedor.
    $(document).on('click', '.btn-ver-perfil', function() {
        const idUsuario = $(this).data('id');
        const $nombre   = $('#perfil-usuario-nombre');
        const $cards    = $('#cards-perfil-usuario');

        $nombre.val('');
        $cards.html('<div class="text-muted small p-2">Cargando...</div>');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=obtener',
            type: 'GET',
            data: { id: idUsuario },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (response.data.id_perfil) {
                        $nombre.val(response.data.perfil || '');
                        cargarAccesosPerfilVer(response.data.id_perfil, '#cards-perfil-usuario');
                    } else {
                        $nombre.val('Sin perfil asignado');
                        $cards.html('<div class="text-muted small p-2">Este usuario no tiene un perfil asignado.</div>');
                    }
                } else {
                    $nombre.val('');
                    $cards.html('<div class="text-danger small p-2">' + response.message + '</div>');
                }
            },
            error: function() {
                $nombre.val('');
                $cards.html('<div class="text-danger small p-2">No se pudieron recuperar los datos del usuario.</div>');
            },
            complete: function() {
                $('#modalVerPerfilUsuario').modal('show');
            }
        });
    });

    // Botón de estado (columna Acciones): alterna activo <-> inactivo. Solo refresca la tabla
    // ante un 'success'; no muestra mensajes en pantalla (igual que menús).
    $('#tabla-consulta tbody').on('click', '.btn-estado-usuario', function() {
        const $btn = $(this);
        const id   = $btn.data('id');

        $btn.prop('disabled', true);

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=cambiar_estado',
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


    // --- 2. ACCIONES DE FORMULARIOS (SUBMITS) ---

    // Registrar Usuario
    $('#form-usuario').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnGuardar');
        const formulario = '#form-usuario';
        const modalMensaje = '#modal-mensajes';
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    $('#label-estado').text('Activo');   // switch vuelve a su valor por defecto
                    tablaConsulta.ajax.reload(null, true);
                    mostrarMensajeFormulario(
                        modalMensaje,
                        'Éxito',
                        res.message,
                        'success',
                        400,
                        res.credenciales
                    );
                }
                else if (res.status === 'error') {

                    $(modalMensaje).slideUp(150);

                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                        // El error de Perfil apunta al hidden #id_perfil: márcalo en el combobox visible.
                        if (res.errors && res.errors.id_perfil) {
                            $('#input_perfil').addClass('is-invalid').removeClass('is-valid');
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


    // Editar Usuario
    $('#form-usuario-editar').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnActualizar');
        const formulario = '#form-usuario-editar';
        const modalMensaje = '#modal-mensajes-editar';
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=editar',
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
                        // El error de Perfil apunta al hidden #id_perfil: márcalo en el combobox visible.
                        if (res.errors && res.errors.id_perfil) {
                            $('#input_perfil_editar').addClass('is-invalid').removeClass('is-valid');
                        }
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

    // Cambiar Contraseña desde el Modal Editar Password
    $('#form-usuario-password').on('submit', function(e) {

        e.preventDefault();

        const btn = $('#btnEditarPassword');
        const formulario = '#form-usuario-password';
        const modalMensaje = '#modal-mensajes-password';
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=cambiar_password',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {

                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    mostrarMensajeFormulario(
                        modalMensaje, 
                        'Éxito', 
                        res.message, 
                        'success', 
                        400, 
                        res.credenciales
                    );
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

    // Confirmar Eliminación de Usuario
    $('#form-usuario-eliminar').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnEliminar');
        const formulario = '#form-usuario-eliminar';
        const modalMensaje = '#modal-mensajes-eliminar';
        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=eliminar',
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

});


// --- 5. DISPARADORES DE APERTURA Y CiERRE DE MODALES ---

$('#modalUsuarioCrear').on('hidden.bs.modal', function (e) {

    // Si venimos del botón "Ver perfiles", mostramos el informativo y NO reseteamos el
    // formulario (volveremos a él al cerrar el informativo).
    if (saltandoVerPerfiles) {
        saltandoVerPerfiles = false;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUsuarioVerPerfiles')).show();
        return;
    }

    limpiarFormularioCompleto("#form-usuario","#modal-mensajes", true);
    $('#label-estado').text('Activo');   // el switch vuelve a su valor por defecto (activo)

});

// Edición: si venimos del botón "Ver Accesos", mostrar el informativo (no se resetea el
// formulario de edición al ocultarse; se rellena al abrirlo).
$('#modalUsuarioEditar').on('hidden.bs.modal', function () {
    if (saltandoVerPerfiles) {
        saltandoVerPerfiles = false;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUsuarioVerPerfiles')).show();
    }
});

// Al cerrar "Ver accesos por perfil" se vuelve al formulario de origen (creación o edición). El
// perfil que quedó elegido en el informativo se refleja en el campo Perfil de ese formulario.
$('#modalUsuarioVerPerfiles').on('hidden.bs.modal', function () {
    const idVer     = $('#id_perfil_ver').val();
    const nombreVer = $('#input_perfil_ver').val();

    const esEditar     = (origenVerAccesos === 'editar');
    const idDestino    = esEditar ? '#id_perfil_editar'    : '#id_perfil';
    const inputDestino = esEditar ? '#input_perfil_editar' : '#input_perfil';
    const modalDestino = esEditar ? 'modalUsuarioEditar'   : 'modalUsuarioCrear';

    if (idVer) {
        $(idDestino).val(idVer);
        $(inputDestino).val(nombreVer).addClass('is-valid').removeClass('is-invalid');
    } else {
        $(idDestino).val('');
        $(inputDestino).val('').removeClass('is-valid is-invalid');
    }
    limpiarErrorCampo($(inputDestino));

    bootstrap.Modal.getOrCreateInstance(document.getElementById(modalDestino)).show();
});

// Cargar datos en Modal de Edición
$(document).on('click', '.btn-editar', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-editar';
    const modalMensaje = '#modal-mensajes-editar';

    limpiarFormularioCompleto(formulario, modalMensaje, true);

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {          

            if (response.status === 'success') {

                $('#id_usuario_editar').val(response.data.id);
                $('#usuario_editar').val(response.data.usuario);
                $('#nombres_editar').val(response.data.nombres);
                $('#apellidos_editar').val(response.data.apellidos);

                // El usuario "admin" no puede cambiar su nombre de usuario: campo en solo lectura.
                const esAdmin = response.data.usuario === 'admin';
                $('#usuario_editar')
                    .prop('readonly', esAdmin)
                    .attr('title', esAdmin ? 'El usuario admin no puede cambiar su nombre de usuario' : '');

                // Perfil actual: id en el hidden, nombre en el visible (sin marcar check al cargar).
                $('#id_perfil_editar').val(response.data.id_perfil || '');
                $('#input_perfil_editar').val(response.data.perfil || '').removeClass('is-valid is-invalid');
                limpiarErrorCampo($('#input_perfil_editar'));

                // Estado actual del usuario en el switch ("admin" no lo puede cambiar).
                const activoEditar = Number(response.data.estado) === 1;
                $('#input_estado_editar')
                    .prop('checked', activoEditar)
                    .prop('disabled', esAdmin);
                $('#label-estado-editar').text(activoEditar ? 'Activo' : 'Inactivo');

            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioEditar').modal('show');
        }
    });

});

// Cargar datos en Modal de Contraseña
$(document).on('click', '.btn-password', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-password';
    const modalMensaje = '#modal-mensajes-password';

    limpiarFormularioCompleto(formulario, modalMensaje, true);
    
    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            

            if (response.status === 'success') {

                $('#id_usuario_password_editar').val(response.data.id);
                $('#input-usuario-pass').val(response.data.usuario);
                
            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioPassword').modal('show');
        }
    });


});


// Cargar datos en Modal de Eliminación
$(document).on('click', '.btn-eliminar', function() {

    const idUsuario = $(this).data('id');
    const formulario = '#form-usuario-eliminar';
    const modalMensaje = '#modal-mensajes-eliminar';

    limpiarFormularioCompleto(formulario, modalMensaje, true);

    $.ajax({
        url: 'controllers/usuarios_controller.php?action=obtener',
        type: 'GET',
        data: { id: idUsuario },
        dataType: 'json',
        success: function(response) {
            
            if (response.status === 'success') {

                $('#id_usuario_eliminar').val(response.data.id);
                $('#input-usuario-eliminar').val(response.data.usuario);
                $('#input-nombres-eliminar').val(response.data.nombres);
                $('#input-apellidos-eliminar').val(response.data.apellidos);
                $('#input-perfil-eliminar').val(response.data.perfil || 'Sin perfil');

            } 
            else {
                mostrarMensajeFormulario(modalMensaje, 'Atención', response.message, 'danger', 0);
            }
        },
        error: function() {
            mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos del usuario.', 'danger', 0);
        },
        complete: function() {
            $('#modalUsuarioEliminar').modal('show');
        }
    });

});


// Botón Copiar Credenciales
$(document).on('click', '.btn-copiar-credenciales-global', function() {
    const $boton = $(this);
    const textoACopiar = $boton.data('clipboard');

    navigator.clipboard.writeText(textoACopiar).then(function() {
        $boton.removeClass('btn-light border-secondary-subtle')
              .addClass('btn-success text-white')
              .html('<i class="bi bi-check-lg me-1"></i> ¡Copiado!');

        setTimeout(function() {
            $boton.removeClass('btn-success text-white')
                  .addClass('btn-light border-secondary-subtle')
                  .html('<i class="bi bi-clipboard me-1"></i> Copiar');
        }, 2000);
    });
});
