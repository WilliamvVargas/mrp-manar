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

});