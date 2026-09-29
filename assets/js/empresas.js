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
            {
                // Nivel de servicio del MRP: se guarda el factor Z; se muestra como % (+ Z).
                data: 'mrp_z_seguridad',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(z) {
                    const zz = parseFloat(z);
                    if (isNaN(zz)) { return '<span class="text-muted">—</span>'; }
                    return coma(pctPorZ(zz)) + '% <span class="text-muted">· Z ' + coma(zz.toFixed(2)) + '</span>';
                }
            },
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
    //  NIVEL DE SERVICIO (Z) DEL MRP  — slider en % que guarda el factor Z
    // ============================================================

    // El slider se mueve en NIVEL DE SERVICIO (%) en pasos de 0,5%, y guardamos el factor Z
    // correspondiente (columna mrp_z_seguridad) en el hidden. El % es intuitivo y los pasos
    // acotados impiden ingresar valores sin sentido.
    const NS_MIN = 80, NS_MAX = 99.5, NS_STEP = 0.5, NS_DEFAULT_PCT = 95;
    const coma = function(v) { return String(v).replace('.', ','); };

    // Inversa de la normal estándar (probit): z tal que P(Z<=z)=p. Aproximación de Acklam
    // (error < 1,15e-9). Convierte el nivel de servicio (p) al factor Z de la fórmula del SS.
    function probit(p) {
        if (p <= 0) { return -Infinity; }
        if (p >= 1) { return Infinity; }
        const a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        const b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01];
        const c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        const d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00];
        const plow = 0.02425, phigh = 1 - plow;
        let q, r;
        if (p < plow) {
            q = Math.sqrt(-2 * Math.log(p));
            return (((((c[0]*q+c[1])*q+c[2])*q+c[3])*q+c[4])*q+c[5]) / ((((d[0]*q+d[1])*q+d[2])*q+d[3])*q+1);
        }
        if (p <= phigh) {
            q = p - 0.5; r = q * q;
            return (((((a[0]*r+a[1])*r+a[2])*r+a[3])*r+a[4])*r+a[5])*q / (((((b[0]*r+b[1])*r+b[2])*r+b[3])*r+b[4])*r+1);
        }
        q = Math.sqrt(-2 * Math.log(1 - p));
        return -(((((c[0]*q+c[1])*q+c[2])*q+c[3])*q+c[4])*q+c[5]) / ((((d[0]*q+d[1])*q+d[2])*q+d[3])*q+1);
    }

    // Factor Z (2 decimales) para un nivel de servicio dado en %.
    function zDePct(pct) { return Math.round(probit(pct / 100) * 100) / 100; }

    // Refresca la lectura (label) y el hidden Z a partir del % del slider. El sufijo distingue
    // el modal de creación ('') del de edición ('_editar'); ambos comparten esta lógica.
    function actualizarNivelServicio(sufijo) {
        const s = sufijo || '';
        let pct = parseFloat($('#mrp_nivel_servicio' + s).val());
        if (isNaN(pct)) { pct = NS_DEFAULT_PCT; }
        const z = zDePct(pct);
        const pctTxt = (pct % 1 === 0) ? String(pct) : pct.toFixed(1);
        $('#mrp_nivel_servicio_out' + s).text(coma(pctTxt) + '% · Z ' + coma(z.toFixed(2)));
        $('#mrp_z_seguridad' + s).val(z);
    }
    $(document).on('input', '#mrp_nivel_servicio_editar', function() { actualizarNivelServicio('_editar'); });
    $(document).on('input', '#mrp_nivel_servicio',        function() { actualizarNivelServicio(''); });

    // Dado el Z guardado, ubica el % del slider cuyo Z esté más cerca (recorre la grilla de 0,5%).
    function pctPorZ(z) {
        const zz = parseFloat(z);
        if (isNaN(zz)) { return NS_DEFAULT_PCT; }
        let best = NS_DEFAULT_PCT, bestD = Infinity;
        for (let p = NS_MIN; p <= NS_MAX + 1e-9; p += NS_STEP) {
            const d = Math.abs(zDePct(p) - zz);
            if (d < bestD) { bestD = d; best = p; }
        }
        return best;
    }

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
                    // Nivel de servicio del MRP: ubica el slider en el % más cercano al Z guardado
                    // y sincroniza la lectura y el hidden Z.
                    $('#mrp_nivel_servicio_editar').val(pctPorZ(res.data.mrp_z_seguridad != null ? res.data.mrp_z_seguridad : 1.65));
                    actualizarNivelServicio();

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
