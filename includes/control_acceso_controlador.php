<?php
    // Control de acceso a nivel de controlador (defensa en profundidad). Complementa al guard de
    // página: aunque un usuario no pueda ABRIR un mantenedor, sin esto podría llamar sus endpoints
    // (AJAX) a mano. Si el perfil del usuario no tiene acceso al item_menu de la sección, corta la
    // ejecución con 403 (JSON). Las acciones de uso compartido entre features o self-service se
    // pasan en $exentas (no exigen el acceso de esa sección).
    //
    // Carga su propia conexión MySQL (idempotente) y la toma con `global $pdo`, para servir también
    // a controladores que solo usan otra conexión (p. ej. consultas_sap con SQL Server).
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../models/accesos_model.php';

    function exigirAccesoControlador(string $enlace, string $accion, array $exentas = []): void
    {
        global $pdo;

        // Acciones compartidas / self-service: no requieren el acceso de esta sección.
        if (in_array($accion, $exentas, true)) {
            return;
        }

        if ((new Acceso($pdo))->usuarioTieneAcceso($_SESSION['usuario_id'] ?? null, $enlace)) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'forbidden',
            'message' => 'No tiene acceso a esta sección.'
        ]);
        exit;
    }
?>
