<?php

    /*
     * Modelo de acceso a datos de la tabla `menus`.
     * Se instancia pasándole una conexión PDO, igual que los demás modelos.
     */
    class Menu
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inserta un nuevo menú. La posición se asigna automáticamente como
         * MAX(posicion) + 1, de modo que el menú nuevo queda al final.
         *
         * @param string $nombre
         * @param int    $estado 1 = activo, 0 = inactivo.
         * @return bool True si la inserción fue exitosa.
         */
        public function crear($nombre, $estado, $posicion = null)
        {
            $this->pdo->beginTransaction();

            try {

                if (!is_numeric($posicion) || (int) $posicion < 1) {
                    // Sin posición indicada: va al final, no hay que correr a nadie.
                    $posicion = $this->siguientePosicion();
                } else {
                    $posicion = (int) $posicion;

                    // No dejar huecos: como tope, al final (MAX + 1).
                    $maximo = $this->siguientePosicion();
                    if ($posicion > $maximo) {
                        $posicion = $maximo;
                    }

                    // Hace espacio: corre +1 los menús en esa posición o posteriores.
                    $corrimiento = $this->pdo->prepare(
                        "UPDATE menus SET posicion = posicion + 1 WHERE posicion >= ?"
                    );
                    $corrimiento->execute([$posicion]);
                }

                $insert = $this->pdo->prepare(
                    "INSERT INTO menus (nombre, estado, posicion) VALUES (?, ?, ?)"
                );
                $ok = $insert->execute([$nombre, $estado, $posicion]);

                $this->pdo->commit();

                return $ok;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Devuelve un menú por su id, o false si no existe.
         *
         * @param int $id Identificador del menú.
         * @return array|false
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT id, nombre, estado, posicion FROM menus WHERE id = ?");
            $stmt->execute([(int) $id]);

            return $stmt->fetch();
        }

        /**
         * Actualiza el nombre y el estado de un menú. La posición no se toca aquí.
         *
         * @param int    $id     Identificador del menú.
         * @param string $nombre Nuevo nombre.
         * @param int    $estado 1 = activo, 0 = inactivo.
         * @return int Cantidad de filas afectadas (0 si no hubo cambios).
         */
        public function actualizar($id, $nombre, $estado)
        {
            $stmt = $this->pdo->prepare("UPDATE menus SET nombre = ?, estado = ? WHERE id = ?");
            $stmt->execute([$nombre, (int) $estado, (int) $id]);

            return $stmt->rowCount();
        }

        /**
         * Cambia la posición de un menú existente, corriendo a los demás para no
         * dejar huecos (misma lógica de inserción que `crear`, pero reubicando).
         *
         * @param int $id            Identificador del menú a mover.
         * @param int $nuevaPosicion Posición destino (se acota al rango [1, total]).
         * @return int 1 si la posición cambió, 0 si quedó igual o el menú no existe.
         */
        public function reposicionar($id, $nuevaPosicion)
        {
            $this->pdo->beginTransaction();

            try {

                $menu = $this->buscarPorId($id);

                if (!$menu) {
                    $this->pdo->commit();
                    return 0;
                }

                $actual  = (int) $menu['posicion'];
                $destino = (int) $nuevaPosicion;

                // Acota el destino al rango válido [1, total].
                $total = (int) $this->pdo->query("SELECT COUNT(*) FROM menus")->fetchColumn();
                if ($destino < 1)      $destino = 1;
                if ($destino > $total) $destino = $total;

                if ($destino === $actual) {
                    $this->pdo->commit();
                    return 0;
                }

                if ($destino < $actual) {
                    // Sube: los que están entre el destino y la posición actual bajan +1.
                    $corrimiento = $this->pdo->prepare(
                        "UPDATE menus SET posicion = posicion + 1 WHERE posicion >= ? AND posicion < ?"
                    );
                    $corrimiento->execute([$destino, $actual]);
                } else {
                    // Baja: los que están entre la posición actual y el destino suben -1.
                    $corrimiento = $this->pdo->prepare(
                        "UPDATE menus SET posicion = posicion - 1 WHERE posicion > ? AND posicion <= ?"
                    );
                    $corrimiento->execute([$actual, $destino]);
                }

                $update = $this->pdo->prepare("UPDATE menus SET posicion = ? WHERE id = ?");
                $update->execute([$destino, (int) $id]);

                $this->pdo->commit();

                return 1;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Alterna el estado de un menú: activo (1) pasa a inactivo (0) y viceversa.
         *
         * @param int $id Identificador del menú.
         * @return bool True si se actualizó algún registro.
         */
        public function cambiarEstado($id)
        {
            $stmt = $this->pdo->prepare("UPDATE menus SET estado = 1 - estado WHERE id = ?");
            $stmt->execute([(int) $id]);

            return $stmt->rowCount() > 0;
        }

        /**
         * Elimina un menú y recompacta las posiciones: los menús posteriores al
         * eliminado bajan -1 para no dejar huecos. Todo dentro de una transacción.
         *
         * @param int $id Identificador del menú.
         * @return int Cantidad de filas eliminadas (0 si no existía).
         */
        public function eliminar($id)
        {
            $this->pdo->beginTransaction();

            try {

                $menu = $this->buscarPorId($id);

                if (!$menu) {
                    $this->pdo->commit();
                    return 0;
                }

                $posicion = (int) $menu['posicion'];

                $borrar = $this->pdo->prepare("DELETE FROM menus WHERE id = ?");
                $borrar->execute([(int) $id]);
                $filas = $borrar->rowCount();

                // Recompacta: los menús posteriores al eliminado bajan una posición.
                $corrimiento = $this->pdo->prepare(
                    "UPDATE menus SET posicion = posicion - 1 WHERE posicion > ?"
                );
                $corrimiento->execute([$posicion]);

                $this->pdo->commit();

                return $filas;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Devuelve la siguiente posición disponible: MAX(posicion) + 1
         * (1 si la tabla está vacía).
         *
         * @return int
         */
        public function siguientePosicion()
        {
            return (int) $this->pdo
                ->query("SELECT COALESCE(MAX(posicion), 0) + 1 FROM menus")
                ->fetchColumn();
        }

        /**
         * Devuelve todos los menús ordenados por posición ascendente.
         *
         * @return array Lista de menús: [ ['id'=>.., 'nombre'=>.., 'posicion'=>..], ... ]
         */
        public function listarOrdenados()
        {
            return $this->pdo
                ->query("SELECT id, nombre, posicion, estado FROM menus ORDER BY posicion ASC")
                ->fetchAll();
        }

        /**
         * Cantidad total de menús (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM menus")->fetchColumn();
        }

        /**
         * Cantidad de menús que coinciden con los filtros (nombre y estado).
         * Si no hay filtros, equivale al total.
         */
        public function contarFiltrados($busqueda, $estado = '')
        {
            list($where, $params) = $this->construirFiltro($busqueda, $estado);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM menus $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de menús para DataTables (server-side).
         * El filtro de búsqueda aplica al nombre; además se puede filtrar por estado.
         *
         * @param string $busqueda     Texto a buscar en el nombre.
         * @param string $estado       '' (todos), '1' (activo) o '0' (inactivo).
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $estado, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'posicion' => 'posicion',
                'nombre'   => 'nombre',
                'estado'   => 'estado',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'posicion';
            $direccion = (strtolower($dirOrden) === 'desc') ? 'DESC' : 'ASC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda, $estado);

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id, posicion, nombre, estado
                    FROM menus
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        /**
         * Arma la cláusula WHERE y sus parámetros para los filtros de la consulta
         * principal (nombre y/o estado). Compartida por contarFiltrados y listarPagina.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $estado)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $condiciones[] = "nombre LIKE ?";
                $params[]      = '%' . $busqueda . '%';
            }

            // '' = todos; '0'/'1' = filtra por ese estado.
            if ($estado !== '') {
                $condiciones[] = "estado = ?";
                $params[]      = (int) $estado;
            }

            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

            return [$where, $params];
        }
    }
