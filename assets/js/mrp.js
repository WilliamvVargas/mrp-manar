$(document).ready(function() {

    // ----- Google Charts (gráfico del forecast semanal en el detalle del producto) -----
    let gchartsListo = false, gchartPend = null;
    if (window.google && google.charts) {
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(function() {
            gchartsListo = true;
            if (gchartPend) { gchartPend(); gchartPend = null; }
        });
    }
    function cuandoGChartsListo(fn) {
        if (gchartsListo) { fn(); } else { gchartPend = fn; }
    }

    const MESES_CORTO_MRP = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    // Etiqueta compacta de la semana desde su lunes 'yyyy-MM-dd': "jul-26 S3".
    function etiquetaSemanaMrp(mondayYmd) {
        const y = mondayYmd.substring(0, 4);
        const m = parseInt(mondayYmd.substring(5, 7), 10);
        const dia = parseInt(mondayYmd.substring(8, 10), 10);
        return MESES_CORTO_MRP[m] + '-' + y.substring(2) + ' S' + Math.ceil(dia / 7);
    }

    let serieForecastGrafico = null; // última serie del forecast cargada (para dibujar al mostrar la pestaña)

    // Lunes (ISO) de la semana actual en 'yyyy-mm-dd'. El detalle del forecast arranca aquí: no se
    // muestran las semanas ya pasadas (no aportan a la reposición y confunden la lectura).
    function lunesActualMrp() {
        const d = new Date();
        const dow = (d.getDay() + 6) % 7; // 0 = lunes
        d.setDate(d.getDate() - dow);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // Índice de semana (lunes ISO) relativo a un lunes de referencia, para rellenar el eje X continuo.
    const REF_LUNES_MRP = Date.UTC(2020, 0, 6); // 2020-01-06 (lunes)
    function fechaAIdxSemanaMrp(ymd) {
        const ms = Date.UTC(+ymd.substring(0, 4), +ymd.substring(5, 7) - 1, +ymd.substring(8, 10));
        return Math.round((ms - REF_LUNES_MRP) / 604800000);
    }
    function idxSemanaAFechaMrp(idx) {
        const d = new Date(REF_LUNES_MRP + idx * 604800000);
        return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-' + String(d.getUTCDate()).padStart(2, '0');
    }
    // Completa las semanas faltantes entre la primera y la última con demanda null (barra en blanco),
    // para que el eje X sea continuo y no se malinterpreten los saltos entre semanas con forecast.
    function rellenarSemanasMrp(serie) {
        if (!serie || !serie.length) { return serie; }
        const mapa = {};
        serie.forEach(function(r) { mapa[String(r.semana_inicio)] = r; });
        const claves = Object.keys(mapa).sort();
        const desde  = fechaAIdxSemanaMrp(claves[0]);
        const hasta  = fechaAIdxSemanaMrp(claves[claves.length - 1]);
        const salida = [];
        for (let i = desde; i <= hasta; i++) {
            const sem = idxSemanaAFechaMrp(i);
            salida.push(mapa[sem] || { semana_inicio: sem, demanda: null });
        }
        return salida;
    }

    // Dibuja el gráfico del forecast semanal (solo la serie de forecast, barras moradas; sin venta
    // histórica ni filtros). Requiere el contenedor visible (vive en una pestaña), por eso se dibuja
    // al mostrar la pestaña Forecast, no al cargar los datos.
    function dibujarForecastGrafico() {
        const cont = document.getElementById('mrp-forecast-grafico');
        if (!cont || !serieForecastGrafico || !serieForecastGrafico.length) { return; }
        cuandoGChartsListo(function() {
            const lun = lunesActualMrp();
            const data = new google.visualization.DataTable();
            data.addColumn('string', 'Semana');
            data.addColumn('number', 'Demanda Forecast');
            data.addColumn({ type: 'string', role: 'style' });   // color por barra (semana actual en amarillo)
            rellenarSemanasMrp(serieForecastGrafico).forEach(function(r) {
                const dem = (r.demanda === null || r.demanda === undefined) ? null : (parseFloat(r.demanda) || 0);
                const color = (String(r.semana_inicio) === lun) ? 'color: #ffc107' : 'color: #6f42c1'; // amarillo / morado
                data.addRow([etiquetaSemanaMrp(String(r.semana_inicio)), dem, color]);
            });
            const opciones = {
                legend:    { position: 'none' },
                height:    300,
                chartArea: { left: 70, right: 20, top: 20, bottom: 75 },
                hAxis:     { slantedText: true, slantedTextAngle: 60, textStyle: { fontSize: 10 } },
                vAxis:     { minValue: 0, title: 'Demanda (unidades)' },
                tooltip:   { trigger: 'focus' }
            };
            new google.visualization.ColumnChart(cont).draw(data, opciones);
        });
    }

    // Al mostrar la pestaña Forecast, (re)dibuja el gráfico con el ancho real del contenedor.
    $(document).on('shown.bs.tab', '#tab-forecast-btn', dibujarForecastGrafico);

    // Redondea a entero con separador de miles (estilo chileno).
    function formatearEntero(valor) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        const num = parseFloat(valor);
        if (isNaN(num)) { return ''; }
        return String(Math.round(num)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Render numérico consciente del tipo: número crudo para ordenar/buscar, formateado para mostrar.
    function renderNumero(d, type) {
        if (type === 'sort' || type === 'type') {
            const n = parseFloat(d);
            return isNaN(n) ? 0 : n;
        }
        return formatearEntero(d);
    }

    // Recepción en camino que llega ESA semana (time-phased): se muestra solo si llega algo;
    // vacío cuando no hay recepción, para que resalte la semana real de llegada. Ordena por valor.
    function renderRecepcion(d, type) {
        if (type === 'sort' || type === 'type') { const n = parseFloat(d); return isNaN(n) ? 0 : n; }
        const n = parseFloat(d);
        if (isNaN(n) || n <= 0) { return ''; }
        return '<span class="badge bg-success"><i class="bi bi-box-arrow-in-down me-1"></i>'
             + formatearEntero(n) + '</span>';
    }

    // Comprometido que SALE de bodega ESA semana (OV firme con entrega esa semana, por DocDueDate).
    // Análogo a la recepción pero de salida: vacío si no sale nada esa semana. Ordena por valor.
    function renderSalidaOV(d, type) {
        if (type === 'sort' || type === 'type') { const n = parseFloat(d); return isNaN(n) ? 0 : n; }
        const n = parseFloat(d);
        if (isNaN(n) || n <= 0) { return ''; }
        return '<span class="badge bg-danger"><i class="bi bi-box-arrow-up me-1"></i>'
             + formatearEntero(n) + '</span>';
    }

    // Demanda de la semana = demanda EFECTIVA usada por la proyección (max(forecast, OV firme)).
    // Cuando una OV firme supera al forecast, se muestra en azul con una banderita y un tooltip
    // que explica el origen. [#2 tooltip] Para revertir: volver la columna a 'demanda_forecast'
    // con renderNumero y quitar esta función.
    function renderDemanda(d, type, row) {
        const ef = parseFloat(row.demanda_efectiva);
        if (type === 'sort' || type === 'type') { return isNaN(ef) ? 0 : ef; }
        const fc  = parseFloat(row.demanda_forecast) || 0;
        const ov  = parseFloat(row.ov_semana) || 0;
        const idx = Number(row.sem_idx) || 0;
        // Tooltip: demanda proyectada ACUMULADA desde la semana 1 hasta esta (inclusive).
        const acum = 'Acumulado (semanas 1 a ' + (idx + 1) + '): ' + formatearEntero(acumular(demPorProd, row.producto_codigo, idx));
        if (ov > fc) {
            const t = 'Demanda efectiva: OV firme de ' + formatearEntero(ov)
                    + ' (supera el forecast de ' + formatearEntero(fc) + ')\n' + acum;
            return '<span class="text-primary fw-bold" title="' + t + '">'
                 + formatearEntero(ef) + ' <i class="bi bi-flag-fill" style="font-size:.7em"></i></span>';
        }
        return '<span title="' + acum + '">' + formatearEntero(isNaN(ef) ? 0 : ef) + '</span>';
    }

    // Sugerido a reponer: en negrita si hay que reponer (>0), gris si 0. Ordena por el valor crudo.
    // Tooltip: total a reponer ACUMULADO desde la semana 1 hasta esta (inclusive).
    function renderSugerido(d, type, row) {
        if (type === 'sort' || type === 'type') {
            const n = parseFloat(d);
            return isNaN(n) ? 0 : n;
        }
        const n   = parseFloat(d);
        const idx = Number(row && row.sem_idx) || 0;
        const cod = row ? row.producto_codigo : '';
        const acum = 'Acumulado a reponer (semanas 1 a ' + (idx + 1) + '): ' + formatearEntero(acumular(sugPorProd, cod, idx));
        if (isNaN(n) || n <= 0) { return '<span class="text-muted" title="' + acum + '">0</span>'; }
        return '<span class="fw-bold" title="' + acum + '">' + formatearEntero(n) + '</span>';
    }

    // Saldo proyectado: negativo (quiebre de stock) en rojo y negrita (alerta). Ordena por el valor crudo.
    function renderSaldo(d, type) {
        if (type === 'sort' || type === 'type') { const n = parseFloat(d); return isNaN(n) ? 0 : n; }
        const n = parseFloat(d);
        if (isNaN(n)) { return ''; }
        const txt = (n < 0 ? '-' : '') + formatearEntero(Math.abs(n));
        return (n < 0) ? '<span class="text-danger fw-bold">' + txt + '</span>' : txt;
    }

    // Color de barra según el estado de la semana (igual que los badges de Estado).
    const COLOR_ESTADO = { quiebre: '#dc3545', ajustado: '#ffc107', ok: '#198754' };

    // Tendencia de la demanda del horizonte: mini bar chart SVG. Cada barra = una semana
    // (desde la actual hacia el final): la ALTURA es la demanda y el COLOR es el estado de
    // esa semana (rojo=quiebre, amarillo=ajustado, verde=ok).
    function renderTendencia(d, type) {
        if (type !== 'display') { return ''; }
        const arr = Array.isArray(d) ? d : [];
        if (arr.length < 1) { return '<span class="text-muted">—</span>'; }
        const w = 104, h = 18, gap = 1;
        // Ancho de barra sobre un nº FIJO de slots (= barsTend del controlador), no sobre las barras
        // presentes: al final del forecast se dibujan menos barras y el resto queda como espacio en
        // blanco (barras "invisibles") → ancho y posición constantes en todas las filas.
        const SLOTS = 16;
        const bw = Math.max(1, (w - gap * (SLOTS - 1)) / SLOTS);
        const max = arr.reduce(function(m, o) { const v = Number(o.d) || 0; return v > m ? v : m; }, 0) || 1;
        let bars = '';
        arr.forEach(function(o, i) {
            const v  = Number(o.d) || 0;
            const bh = Math.max(1, (v / max) * (h - 1));   // altura mínima 1px para que se vea
            const x  = i * (bw + gap);
            const y  = h - bh;
            const fill = COLOR_ESTADO[o.e] || '#0d6efd';
            bars += '<rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) + '" '
                  + 'width="' + bw.toFixed(1) + '" height="' + bh.toFixed(1) + '" '
                  + 'rx="0.4" fill="' + fill + '"/>';
        });
        return '<svg width="' + w + '" height="' + h + '" style="vertical-align:middle">'
             + bars + '</svg>';
    }

    // Estado de abastecimiento del producto: badge según quiebre / ajustado / ok.
    function renderEstado(d, type, row) {
        if (type !== 'display') { return d || ''; }
        if (d === 'quiebre') {
            const n = Number(row.estado_sem) || 0;
            const cuando = (n > 0) ? ('en ' + n + ' sem') : 'semanal actual';
            return '<span class="badge bg-danger">Quiebre ' + cuando + '</span>';
        }
        if (d === 'ajustado') { return '<span class="badge bg-warning text-dark">Ajustado</span>'; }
        return '<span class="badge bg-success">OK</span>';
    }

    // Índice por producto: demanda proyectada (efectiva) por semana (sem_idx), lead time y el
    // stock de seguridad de la semana actual (con los datos de su fórmula para el tooltip).
    // Alimenta el Stock de Seguridad de la ficha. Se reconstruye en cada carga.
    let demPorProd  = {};
    let sugPorProd  = {};
    let leadPorProd = {};
    let segEstPorProd = {};   // SS de la semana actual (clásico o estacional, ver tooltipSegFicha)
    let segInfoPorProd = {};  // datos del tooltip: método, σ, CV y su origen, semanas de historia, demanda prom. LT
    let zPorProd      = {};   // factor Z (nivel de servicio) de la empresa
    function indexarProductos(filas) {
        demPorProd = {}; sugPorProd = {}; leadPorProd = {};
        segEstPorProd = {}; segInfoPorProd = {}; zPorProd = {};
        (filas || []).forEach(function(f) {
            const c = f.producto_codigo;
            if (demPorProd[c] === undefined) {
                demPorProd[c] = []; sugPorProd[c] = [];
                leadPorProd[c]   = Number(f.lead_time) || 0;
                segEstPorProd[c] = Number(f.stock_seguridad_est) || 0;
                segInfoPorProd[c] = {
                    metodo: f.ss_metodo || 'clasico', sigma: Number(f.sigma_semanal) || 0,
                    cv: Number(f.cv_seguridad) || 0, origen: f.cv_origen || '',
                    hist: Number(f.cv_semanas_hist) || 0, dem: Number(f.dem_prom_lt) || 0
                };
                zPorProd[c]      = Number(f.z_seguridad) || 0;
            }
            if (f.semana) {
                const i = Number(f.sem_idx) || 0;
                demPorProd[c][i] = Number(f.demanda_efectiva) || 0;
                sugPorProd[c][i] = Number(f.sugerido) || 0;
            }
        });
    }

    // Stock de Seguridad (FICHA) = SS de la semana actual, calculado en el servidor. Dos fórmulas:
    //  - Producto CON historia (>= 26 sem): clásico fijo, SS = Z × σ × √LT (σ de su demanda real).
    //  - Producto SIN historia suficiente: estacional, SS = Z × CV × demanda promedio de las LT
    //    semanas desde hoy × √LT, con el CV (variabilidad) de su grupo. Sigue al forecast: sube al
    //    entrar a temporada y baja al salir.
    // Z = nivel de servicio de la empresa; LT = lead time en semanas.
    function stockSegFicha(cod) {
        return segEstPorProd[cod] || 0;
    }

    // Tooltip explicativo de la fórmula del stock de seguridad de la ficha.
    function tooltipSegFicha(cod) {
        const z  = zPorProd[cod] || 0;
        const i  = segInfoPorProd[cod] || {};
        const lt = leadPorProd[cod] || 0;
        const fmt2 = function(v) { return Number(v || 0).toLocaleString('es-CL', { maximumFractionDigits: 2 }); };
        if (i.metodo !== 'estacional') {
            return 'SS = Z × σ × √LT = ' + fmt2(z) + ' × ' + formatearEntero(i.sigma) + ' × √' + lt
                 + ' = ' + formatearEntero(stockSegFicha(cod))
                 + '  (σ ' + formatearEntero(i.sigma) + '/sem de demanda real, ' + i.hist + ' sem de historia)';
        }
        const origen = (i.origen === 'grupo') ? 'de su grupo' : 'de la empresa (su grupo no tiene productos con historia)';
        return 'Producto con poca historia (' + i.hist + ' sem): SS estacional de la semana actual = '
             + 'Z × CV × demanda prom. LT × √LT = ' + fmt2(z) + ' × ' + fmt2(i.cv) + ' × '
             + formatearEntero(i.dem) + ' × √' + lt + ' = ' + formatearEntero(stockSegFicha(cod))
             + '  (CV ' + origen + '. Sigue al forecast: cambia semana a semana)';
    }

    // Suma ACUMULADA de un valor por semana (mapa cod->[valor por sem_idx]) desde la 1ª semana del
    // producto hasta la semana idx (inclusive). Alimenta los tooltips de Demanda y Sugerido.
    function acumular(mapa, cod, idx) {
        const arr = mapa[cod] || [];
        let sum = 0;
        for (let i = 0; i <= idx && i < arr.length; i++) { sum += Number(arr[i]) || 0; }
        return sum;
    }

    // Contenido de la celda "Producto" (info del producto apilada en vertical).
    function celdaProducto(row) {
        const esc = function(s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
        const linea = function(k, v, cls) {
            return '<div class="mrp-pl' + (cls ? ' ' + cls : '') + '"><span class="k">' + k + '</span>'
                 + '<span class="v">' + esc(v) + '</span></div>';
        };
        return '<div class="mrp-cod-row">'
             +     '<span class="badge text-bg-secondary mrp-cod-badge" title="Filtrar la consulta por la familia, sub-familia y proveedor de este producto">' + esc(row.producto_codigo) + '</span>'
             +     '<span class="mrp-est">' + renderEstado(row.estado, 'display', row) + '</span>'
             + '</div>'
             + '<div class="mrp-nom">' + esc(row.producto_nombre) + '</div>'
             + (row.origen === 'manual'
                 ? '<div class="mb-1"><span class="badge bg-info text-dark" '
                   + 'title="Forecast cargado manualmente (personalizado); manda sobre Prophet">Manual</span></div>'
                 : '')
             + linea('Familia', row.familia)
             + linea('Sub-Familia', row.sub_familia)
             + linea('Proveedor', row.proveedor)
             + linea('Lead Time', (row.lead_time || 0) + ' sem')
             + linea('Stock Físico', formatearEntero(row.stock_wms))
             + linea('Stock Mín', (Number(row.stock_min) > 0) ? formatearEntero(row.stock_min) : '—', 'mrp-min')
             + linea('Stock Máx', (Number(row.stock_max) > 0) ? formatearEntero(row.stock_max) : '—', 'mrp-max')
             + '<div class="mrp-pl mrp-seg" title="' + tooltipSegFicha(row.producto_codigo) + '">'
             +     '<span class="k">Stock Seguridad</span>'
             +     '<span class="v">' + formatearEntero(stockSegFicha(row.producto_codigo)) + '</span>'
             + '</div>'
             + linea('Próx. Lote por Vencer', (row.controla_vencimiento === false) ? 'Sin vencimiento'
                     : ((row.dias_prox_venc == null || row.dias_prox_venc === '')
                         ? '—' : formatearEntero(row.dias_prox_venc) + ' días'))
             + '<div class="mrp-acciones">'
             +     '<button type="button" class="btn btn-sm btn-outline-dark btn-mrp-detalle" title="Ver detalle">'
             +         '<i class="bi bi-eye me-1"></i> Ver detalle'
             +     '</button>'
             + '</div>';
    }

    // Fecha 'yyyy-mm-dd' -> 'dd-mm-yyyy'.
    function fmtFecha(s) {
        if (!s) { return ''; }
        const p = String(s).substring(0, 10).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : s;
    }

    // Rango de semanas cubierto por la demanda: "desde a hasta" (o una sola si coinciden).
    function renderSemanas(d, type, row) {
        if (type === 'sort' || type === 'type') { return row.semana_desde || ''; }
        const desde = fmtFecha(row.semana_desde);
        const hasta = fmtFecha(row.semana_hasta);
        if (!desde) { return ''; }
        return (desde === hasta) ? desde : (desde + ' a ' + hasta);
    }

    // Días al próximo vencimiento: número plano; "—" si el producto no tiene stock vigente.
    function renderDiasVenc(d, type) {
        if (type === 'sort' || type === 'type') {
            return (d === null || d === undefined || d === '') ? 999999 : parseFloat(d);
        }
        if (d === null || d === undefined || d === '') { return '—'; }
        return formatearEntero(d);
    }

    // ============================================================
    //  Vista en PANELES: un panel por producto = ficha (columna izquierda, FUERA de la tabla)
    //  + tabla con sus semanas. Los datos se cargan una vez (client-side) y aquí se agrupan,
    //  filtran y paginan POR PRODUCTO. Reemplaza al DataTable de una fila por producto × semana,
    //  donde la ficha vivía dentro de la 1ª celda y estiraba la fila de la semana 1.
    // ============================================================

    let productos      = [];   // [{ cod, base, semanas: [filas por sem_idx] }] en orden de urgencia
    let productoPorCod = {};   // cod -> producto
    let paginaActual   = 1;

    // Escapa texto para insertarlo como HTML.
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function mostrarAlerta(msg) {
        $('#alert-container').html(
            '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
            $('<div>').text(msg || 'No se pudo cargar el MRP.').html() +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        );
    }

    // Agrupa las filas (producto × semana) por producto. Orden de productos = el mismo de la vista
    // anterior: urgencia (sugerido_total, desc) y luego nombre. Dentro del producto, semana
    // cronológica. 'base' = fila de su 1ª semana (los campos de la ficha se repiten en todas).
    function agruparProductos(filas) {
        const mapa  = {};
        const lista = [];
        (filas || []).forEach(function(f) {
            const c = f.producto_codigo;
            if (!mapa[c]) { mapa[c] = { cod: c, base: f, semanas: [] }; lista.push(mapa[c]); }
            if (f.semana) { mapa[c].semanas.push(f); }
        });
        lista.forEach(function(p) {
            p.semanas.sort(function(a, b) { return (Number(a.sem_idx) || 0) - (Number(b.sem_idx) || 0); });
            if (p.semanas.length) { p.base = p.semanas[0]; }
        });
        lista.sort(function(a, b) {
            const u = (Number(b.base.sugerido_total) || 0) - (Number(a.base.sugerido_total) || 0);
            if (u !== 0) { return u; }
            return String(a.base.producto_nombre || '').localeCompare(String(b.base.producto_nombre || ''), 'es');
        });
        productos      = lista;
        productoPorCod = mapa;
    }

    // ¿El producto pasa los filtros de la consulta? (todos a nivel PRODUCTO)
    function productoPasaFiltros(p) {
        const b = p.base;

        // Familia / Sub-Familia / Proveedor: coincidencia exacta.
        const fam = $('#filtro-familia').val() || '';
        const sub = $('#filtro-sub-familia').val() || '';
        const prv = $('#filtro-proveedor').val() || '';
        if (fam && (b.familia || '') !== fam)     { return false; }
        if (sub && (b.sub_familia || '') !== sub) { return false; }
        if (prv && (b.proveedor || '') !== prv)   { return false; }

        // En Quiebre: ''=Ver Todo · 'con' · 'sin' · '0'..'7' = quiebre en N semanas · '8+'.
        // estado_sem solo es válido si estado === 'quiebre'.
        const q = $('#filtro-quiebre').val() || '';
        if (q !== '') {
            const enQuiebre = (b.estado === 'quiebre');
            if (q === 'con') { if (!enQuiebre) { return false; } }
            else if (q === 'sin') { if (enQuiebre) { return false; } }
            else {
                const sem = Number(b.estado_sem) || 0;
                if (!enQuiebre) { return false; }
                if (q === '8+' ? sem < 8 : sem !== parseInt(q, 10)) { return false; }
            }
        }

        // Consulta: todas las palabras deben aparecer en código, nombre, familia, sub-familia o proveedor.
        const txt = ($('#consulta-mrp').val() || '').trim().toLowerCase();
        if (txt) {
            const hay = [b.producto_codigo, b.producto_nombre, b.familia, b.sub_familia, b.proveedor]
                .join(' ').toLowerCase();
            const ok = txt.split(/\s+/).every(function(t) { return hay.indexOf(t) !== -1; });
            if (!ok) { return false; }
        }
        return true;
    }

    // Columnas de la tabla de semanas de cada panel (mismas de la vista anterior). 'w' = ancho FIJO
    // (%): todas las tablas usan los mismos anchos (table-layout: fixed + colgroup), así las
    // columnas quedan alineadas entre paneles sin importar el contenido de cada producto.
    const COLS_MRP = [
        { th: 'N° Sem', w: 6, cls: 'text-center', tip: 'Número correlativo de semana desde la actual (1 = esta semana, 2 = la próxima…), en orden por fecha' },
        { th: 'Semana', w: 10, cls: 'text-center', tip: 'Semana del forecast (lunes ISO)' },
        { th: 'En Pedido', w: 11, cls: 'text-end', tip: 'Mercadería EN CAMINO (OC + reserva + producción) que llega esa semana, según su fecha esperada. Las OC vencidas y aún abiertas se acumulan en la primera semana.' },
        { th: 'Comprometido', w: 11, cls: 'text-end', tip: 'Sale de bodega esa semana: Órdenes de Venta (por fecha de entrega) + consumo de componentes por órdenes de producción' },
        { th: 'Stock Teórico', w: 10, cls: 'text-end', tip: 'Inventario teórico acumulado al cierre de esa semana, solo con documentos comprometidos: arranca del stock físico y cada semana suma En Pedido y resta Comprometido. No incluye forecast ni reposición sugerida.' },
        { th: 'Demanda Proyectada', w: 11, cls: 'text-end mrp-sep-left', tip: 'Demanda efectiva de esa semana = mayor entre el forecast y las OV firmes con entrega esa semana. En azul cuando manda una OV.' },
        { th: 'Saldo Proyectado de Inventario', w: 12, cls: 'text-end', tip: 'Saldo disponible proyectado al cierre de esa semana (tras la demanda, las recepciones en camino y las reposiciones planificadas)' },
        { th: 'Sugerido a Reponer', w: 11, cls: 'text-end', tip: 'Cantidad a ORDENAR esa semana para no quebrar stock (ya desfasada por el lead time)' },
        { th: 'Tendencia', w: 18, cls: 'text-center', tip: 'Tendencia de la demanda: próximas 16 semanas hacia adelante (altura = demanda, color = estado: rojo quiebre / amarillo ajustado / verde ok)' }
    ];
    const THEAD_MRP = '<colgroup>'
        + COLS_MRP.map(function(c) { return '<col style="width:' + c.w + '%">'; }).join('')
        + '</colgroup>'
        + '<thead class="table-dark"><tr>'
        + COLS_MRP.map(function(c) { return '<th class="' + c.cls + '" title="' + esc(c.tip) + '">' + c.th + '</th>'; }).join('')
        + '</tr></thead>';

    // Una fila de semana (reutiliza los mismos renders de la vista anterior).
    function filaSemanaHtml(r) {
        return '<tr>'
             + '<td class="text-center">' + ((Number(r.sem_idx) || 0) + 1) + '</td>'
             + '<td class="text-center">' + fmtFecha(r.semana) + '</td>'
             + '<td class="text-end">' + renderRecepcion(r.recepcion, 'display') + '</td>'
             + '<td class="text-end">' + renderSalidaOV(r.comprometido_semana, 'display') + '</td>'
             + '<td class="text-end">' + renderNumero(r.stock_teorico, 'display') + '</td>'
             + '<td class="text-end mrp-tip-cell mrp-sep-left">' + renderDemanda(r.demanda_efectiva, 'display', r) + '</td>'
             + '<td class="text-end">' + renderSaldo(r.saldo_proyectado, 'display') + '</td>'
             + '<td class="text-end mrp-tip-cell">' + renderSugerido(r.sugerido, 'display', r) + '</td>'
             + '<td class="text-center">' + renderTendencia(r.tendencia, 'display') + '</td>'
             + '</tr>';
    }

    // Panel de un producto: ficha a la izquierda + tabla con las semanas del horizonte (h).
    function panelHtml(p, h) {
        const visibles = p.semanas.filter(function(r) { return (Number(r.sem_idx) || 0) < h; });
        const cuerpo = visibles.length
            ? visibles.map(filaSemanaHtml).join('')
            : '<tr><td colspan="' + COLS_MRP.length + '" class="text-center text-muted">Sin semanas de forecast</td></tr>';
        return '<div class="mrp-panel" data-cod="' + esc(p.cod) + '">'
             +   '<aside class="mrp-ficha"><div class="mrp-ficha-inner">' + celdaProducto(p.base) + '</div></aside>'
             +   '<div class="mrp-panel-tabla">'
             +     '<table class="table table-sm table-hover align-middle tabla-compacta mb-0 mrp-tabla">'
             +       THEAD_MRP + '<tbody>' + cuerpo + '</tbody>'
             +     '</table>'
             +   '</div>'
             + '</div>';
    }

    // Dibuja la página actual de paneles + info + paginación (todo client-side).
    function renderPaneles() {
        const h      = parseInt($('#mrp-horizonte').val() || '4', 10);
        const porPag = parseInt($('#mrp-por-pagina').val() || '25', 10);   // -1 = todos
        const filtrados = productos.filter(productoPasaFiltros);
        const total   = filtrados.length;
        const tam     = (porPag > 0) ? porPag : Math.max(total, 1);
        const paginas = Math.max(1, Math.ceil(total / tam));
        if (paginaActual > paginas) { paginaActual = paginas; }
        if (paginaActual < 1)       { paginaActual = 1; }
        const ini = (paginaActual - 1) * tam;
        const pag = filtrados.slice(ini, ini + tam);

        $('#mrp-paneles').html(pag.length
            ? pag.map(function(p) { return panelHtml(p, h); }).join('')
            : '<div class="text-center text-muted py-4">No hay productos que coincidan con los filtros.</div>');

        // Mismos textos que el resto del sistema (idioma es-ES de DataTables), con "productos".
        const filtrado = (total !== productos.length)
            ? ' (filtrado de un total de ' + formatearEntero(productos.length) + ' productos)' : '';
        $('#mrp-info').text(total
            ? 'Mostrando ' + formatearEntero(ini + 1) + ' a ' + formatearEntero(ini + pag.length)
              + ' de ' + formatearEntero(total) + ' productos' + filtrado
            : 'Mostrando productos del 0 al 0' + filtrado);
        renderPaginacion(paginas);
    }

    // Botones de número igual que DataTables ("simple_numbers", máx. 7): todas si caben; si no,
    // primera … vecinas de la actual … última. Índices 0-based; 'e' = puntos suspensivos.
    function numerosPaginacion(pag, paginas) {
        const BOTONES = 7, MITAD = 3;
        const rango = function(a, b) { const r = []; for (let i = a; i < b; i++) { r.push(i); } return r; };
        if (paginas <= BOTONES) { return rango(0, paginas); }
        if (pag <= MITAD) { return rango(0, BOTONES - 2).concat(['e', paginas - 1]); }
        if (pag >= paginas - 1 - MITAD) { return [0, 'e'].concat(rango(paginas - (BOTONES - 2), paginas)); }
        return [0, 'e', pag - 1, pag, pag + 1, 'e', paginas - 1];
    }

    // Paginación con el mismo marcado que DataTables (paginate_button page-item / page-link):
    // Anterior · números · Siguiente. Se muestra siempre (con una sola página, deshabilitada).
    function renderPaginacion(paginas) {
        const item = function(label, pag, clase, disabled, active) {
            return '<li class="paginate_button page-item' + (clase ? ' ' + clase : '')
                 + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">'
                 + '<a href="#" class="page-link"' + (disabled ? '' : ' data-pag="' + pag + '"') + '>' + label + '</a></li>';
        };
        let html = item('Anterior', paginaActual - 1, 'previous', paginaActual <= 1, false);
        numerosPaginacion(paginaActual - 1, paginas).forEach(function(n) {
            html += (n === 'e')
                ? item('…', 0, '', true, false)
                : item(String(n + 1), n + 1, '', false, (n + 1) === paginaActual);
        });
        html += item('Siguiente', paginaActual + 1, 'next', paginaActual >= paginas, false);
        $('#mrp-paginacion').html(html);
    }

    $('#mrp-paginacion').on('click', 'a.page-link', function(e) {
        e.preventDefault();
        const li = $(this).closest('.page-item');
        if (li.hasClass('disabled') || li.hasClass('active') || $(this).attr('data-pag') === undefined) { return; }
        paginaActual = parseInt($(this).attr('data-pag'), 10) || 1;
        renderPaneles();
        const tb = document.getElementById('mrp-toolbar');
        if (tb) { tb.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    // Carga los datos del MRP (una sola vez; se agrupan, filtran y paginan client-side).
    function cargarMrp(onDone) {
        const horizonte = $('#mrp-horizonte').val() || '4';
        $.ajax({
            url: 'controllers/mrp_controller.php?action=listar',
            type: 'GET',
            data: { horizonte: horizonte },
            dataType: 'json',
            complete: function() {
                $('#mrp-loading').remove();
                if (typeof onDone === 'function') { onDone(); }
            },
            success: function(res) {
                if (res.status !== 'success') {
                    mostrarAlerta(res.message);
                    return;
                }
                const filas = res.data || [];
                indexarProductos(filas);   // la ficha y los tooltips usan demPorProd/sugPorProd/...
                poblarProveedores(filas);
                agruparProductos(filas);
                $('#mrp-vista').removeClass('d-none');
                renderPaneles();
            },
            error: function() {
                mostrarAlerta('Error al cargar el MRP.');
            }
        });
    }

    // Puebla el combo de Proveedor con los proveedores DISTINTOS presentes en los datos
    // cargados (que son solo los productos con forecast). Conserva la selección si sigue.
    function poblarProveedores(filas) {
        const $sel = $('#filtro-proveedor');
        if (!$sel.length) { return; }
        const prev = $sel.val();
        const set = {};
        (filas || []).forEach(function(f) {
            const p = (f.proveedor == null ? '' : String(f.proveedor)).trim();
            if (p) { set[p] = true; }
        });
        const provs = Object.keys(set).sort(function(a, b) { return a.localeCompare(b, 'es'); });
        $sel.empty().append('<option value="">Todos</option>');
        provs.forEach(function(p) { $sel.append($('<option>').val(p).text(p)); });
        if (prev && set[prev]) { $sel.val(prev); }
    }

    // Cualquier cambio de filtro vuelve a la página 1.
    function aplicarFiltros() {
        paginaActual = 1;
        renderPaneles();
    }

    $('#consulta-mrp').on('input', aplicarFiltros);
    $('#filtro-familia, #filtro-sub-familia, #filtro-proveedor, #filtro-quiebre, #mrp-por-pagina').on('change', aplicarFiltros);

    // Exportar a Excel: el servidor reconstruye el plan (todas las semanas de todos los productos,
    // sin filtros de pantalla) y devuelve un .xlsx. Se descarga vía un <iframe> oculto para que un
    // posible error no saque al usuario de la página. El cálculo cruza SAP/WMS, así que puede tardar.
    $('#btn-exportar-mrp').on('click', function() {
        const $btn = $(this);
        const original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Generando...');
        const restaurar = function() { $btn.prop('disabled', false).html(original); };

        const $ifr = $('<iframe>', { style: 'display:none' });
        $ifr.on('load', restaurar);                       // la respuesta (descarga) llegó
        $ifr.attr('src', 'controllers/mrp_controller.php?action=exportar').appendTo('body');
        setTimeout(restaurar, 60000);                     // respaldo por si el evento no dispara
        setTimeout(function() { $ifr.remove(); }, 120000);
    });

    // El horizonte NO recalcula: el plan viene calculado sobre una ventana fija. Cambiarlo solo
    // decide CUÁNTAS semanas se ven por producto (mantiene la página actual).
    $('#mrp-horizonte').on('change', renderPaneles);

    // Botón "Limpiar": vacía filtros y buscador, y redibuja sin filtros.
    $('#btn-limpiar-filtros').on('click', function() {
        $('#consulta-mrp, #filtro-familia, #filtro-sub-familia, #filtro-proveedor, #filtro-quiebre').val('');
        aplicarFiltros();
    });

    // ============================================================
    //  Detalle de un registro (columna Acciones): modal Campo | Valor.
    // ============================================================

    function seccionDet(titulo) {
        return '<tr class="table-secondary"><th colspan="2" class="small fw-bold text-uppercase">' + titulo + '</th></tr>';
    }
    function filaDet(label, valor) {
        const v = (valor === '' || valor === null || valor === undefined) ? '<span class="text-muted">—</span>' : valor;
        return '<tr><th class="fw-semibold small text-nowrap" style="width:55%;">' + label + '</th>'
             + '<td class="small">' + v + '</td></tr>';
    }
    const textoDet = function(v) { return (v === null || v === undefined) ? '' : $('<div>').text(v).html(); };
    const numDet   = function(v) { const s = formatearEntero(v); return s === '' ? '0' : s; };

    // Arma el cuerpo del modal de detalle a partir de la fila del DataTable.
    function filasDetalleMrp(f) {
        const cod = f.producto_codigo;
        // Stock Mín/Máx y Stock Seguridad: los MISMOS valores (y tooltip) que la ficha del producto.
        const minTxt = (Number(f.stock_min) > 0) ? numDet(f.stock_min) : '';
        const maxTxt = (Number(f.stock_max) > 0) ? numDet(f.stock_max) : '';
        const segTxt = '<span title="' + tooltipSegFicha(cod).replace(/"/g, '&quot;') + '">'
                     + numDet(stockSegFicha(cod)) + '</span>';
        return seccionDet('Producto')
             + filaDet('Código',      textoDet(f.producto_codigo))
             + filaDet('Nombre',      textoDet(f.producto_nombre))
             + filaDet('Familia',     textoDet(f.familia))
             + filaDet('Sub-Familia', textoDet(f.sub_familia))
             + filaDet('Proveedor',   textoDet(f.proveedor))
             + seccionDet('Planificación')
             + filaDet('Lead Time (semanas)', numDet(f.lead_time))
             + filaDet('Demanda (Forecast)',  numDet(f.demanda_forecast))
             + filaDet('Stock Mín',           minTxt)
             + filaDet('Stock Máx',           maxTxt)
             + filaDet('Stock Seguridad',     segTxt)
             + seccionDet('Disponibilidad')
             + filaDet('Stock Físico',                numDet(f.stock_wms))
             + filaDet('Stock que vence en ≤30 días', (f.controla_vencimiento === false) ? 'Sin vencimiento' : numDet(f.stock_por_vencer))
             + filaDet('Próximo vencimiento (días)',  (f.controla_vencimiento === false) ? 'Sin vencimiento' : renderDiasVenc(f.dias_prox_venc, 'display'))
             + seccionDet('Compromisos y entradas')
             + filaDet('Comprometido',   numDet(f.comprometido))
             + filaDet('En Pedido',      numDet(f.en_pedido))
             + filaDet('En Producción',  numDet(f.en_produccion))
             + filaDet('Stock Teórico',  numDet(f.stock_teorico))
             + seccionDet('Resultado')
             + filaDet('Sugerido a Reponer', '<span class="fw-bold">' + numDet(f.sugerido) + '</span>');
    }

    // Pestaña "Stock": detalle de lotes/pallets del producto (WMS), ordenado por vencimiento.
    function cargarDetalleStock(cod) {
        const $estado = $('#mrp-stock-estado');
        const $wrap   = $('#mrp-stock-wrap');
        const $tbody  = $('#tabla-mrp-stock');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_stock',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el stock.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin stock en el WMS para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    html += '<tr>'
                         + '<td>' + textoDet(r.Lote) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FIngreso) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FVencimiento) + '</td>'
                         + '<td class="text-end">'    + formatearEntero(r.DiasParaVencer) + '</td>'
                         + '<td class="text-center">' + textoDet(r.Ubicacion) + '</td>'
                         + '<td class="text-center">' + textoDet(r.EstadoPallet) + '</td>'
                         + '<td class="text-center">' + textoDet(r.Vencimiento) + '</td>'
                         + '<td class="text-end">'    + formatearEntero(r.Cantidad) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar el stock.').show();
            }
        });
    }

    // Pestaña "Comprometido": líneas de ODV abiertas del producto (SAP, bodega 010).
    function cargarDetalleComprometido(cod) {
        const $estado = $('#mrp-comprometido-estado');
        const $wrap   = $('#mrp-comprometido-wrap');
        const $tbody  = $('#tabla-mrp-comprometido');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_comprometido',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el comprometido.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin ventas comprometidas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    const cliente = (r.CodCliente ? textoDet(r.CodCliente) + ' — ' : '') + textoDet(r.Cliente);
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenVenta) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td>' + cliente + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Cantidad) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar el comprometido.').show();
            }
        });
    }

    // Pestaña "En Pedido": líneas de OC abiertas del producto (SAP, bodega 010).
    function cargarDetalleEnPedido(cod) {
        const $estado = $('#mrp-en-pedido-estado');
        const $wrap   = $('#mrp-en-pedido-wrap');
        const $tbody  = $('#tabla-mrp-en-pedido');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_en_pedido',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar las órdenes de compra.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin órdenes de compra abiertas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    const proveedor = (r.CodProveedor ? textoDet(r.CodProveedor) + ' — ' : '') + textoDet(r.Proveedor);
                    // Origen: bandera (flag-icons por ISO2) + país del proveedor real del documento.
                    const codPais = String(r.PaisCod || '').toLowerCase();
                    const bandera = /^[a-z]{2}$/.test(codPais) ? '<span class="fi fi-' + codPais + ' me-1"></span>' : '';
                    const origen  = r.Pais ? (bandera + textoDet(r.Pais)) : '<span class="text-muted">—</span>';
                    // IMP01 = importación en tránsito; se resalta para distinguirla de la bodega local.
                    const almacen = (String(r.Almacen).toUpperCase() === 'IMP01')
                        ? '<span class="badge bg-info text-dark">IMP01</span>'
                        : textoDet(r.Almacen);
                    // Distingue Factura de Reserva (compra facturada por recibir) de la OC normal.
                    const tipoDoc = (r.TipoDoc === 'Factura de Reserva')
                        ? '<span class="badge bg-warning text-dark">Factura de Reserva</span>'
                        : textoDet(r.TipoDoc);
                    // El MRP proyecta la llegada en la Fecha Ingreso Bodega; si falta, usa la
                    // Fecha Comprometida (se marca para que se note de dónde sale la semana).
                    const fechaIngreso = r.FechaIngresoBodega
                        ? fmtFecha(r.FechaIngresoBodega)
                        : '<span class="badge bg-light text-muted border" title="Sin Fecha Ingreso Bodega: el pronóstico usa la Fecha Comprometida">Usa comprometida</span>';
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenCompra) + '</td>'
                         + '<td class="text-center">' + tipoDoc + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td class="text-center">' + fechaIngreso + '</td>'
                         + '<td class="text-center">' + almacen + '</td>'
                         + '<td>' + proveedor + '</td>'
                         + '<td>' + origen + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Cantidad) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar las órdenes de compra.').show();
            }
        });
    }

    // Pestaña "En Producción": órdenes de producción liberadas del producto (SAP, OWOR).
    function cargarDetalleEnProduccion(cod) {
        const $estado = $('#mrp-en-produccion-estado');
        const $wrap   = $('#mrp-en-produccion-wrap');
        const $tbody  = $('#tabla-mrp-en-produccion');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_en_produccion',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar las órdenes de producción.').show();
                    return;
                }
                const filas = res.data || [];
                if (!filas.length) {
                    $estado.text('Sin órdenes de producción liberadas para este producto.').show();
                    return;
                }
                let html = '';
                filas.forEach(function(r) {
                    html += '<tr>'
                         + '<td>' + textoDet(r.OrdenProduccion) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.Fecha) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.FechaEntrega) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Planificada) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Completada) + '</td>'
                         + '<td class="text-end">' + formatearEntero(r.Pendiente) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $estado.hide();
                $wrap.show();
            },
            error: function() {
                $estado.text('Error al cargar las órdenes de producción.').show();
            }
        });
    }

    // Pestaña "Forecast Semanal": serie semana a semana del producto (forecast_x_producto).
    function cargarDetalleForecast(cod) {
        const $estado = $('#mrp-forecast-estado');
        const $wrap   = $('#mrp-forecast-wrap');
        const $tbody  = $('#tabla-mrp-forecast');

        $estado.text('Cargando...').show();
        $wrap.hide();
        $('#mrp-forecast-grafico-wrap').hide();
        serieForecastGrafico = null;
        $tbody.empty();
        $('#mrp-forecast-total').text('—');
        $('#mrp-forecast-nsem').text('—');

        $.ajax({
            url: 'controllers/mrp_controller.php?action=detalle_forecast',
            type: 'GET',
            data: { itemcode: cod },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    $estado.text(res.message || 'No se pudo cargar el forecast.').show();
                    return;
                }
                // Solo desde la semana actual en adelante (sin semanas pasadas).
                const lun   = lunesActualMrp();
                const filas = (res.data || []).filter(function(r) { return String(r.semana_inicio) >= lun; });
                if (!filas.length) {
                    $estado.text('Sin forecast para este producto.').show();
                    return;
                }
                let html = '', total = 0;
                filas.forEach(function(r) {
                    const dem = parseFloat(r.demanda) || 0;
                    total += dem;
                    const semanaIso = r.iso_year + '-W' + String(r.iso_week).padStart(2, '0');
                    const esActual  = String(r.semana_inicio) === lun;   // resalta la semana en curso
                    html += '<tr' + (esActual ? ' class="fw-bold"' : '') + '>'
                         + '<td class="text-center">' + textoDet(semanaIso) + '</td>'
                         + '<td class="text-center">' + fmtFecha(r.semana_inicio) + '</td>'
                         + '<td class="text-end">' + formatearEntero(dem) + '</td>'
                         + '</tr>';
                });
                $tbody.html(html);
                $('#mrp-forecast-total').text(formatearEntero(total));
                $('#mrp-forecast-nsem').text(filas.length);
                $estado.hide();
                $wrap.show();

                // Serie para el gráfico; se dibuja ahora si la pestaña ya está visible, o al mostrarla.
                serieForecastGrafico = filas;
                $('#mrp-forecast-grafico-wrap').show();
                if ($('#tab-forecast').hasClass('active')) { dibujarForecastGrafico(); }
            },
            error: function() {
                $estado.text('Error al cargar el forecast.').show();
            }
        });
    }

    // Producto del panel donde ocurrió el clic (sus datos de ficha = fila de su 1ª semana).
    function productoDelPanel(el) {
        const p = productoPorCod[$(el).closest('.mrp-panel').attr('data-cod')];
        return p ? p.base : null;
    }

    // Botón "Ver detalle": toma el producto del panel, llena el resumen y carga las pestañas.
    $('#mrp-paneles').on('click', '.btn-mrp-detalle', function() {
        const f = productoDelPanel(this);
        if (!f) { return; }
        $('#mrp-detalle-titulo').text('— ' + (f.producto_codigo || '') + ' · ' + (f.producto_nombre || ''));
        $('#tabla-mrp-detalle').html(filasDetalleMrp(f));
        // Siempre inicia en la pestaña Stock al abrir un producto nuevo.
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-stock-btn')).show();
        cargarDetalleStock(f.producto_codigo);
        cargarDetalleComprometido(f.producto_codigo);
        cargarDetalleEnPedido(f.producto_codigo);
        cargarDetalleEnProduccion(f.producto_codigo);
        cargarDetalleForecast(f.producto_codigo);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMrpDetalle')).show();
    });

    // Clic en la badge del código (ficha): vuelca las características del producto (Familia,
    // Sub-Familia, Proveedor) a los filtros de la consulta y aplica. Limpia el buscador de texto
    // para no restringir a un solo producto: muestra todos los de esas mismas características.
    $('#mrp-paneles').on('click', '.mrp-cod-badge', function(e) {
        e.stopPropagation();
        const d = productoDelPanel(this);
        if (!d) { return; }
        // El código va al buscador de Consulta; las características a sus filtros. Borrar el código
        // del buscador deja ver toda la familia/sub-familia/proveedor de ese producto.
        $('#consulta-mrp').val(d.producto_codigo || '');
        $('#filtro-familia').val(d.familia || '');
        $('#filtro-sub-familia').val(d.sub_familia || '');
        $('#filtro-proveedor').val(d.proveedor || '');
        aplicarFiltros();
    });

    // Carga las opciones de Familia / Sub-Familia (mismo contrato que Forecast).
    function cargarFiltros() {
        $.ajax({
            url: 'controllers/mrp_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                const $fam = $('#filtro-familia');
                $fam.empty().append('<option value="">Todas</option>');
                (res.familias || []).forEach(function(f) { $fam.append($('<option>').val(f).text(f)); });

                const $sub = $('#filtro-sub-familia');
                $sub.empty().append('<option value="">Todas</option>');
                (res.sub_familias || []).forEach(function(sf) { $sub.append($('<option>').val(sf).text(sf)); });
            }
        });
    }

    cargarFiltros();
    cargarMrp();
});
