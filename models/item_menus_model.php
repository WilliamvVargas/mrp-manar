<?php

    /*
     * Modelo de acceso a datos de la tabla `item_menus`.
     *
     * Cada ítem pertenece a un menú (menu_id) y se ordena dentro de ese menú
     * (posicion es relativa a cada menú padre). Se instancia con una conexión PDO.
     */
    class ItemMenu
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inserta un ítem de menú. La posición es relativa al menú padre: si se
         * recibe una posición se inserta ahí (corriendo a los demás del mismo menú);
         * si no, va al final (MAX + 1 dentro del menú).
         *
         * @param array       $d         Claves: menu_id, nombre, icono, enlace, estado, posicion.
         * @param string|null $creadoPor Id del usuario que crea el registro (auditoría).
         * @return int Id del ítem creado.
         */
        public function crear(array $d, $creadoPor = null)
        {
            $this->pdo->beginTransaction();

            try {
                $menuId  = (int) $d['menu_id'];
                $elegida = $d['posicion'] ?? '';

                if (is_numeric($elegida) && (int) $elegida >= 1) {
                    // Posición elegida: corre +1 a los ítems del mismo menú en esa posición o posteriores.
                    $posicion = (int) $elegida;
                    $total    = $this->contarPorMenu($menuId);
                    if ($posicion > $total + 1) {
                        $posicion = $total + 1;
                    }
                    $this->pdo->prepare(
                        "UPDATE item_menus SET posicion = posicion + 1 WHERE menu_id = ? AND posicion >= ?"
                    )->execute([$menuId, $posicion]);
                } else {
                    // Sin posición elegida: al final del menú (MAX + 1).
                    $posicion = $this->siguientePosicion($menuId);
                }

                $stmt = $this->pdo->prepare(
                    "INSERT INTO item_menus (menu_id, nombre, icono_id, enlace, estado, posicion, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $menuId,
                    $d['nombre'],
                    !empty($d['icono_id']) ? (int) $d['icono_id'] : null,
                    ($d['enlace'] ?? '') !== '' ? $d['enlace'] : null,
                    (int) $d['estado'],
                    $posicion,
                    $creadoPor,
                ]);

                $id = (int) $this->pdo->lastInsertId();
                $this->pdo->commit();

                return $id;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Siguiente posición disponible dentro de un menú: MAX(posicion) + 1
         * (1 si el menú aún no tiene ítems).
         *
         * @param int $menuId
         * @return int
         */
        public function siguientePosicion($menuId)
        {
            $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(posicion), 0) + 1 FROM item_menus WHERE menu_id = ?");
            $stmt->execute([(int) $menuId]);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Cantidad de ítems de un menú (para acotar la posición elegida).
         *
         * @param int $menuId
         * @return int
         */
        public function contarPorMenu($menuId)
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM item_menus WHERE menu_id = ?");
            $stmt->execute([(int) $menuId]);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Cantidad de ítems agrupada por menú. Lo usa el combobox para mostrar cuántos
         * ítems tiene cada menú.
         *
         * @return array Mapa [menu_id => total].
         */
        public function contarPorTodosLosMenus()
        {
            $filas = $this->pdo
                ->query("SELECT menu_id, COUNT(*) AS total FROM item_menus GROUP BY menu_id")
                ->fetchAll();

            $mapa = [];
            foreach ($filas as $f) {
                $mapa[(int) $f['menu_id']] = (int) $f['total'];
            }

            return $mapa;
        }

        /**
         * Lista todos los ítems con su menú, ordenados por menú y posición. Lo usa el
         * modal de Asignar Posición (que filtra por el menú elegido en el formulario).
         *
         * @return array Lista de ['id', 'menu_id', 'nombre', 'estado', 'posicion'].
         */
        public function listarOrdenados()
        {
            return $this->pdo
                ->query("SELECT id, menu_id, nombre, icono_id, estado, posicion FROM item_menus ORDER BY menu_id ASC, posicion ASC")
                ->fetchAll();
        }
    }
