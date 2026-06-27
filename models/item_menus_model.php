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
         * Actualiza un ítem. La posición es relativa al menú: se cierra el hueco que deja
         * en su menú de origen y se hace lugar en el menú de destino (sirve tanto para
         * mover dentro del mismo menú como para cambiarlo de menú). Si no se recibe una
         * posición válida, el ítem va al final del menú de destino.
         *
         * @param int         $id            Id del ítem a actualizar.
         * @param array       $d             Claves: menu_id, nombre, icono_id, enlace, estado, posicion.
         * @param string|null $actualizadoPor Id del usuario que edita (auditoría).
         * @return bool true si se actualizó, false si el ítem no existe.
         */
        public function actualizar($id, array $d, $actualizadoPor = null)
        {
            $this->pdo->beginTransaction();

            try {
                $id     = (int) $id;
                $actual = $this->buscarPorId($id);

                if (!$actual) {
                    $this->pdo->rollBack();
                    return false;
                }

                $menuOrigen  = (int) $actual['menu_id'];
                $posOrigen   = (int) $actual['posicion'];
                $menuDestino = (int) $d['menu_id'];

                // 1. Cierra el hueco en el menú de origen (los posteriores suben una posición).
                $this->pdo->prepare(
                    "UPDATE item_menus SET posicion = posicion - 1 WHERE menu_id = ? AND posicion > ?"
                )->execute([$menuOrigen, $posOrigen]);

                // 2. Posición destino: la elegida (acotada) o al final del menú destino.
                $totalDestino = $this->contarPorMenuExcepto($menuDestino, $id);
                $elegida      = $d['posicion'] ?? '';
                if (is_numeric($elegida) && (int) $elegida >= 1) {
                    $posDestino = min((int) $elegida, $totalDestino + 1);
                } else {
                    $posDestino = $totalDestino + 1;
                }

                // 3. Hace lugar en el menú destino (corre +1 a los que estén en esa posición o después).
                $this->pdo->prepare(
                    "UPDATE item_menus SET posicion = posicion + 1 WHERE menu_id = ? AND posicion >= ? AND id <> ?"
                )->execute([$menuDestino, $posDestino, $id]);

                // 4. Actualiza el ítem con sus nuevos datos y su posición final.
                $this->pdo->prepare(
                    "UPDATE item_menus
                        SET menu_id = ?, nombre = ?, icono_id = ?, enlace = ?, estado = ?, posicion = ?, updated_by = ?
                      WHERE id = ?"
                )->execute([
                    $menuDestino,
                    $d['nombre'],
                    !empty($d['icono_id']) ? (int) $d['icono_id'] : null,
                    ($d['enlace'] ?? '') !== '' ? $d['enlace'] : null,
                    (int) $d['estado'],
                    $posDestino,
                    $actualizadoPor,
                    $id,
                ]);

                $this->pdo->commit();
                return true;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Cantidad de ítems de un menú excluyendo uno (para acotar la posición destino al
         * mover/editar, sin contar el propio ítem que se reubica).
         *
         * @param int $menuId
         * @param int $exceptoId
         * @return int
         */
        public function contarPorMenuExcepto($menuId, $exceptoId)
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM item_menus WHERE menu_id = ? AND id <> ?");
            $stmt->execute([(int) $menuId, (int) $exceptoId]);

            return (int) $stmt->fetchColumn();
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
         * Devuelve un ítem por su id con sus campos crudos, para poblar el formulario de
         * edición. El nombre del menú y los datos del ícono los resuelve el front con sus
         * catálogos (MENUS / ICONOS), así que aquí basta con las llaves.
         *
         * @param  int $id
         * @return array|false ['id','menu_id','nombre','enlace','icono_id','estado','posicion'] o false.
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id, menu_id, nombre, enlace, icono_id, estado, posicion
                 FROM item_menus
                 WHERE id = ?"
            );
            $stmt->execute([(int) $id]);

            return $stmt->fetch();
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

        /**
         * Cantidad total de ítems (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM item_menus")->fetchColumn();
        }

        /**
         * Cantidad de ítems que coinciden con los filtros (búsqueda, menú y estado).
         */
        public function contarFiltrados($busqueda, $menuId, $estado)
        {
            list($where, $params) = $this->construirFiltro($busqueda, $menuId, $estado);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM item_menus im $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Página de ítems para DataTables (server-side). Incluye el nombre del menú padre
         * y los datos del ícono (para mostrarlo). La búsqueda aplica a nombre y enlace;
         * además se puede filtrar por menú y por estado.
         *
         * @param string $busqueda     Texto a buscar en nombre o enlace.
         * @param string $menuId       '' (todos) o el id del menú a filtrar.
         * @param string $estado       '' (todos), '1' (activo) o '0' (inactivo).
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset.
         * @param int    $longitud     Cantidad (-1 = todos).
         */
        public function listarPagina($busqueda, $menuId, $estado, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables (evita inyección en el ORDER BY).
            $columnasValidas = [
                'id'       => 'im.id',
                'posicion' => 'im.posicion',
                'menu'     => 'm.nombre',
                'nombre'   => 'im.nombre',
                'enlace'   => 'im.enlace',
                'estado'   => 'im.estado',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'im.id';
            $direccion = (strtolower($dirOrden) === 'desc') ? 'DESC' : 'ASC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda, $menuId, $estado);
            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            // Desempate por menú + posición para que los ítems de un mismo menú salgan en orden.
            $sql = "SELECT im.id, im.menu_id, m.nombre AS menu_nombre, im.nombre, im.enlace,
                           im.icono_id, ic.tipo AS icono_tipo, ic.valor AS icono_valor, ic.archivo AS icono_archivo,
                           im.estado, im.posicion
                    FROM item_menus im
                    INNER JOIN menus m  ON m.id  = im.menu_id
                    LEFT JOIN  iconos ic ON ic.id = im.icono_id
                    $where
                    ORDER BY $columna $direccion, m.nombre ASC, im.posicion ASC
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        /**
         * Arma el WHERE y los parámetros de los filtros de la consulta principal
         * (búsqueda en nombre/enlace, menú y estado). Usa el alias `im` de item_menus.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $menuId, $estado)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $condiciones[] = '(im.nombre LIKE ? OR im.enlace LIKE ?)';
                $like = '%' . $busqueda . '%';
                $params[] = $like;
                $params[] = $like;
            }

            if ($menuId !== '' && ctype_digit((string) $menuId)) {
                $condiciones[] = 'im.menu_id = ?';
                $params[]      = (int) $menuId;
            }

            if ($estado !== '') {
                $condiciones[] = 'im.estado = ?';
                $params[]      = (int) $estado;
            }

            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

            return [$where, $params];
        }
    }
