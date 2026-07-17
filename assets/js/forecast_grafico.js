$(document).ready(function() {

    // ============================================================
    //  Gráfico producto (mantenedor de Forecast): historia real de demanda/venta (SAP)
    //  + Demanda Forecast por producto y su demanda valorizada en $ (MySQL). Se remarca el mes
    //  seleccionado sobre la serie de Demanda Forecast.
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
    let detalleForecastActual = null; // detalle semana a semana del forecast (para refiltrarlo por rango)

    // Multiselección de series a mostrar en el gráfico (reemplaza el antiguo combo "Mostrar").
    // Cualquier combinación de las 4 series; por defecto todas marcadas.
    const mostrarMulti = inicializarMultiselect({
        contenedor: '#fc-grafico-mostrar',
        opciones: [
            { valor: 'demanda_historica',  texto: 'Demanda Histórica' },
            { valor: 'demanda_forecast',   texto: 'Demanda Forecast' },
            { valor: 'venta_neta',         texto: 'Venta Neta' },
            { valor: 'demanda_valorizada', texto: 'Demanda Valorizada' }
        ],
        textoTodos: 'Todas las series',
        textoVacio: 'Ninguna serie',
        onCambio: function() { cuandoChartsListo(redibujarConFiltro); }
    });

    // Filtros de rango AÑO-MES (Flatpickr + plugin monthSelect, misma librería que Ventas Históricas).
    let fpDesde = null, fpHasta = null;
    // 'yyyy-MM' de la fecha elegida en un picker (o '' si no hay selección).
    function ymDeFp(fp) {
        if (!fp || !fp.selectedDates.length) { return ''; }
        const d = fp.selectedDates[0];
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }
    // Nueva config por picker (plugin instanciado aparte para cada uno).
    function optsPickerFecha() {
        return {
            locale: 'es',
            plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: 'm/Y' }) ],
            onChange: function() {
                cuandoChartsListo(redibujarConFiltro);
                renderDetalleFiltrado();
            }
        };
    }
    fpDesde = flatpickr('#fc-grafico-desde', optsPickerFecha());
    fpHasta = flatpickr('#fc-grafico-hasta', optsPickerFecha());

    // Lunes ISO ('yyyy-MM-dd') <-> índice de semana (relativo a un lunes de referencia).
    const REF_LUNES = Date.UTC(2020, 0, 6); // 2020-01-06 (lunes)
    function fechaAIdxSemana(ymd) {
        const ms = Date.UTC(+ymd.substring(0, 4), +ymd.substring(5, 7) - 1, +ymd.substring(8, 10));
        return Math.round((ms - REF_LUNES) / 604800000);
    }
    function idxSemanaAFecha(idx) {
        const d = new Date(REF_LUNES + idx * 604800000);
        return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-' + String(d.getUTCDate()).padStart(2, '0');
    }

    // Completa las SEMANAS faltantes entre la primera y la última con datos, para que el
    // eje X sea continuo. Las semanas sin registro quedan con valores null (se ven en blanco).
    function rellenarSemanas(datos) {
        if (!datos || !datos.length) { return datos; }
        const mapa = {};
        datos.forEach(function(d) { mapa[d.FechaDocumento] = d; });
        const claves = Object.keys(mapa).sort();
        const desde  = fechaAIdxSemana(claves[0]);
        const hasta  = fechaAIdxSemana(claves[claves.length - 1]);
        const salida = [];
        for (let i = desde; i <= hasta; i++) {
            const sem = idxSemanaAFecha(i);
            salida.push(mapa[sem] || { FechaDocumento: sem, Demanda: null, Neto: null, DemandaForecast: null, DemandaValorizada: null });
        }
        return salida;
    }

    // Convierte a número, o null si es null/undefined (para dejar huecos en blanco).
    function numOrNull(v) { return (v === null || v === undefined) ? null : (parseFloat(v) || 0); }

    // Dibuja, sobre una línea de tiempo continua (historia + futuro):
    //   - Demanda histórica (barras azul) + Demanda forecast (barras morado)      -> eje de unidades
    //   - Venta neta real (línea roja)
    //     + Demanda valorizada en $ (línea verde = demanda forecast × precio)    -> eje de $
    // El mes seleccionado (mesResaltado) se remarca en la serie de Demanda Forecast.
    function dibujarGrafico(datos) {
        // Series elegidas en el multiselect "Mostrar" (cualquier combinación de las 4).
        const sel = new Set(mostrarMulti ? mostrarMulti.getValores() : []);
        const showHist  = sel.has('demanda_historica');
        const showFc    = sel.has('demanda_forecast');
        const showVenta = sel.has('venta_neta');
        const showVal   = sel.has('demanda_valorizada');
        datos = rellenarSemanas(datos); // eje X continuo (semanas sin dato en blanco)

        // El eje de $ (derecho) solo se separa del de unidades cuando hay series de ambos tipos.
        const hayUnidades = showHist || showFc;
        const hayPesos    = showVenta || showVal;
        const ejeDem = 0;
        const ejeVen = (hayUnidades && hayPesos) ? 1 : 0;

        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Año-Mes');

        const series = {}; const vAxes = {}; let si = 0;
        const colsPesos = [];   // índices de columnas en $ (para formatear su tooltip)
        const pushers   = [];   // (d, esR) -> valores de la fila, en el mismo orden en que se agregan las columnas

        // --- Series de UNIDADES (eje izquierdo) ---
        if (showHist) {
            data.addColumn('number', 'Demanda Histórica');
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#0d6efd' }; // azul
            pushers.push(function(d) { return [ numOrNull(d.Demanda) ]; });
        }
        if (showFc) {
            data.addColumn('number', 'Demanda Forecast');
            data.addColumn({ type: 'string', role: 'style' });      // resaltado del mes seleccionado
            data.addColumn({ type: 'string', role: 'annotation' });
            series[si++] = { type: 'bars', targetAxisIndex: ejeDem, color: '#6f42c1' }; // morado
            pushers.push(function(d, esR) {
                const fc = numOrNull(d.DemandaForecast);
                return [
                    fc,
                    (esR && fc !== null) ? 'color: #fd7e14; stroke-color: #b45309; stroke-width: 2' : null,
                    (esR && fc !== null) ? formatearNumero(fc, fc % 1 === 0 ? 0 : 2) : null
                ];
            });
        }
        if (hayUnidades) {
            vAxes[ejeDem] = { title: 'Demanda (unidades)', minValue: 0 };
        }

        // --- Series de $ (eje derecho, o único si no hay series de unidades) ---
        if (showVenta) {
            colsPesos.push(data.getNumberOfColumns());
            data.addColumn('number', 'Venta Neta');
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#dc3545', lineWidth: 2, pointSize: 4 }; // roja
            pushers.push(function(d) { return [ numOrNull(d.Neto) ]; });
        }
        if (showVal) {
            colsPesos.push(data.getNumberOfColumns());
            data.addColumn('number', 'Demanda Valorizada');
            series[si++] = { type: 'line', targetAxisIndex: ejeVen, color: '#198754', lineWidth: 2, pointSize: 4, lineDashStyle: [3, 3] }; // verde
            pushers.push(function(d) { return [ numOrNull(d.DemandaValorizada) ]; });
        }
        if (hayPesos) {
            vAxes[ejeVen] = { title: 'Venta Neta ($)', minValue: 0, format: 'short' };
        }

        datos.forEach(function(d) {
            const esR = (d.FechaDocumento === mesResaltado);
            let fila  = [ d.FechaDocumento ];
            pushers.forEach(function(p) { fila = fila.concat(p(d, esR)); });
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
            chartArea: { left: 80, right: (ejeVen === 1 ? 120 : 80), top: 50, bottom: 90 },
            hAxis:     { title: 'Año-Mes', slantedText: true, slantedTextAngle: 60, textStyle: { fontSize: 11 } },
            vAxes:     vAxes,
            tooltip:   { trigger: 'focus' }
        };

        new google.visualization.ComboChart(document.getElementById('fc-grafico-canvas')).draw(data, opciones);
    }

    // --- Filtro por rango AÑO-MES (desde / hasta) ---
    // Desplaza un 'yyyy-MM' n meses.
    function shiftMes(ym, n) {
        let y = parseInt(ym.substring(0, 4), 10);
        let m = parseInt(ym.substring(5, 7), 10) - 1 + n;
        y += Math.floor(m / 12);
        m = ((m % 12) + 12) % 12;
        return y + '-' + String(m + 1).padStart(2, '0');
    }
    // Último día ('yyyy-MM-dd') de un mes 'yyyy-MM'.
    function finDeMes(ym) {
        const d = new Date(Date.UTC(parseInt(ym.substring(0, 4), 10), parseInt(ym.substring(5, 7), 10), 0));
        return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-' + String(d.getUTCDate()).padStart(2, '0');
    }
    // Suma n días a una fecha 'yyyy-MM-dd'.
    function masDias(ymd, n) {
        const d = new Date(Date.UTC(+ymd.substring(0, 4), +ymd.substring(5, 7) - 1, +ymd.substring(8, 10)) + n * 86400000);
        return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-' + String(d.getUTCDate()).padStart(2, '0');
    }
    // Una semana (lunes 'mondayYmd', lun..dom) SOLAPA el rango [desdeYm, hastaYm] (ambos 'yyyy-MM').
    function semanaEnRango(mondayYmd, desdeYm, hastaYm) {
        const ini = desdeYm ? desdeYm + '-01' : '0000-01-01';
        const fin = hastaYm ? finDeMes(hastaYm) : '9999-12-31';
        const domingo = masDias(mondayYmd, 6);
        return mondayYmd <= fin && domingo >= ini;   // solapamiento (comparación lexicográfica ISO)
    }

    // Fija el rango por defecto de los inputs año-mes según los datos: desde = 6 meses antes del
    // mes actual (acotado a los datos), hasta = último mes con datos (incluye el forecast).
    // 'yyyy-MM' -> objeto Date (día 1). Flatpickr parsea strings con su dateFormat ('m/Y'), así
    // que hay que pasarle objetos Date (no strings) a setDate/minDate/maxDate.
    function ymAFecha(ym) {
        return new Date(parseInt(ym.substring(0, 4), 10), parseInt(ym.substring(5, 7), 10) - 1, 1);
    }
    function poblarFiltroFechas(datos) {
        const meses = datos.map(function(d) { return String(d.FechaDocumento).substring(0, 7); }).sort();
        const min = meses[0], max = meses[meses.length - 1];
        let desde = shiftMes(new Date().toISOString().substring(0, 7), -6);
        if (desde < min || desde > max) { desde = min; }
        [fpDesde, fpHasta].forEach(function(fp) {
            fp.set('minDate', ymAFecha(min));
            fp.set('maxDate', ymAFecha(max));
        });
        fpDesde.setDate(ymAFecha(desde), false);   // false = fija sin disparar onChange
        fpHasta.setDate(ymAFecha(max), false);
    }

    // Filtra la serie por el rango de años elegido y (re)dibuja; muestra aviso si queda vacío.
    function redibujarConFiltro() {
        if (!serieGraficoActual) { return; }

        const desde = ymDeFp(fpDesde);
        const hasta = ymDeFp(fpHasta);
        const datos = serieGraficoActual.filter(function(d) {
            return semanaEnRango(String(d.FechaDocumento), desde, hasta);
        });

        const $estado = $('#fc-grafico-estado');
        const $canvas = $('#fc-grafico-canvas');

        if (mostrarMulti && mostrarMulti.getValores().length === 0) {
            $canvas.hide().empty();
            $estado.text('Selecciona al menos una serie para mostrar.').show();
            return;
        }
        if (!datos.length) {
            $canvas.hide().empty();
            $estado.text('No hay datos en el rango de fechas seleccionado.').show();
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
        const ths = ['Código Producto', 'Nombre Producto', 'Familia', 'Sub-Familia', 'Total Forecast (52 semanas)', 'Forecast Semana Siguiente'];
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

    // Detalle semana a semana del forecast del producto (tabla bajo el gráfico).
    function renderDetalle(detalle) {
        const $wrap = $('#fc-grafico-detalle-wrap');
        const $tb   = $('#tabla-detalle-grafico-forecast tbody');

        if (!detalle || !detalle.length) {
            $tb.html('<tr><td colspan="4" class="text-center text-muted py-3">Sin filas en el rango de fechas seleccionado.</td></tr>');
            $wrap.show();
            return;
        }

        const guion = '<span class="text-muted">—</span>';
        $tb.html(detalle.map(function(d) {
            const df = (d.demanda_forecast  === null || d.demanda_forecast  === undefined) ? guion : formatearNumero(d.demanda_forecast, 0);
            const dh = (d.demanda_historica === null || d.demanda_historica === undefined) ? guion : formatearNumero(d.demanda_historica, 0);
            return '<tr>'
                 + '<td>' + esc(d.semana) + '</td>'
                 + '<td>' + esc(d.tipo || 'Forecast') + '</td>'
                 + '<td class="text-end">' + df + '</td>'
                 + '<td class="text-end">' + dh + '</td>'
                 + '</tr>';
        }).join(''));
        $wrap.show();
    }

    // Filtra el detalle por el rango de años elegido (mismo que el gráfico) y lo renderiza.
    function renderDetalleFiltrado() {
        if (!detalleForecastActual) { return; }
        const desde = ymDeFp(fpDesde);
        const hasta = ymDeFp(fpHasta);
        const filtrado = detalleForecastActual.filter(function(d) {
            return semanaEnRango(String(d.semana), desde, hasta);
        });
        renderDetalle(filtrado);
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

                // Detalle mes a mes del forecast (tabla bajo el gráfico). Se guarda para renderarlo
                // filtrado por el mismo rango de años que el gráfico (más abajo, tras poblar los años).
                detalleForecastActual = res.detalle || [];

                if (!historia.length && !forecast.length) {
                    $estado.text('Sin datos para este producto.').show();
                    return;
                }
                // Combina historia (demanda/venta reales) + forecast (Demanda Forecast / demanda valorizada en $).
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
                poblarFiltroFechas(combinado);
                renderDetalleFiltrado();  // detalle acotado al rango de años inicial (igual que el gráfico)
                intentarDibujarGrafico(); // dibuja solo si el modal ya terminó de abrirse
            },
            error: function() {
                $estado.text('Error al cargar el gráfico.').show();
            }
        });
    });

    // El cambio del rango año-mes lo maneja el onChange de cada Flatpickr (ver optsPickerFecha).
    // El multiselect "Mostrar" redibuja el gráfico por su propio onCambio.

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
