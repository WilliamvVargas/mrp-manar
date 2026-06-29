<?php

    /*
     * Modelo de acceso a datos de la tabla `accesos` (ítems menú habilitados por perfil).
     * Un registro por (id_perfil, id_item_menu). `estado` 1 = con acceso, 0 = revocado.
     * Al revocar NO se borra: se cambia el estado (conserva el rastro de quién lo modificó).
     */
    class Acceso
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Ids de los ítems menú con acceso ACTIVO (estado = 1) para un perfil.
         * Lo usa el modal para marcar los checkboxes correspondientes.
         *
         * @param int $idPerfil
         * @return int[] Lista de id_item_menu.
         */
        public function itemMenusConcedidos($idPerfil)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id_item_menu FROM accesos WHERE id_perfil = ? AND estado = 1"
            );
            $stmt->execute([(int) $idPerfil]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        /**
         * Resumen de accesos por menú para un perfil: por cada menú (TODOS) devuelve su
         * posición, nombre, el total de ítems menú del menú y cuántos de esos ítems tiene el
         * perfil con acceso ACTIVO (estado = 1). Lo usa la columna "Total Accesos" del
         * DataTable de perfiles. Ordenado por la posición del menú.
         *
         * @param int $idPerfil
         * @return array Lista de ['id','posicion','nombre','total_items','accesos_activos'].
         */
        public function resumenPorMenu($idPerfil)
        {
            $sql = "SELECT m.id, m.posicion, m.nombre,
                           COUNT(im.id) AS total_items,
                           COUNT(a.id)  AS accesos_activos
                    FROM menus m
                    LEFT JOIN item_menus im ON im.menu_id = m.id
                    LEFT JOIN accesos a ON a.id_item_menu = im.id AND a.id_perfil = ? AND a.estado = 1
                    GROUP BY m.id, m.posicion, m.nombre
                    ORDER BY m.posicion ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([(int) $idPerfil]);

            return $stmt->fetchAll();
        }

        /**
         * Árbol de navegación (menús -> ítems) para el usuario indicado, según su perfil y sus
         * accesos ACTIVOS. Solo incluye menús e ítems ACTIVOS (estado = 1) a los que el perfil
         * tiene acceso (accesos.estado = 1). Ordenado por posición. Lo usa el navbar.
         *
         * @param string|null $usuarioId Id (UUID) del usuario en sesión.
         * @return array Lista de menús: ['nombre', 'items' => [['nombre','enlace','icono_tipo','icono_valor','icono_archivo'], ...]].
         */
        public function menuNavegacion($usuarioId)
        {
            if (!$usuarioId) {
                return [];
            }

            $sql = "SELECT m.id      AS menu_id,
                           m.nombre  AS menu_nombre,
                           im.nombre AS item_nombre,
                           im.enlace AS item_enlace,
                           ic.tipo   AS icono_tipo,
                           ic.valor  AS icono_valor,
                           ic.archivo AS icono_archivo,
                           ic.coloreable AS icono_coloreable
                    FROM usuarios u
                    JOIN accesos a     ON a.id_perfil = u.id_perfil AND a.estado = 1
                    JOIN item_menus im ON im.id = a.id_item_menu AND im.estado = 1
                    JOIN menus m       ON m.id = im.menu_id AND m.estado = 1
                    LEFT JOIN iconos ic ON ic.id = im.icono_id
                    WHERE u.id = ?
                    ORDER BY m.posicion ASC, im.posicion ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioId]);

            // Agrupa los ítems por menú, conservando el orden de la consulta.
            $menus = [];
            foreach ($stmt->fetchAll() as $f) {
                $idMenu = $f['menu_id'];
                if (!isset($menus[$idMenu])) {
                    $menus[$idMenu] = ['nombre' => $f['menu_nombre'], 'items' => []];
                }
                $menus[$idMenu]['items'][] = [
                    'nombre'           => $f['item_nombre'],
                    'enlace'           => $f['item_enlace'],
                    'icono_tipo'       => $f['icono_tipo'],
                    'icono_valor'      => $f['icono_valor'],
                    'icono_archivo'    => $f['icono_archivo'],
                    'icono_coloreable' => $f['icono_coloreable'],
                ];
            }

            return array_values($menus);
        }

        /**
         * ¿El usuario indicado tiene acceso ACTIVO a la página cuyo `enlace` se pasa? Mismo
         * criterio que el navbar: el perfil debe tener un acceso activo (accesos.estado = 1) a un
         * ítem menú ACTIVO (im.estado = 1) de un menú ACTIVO (m.estado = 1) con ese enlace.
         * Lo usa el control de acceso por página.
         *
         * @param string|null $usuarioId Id (UUID) del usuario en sesión.
         * @param string      $enlace    Enlace (ruta) de la página solicitada.
         * @return bool
         */
        public function usuarioTieneAcceso($usuarioId, $enlace)
        {
            if (!$usuarioId || $enlace === '') {
                return false;
            }

            $sql = "SELECT 1
                    FROM usuarios u
                    JOIN accesos a     ON a.id_perfil = u.id_perfil AND a.estado = 1
                    JOIN item_menus im ON im.id = a.id_item_menu AND im.estado = 1
                    JOIN menus m       ON m.id = im.menu_id AND m.estado = 1
                    WHERE u.id = ? AND im.enlace = ?
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioId, $enlace]);

            return (bool) $stmt->fetchColumn();
        }

        /**
         * Aplica los accesos de un perfil según los ítems MARCADOS:
         *   - Marcados   -> estado = 1 (se crea el registro o se reactiva uno revocado).
         *   - Los que estaban activos y ya NO vienen marcados -> estado = 0 (se conserva el
         *     registro y se anota updated_by, p. ej. quién lo desmarcó).
         * Todo dentro de una transacción.
         *
         * @param int   $idPerfil
         * @param array $itemsMarcados Ids de item_menu marcados.
         * @param string|null $usuario  Id del usuario que aplica los cambios (auditoría).
         * @return bool
         */
        public function actualizar($idPerfil, array $itemsMarcados, $usuario = null)
        {
            $idPerfil = (int) $idPerfil;
            $marcados = array_values(array_unique(array_map('intval', $itemsMarcados)));

            $this->pdo->beginTransaction();

            try {
                // 1. Marcados -> estado = 1 (crea o reactiva). En insert anota created_by;
                //    en duplicado (ya existía) anota updated_by.
                if ($marcados) {
                    $up = $this->pdo->prepare(
                        "INSERT INTO accesos (id_perfil, id_item_menu, estado, created_by)
                         VALUES (?, ?, 1, ?)
                         ON DUPLICATE KEY UPDATE estado = 1, updated_by = ?"
                    );
                    foreach ($marcados as $idItem) {
                        $up->execute([$idPerfil, $idItem, $usuario, $usuario]);
                    }
                }

                // 2. Los activos que ya no vienen marcados -> estado = 0 (conserva el registro).
                if ($marcados) {
                    $placeholders = implode(',', array_fill(0, count($marcados), '?'));
                    $sql    = "UPDATE accesos SET estado = 0, updated_by = ?
                               WHERE id_perfil = ? AND estado = 1 AND id_item_menu NOT IN ($placeholders)";
                    $params = array_merge([$usuario, $idPerfil], $marcados);
                } else {
                    $sql    = "UPDATE accesos SET estado = 0, updated_by = ? WHERE id_perfil = ? AND estado = 1";
                    $params = [$usuario, $idPerfil];
                }
                $this->pdo->prepare($sql)->execute($params);

                $this->pdo->commit();

                return true;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }
