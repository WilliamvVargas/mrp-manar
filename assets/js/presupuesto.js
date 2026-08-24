$(document).ready(function() {

    // Render seguro para celdas que pueden venir en NULL.
    const renderTexto  = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderNumero = function(d) { return (d === null || d === undefined) ? '' : d; };

    // Estado del producto (SAP validFor): 'Y' = Activo, 'N' = Inactivo, otro = guion.
    const renderEstado = function(d) {
        const v = (d === null || d === undefined) ? '' : String(d).toUpperCase();
        if (v === 'Y') { return '<span class="badge bg-success">Activo</span>'; }
        if (v === 'N') { return '<span class="badge bg-secondary">Inactivo</span>'; }
        return '<span class="text-muted">—</span>';
    };

    // Formatea un número al estilo chileno: miles con punto y decimales con coma.
    // (Locale-independiente: no depende del soporte de Intl del navegador.)
    function formatearNumero(valor, decimales) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }
        const num = parseFloat(valor);
        if (isNaN(num)) {
            return '';
        }
        const partes = num.toFixed(decimales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');   // punto de miles
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    // Renders por tipo de medida.
    const renderMoneda     = function(d) { return formatearNumero(d, 0) === '' ? '' : '$' + formatearNumero(d, 0); };  // Venta / MG Neto / PP: a la unidad, con $
    const renderPorcentaje = function(d) { return formatearNumero(d, 1); };   // MG %: a la décima
    const renderDecimal4   = function(d) { return formatearNumero(d, 4); };   // KG: 4 decimales

    // Tabla de presupuesto (server-side). Vive en el archivo raíz del mantenedor (presupuesto.php).
    const tablaPresupuesto = inicializarTablaConsulta({
        tabla: '#tabla-consulta-presupuesto',
        url:   'controllers/presupuesto_controller.php?action=listar',
        extra: function(d) {
            d.version     = $('#filtro-version').val();       // '' = todas
            d.familia     = $('#filtro-familia').val();       // '' = todas
            d.sub_familia = $('#filtro-sub-familia').val();   // '' = todas
            d.anio        = $('#filtro-anio').val();          // '' = todos
            d.mes         = $('#filtro-mes').val();           // '' = todos
        },
        orden: [[0, 'desc']],   // por id, descendente
        columnas: [
            { data: 'id',            className: 'text-center' },
            { data: 'version',       className: 'text-center', render: renderTexto },
            { data: 'anio',          className: 'text-center', render: renderNumero },
            { data: 'mes',           className: 'text-center', render: renderNumero },
            { data: 'canal',         render: renderTexto },
            { data: 'sub_canal',     render: renderTexto },
            { data: 'familia',       render: renderTexto },
            { data: 'sub_familia',   render: renderTexto },
            { data: 'venta',         className: 'text-end', render: renderMoneda },
            { data: 'mg_porcentaje', className: 'text-end', render: renderPorcentaje },
            { data: 'mg_neto',       className: 'text-end', render: renderMoneda },
            { data: 'pp',            className: 'text-end', render: renderMoneda },
            { data: 'kg',            className: 'text-end', render: renderDecimal4 },
            {
                // Acciones: vista previa de los productos de la familia de la fila.
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    const fam     = (row.familia === null || row.familia === undefined) ? '' : String(row.familia);
                    const famAttr = $('<div>').text(fam).html().replace(/"/g, '&quot;');
                    return '<button type="button" class="btn btn-sm btn-outline-secondary btn-productos-familia" '
                         + 'data-familia="' + famAttr + '" '
                         + 'title="Ver productos de la familia">'
                         + '<i class="bi bi-eye"></i></button>';
                }
            }
        ]
    });

    // Recargar la tabla al cambiar el filtro de Versión, Familia, Sub-Familia, Año o Mes.
    $('#filtro-version, #filtro-familia, #filtro-sub-familia, #filtro-anio, #filtro-mes').on('change', function() {
        tablaPresupuesto.ajax.reload();
    });

    // Botón "Limpiar": deja todos los filtros por defecto y recarga (motor de utils.js).
    inicializarBotonLimpiar({
        boton:  '#btn-limpiar-filtros',
        tabla:  tablaPresupuesto,
        campos: ['#filtro-version', '#filtro-familia', '#filtro-sub-familia', '#filtro-anio', '#filtro-mes'],
        delay:  250
    });

    // La primera carga de filtros selecciona la última versión por defecto; las
    // siguientes conservan la selección del usuario (salvo que se pida una explícita).
    let primeraCargaFiltros = true;

    // Carga las opciones de los filtros (conservan la selección actual).
    // versionPreferida (opcional): versión a dejar seleccionada tras recargar (p. ej. la recién cargada).
    function cargarFiltros(versionPreferida) {
        $.ajax({
            url: 'controllers/presupuesto_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                // Versión: por defecto la más reciente (primera de la lista, que viene DESC).
                const $ver      = $('#filtro-version');
                const verSel    = $ver.val();
                const versiones = res.versiones || [];
                $ver.empty().append('<option value="">Todas</option>');
                versiones.forEach(function(v) {
                    $ver.append($('<option>').val(v).text(v));
                });

                let verElegida;
                if (versionPreferida && versiones.indexOf(versionPreferida) !== -1) {
                    verElegida = versionPreferida;                 // versión recién cargada
                } else if (primeraCargaFiltros && verSel === '' && versiones.length) {
                    verElegida = versiones[0];                     // primera carga: última versión
                } else {
                    verElegida = verSel;                           // conserva la selección
                }
                $ver.val(verElegida);
                primeraCargaFiltros = false;

                // Si la selección de versión cambió de forma programática, recarga la tabla
                // (el evento 'change' solo se dispara por interacción del usuario).
                if (verElegida !== verSel) {
                    tablaPresupuesto.ajax.reload();
                }

                // Familia (texto escapado vía .text()).
                const $fam   = $('#filtro-familia');
                const famSel = $fam.val();
                $fam.empty().append('<option value="">Todas</option>');
                (res.familias || []).forEach(function(f) {
                    $fam.append($('<option>').val(f).text(f));
                });
                $fam.val(famSel);

                // Sub-Familia (texto escapado).
                const $sub   = $('#filtro-sub-familia');
                const subSel = $sub.val();
                $sub.empty().append('<option value="">Todas</option>');
                (res.sub_familias || []).forEach(function(sf) {
                    $sub.append($('<option>').val(sf).text(sf));
                });
                $sub.val(subSel);

                // Año.
                const $anio   = $('#filtro-anio');
                const anioSel = $anio.val();
                const optsAnio = (res.anios || []).map(function(a) {
                    return '<option value="' + a + '">' + a + '</option>';
                }).join('');
                $anio.html('<option value="">Todos</option>' + optsAnio).val(anioSel);
            }
        });
    }
    cargarFiltros();

    // Carga masiva: solo se aceptan archivos Excel .xlsx
    $('#archivo-excel-presupuesto').on('change', function() {
        const $input  = $(this);
        const archivo = this.files[0];

        if (!archivo) {
            limpiarErrorCampo($input);
            return;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (extension !== 'xlsx') {
            $input.val('');   // descarta el archivo no válido
            $input.addClass('is-invalid');
            $('#error-archivo-presupuesto')
                  .html('Solo se permiten archivos Excel (.xlsx).')
                  .addClass('d-block');
        } else {
            limpiarErrorCampo($input);
            $('#form-carga-masiva-presupuesto').trigger('submit');
        }
    });

    // Carga masiva: envía el archivo por AJAX y muestra el resultado de la extracción
    $('#form-carga-masiva-presupuesto').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaMasivaPresupuesto');
        const modalMensaje = '#modal-mensajes-presupuesto';
        const $archivo     = $('#archivo-excel-presupuesto');

        // Debe haber un archivo seleccionado
        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo-presupuesto')
                    .html('Debe ingresar un archivo .xlsx.')
                    .addClass('d-block');
            return;
        }

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/presupuesto_controller.php?action=procesar',
            type: 'POST',
            data: new FormData(this),   // incluye el archivo y el csrf_token
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    // Recarga filtros y salta a la versión recién creada (que también recarga la tabla).
                    cargarFiltros(res.version);
                } else {
                    let msg = res.message;
                    if (res.errores && res.errores.length) {
                        msg += '<ul class="text-start small mb-0 mt-2">'
                             + res.errores.map(function(e) { return '<li>' + e + '</li>'; }).join('')
                             + '</ul>';
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', msg, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading(btn);
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                $archivo.val('');
            }
        });
    });

    // Descargar a Excel TODAS las filas que cumplen los filtros actuales (no solo la página
    // visible). Al ser DataTable server-side, la exportación la arma el servidor.
    $('#btn-descargar-excel').on('click', function() {
        // Evita generar un archivo vacío si el filtro no devuelve filas.
        const info = tablaPresupuesto.page.info();
        if (info && info.recordsDisplay === 0) {
            mostrarMensajeFormulario('#alert-container', 'Atención', 'No hay filas para exportar.', 'warning');
            return;
        }

        const version = $('#filtro-version').val() || '';
        const params  = new URLSearchParams({
            version:     version,
            familia:     $('#filtro-familia').val() || '',
            sub_familia: $('#filtro-sub-familia').val() || '',
            anio:        $('#filtro-anio').val() || '',
            mes:         $('#filtro-mes').val() || ''
        });

        const $btn = $(this);
        setBtnLoading($btn, 'Generando...');

        fetch('controllers/presupuesto_controller.php?action=exportar&' + params.toString())
            .then(function(r) { return r.ok ? r.blob() : Promise.reject(r); })
            .then(function(blob) {
                const url = URL.createObjectURL(blob);
                const a   = document.createElement('a');
                a.href = url;
                // Nombre: Presupuesto [versión] YYYY-MM-DD.xlsx
                const hoy   = new Date();
                const fecha = hoy.getFullYear() + '-'
                            + String(hoy.getMonth() + 1).padStart(2, '0') + '-'
                            + String(hoy.getDate()).padStart(2, '0');
                const base  = 'Presupuesto' + (version ? ' ' + version : '');
                a.download = base.replace(/[\/\\:*?"<>|]+/g, '_') + ' ' + fecha + '.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            })
            .catch(function(err) {
                if (err && typeof err.text === 'function') {
                    err.text().then(function(t) {
                        let msg = 'No se pudo generar el Excel.';
                        try { const j = JSON.parse(t); if (j && j.message) { msg = j.message; } } catch (e) {}
                        mostrarMensajeFormulario('#alert-container', 'Atención', msg, 'danger');
                    });
                } else {
                    mostrarMensajeFormulario('#alert-container', 'Atención', 'No se pudo generar el Excel.', 'danger');
                }
            })
            .finally(function() {
                resetBtnLoading($btn);
            });
    });

    // Acciones -> vista previa: productos de la familia de la fila (origen SAP).
    $('#tabla-consulta-presupuesto tbody').on('click', '.btn-productos-familia', function() {
        const familia = $(this).data('familia') || '';
        const $tbody  = $('#tabla-productos-familia tbody');

        $('#productos-familia-nombre').text(familia);
        $('#productos-familia-mensaje').empty();
        $tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">'
                  + '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPresupuestoProductos')).show();

        $.ajax({
            url: 'controllers/presupuesto_controller.php?action=productos',
            type: 'GET',
            data: { familia: familia },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $tbody.empty();
                    mostrarMensajeFormulario('#productos-familia-mensaje', 'Atención', res.message || 'No se pudo cargar.', 'danger');
                    return;
                }

                const filas = res.data || [];

                if (!filas.length) {
                    $tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">'
                              + 'Sin productos para esta familia.</td></tr>');
                    return;
                }

                $('#productos-familia-mensaje').html(
                    '<div class="small text-muted mb-2">' + filas.length + ' producto(s).</div>'
                );
                $tbody.html(filas.map(function(p) {
                    return '<tr><td>' + renderTexto(p.producto_codigo) + '</td>'
                         + '<td>' + renderTexto(p.producto_nombre) + '</td>'
                         + '<td class="text-center">' + renderEstado(p.producto_activo) + '</td></tr>';
                }).join(''));
            },
            error: function(jqXHR, textStatus) {
                $tbody.empty();
                manejarErrorAjax(jqXHR, textStatus, '#productos-familia-mensaje');
            }
        });
    });

});
