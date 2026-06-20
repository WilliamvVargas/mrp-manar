$(document).ready(function() {

    // ============================================================
    //  Listado de iconos (DataTable server-side)
    // ============================================================

    // Vista previa según el tipo: Bootstrap con la fuente; personalizado con su archivo SVG.
    function renderVistaPrevia(d, type, fila) {
        if (fila.tipo === 'bootstrap') {
            return '<i class="bi bi-' + fila.valor + '" style="font-size: 1.4rem;"></i>';
        }
        return '<img src="assets/icons/personalizados/' + fila.archivo + '" alt="" '
             + 'style="width: 22px; height: 22px; object-fit: contain;">';
    }

    function renderTipo(tipo) {
        return tipo === 'bootstrap'
            ? '<span class="badge bg-primary"><i class="bi bi-bootstrap-fill me-1"></i>Bootstrap</span>'
            : '<span class="badge bg-secondary"><i class="bi bi-tools me-1"></i>Personalizado</span>';
    }

    // Filtro Tipo: dropdown con iconos (componente reutilizable de utils.js).
    // Se inicializa ANTES de la tabla para que el hidden #filtro-tipo ya exista
    // (con su valor por defecto) en la primera carga.
    inicializarSelectIconos({
        contenedor: '#filtro-tipo-contenedor',
        idValor:    'filtro-tipo',
        opciones: [
            { valor: '',              texto: 'Todos' },
            { valor: 'bootstrap',     texto: 'Bootstrap',     icono: 'bi-bootstrap-fill' },
            { valor: 'personalizado', texto: 'Personalizado', icono: 'bi-tools' }
        ],
        onCambio: function() {
            tablaConsulta.ajax.reload();
        }
    });

    const tablaConsulta = inicializarTablaConsulta({
        tabla: '#tabla-consulta',
        url:   'controllers/iconos_controller.php?action=listar',
        input: '#consulta',
        orden: [[0, 'asc']],   // por posición ascendente
        extra: function(d) {
            d.tipo = $('#filtro-tipo').val();   // '', 'bootstrap' o 'personalizado'
        },
        columnas: [
            { data: 'posicion', className: 'text-center' },
            { data: 'nombre', render: $.fn.dataTable.render.text() },
            { data: 'valor', render: $.fn.dataTable.render.text() },
            { data: 'tipo', className: 'text-center', render: renderTipo },
            { data: null, orderable: false, searchable: false, className: 'text-center', render: renderVistaPrevia },
            { data: 'id', orderable: false, searchable: false, className: 'text-center', render: function() { return ''; } }
        ]
    });

    // ============================================================
    //  Formulario Crear Icono — interactividad y envío
    // ============================================================

    // Catálogo de iconos de Bootstrap embebido desde la vista (ver iconos.php).
    const CATALOGO       = (typeof ICONOS_BOOTSTRAP !== 'undefined') ? ICONOS_BOOTSTRAP : [];
    const MAX_RESULTADOS = 50;   // tope de coincidencias mostradas en el combobox.

    // Icono que se muestra en el recuadro de vista previa cuando no hay nada válido.
    const PLACEHOLDER_ICONO = 'bi-image';

    // Bandera: true si el usuario editó el Nombre a mano (para no autocompletarlo encima).
    let nombreEditadoManualmente = false;

    const $inputIcono  = $('#input_valor_bootstrap');
    const $listaIconos = $('#lista-iconos-bootstrap');

    // ---------- Vista previa ----------

    // Deja el recuadro en su estado por defecto (placeholder gris).
    function previewPlaceholder() {
        $('#preview-personalizado').addClass('d-none').attr('src', '');
        $('#preview-bootstrap').attr('class', 'bi ' + PLACEHOLDER_ICONO + ' text-muted').removeClass('d-none');
    }

    // Muestra en el recuadro el icono de Bootstrap escrito (acepta 'house' o 'bi-house').
    function previewBootstrap() {
        const nombre = $inputIcono.val().trim().replace(/^bi-/, '');

        if (!nombre) {
            previewPlaceholder();
            return;
        }

        $('#preview-personalizado').addClass('d-none').attr('src', '');
        $('#preview-bootstrap').attr('class', 'bi bi-' + nombre).removeClass('d-none');
    }

    // Muestra en el recuadro el SVG personalizado seleccionado.
    function previewArchivo(archivo) {
        if (!archivo || archivo.type !== 'image/svg+xml') {
            previewPlaceholder();
            return;
        }

        const lector = new FileReader();
        lector.onload = function(e) {
            $('#preview-bootstrap').addClass('d-none');
            $('#preview-personalizado').attr('src', e.target.result).removeClass('d-none');
        };
        lector.readAsDataURL(archivo);
    }

    // Convierte el nombre del icono en un nombre legible:
    // 'arrow-up-circle' -> 'Arrow up circle' (primera letra mayúscula, guiones a espacios).
    function humanizarNombreIcono(valor) {
        const nombre = valor.trim().replace(/^bi-/, '').replace(/-/g, ' ').trim();
        if (!nombre) {
            return '';
        }
        return nombre.charAt(0).toUpperCase() + nombre.slice(1);
    }

    // Deriva un Nombre legible a partir del nombre de un archivo: quita la extensión .svg
    // y reemplaza cualquier carácter especial por espacios.
    // 'mi_icono-bonito (2).svg' -> 'Mi icono bonito 2'.
    function nombreDesdeArchivo(nombreArchivo) {
        const base = nombreArchivo
            .replace(/\.svg$/i, '')                          // quita la extensión .svg
            .replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ]+/g, ' ')    // caracteres especiales -> espacio
            .replace(/\s+/g, ' ')                            // colapsa espacios repetidos
            .trim();

        if (!base) {
            return '';
        }
        return base.charAt(0).toUpperCase() + base.slice(1);
    }

    // ---------- Combobox de iconos de Bootstrap ----------

    // Filtra el catálogo por la consulta (ignora el prefijo 'bi-').
    function filtrarIconos(consulta) {
        const q = consulta.trim().toLowerCase().replace(/^bi-/, '');
        if (!q) {
            return CATALOGO;
        }
        return CATALOGO.filter(function(nombre) {
            return nombre.indexOf(q) !== -1;
        });
    }

    // Dibuja el desplegable con las coincidencias (capadas a MAX_RESULTADOS).
    function abrirListaIconos() {
        const coincidencias = filtrarIconos($inputIcono.val());
        const mostradas     = coincidencias.slice(0, MAX_RESULTADOS);
        let html = '';

        if (!mostradas.length) {
            html = '<li class="list-group-item small text-muted">Sin coincidencias</li>';
        } else {
            html = mostradas.map(function(nombre) {
                return '<li class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-1 item-icono" '
                     + 'role="button" data-nombre="' + nombre + '">'
                     + '<i class="bi bi-' + nombre + '"></i>'
                     + '<span class="small text-truncate">' + nombre + '</span>'
                     + '</li>';
            }).join('');

            if (coincidencias.length > MAX_RESULTADOS) {
                html += '<li class="list-group-item small text-muted text-center">'
                      + (coincidencias.length - MAX_RESULTADOS) + ' resultados más… afina tu búsqueda'
                      + '</li>';
            }
        }

        $listaIconos.html(html).removeClass('d-none');
    }

    function cerrarListaIconos() {
        $listaIconos.addClass('d-none').empty();
    }

    // Resalta un ítem del desplegable (navegación con teclado).
    function resaltarItem($item) {
        $listaIconos.children('.item-icono').removeClass('active');
        if ($item && $item.length) {
            $item.addClass('active');
            $item[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Selecciona un icono: asigna el valor, previsualiza y valida (check verde + autocompleta Nombre).
    function seleccionarIcono(nombre) {
        $inputIcono.val(nombre);
        cerrarListaIconos();
        previewBootstrap();
        ejecutarValidacionUniversal($inputIcono);
    }

    // Abrir/filtrar al enfocar.
    $inputIcono.on('focus', abrirListaIconos);

    // Al escribir: filtra el desplegable y actualiza la vista previa.
    $inputIcono.on('input', function() {
        abrirListaIconos();
        previewBootstrap();
    });

    // Navegación con teclado dentro del desplegable.
    $inputIcono.on('keydown', function(e) {
        if ($listaIconos.hasClass('d-none')) {
            return;
        }
        const $items = $listaIconos.children('.item-icono');
        let idx = $items.index($items.filter('.active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = (idx + 1 >= $items.length) ? 0 : idx + 1;
            resaltarItem($items.eq(idx));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = (idx <= 0) ? $items.length - 1 : idx - 1;
            resaltarItem($items.eq(idx));
        } else if (e.key === 'Enter') {
            if (idx >= 0) {
                e.preventDefault();
                seleccionarIcono($items.eq(idx).attr('data-nombre'));
            }
        } else if (e.key === 'Escape') {
            cerrarListaIconos();
        }
    });

    // Selección con el mouse (mousedown para no perder el foco antes del click).
    $listaIconos.on('mousedown', '.item-icono', function(e) {
        e.preventDefault();
        seleccionarIcono($(this).attr('data-nombre'));
    });

    // La flecha de la derecha abre/cierra el listado (mousedown para no robar el foco).
    $('#btn-abrir-iconos').on('mousedown', function(e) {
        e.preventDefault();
        if ($listaIconos.hasClass('d-none')) {
            $inputIcono.trigger('focus');
            abrirListaIconos();
        } else {
            cerrarListaIconos();
        }
    });

    // Cierra el desplegable al hacer clic fuera del combobox.
    $(document).on('mousedown', function(e) {
        if (!$(e.target).closest('#combobox-bootstrap').length) {
            cerrarListaIconos();
        }
    });

    // ---------- Tipo, archivo, nombre y estado ----------

    // Alterna los bloques del formulario y la vista previa según el tipo elegido.
    $('input[name="tipo"]').on('change', function() {
        const esBootstrap = $('#tipo-bootstrap').is(':checked');
        $('#bloque-bootstrap').toggleClass('d-none', !esBootstrap);
        $('#bloque-personalizado').toggleClass('d-none', esBootstrap);

        cerrarListaIconos();

        if (esBootstrap) {
            previewBootstrap();
        } else {
            previewArchivo($('#input_archivo')[0].files[0]);
        }
    });

    // Vista previa + autocompletado del Nombre al seleccionar el SVG personalizado.
    $('#input_archivo').on('change', function() {
        const archivo = this.files && this.files[0];
        previewArchivo(archivo);

        // Propone el Nombre derivado del archivo, salvo que el usuario ya lo haya editado a mano.
        if (archivo && !nombreEditadoManualmente) {
            $('#input_nombre').val(nombreDesdeArchivo(archivo.name));
        }
    });

    // Si el usuario escribe el Nombre a mano, deja de autocompletarse desde el icono.
    $('#input_nombre').on('input', function() {
        nombreEditadoManualmente = true;
    });

    // Observa la validación del icono de Bootstrap: cuando queda válido (clase is-valid),
    // propone el Nombre derivado del icono, salvo que el usuario ya lo haya editado a mano.
    const inputIconoDom = $inputIcono.get(0);
    if (inputIconoDom) {
        const observadorIcono = new MutationObserver(function() {
            const valido = $inputIcono.hasClass('is-valid') && !$inputIcono.hasClass('is-invalid');

            if (valido && !nombreEditadoManualmente) {
                $('#input_nombre').val(humanizarNombreIcono($inputIcono.val()));
            }
        });
        observadorIcono.observe(inputIconoDom, { attributes: true, attributeFilter: ['class'] });
    }

    // Limpia la alerta general al escribir en el formulario.
    activarLimpiezaMensajeAlEscribir('#form-icono', '#modal-mensajes');

    // Restablece el formulario a su estado inicial (form, combobox y vista previa).
    function resetearFormularioIcono() {
        limpiarFormularioCompleto('#form-icono', '#modal-mensajes', true);
        cerrarListaIconos();
        $('#tipo-bootstrap').prop('checked', true);
        $('#bloque-bootstrap').removeClass('d-none');
        $('#bloque-personalizado').addClass('d-none');
        nombreEditadoManualmente = false;
        previewPlaceholder();
    }

    // Guardar Icono.
    $('#form-icono').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnGuardarIcono');
        const formulario   = '#form-icono';
        const modalMensaje = '#modal-mensajes';

        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/iconos_controller.php?action=registrar',
            type: 'POST',
            data: new FormData(this),   // incluye el csrf_token (y el archivo cuando aplique)
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    resetearFormularioIcono();
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                    tablaConsulta.ajax.reload(null, false);
                }
                else if (res.status === 'error') {
                    $(modalMensaje).slideUp(150);
                    if (res.type === 'fields') {
                        renderizarErroresCampos(formulario, res.errors);
                    }
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                resetBtnLoading(btn);
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            }
        });
    });

    // Al cerrar el modal: restablece el formulario a su estado inicial.
    $('#modalIconoCrear').on('hidden.bs.modal', resetearFormularioIcono);

});
