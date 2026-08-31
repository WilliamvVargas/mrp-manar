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
                data: 'modo_transporte',
                className: 'text-center',
                orderable: false,
                render: function(v) {
                    return v ? $('<div>').text(v).html() : '<span class="text-muted">—</span>';
                }
            }
        ]
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
