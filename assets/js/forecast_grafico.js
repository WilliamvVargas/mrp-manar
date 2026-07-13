$(document).ready(function() {

    // ============================================================
    //  Gráfico producto (mantenedor de Forecast): historia real de demanda/venta (SAP)
    //  + Cantidad Forecast por producto y su demanda valorizada en $ (MySQL). Se remarca el mes
    //  seleccionado sobre la serie de Cantidad Forecast.
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
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return ''; }
        const partes = num.toFixed(decimales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.length > 1 ? partes[0] + ',' + partes[1] : partes[0];
    }

    const esc = function(v) { return (v === null || v === undefined) ? '' : $('<div>').text(v).html(); };

    let serieGraficoActual = null; // última serie cargada (para redibujar al mostrar el modal)
    let mesResaltado       = null; // año-mes del registro abierto (se remarca en el forecast)
    let modalGraficoShown  = false; // true cuando el modal terminó de abrirse (ancho final)
    let graficoDibujado    = false; // true tras el primer dibujo (evita dibujar dos veces)

    const anioActual = new Date().getFullYear();

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
            salida.push(mapa[ym] || { FechaDocumento: ym, Demanda: null, Neto: null, DemandaForecast: null, DemandaValorizada: null });
        }
        return salida;
    }

    // Convierte a número, o null si es null/undefined (para dejar huecos en blanco).
    function numOrNull(v) { return (v === null || v === undefined) ? null : (parseFloat(v) || 0); }

    // Dibuja, sobre una línea de tiempo continua (historia + futuro):
    //   - Demanda real (barras azul) + Cantidad Forecast (barras morado)         -> eje de unidades
    //   - Venta neta real (línea roja)
    //     + Demanda valorizada en $ (línea verde = demanda forecast × precio)    -> eje de $
    // El mes seleccionado (mesResaltado) se remarca en la serie de Cantidad Forecast.
    function dibujarGrafico(datos) {
        const modo       = $('#fc-grafico-mostrar').val() || 'ambos';
        const mostrarDem = (modo === 'demanda' || modo === 'ambos');
        const mostrarVen = (modo === 'venta'   || modo === 'ambos');
        datos = rellenarMeses(datos); // eje X continuo (meses sin dato en blanco)

        const ejeDem = 0;
        const ejeVen = (modo === 'ambos') ? 1 : 0;

        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Año-Mes');

        const series = {}; const vAxes = {}; let si = 0;
        const colsPesos = []; // índices de las columnas en $ (Venta Neta, Demanda Valorizada) para formatear su tooltip

        if (mostrarDem) {
            data.addColumn('number', 'Demanda');
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#0d6efd' }; // demanda real (azul)

            data.addColumn('number', 'Cantidad Forecast');
            data.addColumn({ type: 'string', role: 'style' });      // resaltado del mes seleccionado
            data.addColumn({ type: 'string', role: 'annotation' });
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#6f42c1' }; // forecast (morado)

            vAxes[ejeDem] = { title: 'Demanda (unidades)', minValue: 0 };
        }
        if (mostrarVen) {
            colsPesos.push(data.getNumberOfColumns());
            data.addColumn('number', 'Venta Neta');
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#dc3545', lineWidth: 2, pointSize: 4 }; // venta real (roja)

            colsPesos.push(data.getNumberOfColumns());
            data.addColumn('number', 'Demanda Valorizada');
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#198754', lineWidth: 2, pointSize: 4, lineDashStyle: [3, 3] }; // demanda forecast × precio (verde)

            vAxes[ejeVen] = { title: 'Venta Neta ($)', minValue: 0, format: 'short' };
        }

        datos.forEach(function(d) {
            const esR  = (d.FechaDocumento === mesResaltado);
            const fila = [ d.FechaDocumento ];
            if (mostrarDem) {
                const fc = numOrNull(d.DemandaForecast);
                fila.push(
                    numOrNull(d.Demanda),
                    fc,
                    (esR && fc !== null) ? 'color: #fd7e14; stroke-color: #b45309; stroke-width: 2' : null,
                    (esR && fc !== null) ? formatearNumero(fc, fc % 1 === 0 ? 0 : 2) : null
                );
            }
            if (mostrarVen) {
                fila.push(numOrNull(d.Neto), numOrNull(d.DemandaValorizada));
            }
            data.addRow(fila);
        });

        // Tooltip de las series en $ (Venta Neta, Demanda Valorizada): entero, miles con punto y "$".
        const fmtPesos = new google.visualization.NumberFormat({ prefix: '$', fractionDigits: 0, groupingSymbol: '.' });
        colsPesos.forEach(function(c) { fmtPesos.format(data, c); });

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

        new google.visualization.ComboChart(document.getElementById('fc-grafico-canvas')).draw(data, opciones);
    }

    // Llena los selects Año desde / Año hasta con los años presentes en la serie.
    // Por defecto muestra el AÑO ACTUAL y el AÑO ANTERIOR, acotado a los años que
    // el producto realmente tenga (si no existen, cae al año disponible más cercano).
    function poblarFiltrosAnios(datos) {
        const set = {};
        datos.forEach(function(d) { set[String(d.FechaDocumento).substring(0, 4)] = true; });
        const lista = Object.keys(set).sort();
        const opts  = lista.map(function(a) { return '<option value="' + a + '">' + a + '</option>'; }).join('');
        $('#fc-grafico-anio-desde').html(opts);
        $('#fc-grafico-anio-hasta').html(opts);

        const aniosNum = lista.map(Number);
        function mayorHasta(limite) {
            const c = aniosNum.filter(function(y) { return y <= limite; });
            return c.length ? c[c.length - 1] : aniosNum[0];
        }
        $('#fc-grafico-anio-hasta').val(String(mayorHasta(anioActual)));       // año actual
        $('#fc-grafico-anio-desde').val(String(mayorHasta(anioActual - 1)));   // año anterior
    }

    // Filtra la serie por el rango de años elegido y (re)dibuja; muestra aviso si queda vacío.
    function redibujarConFiltro() {
        if (!serieGraficoActual) { return; }

        const desde = parseInt($('#fc-grafico-anio-desde').val(), 10);
        const hasta = parseInt($('#fc-grafico-anio-hasta').val(), 10);
        const datos = serieGraficoActual.filter(function(d) {
            const anio = parseInt(String(d.FechaDocumento).substring(0, 4), 10);
            return anio >= desde && anio <= hasta;
        });

        const $estado = $('#fc-grafico-estado');
        const $canvas = $('#fc-grafico-canvas');

        if (!datos.length) {
            $canvas.hide().empty();
            $estado.text('No hay datos en el rango de años seleccionado.').show();
            return;
        }
        $estado.hide();
        $canvas.show();
        dibujarGrafico(datos);
    }

    // Primer dibujo: solo cuando el modal YA terminó de abrirse (ancho final) y hay datos.
    function intentarDibujarGrafico() {
        if (graficoDibujado || !modalGraficoShown) { return; }
        if (!serieGraficoActual || !serieGraficoActual.length) { return; }
        graficoDibujado = true;
        $('#fc-grafico-estado').hide();
        $('#fc-grafico-filtros').css('display', 'flex');
        $('#fc-grafico-canvas').show();
        cuandoChartsListo(redibujarConFiltro);
    }

    // Resumen "Producto seleccionado": datos del producto (la vista es agregada, sin mes puntual).
    function renderResumen(d) {
        const ths = ['Código Producto', 'Nombre Producto', 'Familia', 'Sub-Familia', 'Total Forecast (12 meses)', 'Forecast Mes Siguiente'];
        const tds = [
            esc(d.codigo), esc(d.nombre), esc(d.familia), esc(d.subfamilia),
            '<span class="fw-bold">' + esc(formatearNumero(d.total, 0)) + '</span>',
            '<span class="fw-bold">' + esc(formatearNumero(d.sig, 0)) + '</span>'
        ];
        $('#tabla-resumen-grafico-forecast thead').html('<tr>' + ths.map(function(t) { return '<th>' + t + '</th>'; }).join('') + '</tr>');
        $('#tabla-resumen-grafico-forecast tbody').html('<tr>' + tds.map(function(t, i) {
            return '<td class="' + (i >= 4 ? 'text-end' : '') + '">' + t + '</td>';
        }).join('') + '</tr>');
    }

    // Nombres de mes (índice 1-12) para el detalle bajo el gráfico.
    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // Detalle mes a mes del forecast del producto (tabla bajo el gráfico).
    function renderDetalle(detalle) {
        const $wrap = $('#fc-grafico-detalle-wrap');
        const $tb   = $('#tabla-detalle-grafico-forecast tbody');

        if (!detalle || !detalle.length) {
            $tb.html('<tr><td colspan="4" class="text-center text-muted py-3">Sin filas de forecast para este producto.</td></tr>');
            $wrap.show();
            return;
        }

        $tb.html(detalle.map(function(d) {
            const mesNom = MESES[d.mes] || d.mes;
            const vp = (d.venta_presupuestada === null || d.venta_presupuestada === undefined)
                ? '<span class="text-muted">—</span>'
                : '$' + formatearNumero(d.venta_presupuestada, 0);
            return '<tr>'
                 + '<td>' + esc(d.anio) + '</td>'
                 + '<td>' + esc(mesNom) + '</td>'
                 + '<td class="text-end">' + formatearNumero(d.demanda_forecast, 0) + '</td>'
                 + '<td class="text-end">' + vp + '</td>'
                 + '</tr>';
        }).join(''));
        $wrap.show();
    }

    // Botón "Gráfico producto" (columna Acciones): abre el modal y pide la serie del producto.
    $('#tabla-consulta-forecast tbody').on('click', '.btn-grafico-producto', function() {
        const b = $(this);
        const reg = {
            codigo:     b.data('codigo'),
            nombre:     b.data('nombre'),
            familia:    b.data('familia'),
            subfamilia: b.data('subfamilia'),
            total:      b.data('total'),
            sig:        b.data('sig')
        };
        if (reg.codigo === undefined || reg.codigo === '') { return; }

        const $estado = $('#fc-grafico-estado');
        const $canvas = $('#fc-grafico-canvas');

        serieGraficoActual = null;
        mesResaltado       = null;   // vista por producto: no se remarca un mes puntual
        graficoDibujado    = false;
        modalGraficoShown  = false;
        $('#fc-grafico-titulo').text('');
        renderResumen(reg);
        $('#fc-grafico-filtros').hide();
        $('#fc-grafico-detalle-wrap').hide();
        $('#tabla-detalle-grafico-forecast tbody').empty();
        $estado.text('Cargando...').show();
        $canvas.hide().empty();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalForecastGrafico')).show();

        $.ajax({
            url: 'controllers/forecast_controller.php?action=grafico_producto',
            type: 'GET',
            data: { itemcode: reg.codigo },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el gráfico.').show();
                    return;
                }
                const historia = res.data || [];
                const forecast = res.forecast || [];

                // Detalle mes a mes del forecast (tabla bajo el gráfico), independiente de la historia.
                renderDetalle(res.detalle || []);

                if (!historia.length && !forecast.length) {
                    $estado.text('Sin datos para este producto.').show();
                    return;
                }
                // Combina historia (demanda/venta reales) + forecast (Cantidad Forecast / demanda valorizada en $).
                const mapa = {};
                historia.forEach(function(h) {
                    mapa[h.FechaDocumento] = {
                        FechaDocumento: h.FechaDocumento,
                        Demanda: h.Demanda, Neto: h.Neto,
                        DemandaForecast: null, DemandaValorizada: null
                    };
                });
                forecast.forEach(function(f) {
                    if (!mapa[f.ym]) {
                        mapa[f.ym] = { FechaDocumento: f.ym, Demanda: null, Neto: null, DemandaForecast: null, DemandaValorizada: null };
                    }
                    mapa[f.ym].DemandaForecast   = f.DemandaForecast;
                    mapa[f.ym].DemandaValorizada = f.DemandaValorizada;
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
    $('#fc-grafico-mostrar, #fc-grafico-anio-desde, #fc-grafico-anio-hasta').on('change', function() {
        cuandoChartsListo(redibujarConFiltro);
    });

    // Redibuja al terminar de mostrarse el modal (evita ancho 0 si se dibujó antes de la transición).
    document.getElementById('modalForecastGrafico')
        .addEventListener('shown.bs.modal', function() {
            modalGraficoShown = true;
            intentarDibujarGrafico();
        });

    // Responsivo: Google Charts no se reajusta solo; se redibuja al cambiar el tamaño (con debounce).
    $(window).on('resize', debounce(function() {
        if (serieGraficoActual && $('#modalForecastGrafico').hasClass('show')) {
            cuandoChartsListo(redibujarConFiltro);
        }
    }, 200));

});
