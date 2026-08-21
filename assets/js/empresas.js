$(document).ready(function() {

    // --- Tabla de empresas (server-side, helper de utils.js) ---
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/empresas_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por Posición ascendente
        columnas: [
            { data: 'posicion', className: 'text-center' },
            {
                // Logo: miniatura si hay imagen; ícono gris si no.
                data: 'logo',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(logo) {
                    if (!logo) {
                        return '<span class="text-muted"><i class="bi bi-image"></i></span>';
                    }
                    const src = 'assets/img/empresas/' + encodeURIComponent(logo);
                    return '<img src="' + src + '" alt="logo" class="p-1" '
                         + 'style="height: 40px; max-width: 90px; object-fit: contain;">';
                }
            },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            { data: 'fecha' },
            {
                // Acciones: Conexión SAP / Editar / Eliminar (Eliminar pendiente).
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id, type, fila) {
                    const nombre = $('<div>').text(fila.nombre || '').html();
                    return `
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-dark btn-editar-empresa" data-id="${id}" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-dark btn-conexion-empresa" data-id="${id}" data-nombre="${nombre}" title="Conexión SAP">
                                <i class="bi bi-hdd-network"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-eliminar-empresa" data-id="${id}" title="Eliminar (pendiente)" disabled>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`;
                }
            }
        ]
    });

    // ============================================================
    //  EMPRESA WMS (selector poblado desde el maestro dbo.EMPRESA)
    // ============================================================

    // Carga las empresas del WMS y las inyecta en los selectores de crear/editar.
    // Se piden una sola vez al cargar la página.
    (function cargarEmpresasWms() {
        $.ajax({
            url: 'controllers/empresas_controller.php?action=listar_empresas_wms',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success' || !Array.isArray(res.data)) { return; }
                let opciones = '<option value="">Seleccione una empresa...</option>';
                res.data.forEach(function(e) {
                    const cod    = $('<div>').text(e.Cod_Emp).html();
                    const nombre = $('<div>').text(e.EmpDsc || '').html();
                    opciones += '<option value="' + cod + '">' + cod + '. ' + nombre + '</option>';
                });
                $('#empresa_wms, #empresa_wms_editar').html(opciones);
            }
        });
    })();

    // Al elegir una empresa WMS, limpia el estado de error del selector.
    $(document).on('change', '#empresa_wms, #empresa_wms_editar', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
            $('#' + $(this).attr('id')).closest('.mb-2').find('.invalid-feedback').text('');
        }
    });

    // ============================================================
    //  ASIGNAR POSICIÓN (widget reutilizable, igual que Menús)
    // ============================================================

    // Habilita "Asignar Posición" solo cuando el Nombre es válido (observa la clase is-valid).
    function activarObservadorNombre(idInput, selectorBoton) {
        const input = document.getElementById(idInput);
        if (!input) { return; }
        const observador = new MutationObserver(function() {
            const $n = $(input);
            const valido = $n.hasClass('is-valid') && !$n.hasClass('is-invalid');
            $(selectorBoton).prop('disabled', !valido);
        });
        observador.observe(input, { attributes: true, attributeFilter: ['class'] });
    }
    activarObservadorNombre('input_nombre', '#btn-asignar-posicion');
    activarObservadorNombre('nombre_editar', '#btn-asignar-posicion-editar');

    // Contexto CREACIÓN: la empresa nueva es un ítem sintético que se inserta en la lista.
    const ctxCrearEmpresa = {
        boton:        '#btn-asignar-posicion',
        modalPadre:   '#modalEmpresaCrear',
        inputVisible: '#input_posicion',
        inputHidden:  '#posicion',
        idMovible:    function() { return 'nuevo'; },
        construirItems: function(data) {
            const items = data.map(function(r, i) {
                return itemPosicion(r.id, r.nombre, i + 1, 'fijo', null, null);
            });
            const nombreNuevo = $('#input_nombre').val().trim() || '(nueva empresa)';
            const itemNuevo   = itemPosicion('nuevo', nombreNuevo, items.length + 1, 'movible', 'Nueva', null);
            let pos = parseInt($('#input_posicion').val(), 10);
            if (isNaN(pos) || pos < 1 || pos > items.length + 1) { pos = items.length + 1; }
            items.splice(pos - 1, 0, itemNuevo);
            return items;
        },
        alCerrarReal: function() {
            limpiarFormularioCompleto('#form-empresa', '#modal-mensajes', true);
            $('#logo-preview-wrap').addClass('d-none');
            $('#logo-preview').attr('src', '');
        }
    };

    // Contexto EDICIÓN: la empresa en edición es el ítem movible (ya viene en el listado).
    const ctxEditarEmpresa = {
        boton:        '#btn-asignar-posicion-editar',
        modalPadre:   '#modalEmpresaEditar',
        inputVisible: '#input_posicion_editar',
        inputHidden:  '#posicion_editar',
        idMovible:    function() { return String($('#id_empresa_editar').val()); },
        construirItems: function(data) {
            const idEditado = String($('#id_empresa_editar').val());
            let indiceMovible = -1;
            const items = data.map(function(r, i) {
                const esMovible = String(r.id) === idEditado;
                if (esMovible) { indiceMovible = i; }
                return itemPosicion(r.id, r.nombre, i + 1, esMovible ? 'movible' : 'fijo', 'Editando', null);
            });
            const posElegida = parseInt($('#posicion_editar').val(), 10);
            if (!isNaN(posElegida) && posElegida >= 1 && posElegida <= items.length
                && indiceMovible !== -1 && (posElegida - 1) !== indiceMovible) {
                const movido = items.splice(indiceMovible, 1)[0];
                items.splice(posElegida - 1, 0, movido);
            }
            return items;
        },
        alCerrarReal: function() {
            limpiarFormularioCompleto('#form-empresa-editar', '#modal-mensajes-editar', true);
            $('#logo-nuevo-wrap').addClass('d-none');
            $('#logo-nuevo-editar').attr('src', '');
        }
    };

    inicializarAsignadorPosicion({
        urlListar:    'controllers/empresas_controller.php?action=listar_orden',
        urlReordenar: 'controllers/empresas_controller.php?action=reordenar',
        tabla:        tablaConsulta,
        contextos:    [ctxCrearEmpresa, ctxEditarEmpresa]
    });

    // Limpia el mensaje del modal al empezar a escribir/cambiar (helper de utils.js).
    activarLimpiezaMensajeAlEscribir('#form-empresa', '#modal-mensajes');

    // Previsualización del logo al seleccionar la imagen.
    $('#input_logo').on('change', function() {
        const file  = this.files && this.files[0];
        const $wrap = $('#logo-preview-wrap');
        const $img  = $('#logo-preview');

        if (!file) {
            $wrap.addClass('d-none');
            $img.attr('src', '');
            return;
        }
        $img.attr('src', URL.createObjectURL(file));
        $wrap.removeClass('d-none');
    });

    // Registrar Empresa. Usa FormData (no serialize) porque incluye un archivo (logo).
    $('#form-empresa').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardar');
        const formulario   = '#form-empresa';
        const modalMensaje = '#modal-mensajes';
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/empresas_controller.php?action=registrar',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, true);
                    $('#logo-preview-wrap').addClass('d-none');
                    $('#logo-preview').attr('src', '');
                    tablaConsulta.ajax.reload(null, false);   // refresca el listado
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success', 400);
                } else if (res.status === 'error') {
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

    // ============================================================
    //  EDITAR EMPRESA
    // ============================================================

    // Preview del logo nuevo (opcional) en el modal de edición.
    $('#input_logo_editar').on('change', function() {
        const file  = this.files && this.files[0];
        const $wrap = $('#logo-nuevo-wrap');
        const $img  = $('#logo-nuevo-editar');

        if (!file) {
            $wrap.addClass('d-none');
            $img.attr('src', '');
            return;
        }
        $img.attr('src', URL.createObjectURL(file));
        $wrap.removeClass('d-none');
    });

    // Botón "Editar": carga los datos de la empresa en el modal y lo abre.
    $(document).on('click', '.btn-editar-empresa', function() {
        const id           = $(this).data('id');
        const formulario   = '#form-empresa-editar';
        const modalMensaje = '#modal-mensajes-editar';

        limpiarFormularioCompleto(formulario, modalMensaje, true);
        // Reinicia previews de logo.
        $('#logo-nuevo-wrap').addClass('d-none');
        $('#logo-nuevo-editar').attr('src', '');

        $.ajax({
            url: 'controllers/empresas_controller.php?action=obtener',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#id_empresa_editar').val(res.data.id);
                    $('#nombre_editar').val(res.data.nombre);
                    // Empresa WMS asociada (persistencia en BD pendiente; hoy queda "Sin asociar").
                    $('#empresa_wms_editar').val(res.data.empresa_wms || '');

                    // Posición: muestra la actual; el hidden queda vacío (= sin cambio) hasta
                    // que el usuario elija otra en el selector. Habilita el botón "Asignar".
                    $('#input_posicion_editar').val(res.data.posicion);
                    $('#posicion_editar').val('');
                    $('#btn-asignar-posicion-editar').prop('disabled', false);

                    // Logo actual: muestra la miniatura o "Sin logo".
                    if (res.data.logo) {
                        $('#logo-actual-editar')
                            .attr('src', 'assets/img/empresas/' + encodeURIComponent(res.data.logo))
                            .removeClass('d-none');
                        $('#logo-actual-vacio').addClass('d-none');
                    } else {
                        $('#logo-actual-editar').addClass('d-none').attr('src', '');
                        $('#logo-actual-vacio').removeClass('d-none');
                    }
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudieron recuperar los datos de la empresa.', 'danger', 0);
            },
            complete: function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEmpresaEditar')).show();
            }
        });
    });

    // Actualizar Empresa (FormData por el logo).
    $('#form-empresa-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizar');
        const formulario   = '#form-empresa-editar';
        const modalMensaje  = '#modal-mensajes-editar';
        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/empresas_controller.php?action=editar',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    tablaConsulta.ajax.reload(null, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else if (res.status === 'no_changes') {
                    limpiarFormularioCompleto(formulario, modalMensaje, false);
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
                } else {
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

    // ============================================================
    //  CONEXIÓN SAP (por empresa)  — 1 a 1 con la empresa
    // ============================================================

    // Botón "Conexión SAP": carga la conexión guardada de la empresa y abre el modal.
    $(document).on('click', '.btn-conexion-empresa', function() {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre') || '';
        const modalMensaje = '#modal-mensajes-conexion';

        // Limpia el formulario y los mensajes previos.
        document.getElementById('form-empresa-conexion').reset();
        $('#modal-mensajes-conexion').empty();
        $('#form-empresa-conexion .is-invalid').removeClass('is-invalid');

        $('#id_empresa_conexion').val(id);
        $('#conexion-empresa-nombre').val(nombre);

        $.ajax({
            url: 'controllers/empresas_controller.php?action=obtener_conexion',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#conexion_servidor').val(res.data.sap_servidor || '');
                    $('#conexion_base').val(res.data.sap_base || '');
                    $('#conexion_usuario').val(res.data.sap_usuario || '');
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger', 0);
                }
            },
            error: function() {
                mostrarMensajeFormulario(modalMensaje, 'Error de Sistema', 'No se pudo recuperar la conexión de la empresa.', 'danger', 0);
            },
            complete: function() {
                bootstrap.Modal
                    .getOrCreateInstance(document.getElementById('modalEmpresaConexionSap'))
                    .show();
            }
        });
    });

    // Limpia el mensaje del modal al empezar a escribir (helper de utils.js).
    activarLimpiezaMensajeAlEscribir('#form-empresa-conexion', '#modal-mensajes-conexion');

    // Guardar Conexión SAP (upsert sobre la fila de la empresa; no toca la contraseña).
    $('#form-empresa-conexion').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarConexion');
        const formulario   = '#form-empresa-conexion';
        const modalMensaje  = '#modal-mensajes-conexion';
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/empresas_controller.php?action=guardar_conexion',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else {
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
});
