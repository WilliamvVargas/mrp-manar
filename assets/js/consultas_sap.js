$(document).ready(function() {

    // ============================================================
    //  Consultas SAP — carga de consultas a SQL Server en el DataTable.
    //  Cada botón (ODV, OC, Stock) define su propio endpoint y columnas;
    //  el motor reconstruye la tabla con la consulta seleccionada.
    // ============================================================

    // Número estilo chileno: miles con punto y decimales con coma.
    function formatearNumero(valor, decimales) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }
        const num = parseFloat(valor);
        if (isNaN(num)) {
            return '';
        }
        const partes = num.toFixed(decimales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    const renderTexto  = function(d) { return (d === null || d === undefined) ? '' : $('<div>').text(d).html(); };
    const renderNumero = function(d) { return (d === null || d === undefined || d === '') ? '' : d; };

    // Cantidad: entero si es exacto, con 2 decimales si es fraccionario.
    function renderCantidad(d) {
        if (d === null || d === undefined || d === '') {
            return '';
        }
        const n = parseFloat(d);
        if (isNaN(n)) {
            return '';
        }
        return formatearNumero(n, n % 1 === 0 ? 0 : 2);
    }

    // Fecha SQL Server ('YYYY-MM-DD' o 'YYYY-MM-DD HH:MM:SS...') -> 'DD-MM-YYYY'.
    function renderFecha(d) {
        if (!d) {
            return '';
        }
        const p = String(d).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : d;
    }

    // ============================================================
    //  Definición de cada consulta: endpoint + columnas del DataTable.
    // ============================================================

    const CONSULTAS = {
        odv: {
            url: 'controllers/consultas_sap_controller.php?action=odv',
            titulo: '<i class="bi bi-cart-check me-2"></i>Consulta ODV — Órdenes de Venta',
            columnas: [
                { data: 'OrdenVenta',                    title: 'Orden Venta',     className: 'text-center', render: renderNumero },
                { data: 'FechaOV',                       title: 'Fecha OV',        className: 'text-center', render: renderFecha },
                { data: 'CodCliente',                    title: 'Cód. Cliente',    render: renderTexto },
                { data: 'Cliente',                       title: 'Cliente',         render: renderTexto },
                { data: 'LineaOV',                       title: 'Línea',           className: 'text-center', render: renderNumero },
                { data: 'CodArticulo',                   title: 'Cód. Artículo',   render: renderTexto },
                { data: 'Articulo',                      title: 'Artículo',        render: renderTexto },
                { data: 'Almacen',                       title: 'Almacén',         className: 'text-center', render: renderTexto },
                { data: 'CantidadOrdenada',              title: 'Cant. Ordenada',  className: 'text-end', render: renderCantidad },
                { data: 'CantidadPendienteDespacho',     title: 'Cant. Pendiente', className: 'text-end', render: renderCantidad },
                { data: 'EntregaRelacionada',            title: 'Entrega',         className: 'text-center', render: renderNumero },
                { data: 'CantidadDespachadaRelacionada', title: 'Cant. Despachada',className: 'text-end', render: renderCantidad }
            ]
        },
        oc: {
            url: 'controllers/consultas_sap_controller.php?action=oc',
            titulo: '<i class="bi bi-bag-check me-2"></i>Consulta OC — Órdenes de Compra',
            columnas: [
                { data: 'OrdenCompra',                  title: 'Orden Compra',    className: 'text-center', render: renderNumero },
                { data: 'FechaOC',                      title: 'Fecha OC',        className: 'text-center', render: renderFecha },
                { data: 'CodProveedor',                 title: 'Cód. Proveedor',  render: renderTexto },
                { data: 'Proveedor',                    title: 'Proveedor',       render: renderTexto },
                { data: 'LineaOC',                      title: 'Línea',           className: 'text-center', render: renderNumero },
                { data: 'CodArticulo',                  title: 'Cód. Artículo',   render: renderTexto },
                { data: 'Articulo',                     title: 'Artículo',        render: renderTexto },
                { data: 'Almacen',                      title: 'Almacén',         className: 'text-center', render: renderTexto },
                { data: 'CantidadOrdenada',             title: 'Cant. Ordenada',  className: 'text-end', render: renderCantidad },
                { data: 'CantidadPendienteRecepcion',   title: 'Cant. Pendiente', className: 'text-end', render: renderCantidad },
                { data: 'EntradaMercanciaRelacionada',  title: 'Entrada Merc.',   className: 'text-center', render: renderNumero },
                { data: 'CantidadRecibidaRelacionada',  title: 'Cant. Recibida',  className: 'text-end', render: renderCantidad }
            ]
        },
        stock: {
            url: 'controllers/consultas_sap_controller.php?action=stock',
            titulo: '<i class="bi bi-boxes me-2"></i>Consulta Stock — Existencias por Bodega',
            columnas: [
                { data: 'CodigoArticulo',   title: 'Cód. Artículo', render: renderTexto },
                { data: 'NombreArticulo',   title: 'Artículo',      render: renderTexto },
                { data: 'UnidadInventario', title: 'Unidad',        className: 'text-center', render: renderTexto },
                { data: 'CodigoBodega',     title: 'Cód. Bodega',   className: 'text-center', render: renderTexto },
                { data: 'NombreBodega',     title: 'Bodega',        render: renderTexto },
                { data: 'StockActual',      title: 'Stock Actual',  className: 'text-end', render: renderCantidad },
                { data: 'Comprometido',     title: 'Comprometido',  className: 'text-end', render: renderCantidad },
                { data: 'Pedido',           title: 'Pedido',        className: 'text-end', render: renderCantidad },
                { data: 'StockMinimo',      title: 'Stock Mín.',    className: 'text-end', render: renderCantidad },
                { data: 'StockMaximo',      title: 'Stock Máx.',    className: 'text-end', render: renderCantidad }
            ]
        }
    };

    let tabla       = null;   // instancia actual de DataTable
    let columnasAct = [];     // columnas de la consulta cargada (para el detalle)
    let filasAct    = [];     // datos crudos de la consulta cargada (para el detalle)

    // ============================================================
    //  Motor de carga de consultas
    // ============================================================

    // Construye el <thead> según las columnas (más la columna de acciones).
    function construirCabecera(columnas) {
        const ths = columnas.map(function(c) {
            return '<th class="' + (c.className || '') + '">' + c.title + '</th>';
        }).join('');
        $('#tabla-consulta thead').html('<tr>' + ths + '<th class="text-center">Acciones</th></tr>');
    }

    // Columna de acciones (botón "Ver detalle"); el índice de la fila viaja en data-fila.
    function columnaAcciones() {
        return {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function(data, type, row, meta) {
                return '<button type="button" class="btn btn-sm btn-outline-secondary btn-ver-consulta" '
                     + 'data-fila="' + meta.row + '" title="Ver detalle"><i class="bi bi-eye"></i></button>';
            }
        };
    }

    // Carga una consulta: pide los datos al controlador y (re)dibuja la tabla.
    function cargarConsulta(clave, $boton) {
        const cfg = CONSULTAS[clave];
        if (!cfg) {
            return;
        }

        setBtnLoading($boton, 'Cargando...');

        $.ajax({
            url: cfg.url,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    mostrarMensajeFormulario('#alert-container', 'Atención', res.message || 'No se pudo cargar la consulta.', 'danger');
                    return;
                }

                $('#consulta-sap-placeholder').hide();
                $('#alert-container').empty();
                $('#consulta-sap-titulo').html(cfg.titulo).removeClass('d-none');

                columnasAct = cfg.columnas;
                filasAct    = res.data || [];

                // Reinicia la tabla previa antes de cambiar las columnas.
                if (tabla) {
                    tabla.destroy();
                    $('#tabla-consulta tbody').empty();
                }

                construirCabecera(cfg.columnas);

                tabla = $('#tabla-consulta').DataTable({
                    data: filasAct,
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row'<'col-sm-12'p>>",
                    autoWidth: false,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                    },
                    columns: cfg.columnas.concat([columnaAcciones()])
                });

                // El buscador propio aplica sobre la tabla recién cargada.
                $('#consulta').val('');
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, '#alert-container');
            },
            complete: function() {
                resetBtnLoading($boton);
            }
        });
    }

    // ============================================================
    //  Botones de consulta
    // ============================================================

    $('#btn-consulta-odv').on('click', function() {
        cargarConsulta('odv', $(this));
    });

    $('#btn-consulta-oc').on('click', function() {
        cargarConsulta('oc', $(this));
    });

    $('#btn-consulta-stock').on('click', function() {
        cargarConsulta('stock', $(this));
    });

    // Buscador propio con debounce → busca en toda la tabla (client-side).
    $('#consulta').on('input', debounce(function() {
        if (tabla) {
            tabla.search($(this).val()).draw();
        }
    }, 400));

    // ============================================================
    //  Ver detalle (modal con todos los campos de la fila)
    // ============================================================

    // Etiqueta legible a partir del título de columna; si el campo no es columna
    // visible (campos técnicos), usa el propio nombre del campo.
    function etiquetaCampo(campo) {
        const col = columnasAct.find(function(c) { return c.data === campo; });
        return col ? col.title : campo;
    }

    // Arma las filas Campo | Valor del detalle con todos los campos de la fila.
    function filasDetalle(registro) {
        return Object.keys(registro).map(function(campo) {
            const valor = registro[campo];
            const texto = (valor === null || valor === undefined || valor === '')
                ? '<span class="text-muted">—</span>'
                : $('<div>').text(valor).html();
            return '<tr>'
                 + '<th class="fw-semibold small text-nowrap" style="width: 40%;">' + etiquetaCampo(campo) + '</th>'
                 + '<td class="small">' + texto + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Botón "Ver detalle": resuelve la fila por su índice y abre el modal.
    $('#tabla-consulta tbody').on('click', '.btn-ver-consulta', function() {
        const idx      = $(this).data('fila');
        const registro = filasAct[idx];
        const $tbody   = $('#tabla-detalle-consulta-sap');

        if (registro) {
            $tbody.html(filasDetalle(registro));
        } else {
            $tbody.html('<tr><td class="text-danger small">No se encontró el registro.</td></tr>');
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaSapDetalle')).show();
    });

    // Carga inicial: al entrar al mantenedor se muestra la consulta ODV por defecto.
    cargarConsulta('odv', $('#btn-consulta-odv'));

});
