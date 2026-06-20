<?php

    /*
     * Modelo de acceso a datos de la tabla `iconos`.
     *
     * Se instancia pasándole una conexión PDO, igual que los demás modelos.
     */
    class Icono
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inserta un icono. La posición se asigna como MAX(posicion) + 1 (al final).
         *
         * @param array       $d         Claves: nombre, tipo, valor, archivo (o null), coloreable.
         * @param string|null $creadoPor Id del usuario que crea el registro (auditoría).
         * @return int Id del icono creado.
         */
        public function crear(array $d, $creadoPor = null)
        {
            $posicion = $this->siguientePosicion();

            $sql = "INSERT INTO iconos (nombre, tipo, valor, archivo, coloreable, posicion, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $d['nombre'],
                $d['tipo'],
                $d['valor'],
                $d['archivo'],
                (int) $d['coloreable'],
                $posicion,
                $creadoPor,
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        /**
         * Siguiente posición disponible: MAX(posicion) + 1 (1 si la tabla está vacía).
         *
         * @return int
         */
        public function siguientePosicion()
        {
            return (int) $this->pdo
                ->query("SELECT COALESCE(MAX(posicion), 0) + 1 FROM iconos")
                ->fetchColumn();
        }

        /**
         * Indica si ya existe un icono con ese `valor` (id de símbolo / nombre Bootstrap).
         *
         * @param string $valor
         * @return bool
         */
        public function existePorValor($valor, $excluirId = null)
        {
            $sql    = "SELECT 1 FROM iconos WHERE valor = ?";
            $params = [$valor];

            if ($excluirId !== null) {
                $sql     .= " AND id <> ?";
                $params[] = (int) $excluirId;
            }
            $sql .= " LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (bool) $stmt->fetchColumn();
        }

        /**
         * Devuelve un icono por su id (o false si no existe).
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT id, nombre, tipo, valor, archivo, coloreable, posicion FROM iconos WHERE id = ?");
            $stmt->execute([(int) $id]);

            return $stmt->fetch();
        }

        /**
         * Actualiza solo el nombre de un icono (caso Bootstrap o personalizado sin cambio de valor).
         *
         * @return int Filas afectadas (0 si no hubo cambios).
         */
        public function actualizarNombre($id, $nombre, $actualizadoPor = null)
        {
            $stmt = $this->pdo->prepare("UPDATE iconos SET nombre = ?, updated_by = ? WHERE id = ?");
            $stmt->execute([$nombre, $actualizadoPor, (int) $id]);

            return $stmt->rowCount();
        }

        /**
         * Actualiza un icono personalizado cuando cambia su valor: nombre, valor y archivo.
         *
         * @return int Filas afectadas.
         */
        public function actualizarPersonalizado($id, $nombre, $valor, $archivo, $actualizadoPor = null)
        {
            $stmt = $this->pdo->prepare("UPDATE iconos SET nombre = ?, valor = ?, archivo = ?, updated_by = ? WHERE id = ?");
            $stmt->execute([$nombre, $valor, $archivo, $actualizadoPor, (int) $id]);

            return $stmt->rowCount();
        }

        /**
         * Elimina un icono por su id.
         *
         * @return int Filas eliminadas (0 si no existía).
         */
        public function eliminar($id)
        {
            $stmt = $this->pdo->prepare("DELETE FROM iconos WHERE id = ?");
            $stmt->execute([(int) $id]);

            return $stmt->rowCount();
        }

        /**
         * Devuelve los iconos personalizados (valor + archivo), ordenados por posición.
         * Lo usa la regeneración del sprite combinado.
         *
         * @return array Lista de ['valor' => ..., 'archivo' => ...].
         */
        public function listarPersonalizados()
        {
            return $this->pdo
                ->query("SELECT valor, archivo FROM iconos WHERE tipo = 'personalizado' ORDER BY posicion")
                ->fetchAll();
        }

        /**
         * Cantidad total de iconos (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM iconos")->fetchColumn();
        }

        /**
         * Cantidad de iconos que coinciden con el buscador (nombre/valor) y el filtro de tipo.
         */
        public function contarFiltrados($busqueda, $tipo)
        {
            [$where, $params] = $this->construirFiltro($busqueda, $tipo);

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM iconos $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Página de iconos para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en nombre o valor.
         * @param string $tipo         '' (todos), 'bootstrap' o 'personalizado'.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset.
         * @param int    $longitud     Cantidad (-1 = todos).
         */
        public function listarPagina($busqueda, $tipo, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            $columnasValidas = [
                'id'       => 'id',
                'nombre'   => 'nombre',
                'valor'    => 'valor',
                'tipo'     => 'tipo',
                'posicion' => 'posicion',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'posicion';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            [$where, $params] = $this->construirFiltro($busqueda, $tipo);
            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id, nombre, tipo, valor, archivo, coloreable, posicion
                    FROM iconos
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        /**
         * Arma el WHERE y los parámetros del buscador (nombre/valor) + filtro de tipo.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $tipo)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $condiciones[] = '(nombre LIKE ? OR valor LIKE ?)';
                $like = '%' . $busqueda . '%';
                $params[] = $like;
                $params[] = $like;
            }

            if ($tipo === 'bootstrap' || $tipo === 'personalizado') {
                $condiciones[] = 'tipo = ?';
                $params[] = $tipo;
            }

            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

            return [$where, $params];
        }
    }
