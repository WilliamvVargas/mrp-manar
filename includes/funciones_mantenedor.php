<?php
    // Helpers para el encabezado (card-header) de los mantenedores: toman el ícono y el nombre
    // del item_menu cuyo `enlace` coincide con la página actual.
    require_once __DIR__ . '/../models/item_menus_model.php';

    if (!function_exists('iconoMantenedor')) {
        /**
         * HTML del ícono de un ítem menú para un encabezado en fondo claro (Bootstrap por fuente
         * o personalizado por archivo; los monocromáticos se tiñen de negro vía CSS .icono-mono).
         */
        function iconoMantenedor(array $item): string
        {
            if (($item['icono_tipo'] ?? '') === 'bootstrap' && !empty($item['icono_valor'])) {
                return '<i class="bi bi-' . htmlspecialchars($item['icono_valor']) . ' me-2"></i>';
            }
            if (($item['icono_tipo'] ?? '') === 'personalizado' && !empty($item['icono_archivo'])) {
                $claseMono = !empty($item['icono_coloreable']) ? ' icono-mono' : '';
                return '<img src="assets/icons/personalizados/' . htmlspecialchars($item['icono_archivo'])
                     . '" alt="" class="me-2' . $claseMono . '" style="width:1em;height:1em;object-fit:contain;vertical-align:-0.125em;">';
            }
            return '';
        }
    }

    if (!function_exists('encabezadoMantenedor')) {
        /**
         * Ícono + nombre del item_menu correspondiente a la página actual (por su `enlace`, que
         * coincide con el nombre del archivo). Si no se encuentra, devuelve el texto por defecto.
         *
         * @param PDO    $pdo
         * @param string $porDefecto Nombre a mostrar si no existe el item_menu (sin ícono).
         */
        function encabezadoMantenedor(PDO $pdo, string $porDefecto = ''): string
        {
            $ruta = basename($_SERVER['PHP_SELF'], '.php');
            $item = (new ItemMenu($pdo))->obtenerPorEnlace($ruta);

            if (!$item) {
                return htmlspecialchars($porDefecto);
            }

            return iconoMantenedor($item) . htmlspecialchars($item['nombre']);
        }
    }
?>
