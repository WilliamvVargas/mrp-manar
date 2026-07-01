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
                { data: 'TipoDoc',          title: 'Tipo Doc.',      className: 'text-center', render: renderTexto },
                { data: 'NumDoc',           title: 'N° Doc.',        className: 'text-center', render: renderNumero },
                { data: 'FechaDocumento',   title: 'Fecha Documento',className: 'text-center', render: renderFecha },
                { data: 'CodCliente',       title: 'Cód. Cliente',   render: renderTexto },
                { data: 'Cliente',          title: 'Cliente',        render: renderTexto },
                { data: 'Moneda',           title: 'Moneda',         className: 'text-center', render: renderTexto },
                { data: 'Neto',             title: 'Neto',           className: 'text-end', render: renderMonto },
                { data: 'Impuesto',         title: 'Impuesto',       className: 'text-end', render: renderMonto },
                { data: 'Total',            title: 'Total',          className: 'text-end', render: renderMonto },
                { data: 'Estado',           title: 'Estado',         className: 'text-center', render: renderTexto }
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
                let botones = '<button type="button" class="btn btn-sm btn-outline-secondary btn-ver-consulta" '
                            + 'data-fila="' + meta.row + '" title="Ver detalle"><i class="bi bi-eye"></i></button>';

                if (cfg.verLineas) {
                    botones += ' <button type="button" class="btn btn-sm btn-outline-primary btn-ver-lineas" '
                             + 'data-fila="' + meta.row + '" title="Ver líneas"><i class="bi bi-list-ul"></i></button>';
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

        // Muestra el filtro de fecha solo en las consultas que lo soportan.
        $('.filtro-fecha-facs').toggleClass('d-none', !cfg.filtroFecha);

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
                                $(api.column(idx).footer()).html(renderMonto(suma));
                            }
                        });

                        $(api.column(0).footer()).html('Total');
                    }
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

    $('#btn-consulta-facs-ncs').on('click', function() {
        cargarConsulta('facs_ncs', $(this));
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
    flatpickr('#facs-fecha-desde', {
        locale: 'es',
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
        defaultDate: new Date(anioActual, 0, 1),   // enero del año actual
        onChange: function(fechas) {
            filtroDesde = fechas.length ? primerDiaMes(fechas[0]) : '';
            recargarPorFiltroFecha();
        },
        onReady: agregarQuitarFiltro
    });

    flatpickr('#facs-fecha-hasta', {
        locale: 'es',
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
        onChange: function(fechas) {
            filtroHasta = fechas.length ? ultimoDiaMes(fechas[0]) : '';
            recargarPorFiltroFecha();
        },
        onReady: agregarQuitarFiltro
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

    // Arma las filas del detalle de líneas; colspan 10 = nº de columnas del <thead>.
    function filasLineas(lineas) {
        if (!lineas.length) {
            return '<tr><td colspan="10" class="text-center text-muted py-3">El documento no tiene líneas.</td></tr>';
        }
        return lineas.map(function(l) {
            return '<tr>'
                 + '<td class="text-center">' + renderNumero(l.Linea) + '</td>'
                 + '<td>' + renderTexto(l.CodArticulo) + '</td>'
                 + '<td>' + renderTexto(l.Articulo) + '</td>'
                 + '<td class="text-center">' + renderTexto(l.Unidad) + '</td>'
                 + '<td class="text-center">' + renderTexto(l.Bodega) + '</td>'
                 + '<td class="text-end">' + renderCantidad(l.Cantidad) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.PrecioUnitario) + '</td>'
                 + '<td class="text-end">' + renderPorcentaje(l.PctDescuento) + '</td>'
                 + '<td class="text-end">' + renderPorcentaje(l.PctIVA) + '</td>'
                 + '<td class="text-end">' + renderMonto(l.TotalLinea) + '</td>'
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
        $tbody.html('<tr><td colspan="10" class="text-center text-muted py-3">Cargando...</td></tr>');
        $('#lineas-total-suma').text('');
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

                    // Suma de los totales de línea para el pie de la tabla.
                    const totalSuma = lineas.reduce(function(acc, l) {
                        return acc + (parseFloat(l.TotalLinea) || 0);
                    }, 0);
                    $('#lineas-total-suma').text(lineas.length ? renderMonto(totalSuma) : '');
                } else {
                    $tbody.html('<tr><td colspan="10" class="text-danger small py-3">' + (res.message || 'No se pudieron cargar las líneas.') + '</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="10" class="text-danger small py-3">Error al cargar las líneas.</td></tr>');
            }
        });
    });

    // Carga inicial: al entrar al mantenedor se muestra la consulta ODV por defecto.
    cargarConsulta('odv', $('#btn-consulta-odv'));

});
