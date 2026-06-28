<?php

    /*
     * Modelo de acceso a datos de la tabla `perfiles`.
     * De momento solo id + nombre (único). Se instancia con una conexión PDO.
     */
    class Perfil
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inserta un perfil.
         *
         * @param string      $nombre
         * @param string|null $creadoPor Id del usuario que crea el registro (auditoría).
         * @return int Id del perfil creado.
         */
        public function crear($nombre, $creadoPor = null)
        {
            $stmt = $this->pdo->prepare(
                "INSERT INTO perfiles (nombre, created_by) VALUES (?, ?)"
            );
            $stmt->execute([$nombre, $creadoPor]);

            return (int) $this->pdo->lastInsertId();
        }

        /**
         * ¿Existe un perfil con ese nombre? La unicidad es case-insensitive (colación de la
         * tabla). Permite excluir un id (para la edición). Lo usa la validación de unicidad.
         *
         * @param string          $nombre
         * @param int|string|null $exceptoId Id a excluir de la búsqueda (o null).
         * @return bool
         */
        public function existeNombre($nombre, $exceptoId = null)
        {
            $sql    = "SELECT COUNT(*) FROM perfiles WHERE nombre = ?";
            $params = [trim($nombre)];

            if ($exceptoId !== null && ctype_digit((string) $exceptoId)) {
                $sql     .= " AND id <> ?";
                $params[] = (int) $exceptoId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn() > 0;
        }

        /**
         * Cantidad total de perfiles (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM perfiles")->fetchColumn();
        }

        /**
         * Cantidad de perfiles que coinciden con el filtro de búsqueda (por nombre).
         * Si el filtro está vacío, equivale al total.
         */
        public function contarFiltrados($busqueda)
        {
            if ($busqueda === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM perfiles WHERE nombre LIKE ?");
            $stmt->execute(['%' . $busqueda . '%']);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de perfiles para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en el nombre.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables (evita inyección en el ORDER BY).
            $columnasValidas = [
                'id'     => 'id',
                'nombre' => 'nombre',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'id';
            $direccion = (strtolower($dirOrden) === 'desc') ? 'DESC' : 'ASC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            $where  = '';
            $params = [];
            if ($busqueda !== '') {
                $where    = "WHERE nombre LIKE ?";
                $params[] = '%' . $busqueda . '%';
            }

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id, nombre
                    FROM perfiles
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }
    }
