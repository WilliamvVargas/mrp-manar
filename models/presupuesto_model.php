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

        /** @var string|null Empresa activa: todo el presupuesto se filtra y se estampa por ella. */
        private $empresaId;

        public function __construct(PDO $pdo, $empresaId = null)
        {
            $this->pdo       = $pdo;
            $this->empresaId = $empresaId;
        }

        /**
         * Calcula la versión que le corresponde a una nueva carga de la empresa activa.
         *
         * Formato: V + AAAAMM (año-mes de la FECHA DE CARGA) + NNN (correlativo de 3
         * dígitos que reinicia cada mes). Ejemplo: la primera carga de mayo 2026 es
         * "V202605001", la segunda "V202605002"; la primera de junio vuelve a "...001".
         * El correlativo se acota por empresa (cada empresa lleva su propia numeración).
         *
         * @return string Versión con formato VAAAAMMNNN (10 caracteres).
         */
        public function siguienteVersion()
        {
            $prefijo = 'V' . date('Ym');   // año-mes de la carga actual (p. ej. V202605)

            // Mayor correlativo ya usado por esta empresa dentro del mismo año-mes.
            $stmt = $this->pdo->prepare(
                "SELECT MAX(CAST(SUBSTRING(version, 8, 3) AS UNSIGNED))
                 FROM presupuestos
                 WHERE empresa_id = ? AND version LIKE ?"
            );
            $stmt->execute([$this->empresaId, $prefijo . '%']);
            $ultimo = (int) $stmt->fetchColumn();

            return $prefijo . str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);
        }

        /**
         * Inserta masivamente un conjunto de registros dentro de una transacción
         * (todo o nada): si alguno falla, se revierten todos.
         *
         * Todos los registros de la carga comparten una misma versión, calculada
         * automáticamente (ver siguienteVersion): así una empresa puede tener varias
         * versiones de su presupuesto, incluso cargadas el mismo mes.
         *
         * Cada registro debe traer las claves: anio, mes, canal, sub_canal, familia,
         * sub_familia, venta, mg_porcentaje, mg_neto, pp, kg (los vacíos llegan como null).
         *
         * @param array       $registros Lista de registros a insertar.
         * @param string|null $creadoPor Id del usuario que realiza la carga (auditoría).
         * @return array ['insertados' => int, 'version' => string]
         */
        public function insertarMasivo(array $registros, $creadoPor = null)
        {
            $sql = "INSERT INTO presupuestos (empresa_id,
                                              version,
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
                                              kg,
                                              created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            $this->pdo->beginTransaction();

            try {
                // Se calcula dentro de la transacción para acotar la ventana de colisión.
                $version = $this->siguienteVersion();

                foreach ($registros as $r) {
                    $stmt->execute([
                        $this->empresaId,
                        $version,
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

            return ['insertados' => count($registros), 'version' => $version];
        }

        /**
         * Cantidad total de registros (sin filtro).
         */
        public function contarTodos()
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM presupuestos WHERE empresa_id = ?");
            $stmt->execute([$this->empresaId]);
            return (int) $stmt->fetchColumn();
        }

        /**
         * Cantidad de registros que coinciden con los filtros (familia, sub-familia, año y mes).
         * Si no hay ningún filtro, equivale al total.
         */
        public function contarFiltrados($familia, $subFamilia = '', $anio = '', $mes = '', $version = '')
        {
            list($where, $params) = $this->construirFiltro($familia, $subFamilia, $anio, $mes, $version);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM presupuestos $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Construye el WHERE de los filtros de la tabla (familia y sub-familia exactas, año y mes).
         * Compartido por contarFiltrados y listarPagina.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($familia, $subFamilia, $anio, $mes, $version = '')
        {
            // Todo el presupuesto se acota SIEMPRE a la empresa activa.
            $condiciones = ['empresa_id = ?'];
            $params      = [$this->empresaId];

            if ($version !== '') {
                $condiciones[] = "version = ?";
                $params[]      = $version;
            }

            if ($familia !== '') {
                $condiciones[] = "familia = ?";
                $params[]      = $familia;
            }

            if ($subFamilia !== '') {
                $condiciones[] = "sub_familia = ?";
                $params[]      = $subFamilia;
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
            $stmt = $this->pdo->prepare("SELECT DISTINCT anio FROM presupuestos WHERE empresa_id = ? ORDER BY anio DESC");
            $stmt->execute([$this->empresaId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        /**
         * Versiones distintas presentes en los presupuestos de la empresa (la más
         * reciente primero), para el filtro de Versión. Al ser de largo fijo, el orden
         * descendente por texto equivale al orden cronológico.
         *
         * @return string[]
         */
        public function versionesDisponibles()
        {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT version FROM presupuestos WHERE empresa_id = ? AND version IS NOT NULL ORDER BY version DESC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Familias distintas presentes en los presupuestos (alfabético), para el filtro de Familia.
         *
         * @return string[]
         */
        public function familiasDisponibles()
        {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT familia FROM presupuestos WHERE empresa_id = ? AND familia IS NOT NULL AND familia <> '' ORDER BY familia ASC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Sub-familias distintas presentes en los presupuestos (alfabético), para el filtro.
         *
         * @return string[]
         */
        public function subFamiliasDisponibles()
        {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT sub_familia FROM presupuestos WHERE empresa_id = ? AND sub_familia IS NOT NULL AND sub_familia <> '' ORDER BY sub_familia ASC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Devuelve una página de registros para DataTables (server-side).
         *
         * @param string $familia      Familia exacta a filtrar ('' = todas).
         * @param string $subFamilia   Sub-familia exacta a filtrar ('' = todas).
         * @param string $anio         Año a filtrar ('' = todos).
         * @param string $mes          Mes (1-12) a filtrar ('' = todos).
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($familia, $subFamilia, $anio, $mes, $version, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'id'            => 'id',
                'version'       => 'version',
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

            list($where, $params) = $this->construirFiltro($familia, $subFamilia, $anio, $mes, $version);

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id,
                           version,
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
