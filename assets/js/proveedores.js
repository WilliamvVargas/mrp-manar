$(document).ready(function() {

    // Tabla de proveedores (solo consulta): datos desde SAP (OCRD) de la empresa activa.
    // El modo de transporte aún no se registra, así que se muestra en blanco.
    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/proveedores_controller.php?action=listar',
        input: '#consulta',
        orden: [[1, 'asc']],   // por nombre ascendente
        extra: function(d) {
            d.pais = $('#filtro-pais').val();   // filtro por país (código ISO2)
        },
        columnas: [
            { data: 'codigo',    className: 'text-center', render: $.fn.dataTable.render.text() },
            { data: 'nombre',    render: $.fn.dataTable.render.text() },
            {
                // País: bandera (flag-icons, por código ISO2) + nombre resuelto.
                data: 'pais',
                className: 'text-center',
                render: function(nombre, type, fila) {
                    const nom = (nombre == null) ? '' : $('<div>').text(nombre).html();
                    if (type !== 'display') { return nom; }
                    const cod = String(fila.pais_codigo || '').toLowerCase();
                    const bandera = /^[a-z]{2}$/.test(cod) ? '<span class="fi fi-' + cod + ' me-1"></span>' : '';
                    return nom ? (bandera + nom) : '';
                }
            },
            { data: 'direccion', render: $.fn.dataTable.render.text() },
            {
                // Lead Time real: mediana de días + N° de recepciones; promedio en el tooltip.
                data: 'lead_mediana',
                className: 'text-center',
                render: function(mediana, type, fila) {
                    if (type !== 'display') { return (mediana == null) ? -1 : mediana; }
                    if (mediana == null) { return '<span class="text-muted">—</span>'; }
                    const n = fila.lead_recep || 0;
                    const prom = (fila.lead_promedio == null) ? '—' : fila.lead_promedio;
                    return '<span title="Promedio: ' + prom + ' días · ' + n + ' recepciones">'
                         + '<span class="fw-bold">' + mediana + '</span> d '
                         + '<small class="text-muted">(n=' + n + ')</small></span>';
                }
            },
            {
                data: 'modo_transporte',
                className: 'text-center',
                orderable: false,
                render: function(v) {
                    return v ? $('<div>').text(v).html() : '<span class="text-muted">—</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(fila) {
                    return '<button type="button" class="btn btn-sm btn-outline-dark btn-detalle-oc" '
                         + 'data-codigo="' + $('<div>').text(fila.codigo).html().replace(/"/g, '&quot;') + '" '
                         + 'data-nombre="' + $('<div>').text(fila.nombre).html().replace(/"/g, '&quot;') + '" '
                         + 'title="Ver detalle de OC"><i class="bi bi-receipt"></i></button>';
                }
            }
        ]
    });

    // Fecha 'yyyy-mm-dd' -> 'dd-mm-yyyy'.
    function fmtFecha(s) {
        if (!s) { return ''; }
        const p = String(s).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : s;
    }

    // Entero con separador de miles (estilo chileno).
    function fmtEntero(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        const n = parseFloat(v);
        return isNaN(n) ? '' : String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    const esc = function(v) { return (v === null || v === undefined) ? '' : $('<div>').text(v).html(); };

    // Botón "Ver detalle de OC": abre el modal y carga las recepciones del proveedor.
    $('#tabla-consulta tbody').on('click', '.btn-detalle-oc', function() {
        const codigo = $(this).data('codigo');
        const nombre = $(this).data('nombre') || '';

        $('#prov-oc-titulo').text('— ' + nombre);
        $('#prov-oc-estado').text('Cargando...').show();
        $('#prov-oc-wrap').hide();
        $('#tabla-prov-oc').empty();
        $('#prov-oc-resumen').text('');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedorOc')).show();

        $.ajax({
            url: 'controllers/proveedores_controller.php?action=detalle_oc',
            type: 'GET',
            data: { codigo: codigo },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $('#prov-oc-estado').text(res.message || 'No se pudo cargar el detalle.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $('#prov-oc-estado').text('Este proveedor no tiene OC recibidas en el historial.').show();
                    return;
                }
                let html = '', suma = 0;
                filas.forEach(function(r) {
                    suma += parseInt(r.Dias, 10) || 0;
                    const via = (r.Via === 'Factura de Reserva')
                        ? '<span class="badge bg-warning text-dark">Fact. Reserva</span>'
                        : '<span class="badge bg-secondary">Directa</span>';
                    html += '<tr>'
                         + '<td class="text-center">' + esc(r.OrdenCompra) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaOC) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaRecepcion) + '</td>'
                         + '<td class="text-end fw-bold">' + esc(r.Dias) + '</td>'
                         + '<td class="text-center">' + via + '</td>'
                         + '<td>' + esc(r.CodArticulo) + '</td>'
                         + '<td>' + esc(r.Articulo) + '</td>'
                         + '<td class="text-end">' + fmtEntero(r.Cantidad) + '</td>'
                         + '<td class="text-center">' + esc(r.Entrada) + '</td>'
                         + '</tr>';
                });
                $('#tabla-prov-oc').html(html);
                const prom = Math.round(suma / filas.length);
                $('#prov-oc-resumen').text(filas.length + ' recepciones · promedio ' + prom + ' días');
                $('#prov-oc-estado').hide();
                $('#prov-oc-wrap').show();
            },
            error: function() {
                $('#prov-oc-estado').text('Error al cargar el detalle de OC.').show();
            }
        });
    });

    // HTML de la bandera (flag-icons) para un código ISO2, o vacío si no es válido.
    function banderaHtml(cod) {
        const c = String(cod || '').toLowerCase();
        return /^[a-z]{2}$/.test(c) ? '<span class="fi fi-' + c + ' me-1"></span>' : '';
    }

    // Opciones del filtro País (dropdown de Bootstrap): solo los países presentes en la lista,
    // cada uno con su bandera.
    $.ajax({
        url: 'controllers/proveedores_controller.php?action=paises',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            const $menu = $('#filtro-pais-menu');
            (res.data || []).forEach(function(p) {
                const nombre = $('<div>').text(p.nombre).html();
                $menu.append(
                    '<li><a class="dropdown-item d-flex align-items-center" href="#" '
                    + 'data-codigo="' + $('<div>').text(p.codigo).html().replace(/"/g, '&quot;') + '" '
                    + 'data-nombre="' + nombre.replace(/"/g, '&quot;') + '">'
                    + banderaHtml(p.codigo) + nombre + '</a></li>'
                );
            });
        }
    });

    // Selección de un país en el dropdown: fija el código, actualiza la etiqueta y recarga.
    $('#filtro-pais-menu').on('click', '.dropdown-item', function(e) {
        e.preventDefault();
        const codigo = $(this).data('codigo') || '';
        const nombre = $(this).data('nombre') || 'Todos';
        $('#filtro-pais').val(codigo);
        $('#filtro-pais-label').html(codigo ? (banderaHtml(codigo) + $('<div>').text(nombre).html()) : 'Todos');
        tablaConsulta.ajax.reload();
    });

    // Botón "Limpiar": vacía buscador y país, restablece la etiqueta y recarga.
    $('#btn-limpiar-filtros').on('click', function() {
        $('#consulta').val('');
        $('#filtro-pais').val('');
        $('#filtro-pais-label').text('Todos');
        tablaConsulta.ajax.reload();
    });

});
