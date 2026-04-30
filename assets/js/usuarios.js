$(document).ready(function() {
    listarUsuarios();

    function listarUsuarios() {
        $.ajax({
            url: 'controllers/usuarios_controller.php?action=listar',
            type: 'GET',
            dataType: 'json',
            success: function(response) {

                console.log(response)

                if (response.status === 'success') {

                    // 1. Destruir la instancia previa si existe
                    if ($.fn.DataTable.isDataTable('#tabla-usuarios')) {
                        $('#tabla-usuarios').DataTable().destroy();
                    }

                    let filas = '';
                    response.data.forEach(user => {
                        filas += `
                            <tr>
                                <td>${user.id}</td>
                                <td><strong>${user.usuario}</strong></td>
                                <td>${user.fecha}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning btn-editar" data-id="${user.id}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${user.id}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                                        
                    $('#lista-usuarios').html(filas);

                    // 2. Inicializar DataTables
                    $('#tabla-usuarios').DataTable({
                        autoWidth: false,
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                        },
                        "columnDefs": [
                            { "orderable": false, "targets": 3 }
                        ],
                        "order": [[0, "desc"]]
                    });

                } 
                else {
                    mostrarAlertaGeneral("Error", response.message, "danger");
                }
            }
        });
    }

    $('#form-usuario').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#btnGuardar');
        const errorContainer = $('#modal-error-container');
        setBtnLoading(btn, 'Guardando...');

        $.ajax({
            url: 'controllers/usuarios_controller.php?action=registrar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    // CASO EXITOSO: Todo a la raíz
                    resetBtnLoading(btn);
                    $('#modalUsuario').modal('hide');
                    $('#form-usuario')[0].reset();
                    listarUsuarios();
                    
                    // Usamos tu función global para la raíz
                    mostrarAlertaGeneral("¡Hecho!", res.message, "success");
                } else {
                    // CASO ERROR: Se queda en el modal
                    resetBtnLoading(btn);
                    
                    // Creamos una alerta local estilo Bootstrap
                    const alerta = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ${res.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                    
                    errorContainer.html(alerta);
                }
            },
            error: function() {
                resetBtnLoading(btn);
                errorContainer.html('<div class="alert alert-danger">Error crítico en el servidor.</div>');
            }
        });
    });

});