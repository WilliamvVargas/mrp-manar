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
         * Cantidad de registros que coinciden con los filtros (familia, año y mes).
         * Si no hay ningún filtro, equivale al total.
         */
        public function contarFiltrados($familia, $anio = '', $mes = '')
        {
            list($where, $params) = $this->construirFiltro($familia, $anio, $mes);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM presupuestos $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Construye el WHERE de los filtros de la tabla (familia exacta, año y mes).
         * Compartido por contarFiltrados, listarPagina y sumaVenta.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($familia, $anio, $mes)
        {
            $condiciones = [];
            $params      = [];

            if ($familia !== '') {
                $condiciones[] = "familia = ?";
                $params[]      = $familia;
            }

            if ($anio !== '' && ctype_digit((string) $anio)) {
                $condiciones[] = "anio = ?";
                $params[]      = (int) $anio;
            }

            if ($mes !== '' && ctype_digit((string) $mes)) {
                $condiciones[] = "mes = ?";
                $params[]      = (int) $mes;
            }

            $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

            return [$where, $params];
        }

        /**
         * Años distintos presentes en los presupuestos (descendente), para el filtro de Año.
         *
         * @return int[]
         */
        public function aniosDisponibles()
        {
            return array_map(
                'intval',
                $this->pdo->query("SELECT DISTINCT anio FROM presupuestos ORDER BY anio DESC")
                          ->fetchAll(PDO::FETCH_COLUMN)
            );
        }

        /**
         * Familias distintas presentes en los presupuestos (alfabético), para el filtro de Familia.
         *
         * @return string[]
         */
        public function familiasDisponibles()
        {
            return $this->pdo->query(
                "SELECT DISTINCT familia FROM presupuestos WHERE familia IS NOT NULL AND familia <> '' ORDER BY familia ASC"
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Suma de la columna `venta` para el conjunto filtrado (mismos filtros que la tabla).
         * Lo usa el total del pie de la tabla.
         *
         * @return float
         */
        public function sumaVenta($familia, $anio = '', $mes = '')
        {
            list($where, $params) = $this->construirFiltro($familia, $anio, $mes);

            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(venta), 0) FROM presupuestos $where");
            $stmt->execute($params);

            return (float) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de registros para DataTables (server-side).
         *
         * @param string $familia      Familia exacta a filtrar ('' = todas).
         * @param string $anio         Año a filtrar ('' = todos).
         * @param string $mes          Mes (1-12) a filtrar ('' = todos).
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($familia, $anio, $mes, $columnaOrden, $dirOrden, $inicio, $longitud)
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

            list($where, $params) = $this->construirFiltro($familia, $anio, $mes);

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
