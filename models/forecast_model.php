<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast`.
     *
     * Centraliza las consultas SQL del forecast. Se instancia pasándole una
     * conexión PDO, igual que el modelo de usuarios.
     */
    class Forecast
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inserta masivamente un conjunto de registros dentro de una transacción
         * (todo o nada): si alguno falla, se revierten todos.
         *
         * Cada registro debe traer las claves: empresa, version, codigo_cliente,
         * nombre_cliente, fecha, codigo_producto, nombre_producto, cantidad.
         *
         * @param array       $registros Lista de registros a insertar.
         * @param string|null $creadoPor Id del usuario que realiza la carga (auditoría).
         * @return int Cantidad de registros insertados.
         */
        public function insertarMasivo(array $registros, $creadoPor = null)
        {
            $sql = "INSERT INTO forecast (empresa,
                                         version,
                                         codigo_cliente,
                                         nombre_cliente,
                                         fecha,
                                         codigo_producto,
                                         nombre_producto,
                                         cantidad,
                                         created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            $this->pdo->beginTransaction();

            try {
                foreach ($registros as $r) {
                    $stmt->execute([
                        $r['empresa'],
                        $r['version'],
                        $r['codigo_cliente'],
                        $r['nombre_cliente'],
                        $r['fecha'],
                        $r['codigo_producto'],
                        $r['nombre_producto'],
                        $r['cantidad'],
                        $creadoPor,
                    ]);
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            return count($registros);
        }

        /**
         * Cantidad total de registros de forecast (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM forecast")->fetchColumn();
        }

        /**
         * Cantidad de registros que coinciden con el filtro en nombre_cliente o nombre_producto.
         * Si el filtro está vacío, equivale al total.
         */
        public function contarFiltrados($busqueda)
        {
            if ($busqueda === '') {
                return $this->contarTodos();
            }

            $like = '%' . $busqueda . '%';
            $stmt = $this->pdo->prepare("SELECT COUNT(*)
                                         FROM forecast
                                         WHERE nombre_cliente LIKE ?
                                            OR nombre_producto LIKE ?");
            $stmt->execute([$like, $like]);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de registros para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en nombre_cliente o nombre_producto.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'id'              => 'id',
                'empresa'         => 'empresa',
                'version'         => 'version',
                'codigo_cliente'  => 'codigo_cliente',
                'nombre_cliente'  => 'nombre_cliente',
                'codigo_producto' => 'codigo_producto',
                'nombre_producto' => 'nombre_producto',
                'cantidad'        => 'cantidad',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'id';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            $where  = '';
            $params = [];
            if ($busqueda !== '') {
                $like   = '%' . $busqueda . '%';
                $where  = "WHERE nombre_cliente LIKE ? OR nombre_producto LIKE ?";
                $params = [$like, $like];
            }

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id,
                           empresa,
                           version,
                           codigo_cliente,
                           nombre_cliente,
                           codigo_producto,
                           nombre_producto,
                           cantidad
                    FROM forecast
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }
    }
