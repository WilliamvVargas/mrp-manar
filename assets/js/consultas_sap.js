$(document).ready(function() {

    // ============================================================
    //  Consultas SAP — carga de consultas a SQL Server en el DataTable.
    //  Cada botón (ODV, OC, Stock) define su propio endpoint y columnas;
    //  el motor reconstruye la tabla con la consulta seleccionada.
    // ============================================================

    // Google Charts: carga diferida. Se encola el dibujo hasta que esté listo.
    let chartsListo    = false;
    let chartPendiente = null;
    google.charts.load('current', { packages: ['corechart'] });
    google.charts.setOnLoadCallback(function() {
        chartsListo = true;
        if (chartPendiente) { chartPendiente(); chartPendiente = null; }
    });
    function cuandoChartsListo(fn) {
        if (chartsListo) { fn(); } else { chartPendiente = fn; }
    }

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

    // Monto: siempre con 2 decimales (soporta negativos de las notas de crédito).
    function renderMonto(d) {
        return (d === null || d === undefined || d === '') ? '' : formatearNumero(d, 2);
    }

    // Porcentaje: 2 decimales con símbolo (ej. 15,00%).
    function renderPorcentaje(d) {
        return (d === null || d === undefined || d === '') ? '' : formatearNumero(d, 2) + '%';
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
        facs_ncs: {
            url: 'controllers/consultas_sap_controller.php?action=facs_ncs',
            titulo: '<i class="bi bi-receipt me-2"></i>Consulta Facturas y Notas de Crédito',
            filtroFecha: true,
            verLineas: true,
            totales: ['Neto', 'Impuesto', 'Total'],
            columnas: [
                { data: 'DocEntry',         title: 'DocEntry (SAP)', className: 'text-center', render: renderNumero },
                { data: 'TipoDoc',          title: 'Tipo Doc.',      className: 'text-center', render: renderTexto },
                { data: 'NumDoc',           title: 'N° Doc.',        className: 'text-center', render: renderNumero },
                { data: 'FechaDocumento',   title: 'Fecha Documento',className: 'text-center', render: renderFecha },
                { data: 'CodCliente',       title: 'Cód. Cliente',   render: renderTexto },
                { data: 'Cliente',          title: 'Cliente',        render: renderTexto },
                { data: 'Neto',             title: 'Neto',           className: 'text-end', render: renderMonto },
                { data: 'Impuesto',         title: 'Impuesto',       className: 'text-end', render: renderMonto },
                { data: 'Total',            title: 'Total',          className: 'text-end', render: renderMonto },
                { data: 'Estado',           title: 'Estado',         className: 'text-center', render: renderTexto }
            ]
        },
        facs_ncs_v2: {
            url: 'controllers/consultas_sap_controller.php?action=facs_ncs_v2',
            titulo: '<i class="bi bi-receipt-cutoff me-2"></i>Consulta Facturas y Notas de Crédito v2 — Líneas',
            filtroFecha: true,
            filtros: ['tipo', 'familia', 'subfamilia'],
            totales: ['TotalNeto', 'IvaMonto', 'TotalBruto'],
            columnas: [
                { data: 'DocEntry',       title: 'DocEntry (SAP)', className: 'text-center', render: renderNumero },
                { data: 'TipoDoc',        title: 'Tipo Doc.',      className: 'text-center', render: renderTexto },
                { data: 'NumDoc',         title: 'N° Doc.',        className: 'text-center', render: renderNumero },
                { data: 'FechaDocumento', title: 'Fecha Documento',className: 'text-center', render: renderFecha },
                { data: 'Cliente',        title: 'Cliente',        render: renderTexto },
                { data: 'Linea',          title: 'Línea',          className: 'text-center', render: renderNumero },
                { data: 'CodArticulo',    title: 'Cód. Artículo',  render: renderTexto },
                { data: 'Articulo',       title: 'Artículo',       render: renderTexto },
                { data: 'Familia',        title: 'Familia',        render: renderTexto },
                { data: 'SubFamilia',     title: 'Sub-Familia',    render: renderTexto },
                { data: 'Unidad',         title: 'Unidad',         className: 'text-center', render: renderTexto },
                { data: 'Cantidad',       title: 'Cantidad',       className: 'text-end', render: renderCantidad },
                { data: 'PrecioSinDesc',  title: 'Precio s/Desc.', className: 'text-end', render: renderMonto },
                { data: 'PctDescuento',   title: '% Desc.',        className: 'text-end', render: renderPorcentaje },
                { data: 'PrecioUnitario', title: 'Precio Unit.',   className: 'text-end', render: renderMonto },
                { data: 'TotalNeto',      title: 'Total Neto',     className: 'text-end', render: renderMonto },
                { data: 'PctIVA',         title: '% IVA',          className: 'text-end', render: renderPorcentaje },
                { data: 'IvaMonto',       title: 'IVA ($)',        className: 'text-end', render: renderMonto },
                { data: 'TotalBruto',     title: 'Total Bruto',    className: 'text-end', render: renderMonto }
            ]
        },
        facs_ncs_v3: {
            url: 'controllers/consultas_sap_controller.php?action=facs_ncs_v3',
            titulo: '<i class="bi bi-collection me-2"></i>Consulta Facturas y Notas de Crédito v3 — Agrupado por artículo',
            filtroFecha: true,
            etiquetaFecha: 'Año-Mes',
            filtros: ['familia', 'subfamilia'],
            verDocumentos: true,
            verGrafico: true,
            totales: ['Cantidad', 'TotalNeto', 'IvaMonto', 'TotalBruto'],
            columnas: [
                { data: 'FechaDocumento', title: 'Año-Mes',        className: 'text-center', render: renderTexto },
                { data: 'CodArticulo',    title: 'Cód. Artículo',  render: renderTexto },
                { data: 'Articulo',       title: 'Artículo',       render: renderTexto },
                { data: 'Familia',        title: 'Familia',        render: renderTexto },
                { data: 'SubFamilia',     title: 'Sub-Familia',    render: renderTexto },
                { data: 'Cantidad',       title: 'Cantidad',       className: 'text-end', render: renderCantidad },
                { data: 'PrecioSinDesc',  title: 'Precio s/Desc.', className: 'text-end', render: renderMonto },
                { data: 'TotalNeto',      title: 'Total Neto',     className: 'text-end', render: renderMonto },
                { data: 'IvaMonto',       title: 'IVA ($)',        className: 'text-end', render: renderMonto },
                { data: 'TotalBruto',     title: 'Total Bruto',    className: 'text-end', render: renderMonto }
            ]
        },
        facs_ncs_v4: {
            url: 'controllers/consultas_sap_controller.php?action=facs_ncs_v4',
            titulo: '<i class="bi bi-diagram-3 me-2"></i>Consulta Facturas y Notas de Crédito v4 — Agrupado por familia',
            filtroFecha: true,
            etiquetaFecha: 'Año-Mes',
            filtros: ['familia'],
            totales: ['Cantidad', 'TotalNeto', 'IvaMonto', 'TotalBruto'],
            columnas: [
                { data: 'FechaDocumento', title: 'Año-Mes',     className: 'text-center', render: renderTexto },
                { data: 'Familia',        title: 'Familia',     render: renderTexto },
                { data: 'Cantidad',       title: 'Cantidad',    className: 'text-end', render: renderCantidad },
                { data: 'TotalNeto',      title: 'Total Neto',  className: 'text-end', render: renderMonto },
                { data: 'IvaMonto',       title: 'IVA ($)',     className: 'text-end', render: renderMonto },
                { data: 'TotalBruto',     title: 'Total Bruto', className: 'text-end', render: renderMonto }
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
    let claveActual = null;   // clave de la consulta cargada (para recargar al filtrar)
    let botonActual = null;   // botón que disparó la consulta actual
    let filtrosActivos   = [];      // lista de filtros client-side activos (tipo/familia/subfamilia)
    let filtrosV2Activos = false;   // true cuando hay algún filtro client-side activo

    // Rango de Fecha Documento (mes/año) como límites 'YYYY-MM-DD'; los setea Flatpickr.
    const anioActual = new Date().getFullYear();
    let filtroDesde  = anioActual + '-01-01';   // por defecto: enero del año actual
    let filtroHasta  = '';                       // sin límite superior

    // Primer día del mes de una fecha -> 'YYYY-MM-01'.
    function primerDiaMes(fecha) {
        const mm = String(fecha.getMonth() + 1).padStart(2, '0');
        return fecha.getFullYear() + '-' + mm + '-01';
    }

    // Último día del mes de una fecha -> 'YYYY-MM-DD'.
    function ultimoDiaMes(fecha) {
        const ultimo = new Date(fecha.getFullYear(), fecha.getMonth() + 1, 0);
        const mm = String(ultimo.getMonth() + 1).padStart(2, '0');
        const dd = String(ultimo.getDate()).padStart(2, '0');
        return ultimo.getFullYear() + '-' + mm + '-' + dd;
    }

    // Arma la URL de la consulta agregando el rango mes/año (como límites de día) si aplica.
    function construirUrl(cfg) {
        let url = cfg.url;
        if (cfg.filtroFecha) {
            if (filtroDesde) { url += '&desde=' + encodeURIComponent(filtroDesde); }
            if (filtroHasta) { url += '&hasta=' + encodeURIComponent(filtroHasta); }
        }
        return url;
    }

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

    // Construye el <tfoot> (una celda por columna + acciones) para los totales; vacío si no aplica.
    function construirPie(columnas, cfg) {
        if (!cfg.totales) {
            $('#tabla-consulta tfoot').empty();
            return;
        }
        const ths = columnas.map(function(c) {
            return '<th class="' + (c.className || '') + '"></th>';
        }).join('');
        $('#tabla-consulta tfoot').html('<tr>' + ths + '<th></th></tr>');
    }

    // Columna de acciones (botón "Ver detalle" y, si la consulta lo soporta, "Ver líneas");
    // el índice de la fila viaja en data-fila.
    function columnaAcciones(cfg) {
        return {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center text-nowrap',
            render: function(data, type, row, meta) {
                let botones = '<button type="button" class="btn btn-sm btn-outline-dark btn-ver-consulta" '
                            + 'data-fila="' + meta.row + '" title="Ver detalle"><i class="bi bi-eye"></i></button>';

                if (cfg.verLineas) {
                    botones += ' <button type="button" class="btn btn-sm btn-outline-dark btn-ver-lineas" '
                             + 'data-fila="' + meta.row + '" title="Ver líneas"><i class="bi bi-list-ul"></i></button>';
                }

                if (cfg.verDocumentos) {
                    botones += ' <button type="button" class="btn btn-sm btn-outline-dark btn-ver-documentos" '
                             + 'data-fila="' + meta.row + '" title="Ver documentos"><i class="bi bi-files"></i></button>';
                }

                if (cfg.verGrafico) {
                    botones += ' <button type="button" class="btn btn-sm btn-outline-dark btn-grafico-producto" '
                             + 'data-fila="' + meta.row + '" title="Gráfico producto"><i class="bi bi-graph-up"></i></button>';
                }

                return botones;
            }
        };
    }

    // Carga una consulta: pide los datos al controlador y (re)dibuja la tabla.
    function cargarConsulta(clave, $boton) {
        const cfg = CONSULTAS[clave];
        if (!cfg) {
            return;
        }

        claveActual = clave;
        botonActual = $boton;

        // Muestra el filtro de fecha y cada filtro client-side según lo que declare la consulta.
        filtrosActivos   = cfg.filtros || [];
        filtrosV2Activos = filtrosActivos.length > 0;
        $('.filtro-fecha-facs').toggleClass('d-none', !cfg.filtroFecha);

        // Etiqueta del filtro de fecha según la consulta (por defecto "Fecha Doc.").
        const etFecha = cfg.etiquetaFecha;
        $('label[for="facs-fecha-desde"]').text(etFecha ? etFecha + ' desde' : 'Fecha Doc. desde (mes/año)');
        $('label[for="facs-fecha-hasta"]').text(etFecha ? etFecha + ' hasta' : 'Fecha Doc. hasta (mes/año)');

        $('.filtro-item-tipo').toggleClass('d-none', filtrosActivos.indexOf('tipo') < 0);
        $('.filtro-item-familia').toggleClass('d-none', filtrosActivos.indexOf('familia') < 0);
        $('.filtro-item-subfamilia').toggleClass('d-none', filtrosActivos.indexOf('subfamilia') < 0);
        $('.filtro-item-limpiar').toggleClass('d-none', filtrosActivos.length === 0);

        setBtnLoading($boton, 'Cargando...');

        $.ajax({
            url: construirUrl(cfg),
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
                construirPie(cfg.columnas, cfg);

                tabla = $('#tabla-consulta').DataTable({
                    data: filasAct,
                    dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'i>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row'<'col-sm-12'p>>",
                    autoWidth: false,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                    },
                    columns: cfg.columnas.concat([columnaAcciones(cfg)]),
                    // Fila de totales: suma las columnas indicadas en cfg.totales sobre TODAS
                    // las filas que pasan el buscador (todas las páginas).
                    footerCallback: function(row, data, start, end, display) {
                        if (!cfg.totales) {
                            return;
                        }
                        const api   = this.api();
                        const toNum = function(v) {
                            const n = parseFloat(v);
                            return isNaN(n) ? 0 : n;
                        };

                        cfg.columnas.forEach(function(col, idx) {
                            if (cfg.totales.indexOf(col.data) !== -1) {
                                let suma = 0;
                                api.column(idx, { search: 'applied', page: 'all' }).data().each(function(v) {
                                    suma += toNum(v);
                                });
                                // Cada total usa el formato de su propia columna (cantidad, monto, etc.).
                                const formato = col.render || renderMonto;
                                $(api.column(idx).footer()).html(formato(suma));
                            }
                        });

                        $(api.column(0).footer()).html('Total');
                    }
                });

                // El buscador propio aplica sobre la tabla recién cargada.
                $('#consulta').val('');

                // Llena los selects de Familia/Sub-Familia con los valores recién cargados.
                if ((cfg.filtros || []).length) {
                    poblarFiltrosV2();
                }
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

    $('#btn-consulta-facs-ncs').on('click', function() {
        cargarConsulta('facs_ncs', $(this));
    });

    $('#btn-consulta-facs-ncs-v2').on('click', function() {
        cargarConsulta('facs_ncs_v2', $(this));
    });

    $('#btn-consulta-facs-ncs-v3').on('click', function() {
        cargarConsulta('facs_ncs_v3', $(this));
    });

    $('#btn-consulta-facs-ncs-v4').on('click', function() {
        cargarConsulta('facs_ncs_v4', $(this));
    });

    // Recarga la consulta activa (si soporta filtro por fecha) tras cambiar el rango mes/año.
    function recargarPorFiltroFecha() {
        const cfg = claveActual ? CONSULTAS[claveActual] : null;
        if (cfg && cfg.filtroFecha) {
            cargarConsulta(claveActual, botonActual || $('#btn-consulta-facs-ncs'));
        }
    }

    // Opción "Quitar filtro" al pie del calendario (como en Ventas Históricas).
    function agregarQuitarFiltro(selectedDates, dateStr, instance) {
        const $limpiar = $('<div class="text-center small text-primary border-top py-1" style="cursor: pointer;">Quitar filtro</div>');
        $limpiar.on('click', function() {
            instance.clear();   // dispara onChange -> limpia el límite y recarga
            instance.close();
        });
        $(instance.calendarContainer).append($limpiar);
    }

    // Filtro Fecha Documento por mes/año (Flatpickr + plugin monthSelect).
    const fpFacsDesde = flatpickr('#facs-fecha-desde', {
        locale: 'es',
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
        defaultDate: new Date(anioActual, 0, 1),   // enero del año actual
        onChange: function(fechas) {
            filtroDesde = fechas.length ? primerDiaMes(fechas[0]) : '';
            recargarPorFiltroFecha();
        },
        onReady: agregarQuitarFiltro
    });

    const fpFacsHasta = flatpickr('#facs-fecha-hasta', {
        locale: 'es',
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
        onChange: function(fechas) {
            filtroHasta = fechas.length ? ultimoDiaMes(fechas[0]) : '';
            recargarPorFiltroFecha();
        },
        onReady: agregarQuitarFiltro
    });

    // ============================================================
    //  Filtros de la consulta v2 (Tipo Doc., Familia, Sub-Familia) + Limpiar
    // ============================================================

    // Valores distintos de un campo en las filas cargadas; si se pasa 'familia',
    // solo considera las filas de esa familia (para la cascada Familia -> Sub-Familia).
    function distintosV2(campo, familia) {
        const set = {};
        filasAct.forEach(function(r) {
            if (familia && (r.Familia || '') !== familia) { return; }
            const v = r[campo];
            if (v !== null && v !== undefined && v !== '') { set[v] = true; }
        });
        return Object.keys(set).sort();
    }

    // Rellena un select conservando la selección SOLO si sigue disponible.
    function llenarSelectV2(selector, valores) {
        const $sel   = $(selector);
        const actual = $sel.val();
        $sel.empty().append('<option value="">Todas</option>');
        valores.forEach(function(v) { $sel.append($('<option>').val(v).text(v)); });
        $sel.val(valores.indexOf(actual) !== -1 ? actual : '');
    }

    // Repuebla Sub-Familia según la Familia elegida (cascada).
    function poblarSubFamiliasV2() {
        llenarSelectV2('#filtro-v2-subfamilia', distintosV2('SubFamilia', $('#filtro-v2-familia').val()));
    }

    // Llena los selects de Familia y Sub-Familia con los valores ya cargados. Tipo Doc. es fijo.
    function poblarFiltrosV2() {
        llenarSelectV2('#filtro-v2-familia', distintosV2('Familia', ''));
        poblarSubFamiliasV2();
    }

    // Filtro client-side por Tipo Doc. / Familia / Sub-Familia (solo cuando la v2 está activa).
    $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
        if (!filtrosV2Activos || settings.nTable.id !== 'tabla-consulta') {
            return true;
        }
        const fila = new $.fn.dataTable.Api(settings).row(dataIndex).data();
        if (!fila) { return true; }

        if (filtrosActivos.indexOf('tipo') >= 0) {
            const tipo = $('#filtro-v2-tipo').val();
            if (tipo && fila.TipoDoc !== tipo) { return false; }
        }
        if (filtrosActivos.indexOf('familia') >= 0) {
            const fam = $('#filtro-v2-familia').val();
            if (fam && (fila.Familia || '') !== fam) { return false; }
        }
        if (filtrosActivos.indexOf('subfamilia') >= 0) {
            const sub = $('#filtro-v2-subfamilia').val();
            if (sub && (fila.SubFamilia || '') !== sub) { return false; }
        }
        return true;
    });

    // Al cambiar la Familia: acota las Sub-Familias disponibles (cascada) y re-dibuja.
    $('#filtro-v2-familia').on('change', function() {
        poblarSubFamiliasV2();
        if (tabla) { tabla.draw(); }
    });

    // Tipo Doc. y Sub-Familia solo re-dibujan (client-side).
    $('#filtro-v2-tipo, #filtro-v2-subfamilia').on('change', function() {
        if (tabla) { tabla.draw(); }
    });

    // Botón "Limpiar": deja todos los filtros por defecto y recarga la consulta activa.
    $('#btn-limpiar-filtros-sap').on('click', function() {
        $('#filtro-v2-tipo, #filtro-v2-familia, #filtro-v2-subfamilia').val('');
        $('#consulta').val('');

        // Fecha vuelve al rango por defecto: desde enero del año actual, hasta sin límite.
        filtroDesde = anioActual + '-01-01';
        filtroHasta = '';
        fpFacsDesde.setDate(new Date(anioActual, 0, 1), false);   // sin disparar onChange
        fpFacsHasta.clear(false);

        if (claveActual) {
            cargarConsulta(claveActual, botonActual || $('#btn-consulta-facs-ncs-v2'));
        }
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

    // ============================================================
    //  Ver líneas (modal con las líneas de la factura / NC)
    // ============================================================

    // Dibuja la cabecera del documento en el modal usando las MISMAS columnas y renders
    // del DataTable activo (así coincide exactamente con lo que se ve en la tabla principal).
    function renderCabeceraDoc(registro, tablaSelector) {
        const cols = (claveActual && CONSULTAS[claveActual]) ? CONSULTAS[claveActual].columnas : [];

        const ths = cols.map(function(c) {
            return '<th class="' + (c.className || '') + '">' + c.title + '</th>';
        }).join('');

        const tds = cols.map(function(c) {
            const val = registro[c.data];
            const contenido = c.render ? c.render(val) : ((val === null || val === undefined) ? '' : val);
            return '<td class="' + (c.className || '') + '">' + contenido + '</td>';
        }).join('');

        $(tablaSelector + ' thead').html('<tr>' + ths + '</tr>');
        $(tablaSelector + ' tbody').html('<tr>' + tds + '</tr>');
    }

    // Arma las filas del detalle de líneas; colspan 10 = nº de columnas del <thead>.
    function filasLineas(lineas) {
        if (!lineas.length) {
            return '<tr><td colspan="13" class="text-center text-muted py-3">El documento no tiene líneas.</td></tr>';
        }
        return lineas.map(function(l) {
            return '<tr>'
                 + '<td class="text-center">' + renderNumero(l.Linea) + '</td>'
                 + '<td>' + renderTexto(l.CodArticulo) + '</td>'
                 + '<td>' + renderTexto(l.Articulo) + '</td>'
                 + '<td class="text-center">' + renderTexto(l.Unidad) + '</td>'
                 + '<td class="text-center">' + renderTexto(l.Bodega) + '</td>'
                 + '<td class="text-end">' + renderCantidad(l.Cantidad) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.PrecioSinDesc) + '</td>'
                 + '<td class="text-end">' + renderPorcentaje(l.PctDescuento) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.PrecioUnitario) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.TotalNeto) + '</td>'
                 + '<td class="text-end">' + renderPorcentaje(l.PctIVA) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.IvaMonto) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.TotalBruto) + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Botón "Ver líneas": resuelve la fila, abre el modal y pide las líneas al controlador.
    $('#tabla-consulta tbody').on('click', '.btn-ver-lineas', function() {
        const idx      = $(this).data('fila');
        const registro = filasAct[idx];
        const $tbody   = $('#tabla-lineas-consulta-sap');

        if (!registro) {
            return;
        }

        $('#lineas-doc-titulo').text('— ' + registro.TipoDoc + ' N° ' + registro.NumDoc);
        renderCabeceraDoc(registro, '#tabla-cabecera-consulta-sap');
        $tbody.html('<tr><td colspan="13" class="text-center text-muted py-3">Cargando...</td></tr>');
        $('#lineas-total-neto').text('');
        $('#lineas-total-iva').text('');
        $('#lineas-total-bruto').text('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaSapLineas')).show();

        $.ajax({
            url: 'controllers/consultas_sap_controller.php?action=lineas',
            type: 'GET',
            data: { tipo: registro.TipoDoc, docentry: registro.DocEntry },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const lineas = res.data || [];
                    $tbody.html(filasLineas(lineas));

                    // Sumas del pie: Total Neto, IVA ($) y Total Bruto.
                    const totalNeto = lineas.reduce(function(acc, l) {
                        return acc + (parseFloat(l.TotalNeto) || 0);
                    }, 0);
                    const totalIva = lineas.reduce(function(acc, l) {
                        return acc + (parseFloat(l.IvaMonto) || 0);
                    }, 0);
                    const totalBruto = lineas.reduce(function(acc, l) {
                        return acc + (parseFloat(l.TotalBruto) || 0);
                    }, 0);
                    $('#lineas-total-neto').text(lineas.length ? renderMonto(totalNeto) : '');
                    $('#lineas-total-iva').text(lineas.length ? renderMonto(totalIva) : '');
                    $('#lineas-total-bruto').text(lineas.length ? renderMonto(totalBruto) : '');
                } else {
                    $tbody.html('<tr><td colspan="13" class="text-danger small py-3">' + (res.message || 'No se pudieron cargar las líneas.') + '</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="13" class="text-danger small py-3">Error al cargar las líneas.</td></tr>');
            }
        });
    });

    // ============================================================
    //  Ver documentos (v3): facturas/NC de un artículo en un año-mes
    // ============================================================

    // Arma las filas del listado de documentos; colspan 9 = nº de columnas del <thead>.
    function filasDocs(docs) {
        if (!docs.length) {
            return '<tr><td colspan="9" class="text-center text-muted py-3">Sin documentos.</td></tr>';
        }
        return docs.map(function(d) {
            return '<tr>'
                 + '<td class="text-center">' + renderNumero(d.DocEntry) + '</td>'
                 + '<td class="text-center">' + renderTexto(d.TipoDoc) + '</td>'
                 + '<td class="text-center">' + renderNumero(d.NumDoc) + '</td>'
                 + '<td class="text-center">' + renderFecha(d.FechaDocumento) + '</td>'
                 + '<td>' + renderTexto(d.Cliente) + '</td>'
                 + '<td class="text-end">' + renderCantidad(d.Cantidad) + '</td>'
                 + '<td class="text-end">' + renderMonto(d.TotalNeto) + '</td>'
                 + '<td class="text-end">' + renderMonto(d.IvaMonto) + '</td>'
                 + '<td class="text-end">' + renderMonto(d.TotalBruto) + '</td>'
                 + '</tr>';
        }).join('');
    }

    // Botón "Ver documentos": abre el modal y pide los documentos del artículo en ese año-mes.
    $('#tabla-consulta tbody').on('click', '.btn-ver-documentos', function() {
        const idx      = $(this).data('fila');
        const registro = filasAct[idx];
        const $tbody   = $('#tabla-docs-consulta-sap');

        if (!registro) {
            return;
        }

        $('#docs-titulo').text('— ' + registro.CodArticulo + ' · ' + registro.FechaDocumento);
        renderCabeceraDoc(registro, '#tabla-resumen-docs-sap');
        $tbody.html('<tr><td colspan="9" class="text-center text-muted py-3">Cargando...</td></tr>');
        $('#docs-total-cant, #docs-total-neto, #docs-total-iva, #docs-total-bruto').text('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaSapDocs')).show();

        $.ajax({
            url: 'controllers/consultas_sap_controller.php?action=docs_articulo_mes',
            type: 'GET',
            data: { aniomes: registro.FechaDocumento, itemcode: registro.CodArticulo },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $tbody.html('<tr><td colspan="9" class="text-danger small py-3">' + (res.message || 'No se pudieron cargar los documentos.') + '</td></tr>');
                    return;
                }
                const docs = res.data || [];
                $tbody.html(filasDocs(docs));

                if (docs.length) {
                    const suma = function(campo) {
                        return docs.reduce(function(acc, d) { return acc + (parseFloat(d[campo]) || 0); }, 0);
                    };
                    $('#docs-total-cant').text(renderCantidad(suma('Cantidad')));
                    $('#docs-total-neto').text(renderMonto(suma('TotalNeto')));
                    $('#docs-total-iva').text(renderMonto(suma('IvaMonto')));
                    $('#docs-total-bruto').text(renderMonto(suma('TotalBruto')));
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="9" class="text-danger small py-3">Error al cargar los documentos.</td></tr>');
            }
        });
    });

    // ============================================================
    //  Gráfico producto (v3): demanda mensual de un artículo (toda su historia,
    //  independiente del filtro de fecha de la consulta).
    // ============================================================

    let serieGraficoActual = null; // última serie cargada (para redibujar al mostrar el modal)
    let mesResaltado       = null; // año-mes del registro abierto (se remarca en el gráfico)
    let modalGraficoShown  = false; // true cuando el modal terminó de abrirse (ancho final)
    let graficoDibujado    = false; // true tras el primer dibujo (evita dibujar dos veces)

    // Año-mes ('yyyy-MM') <-> índice de mes (año*12 + mes-1), para recorrer meses.
    function ymAIndice(ym) {
        return parseInt(ym.substring(0, 4), 10) * 12 + (parseInt(ym.substring(5, 7), 10) - 1);
    }
    function indiceAYm(idx) {
        return Math.floor(idx / 12) + '-' + String((idx % 12) + 1).padStart(2, '0');
    }

    // Completa los año-mes faltantes entre el primero y el último con datos, para que el
    // eje X sea continuo. Los meses sin registro quedan con valores null (se ven en blanco).
    function rellenarMeses(datos) {
        if (!datos || !datos.length) { return datos; }
        const mapa = {};
        datos.forEach(function(d) { mapa[d.FechaDocumento] = d; });
        const claves = Object.keys(mapa).sort();
        const desde  = ymAIndice(claves[0]);
        const hasta  = ymAIndice(claves[claves.length - 1]);
        const salida = [];
        for (let i = desde; i <= hasta; i++) {
            const ym = indiceAYm(i);
            salida.push(mapa[ym] || { FechaDocumento: ym, Demanda: null, Neto: null, DemandaForecast: null, PresupuestoFuturo: null });
        }
        return salida;
    }

    // Convierte a número, o null si es null/undefined (para dejar huecos en blanco).
    function numOrNull(v) { return (v === null || v === undefined) ? null : (parseFloat(v) || 0); }

    // Dibuja, sobre una línea de tiempo continua (historia + futuro):
    //   - Demanda real (barras azul) + Demanda forecast (barras morado)   -> eje de unidades
    //   - Venta neta real (línea roja) + Presupuesto futuro (línea amarilla) -> eje de $
    // El mes abierto (mesResaltado, histórico) se remarca en la serie real. "Mostrar" decide
    // si se ven ambas dimensiones (dos ejes) o solo una (un eje).
    function dibujarGraficoDemanda(datos) {
        const modo       = $('#grafico-mostrar').val() || 'ambos';
        const mostrarDem = (modo === 'demanda' || modo === 'ambos');
        const mostrarVen = (modo === 'venta'   || modo === 'ambos');
        datos = rellenarMeses(datos); // eje X continuo (meses sin dato en blanco)

        const ejeDem = 0;
        const ejeVen = (modo === 'ambos') ? 1 : 0;

        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Año-Mes');

        const series = {}; const vAxes = {}; let si = 0; let annVen = false;

        if (mostrarDem) {
            data.addColumn('number', 'Demanda');
            data.addColumn({ type: 'string', role: 'style' });      // resaltado del mes abierto
            data.addColumn({ type: 'string', role: 'annotation' });
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#0d6efd' }; // demanda real (azul)

            data.addColumn('number', 'Demanda (forecast)');
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#6f42c1' }; // forecast (morado)

            vAxes[ejeDem] = { title: 'Demanda (unidades)', minValue: 0 };
        }
        if (mostrarVen) {
            data.addColumn('number', 'Venta Neta');
            if (modo === 'venta') { data.addColumn({ type: 'string', role: 'annotation' }); annVen = true; }
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#dc3545', lineWidth: 2, pointSize: 4 }; // venta real (roja)

            data.addColumn('number', 'Presupuesto futuro');
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#e0a800', lineWidth: 2, pointSize: 4 }; // presupuesto (amarillo)

            vAxes[ejeVen] = { title: 'Venta / Presupuesto ($)', minValue: 0, format: 'short' };
        }

        datos.forEach(function(d) {
            const esR  = (d.FechaDocumento === mesResaltado);
            const fila = [ d.FechaDocumento ];
            if (mostrarDem) {
                const dem = numOrNull(d.Demanda);
                fila.push(
                    dem,
                    (esR && dem !== null) ? 'color: #fd7e14; stroke-color: #b45309; stroke-width: 2' : null,
                    (esR && dem !== null) ? formatearNumero(dem, dem % 1 === 0 ? 0 : 2) : null,
                    numOrNull(d.DemandaForecast)
                );
            }
            if (mostrarVen) {
                const ven = numOrNull(d.Neto);
                fila.push(ven);
                if (annVen) { fila.push((esR && ven !== null) ? formatearNumero(ven, 0) : null); }
                fila.push(numOrNull(d.PresupuestoFuturo));
            }
            data.addRow(fila);
        });

        const opciones = {
            seriesType: 'bars',
            series:     series,
            annotations: {
                textStyle:     { bold: true, fontSize: 12, color: '#b45309' },
                alwaysOutside: true,
                stem:          { color: 'transparent' }
            },
            legend:    { position: 'top' },
            height:    460,
            chartArea: { left: 80, right: (modo === 'ambos' ? 120 : 80), top: 50, bottom: 90 },
            hAxis:     { title: 'Año-Mes', slantedText: true, slantedTextAngle: 60, textStyle: { fontSize: 11 } },
            vAxes:     vAxes,
            tooltip:   { trigger: 'focus' }
        };

        new google.visualization.ComboChart(document.getElementById('grafico-producto-canvas')).draw(data, opciones);
    }

    // Llena los selects Año desde / Año hasta con los años presentes en la serie.
    // Por defecto muestra el AÑO ACTUAL y el AÑO ANTERIOR, acotado a los años que
    // el producto realmente tenga (si no existen, cae al año disponible más cercano).
    function poblarFiltrosAnios(datos) {
        const set = {};
        datos.forEach(function(d) { set[String(d.FechaDocumento).substring(0, 4)] = true; });
        const lista = Object.keys(set).sort();
        const opts  = lista.map(function(a) { return '<option value="' + a + '">' + a + '</option>'; }).join('');
        $('#grafico-anio-desde').html(opts);
        $('#grafico-anio-hasta').html(opts);

        // Mayor año disponible que no supere el límite (o el más antiguo si no hay ninguno).
        const aniosNum = lista.map(Number);
        function mayorHasta(limite) {
            const c = aniosNum.filter(function(y) { return y <= limite; });
            return c.length ? c[c.length - 1] : aniosNum[0];
        }
        $('#grafico-anio-hasta').val(String(mayorHasta(anioActual)));       // año actual
        $('#grafico-anio-desde').val(String(mayorHasta(anioActual - 1)));   // año anterior
    }

    // Filtra la serie por el rango de años elegido y (re)dibuja; muestra aviso si queda vacío.
    function redibujarConFiltro() {
        if (!serieGraficoActual) { return; }

        const desde = parseInt($('#grafico-anio-desde').val(), 10);
        const hasta = parseInt($('#grafico-anio-hasta').val(), 10);
        const datos = serieGraficoActual.filter(function(d) {
            const anio = parseInt(String(d.FechaDocumento).substring(0, 4), 10);
            return anio >= desde && anio <= hasta;
        });

        const $estado = $('#grafico-producto-estado');
        const $canvas = $('#grafico-producto-canvas');

        if (!datos.length) {
            $canvas.hide().empty();
            $estado.text('No hay datos de demanda en el rango de años seleccionado.').show();
            return;
        }
        $estado.hide();
        $canvas.show();
        dibujarGraficoDemanda(datos);
    }

    // Primer dibujo: solo cuando el modal YA terminó de abrirse (ancho final) y hay datos.
    // Así se dibuja una sola vez, a tamaño correcto, sin el "salto" de redimensionar.
    function intentarDibujarGrafico() {
        if (graficoDibujado || !modalGraficoShown) { return; }
        if (!serieGraficoActual || !serieGraficoActual.length) { return; }
        graficoDibujado = true;
        $('#grafico-producto-estado').hide();
        $('#grafico-producto-filtros').css('display', 'flex');
        $('#grafico-producto-canvas').show();
        cuandoChartsListo(redibujarConFiltro);
    }

    // Botón "Gráfico producto": abre el modal y pide la demanda mensual del artículo.
    $('#tabla-consulta tbody').on('click', '.btn-grafico-producto', function() {
        const idx      = $(this).data('fila');
        const registro = filasAct[idx];
        if (!registro) { return; }

        const $estado = $('#grafico-producto-estado');
        const $canvas = $('#grafico-producto-canvas');

        serieGraficoActual = null;
        mesResaltado       = registro.FechaDocumento; // año-mes del registro abierto
        graficoDibujado    = false;
        modalGraficoShown  = false;
        $('#grafico-producto-titulo').text('— ' + registro.CodArticulo + ' · ' + (registro.Articulo || ''));
        renderCabeceraDoc(registro, '#tabla-resumen-grafico-sap'); // info del registro (mismas columnas de la V3)
        $('#grafico-producto-filtros').hide();
        $estado.text('Cargando...').show();
        $canvas.hide().empty();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaSapGrafico')).show();

        $.ajax({
            url: 'controllers/consultas_sap_controller.php?action=grafico_producto',
            type: 'GET',
            data: { itemcode: registro.CodArticulo },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el gráfico.').show();
                    return;
                }
                const historia = res.data || [];
                const forecast = res.forecast || [];
                if (!historia.length && !forecast.length) {
                    $estado.text('Sin datos de demanda para este producto.').show();
                    return;
                }
                // Combina historia (demanda/venta reales) + forecast (demanda pronosticada / presupuesto futuro).
                const mapa = {};
                historia.forEach(function(h) {
                    mapa[h.FechaDocumento] = {
                        FechaDocumento: h.FechaDocumento,
                        Demanda: h.Demanda, Neto: h.Neto,
                        DemandaForecast: null, PresupuestoFuturo: null
                    };
                });
                forecast.forEach(function(f) {
                    if (!mapa[f.ym]) {
                        mapa[f.ym] = { FechaDocumento: f.ym, Demanda: null, Neto: null, DemandaForecast: null, PresupuestoFuturo: null };
                    }
                    mapa[f.ym].DemandaForecast   = f.DemandaForecast;
                    mapa[f.ym].PresupuestoFuturo = f.PresupuestoFuturo;
                });
                const combinado = Object.keys(mapa).sort().map(function(k) { return mapa[k]; });

                serieGraficoActual = combinado;
                poblarFiltrosAnios(combinado);
                intentarDibujarGrafico(); // dibuja solo si el modal ya terminó de abrirse
            },
            error: function() {
                $estado.text('Error al cargar el gráfico.').show();
            }
        });
    });

    // Cambio de "Mostrar" o del rango de años: refiltra/redibuja (client-side).
    $('#grafico-mostrar, #grafico-anio-desde, #grafico-anio-hasta').on('change', function() {
        cuandoChartsListo(redibujarConFiltro);
    });

    // Redibuja al terminar de mostrarse el modal (evita ancho 0 si el gráfico se dibujó
    // antes de completarse la transición).
    document.getElementById('modalConsultaSapGrafico')
        .addEventListener('shown.bs.modal', function() {
            modalGraficoShown = true;
            intentarDibujarGrafico(); // ancho final ya disponible: dibuja una sola vez
        });

    // Responsivo: Google Charts no se reajusta solo, así que se redibuja al cambiar el
    // tamaño de la ventana mientras el modal está abierto (con debounce para no saturar).
    $(window).on('resize', debounce(function() {
        if (serieGraficoActual && $('#modalConsultaSapGrafico').hasClass('show')) {
            cuandoChartsListo(redibujarConFiltro);
        }
    }, 200));

    // Carga inicial: al entrar al mantenedor se muestra la consulta ODV por defecto.
    cargarConsulta('odv', $('#btn-consulta-odv'));

});
