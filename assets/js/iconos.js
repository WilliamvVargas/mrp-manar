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
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id) {
                    const btnVista = '<button type="button" class="btn btn-sm btn-outline-secondary btn-vista-previa-icono me-1" '
                                   + 'data-id="' + id + '" title="Vista Previa"><i class="bi bi-eye"></i></button>';
                    const btnEditar = '<button type="button" class="btn btn-sm btn-outline-dark btn-editar-icono me-1" '
                                    + 'data-id="' + id + '" title="Editar icono"><i class="bi bi-pencil"></i></button>';
                    const btnEliminar = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-icono" '
                                      + 'data-id="' + id + '" title="Eliminar icono"><i class="bi bi-trash"></i></button>';
                    return btnVista + btnEditar + btnEliminar;
                }
            }
        ]
    });

    // ============================================================
    //  Asignar Posiciones (reordenar todos los iconos) — motor de utils.js, en GRILLA
    // ============================================================

    // Celda de la grilla: número de orden + vista previa del icono + nombre.
    // Incluye .numero-orden y data-original/data-movible para que el motor renumere igual.
    function celdaIconoPosicion(registro, orden) {
        const preview = (registro.tipo === 'bootstrap')
            ? '<i class="bi bi-' + registro.valor + '"></i>'
            : '<img src="assets/icons/personalizados/' + registro.archivo + '" alt="">';

        const nombreEsc = $('<div>').text(registro.nombre).html();

        return '<li class="lista-posicion-celda" data-id="' + registro.id + '" data-original="' + orden + '" data-movible="0">'
             + '<span class="numero-orden">' + orden + '.</span>'
             + '<div class="celda-preview">' + preview + '</div>'
             + '<span class="celda-nombre small text-truncate">' + nombreEsc + '</span>'
             + '<span class="celda-cambio"><span class="badge bg-warning text-dark badge-cambio"></span></span>'
             + '</li>';
    }

    inicializarAsignadorPosicion({
        urlListar:    'controllers/iconos_controller.php?action=listar_orden',
        urlReordenar: 'controllers/iconos_controller.php?action=reordenar',
        tabla:        tablaConsulta,
        grilla:       true,   // layout en grilla 2D (solo iconos; menús sigue en lista)
        contextos: [{
            global:    true,
            boton:     '#btn-asignar-posiciones',
            idMovible: function() { return null; },
            construirItems: function(data) {
                return data.map(function(registro, i) {
                    return celdaIconoPosicion(registro, i + 1);
                });
            }
        }]
    });

    // El modal de Asignar Posición de iconos usa el tamaño más grande (solo en esta página).
    $('#modalAsignarPosicion .modal-dialog').removeClass('modal-md').addClass('modal-xl');

    // ============================================================
    //  Editar Icono (frontend)
    // ============================================================

    // Tipo del icono que se está editando (para recalcular el Valor solo en personalizados).
    let tipoIconoEditando = null;

    // Genera el slug del valor a partir del nombre, igual que el backend (slugIcono PHP):
    // minúsculas, sin acentos, solo [a-z0-9-]. Ej: 'Engranaje Ñoño' -> 'engranaje-nono'.
    function slugIcono(nombre) {
        let s = (nombre || '').toLowerCase().trim();
        s = s.replace(/[áàä]/g, 'a').replace(/[éèë]/g, 'e').replace(/[íìï]/g, 'i')
             .replace(/[óòö]/g, 'o').replace(/[úùü]/g, 'u').replace(/ñ/g, 'n');
        s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return s === '' ? 'icono' : s;
    }

    // HTML del icono para el recuadro de vista previa del modal.
    function previewIconoModalHtml(fila) {
        if (fila.tipo === 'bootstrap') {
            return '<i class="bi bi-' + fila.valor + '" style="font-size: 3rem;"></i>';
        }
        return '<img src="assets/icons/personalizados/' + fila.archivo + '" alt="" '
             + 'style="max-width: 72%; max-height: 72%;">';
    }

    // Abre el modal de edición con los datos de la fila (sin llamar al backend).
    function abrirEditarIcono(fila) {
        limpiarFormularioCompleto('#form-icono-editar', '#modal-mensajes-editar', true);
        tipoIconoEditando = fila.tipo;
        $('#id_icono_editar').val(fila.id);
        $('#input_tipo_editar').val(fila.tipo === 'bootstrap' ? 'Bootstrap' : 'Personalizado');
        $('#input_valor_editar').val(fila.valor);
        $('#input_nombre_editar').val(fila.nombre);
        $('#preview-icono-editar').html(previewIconoModalHtml(fila));
        $('#modalIconoEditar').modal('show');
    }

    // Botón Editar de la columna Acciones: toma la fila del DataTable y abre el modal.
    $('#tabla-consulta tbody').on('click', '.btn-editar-icono', function() {
        const fila = tablaConsulta.row($(this).closest('tr')).data();
        if (fila) {
            abrirEditarIcono(fila);
        }
    });

    // Solo en personalizados: al editar el Nombre, el Valor se recalcula con el mismo
    // formato que al crear (custom-<slug>). En Bootstrap el valor es fijo.
    $('#input_nombre_editar').on('input', function() {
        if (tipoIconoEditando === 'personalizado') {
            $('#input_valor_editar').val('custom-' + slugIcono($(this).val()));
        }
    });

    // Limpia la alerta general al escribir en el formulario de edición.
    activarLimpiezaMensajeAlEscribir('#form-icono-editar', '#modal-mensajes-editar');

    // Envío del formulario de edición.
    $('#form-icono-editar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnActualizarIcono');
        const formulario   = '#form-icono-editar';
        const modalMensaje = '#modal-mensajes-editar';

        setBtnLoading(btn, 'Actualizando...');

        $.ajax({
            url: 'controllers/iconos_controller.php?action=editar',
            type: 'POST',
            data: $(this).serialize(),   // csrf_token, id_registro y nombre
            dataType: 'json',
            success: function(res) {
                resetBtnLoading(btn);

                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(null, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                }
                else if (res.status === 'no_changes') {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'warning');
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

    // Al cerrar el modal de edición: limpia el formulario y la vista previa.
    $('#modalIconoEditar').on('hidden.bs.modal', function() {
        limpiarFormularioCompleto('#form-icono-editar', '#modal-mensajes-editar', true);
        $('#preview-icono-editar').empty();
    });

    // ============================================================
    //  Eliminar Icono (frontend)
    // ============================================================

    // Abre el modal de eliminación con los datos de la fila.
    function abrirEliminarIcono(fila) {
        limpiarFormularioCompleto('#form-icono-eliminar', '#modal-mensajes-eliminar', true);
        $('#id_icono_eliminar').val(fila.id);
        $('#input-nombre-eliminar').val(fila.nombre);
        $('#input-tipo-eliminar').val(fila.tipo === 'bootstrap' ? 'Bootstrap' : 'Personalizado');
        $('#input-valor-eliminar').val(fila.valor);
        $('#preview-icono-eliminar').html(previewIconoModalHtml(fila));
        $('#modalIconoEliminar').modal('show');
    }

    // Botón Eliminar de la columna Acciones: toma la fila del DataTable y abre el modal.
    $('#tabla-consulta tbody').on('click', '.btn-eliminar-icono', function() {
        const fila = tablaConsulta.row($(this).closest('tr')).data();
        if (fila) {
            abrirEliminarIcono(fila);
        }
    });

    // Envío del formulario de eliminación.
    $('#form-icono-eliminar').on('submit', function(e) {
        e.preventDefault();

        const btn          = $('#btnEliminarIcono');
        const modalMensaje = '#modal-mensajes-eliminar';

        setBtnLoading(btn, 'Eliminando...');

        $.ajax({
            url: 'controllers/iconos_controller.php?action=eliminar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    tablaConsulta.ajax.reload(function() {
                        // Si la página actual quedó vacía, retrocede a la última con registros.
                        const info = tablaConsulta.page.info();
                        if (info.pages > 0 && info.page >= info.pages) {
                            tablaConsulta.page(info.pages - 1).draw('page');
                        }
                    }, false);
                    mostrarMensajeFormulario(modalMensaje, 'Éxito', res.message, 'success');
                } else {
                    mostrarMensajeFormulario(modalMensaje, 'Atención', res.message, 'danger');
                }
            },
            error: function(jqXHR, textStatus) {
                manejarErrorAjax(jqXHR, textStatus, modalMensaje);
            },
            complete: function() {
                resetBtnLoading(btn);
            }
        });
    });

    // Al cerrar el modal de eliminación: limpia el formulario y la vista previa.
    $('#modalIconoEliminar').on('hidden.bs.modal', function() {
        limpiarFormularioCompleto('#form-icono-eliminar', '#modal-mensajes-eliminar', true);
        $('#preview-icono-eliminar').empty();
    });

    // ============================================================
    //  Vista Previa de Icono (con selector de color)
    // ============================================================

    const COLOR_PREVIEW_DEFECTO = '#212529';
    const FONDO_PREVIEW_DEFECTO = '#ffffff';

    // Renderiza el icono en grande y aplica el color actual. Los personalizados se cargan
    // INLINE para que los monocromáticos hereden el color vía currentColor (un <img> no se tiñe).
    function mostrarVistaPrevia(fila) {
        const $cont      = $('#vista-previa-icono');
        const coloreable = Number(fila.coloreable) === 1;

        // Datos del icono (solo lectura).
        $('#input-nombre-preview').val(fila.nombre);
        $('#input-valor-preview').val(fila.valor);
        $('#input-tipo-preview').val(fila.tipo === 'bootstrap' ? 'Bootstrap' : 'Personalizado');

        // El color solo aplica a iconos coloreables (Bootstrap y personalizados monocromáticos);
        // en multicolor se oculta el selector.
        $('#contenedor-color-preview').toggleClass('d-none', !coloreable);
        $('#mensaje-multicolor-preview').toggleClass('d-none', coloreable);
        $('#input-color-preview').val(COLOR_PREVIEW_DEFECTO);
        $('#input-fondo-preview').val(FONDO_PREVIEW_DEFECTO);
        $cont.css({ 'color': COLOR_PREVIEW_DEFECTO, 'background-color': FONDO_PREVIEW_DEFECTO });

        if (fila.tipo === 'bootstrap') {
            $cont.html('<i class="bi bi-' + fila.valor + '" style="font-size: 12rem; line-height: 1;"></i>');
        } else {
            $cont.html('<span class="text-muted small">Cargando…</span>');
            $.get('assets/icons/personalizados/' + fila.archivo, function(svg) {
                $cont.html(svg);
                $cont.find('svg').css({ width: '12rem', height: '12rem', maxWidth: '100%', maxHeight: '300px' });
            }, 'text').fail(function() {
                $cont.html('<span class="text-danger small">No se pudo cargar el icono.</span>');
            });
        }
    }

    // Botón Vista Previa de la columna Acciones.
    $('#tabla-consulta tbody').on('click', '.btn-vista-previa-icono', function() {
        const fila = tablaConsulta.row($(this).closest('tr')).data();
        if (fila) {
            mostrarVistaPrevia(fila);
            $('#modalIconoVistaPrevia').modal('show');
        }
    });

    // Al cambiar el color, tiñe el icono del body (Bootstrap y SVG monocromático vía currentColor).
    $('#input-color-preview').on('input', function() {
        $('#vista-previa-icono').css('color', $(this).val());
    });

    // Al cambiar el fondo, cambia el color de fondo del contenedor del icono.
    $('#input-fondo-preview').on('input', function() {
        $('#vista-previa-icono').css('background-color', $(this).val());
    });

    // Al cerrar el modal de vista previa: limpia el contenido.
    $('#modalIconoVistaPrevia').on('hidden.bs.modal', function() {
        $('#vista-previa-icono').empty();
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

    // Muestra en el input "Valor" lo que se guardaría: en Bootstrap el nombre del icono;
    // en Personalizado, custom-<slug del Nombre> (misma lógica que en edición).
    function actualizarValorCrear() {
        if ($('#tipo-bootstrap').is(':checked')) {
            $('#input_valor_crear').val($('#input_valor_bootstrap').val().trim().replace(/^bi-/, ''));
        } else {
            const nombre = $('#input_nombre').val().trim();
            $('#input_valor_crear').val(nombre ? 'custom-' + slugIcono(nombre) : '');
        }
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
        actualizarValorCrear();
    }

    // Abrir/filtrar al enfocar.
    $inputIcono.on('focus', abrirListaIconos);

    // Al escribir: filtra el desplegable y actualiza la vista previa.
    $inputIcono.on('input', function() {
        abrirListaIconos();
        previewBootstrap();
        actualizarValorCrear();
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

        actualizarValorCrear();
    });

    // Vista previa + autocompletado del Nombre al seleccionar el SVG personalizado.
    $('#input_archivo').on('change', function() {
        const archivo = this.files && this.files[0];
        previewArchivo(archivo);

        // Propone el Nombre derivado del archivo, salvo que el usuario ya lo haya editado a mano.
        if (archivo && !nombreEditadoManualmente) {
            $('#input_nombre').val(nombreDesdeArchivo(archivo.name)).removeClass('is-valid');
            limpiarErrorCampo($('#input_nombre'));   // el valor cambió: descarta un error/estado previo
        }

        actualizarValorCrear();
    });

    // Marca el Nombre como editado a mano SOLO si tiene contenido. Si el usuario lo deja
    // en blanco, se "libera" y vuelve a permitir el autocompletado (desde archivo o icono).
    $('#input_nombre').on('input', function() {
        nombreEditadoManualmente = $(this).val().trim() !== '';
        actualizarValorCrear();   // en Personalizado el Valor sigue al Nombre
    });

    // Observa la validación del icono de Bootstrap: cuando queda válido (clase is-valid),
    // propone el Nombre derivado del icono, salvo que el usuario ya lo haya editado a mano.
    const inputIconoDom = $inputIcono.get(0);
    if (inputIconoDom) {
        const observadorIcono = new MutationObserver(function() {
            const valido = $inputIcono.hasClass('is-valid') && !$inputIcono.hasClass('is-invalid');

            if (valido && !nombreEditadoManualmente) {
                $('#input_nombre').val(humanizarNombreIcono($inputIcono.val())).removeClass('is-valid');
                limpiarErrorCampo($('#input_nombre'));   // el valor cambió: descarta un error/estado previo
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
        actualizarValorCrear();
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
