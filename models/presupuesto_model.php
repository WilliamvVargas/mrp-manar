<?php

    /*
     * Modelo de acceso a datos de la tabla `presupuestos`.
     *
     * Centraliza las consultas SQL del presupuesto. Se instancia pasándole una
     * conexión PDO, igual que los demás modelos.
     */
    class Presupuesto
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
         * Cada registro debe traer las claves: anio, mes, canal, sub_canal, familia,
         * sub_familia, venta, mg_porcentaje, mg_neto, pp, kg (los vacíos llegan como null).
         *
         * @param array       $registros Lista de registros a insertar.
         * @param string|null $creadoPor Id del usuario que realiza la carga (auditoría).
         * @return int Cantidad de registros insertados.
         */
        public function insertarMasivo(array $registros, $creadoPor = null)
        {
            $sql = "INSERT INTO presupuestos (anio,
                                              mes,
                                              canal,
                                              sub_canal,
                                              familia,
                                              sub_familia,
                                              venta,
                                              mg_porcentaje,
                                              mg_neto,
                                              pp,
                                              kg,
                                              created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            $this->pdo->beginTransaction();

            try {
                foreach ($registros as $r) {
                    $stmt->execute([
                        $r['anio'],
                        $r['mes'],
                        $r['canal'],
                        $r['sub_canal'],
                        $r['familia'],
                        $r['sub_familia'],
                        $r['venta'],
                        $r['mg_porcentaje'],
                        $r['mg_neto'],
                        $r['pp'],
                        $r['kg'],
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
         * Cantidad total de registros (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM presupuestos")->fetchColumn();
        }

        /**
         * Cantidad de registros que coinciden con el filtro en canal, sub_canal,
         * familia o sub_familia. Si el filtro está vacío, equivale al total.
         */
        public function contarFiltrados($busqueda)
        {
            if ($busqueda === '') {
                return $this->contarTodos();
            }

            $like = '%' . $busqueda . '%';
            $stmt = $this->pdo->prepare("SELECT COUNT(*)
                                         FROM presupuestos
                                         WHERE canal       LIKE ?
                                            OR sub_canal   LIKE ?
                                            OR familia     LIKE ?
                                            OR sub_familia LIKE ?");
            $stmt->execute([$like, $like, $like, $like]);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de registros para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en canal/sub_canal/familia/sub_familia.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'id'            => 'id',
                'anio'          => 'anio',
                'mes'           => 'mes',
                'canal'         => 'canal',
                'sub_canal'     => 'sub_canal',
                'familia'       => 'familia',
                'sub_familia'   => 'sub_familia',
                'venta'         => 'venta',
                'mg_porcentaje' => 'mg_porcentaje',
                'mg_neto'       => 'mg_neto',
                'pp'            => 'pp',
                'kg'            => 'kg',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'id';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            $where  = '';
            $params = [];
            if ($busqueda !== '') {
                $like   = '%' . $busqueda . '%';
                $where  = "WHERE canal LIKE ? OR sub_canal LIKE ? OR familia LIKE ? OR sub_familia LIKE ?";
                $params = [$like, $like, $like, $like];
            }

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id,
                           anio,
                           mes,
                           canal,
                           sub_canal,
                           familia,
                           sub_familia,
                           venta,
                           mg_porcentaje,
                           mg_neto,
                           pp,
                           kg
                    FROM presupuestos
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }
    }
