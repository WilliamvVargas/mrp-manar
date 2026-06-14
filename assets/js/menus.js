$(document).ready(function() {

    // Switch de Estado: alterna la etiqueta entre Activo / Inactivo según su valor.
    $('#input_estado').on('change', function() {
        $('#label-estado').text(this.checked ? 'Activo' : 'Inactivo');
    });

    // Crear Menú: por ahora solo evita el envío nativo (el backend se hará luego).
    $('#form-menu').on('submit', function(e) {
        e.preventDefault();
        // TODO: enviar por AJAX a menus_controller.php cuando exista el backend.
    });

});