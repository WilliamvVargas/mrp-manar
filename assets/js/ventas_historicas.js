$(document).ready(function() {

    // ============================================================
    //  Formatos de celdas
    // ============================================================

    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

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
    const renderNumero = function(d) { return (d === null || d === undefined) ? '' : d; };
    const renderMoneda = function(d) { return formatearNumero(d, 0) === '' ? '' : '$' + formatearNumero(d, 0); };

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

    // Fecha 'YYYY-MM-DD' -> 'DD-MM-YYYY'.
    function renderFecha(d) {
        if (!d) {
            return '';
        }
        const p = String(d).split('-');
        return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : d;
    }

    // Mes (1-12) -> nombre.
    function renderMes(d) {
        const n = parseInt(d, 10);
        return (n >= 1 && n <= 12) ? MESES[n] : '';
    }

    // ============================================================
    //  Tabla principal (DataTable server-side)
    // ============================================================

    const tablaVentas = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/ventas_historicas_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'desc']],   // por id descendente (lo más reciente primero)
        extra: function(d) {
            d.version = $('#filtro-version').val();   // '' o versión exacta
            d.anio    = $('#filtro-anio').val();      // '' o año
        },
        columnas: [
            { data: 'id',                   className: 'text-center' },
            { data: 'version',              className: 'text-center', render: renderTexto },
            { data: 'fecha_docto',          className: 'text-center', render: renderFecha },
            { data: 'nro_docto',            className: 'text-center', render: renderNumero },
            { data: 'razon_social',         render: renderTexto },
            { data: 'descripcion_articulo', render: renderTexto },
            { data: 'cant_docto',           className: 'text-end', render: renderCantidad },
            { data: 'venta_bruta',          className: 'text-end', render: renderMoneda },
            { data: 'anio',                 className: 'text-center', render: renderNumero },
            { data: 'mes',                  className: 'text-center', render: renderMes }
        ]
    });

    // Recargar la tabla al cambiar los filtros de Versión o Año.
    $('#filtro-version, #filtro-anio').on('change', function() {
        tablaVentas.ajax.reload();
    });

    // Llena los selects de Versión y Año con los valores disponibles (conserva la selección).
    function cargarFiltros() {
        $.ajax({
            url: 'controllers/ventas_historicas_controller.php?action=filtros',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success') {
                    return;
                }

                const verSel  = $('#filtro-version').val();
                const opcVer  = (res.versiones || []).map(function(v) {
                    return '<option value="' + v + '">' + v + '</option>';
                }).join('');
                $('#filtro-version').html('<option value="">Todas</option>' + opcVer).val(verSel);

                const anioSel = $('#filtro-anio').val();
                const opcAnio = (res.anios || []).map(function(a) {
                    return '<option value="' + a + '">' + a + '</option>';
                }).join('');
                $('#filtro-anio').html('<option value="">Todos</option>' + opcAnio).val(anioSel);
            }
        });
    }

    // ============================================================
    //  Carga masiva (.xlsx)
    // ============================================================

    // Solo se aceptan .xlsx; al elegir un archivo válido se envía.
    $('#archivo-excel-ventas').on('change', function() {
        const $input  = $(this);
        const archivo = this.files[0];

        if (!archivo) {
            limpiarErrorCampo($input);
            return;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (extension !== 'xlsx') {
            $input.val('').addClass('is-invalid');
            $('#error-archivo-ventas').html('Solo se permiten archivos Excel (.xlsx).').addClass('d-block');
        } else {
            limpiarErrorCampo($input);
            $('#form-carga-masiva-ventas').trigger('submit');
        }
    });

    // Envía el archivo por AJAX y muestra el resultado de la carga.
    $('#form-carga-masiva-ventas').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnCargaMasivaVentas');
        const modalMensaje = '#modal-mensajes-ventas';
        const $archivo     = $('#archivo-excel-ventas');

        if (!$archivo[0].files.length) {
            $archivo.addClass('is-invalid');
            $('#error-archivo-ventas').html('Debe ingresar un archivo .xlsx.').addClass('d-block');
            return;
        }

        setBtnLoading(btn, 'Procesando...');

        $.ajax({
            url: 'controllers/ventas_historicas_controller.php?action=procesar',
            type: 'POST',
            data: new FormData(this),   // incluye el archivo y el csrf_token
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);
                if (res.status === 'success') {
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaVentas.ajax.reload(null, false);   // muestra los nuevos registros
                    cargarFiltros();                        // aparece la nueva versión en el filtro
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
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

    // Carga inicial de los filtros (Versión y Año).
    cargarFiltros();

});
