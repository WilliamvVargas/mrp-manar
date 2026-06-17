$(document).ready(function() {

    // ============================================================
    //  Formulario Crear Icono — interactividad de la vista (frontend)
    //  El listado (DataTables server-side) y el envío AJAX del formulario
    //  se implementarán junto con el backend (controlador + modelo de iconos).
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

    // Vista previa del SVG personalizado al seleccionar el archivo.
    $('#input_archivo').on('change', function() {
        previewArchivo(this.files && this.files[0]);
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

    // Al cerrar el modal: restablece el formulario a su estado inicial.
    $('#modalIconoCrear').on('hidden.bs.modal', function() {
        limpiarFormularioCompleto('#form-icono', '#modal-mensajes', true);

        cerrarListaIconos();
        $('#tipo-bootstrap').prop('checked', true);
        $('#bloque-bootstrap').removeClass('d-none');
        $('#bloque-personalizado').addClass('d-none');
        nombreEditadoManualmente = false;
        previewPlaceholder();
    });

});
