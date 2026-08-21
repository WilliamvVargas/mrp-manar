<?php
    /*
     * Modelo de acceso a datos de la tabla `empresas`. Se instancia con una conexión PDO.
     * El `id` (UUID) lo genera el trigger `antes_insertar_empresas` durante el INSERT.
     */
    class Empresa
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * ¿Existe una empresa con ese nombre? (para evitar duplicados).
         *
         * @param string   $nombre
         * @param string|null $idExcluir Id a excluir (para editar; null al crear).
         * @return bool
         */
        public function existePorNombre($nombre, $idExcluir = null)
        {
            $sql    = "SELECT 1 FROM empresas WHERE nombre = ?";
            $params = [trim($nombre)];

            if ($idExcluir !== null) {
                $sql     .= " AND id <> ?";
                $params[] = $idExcluir;
            }
            $sql .= " LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (bool) $stmt->fetchColumn();
        }

        /**
         * Crea una empresa. El id (UUID) lo pone el trigger antes_insertar_empresas.
         *
         * @param string      $nombre
         * @param string|null $logo      Nombre del archivo del logo (en assets/img/empresas/), o null.
         * @param string|null $creadoPor Id del usuario que la crea (auditoría).
         * @return bool
         */
        public function crear($nombre, $logo = null, $posicion = null, $creadoPor = null, $empresaWms = null)
        {
            $this->pdo->beginTransaction();
            try {
                if (!is_numeric($posicion) || (int) $posicion < 1) {
                    // Sin posición indicada: va al final (no hay que correr a nadie).
                    $posicion = $this->siguientePosicion();
                } else {
                    $posicion = (int) $posicion;
                    $maximo   = $this->siguientePosicion();   // como tope, al final (MAX + 1)
                    if ($posicion > $maximo) {
                        $posicion = $maximo;
                    }
                    // Hace espacio: corre +1 las empresas en esa posición o posteriores.
                    $this->pdo->prepare("UPDATE empresas SET posicion = posicion + 1 WHERE posicion >= ?")
                              ->execute([$posicion]);
                }

                $insert = $this->pdo->prepare(
                    "INSERT INTO empresas (nombre, empresa_wms, logo, posicion, created_by) VALUES (?, ?, ?, ?, ?)"
                );
                $ok = $insert->execute([trim($nombre), $empresaWms, $logo, $posicion, $creadoPor]);

                $this->pdo->commit();
                return $ok;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /** Siguiente posición disponible: MAX(posicion) + 1 (1 si está vacía). */
        public function siguientePosicion()
        {
            return (int) $this->pdo
                ->query("SELECT COALESCE(MAX(posicion), 0) + 1 FROM empresas")
                ->fetchColumn();
        }

        /**
         * Mueve una empresa a una nueva posición, corriendo a las demás para no dejar huecos.
         * El destino se acota al rango [1, total].
         *
         * @return int 1 si cambió, 0 si quedó igual o no existe.
         */
        public function reposicionar($id, $nuevaPosicion)
        {
            $this->pdo->beginTransaction();
            try {
                $empresa = $this->buscarPorId($id);
                if (!$empresa) {
                    $this->pdo->commit();
                    return 0;
                }

                $actual  = (int) $empresa['posicion'];
                $destino = (int) $nuevaPosicion;

                $total = (int) $this->pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
                if ($destino < 1)      { $destino = 1; }
                if ($destino > $total) { $destino = $total; }

                if ($destino === $actual) {
                    $this->pdo->commit();
                    return 0;
                }

                if ($destino < $actual) {
                    $this->pdo->prepare("UPDATE empresas SET posicion = posicion + 1 WHERE posicion >= ? AND posicion < ?")
                              ->execute([$destino, $actual]);
                } else {
                    $this->pdo->prepare("UPDATE empresas SET posicion = posicion - 1 WHERE posicion > ? AND posicion <= ?")
                              ->execute([$actual, $destino]);
                }

                $this->pdo->prepare("UPDATE empresas SET posicion = ? WHERE id = ?")->execute([$destino, $id]);

                $this->pdo->commit();
                return 1;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /** Todas las empresas (id, nombre, posicion) ordenadas, para el selector de posición. */
        public function listarOrden()
        {
            return $this->pdo
                ->query("SELECT id, nombre, posicion FROM empresas ORDER BY posicion ASC, nombre ASC")
                ->fetchAll();
        }

        /**
         * Reasigna las posiciones (1..N) según el orden de ids recibido (guardado global
         * del modal de Asignar Posición).
         *
         * @param array $ids Lista de ids en el nuevo orden.
         */
        public function reordenar(array $ids)
        {
            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("UPDATE empresas SET posicion = ? WHERE id = ?");
                $posicion = 1;
                foreach ($ids as $id) {
                    $stmt->execute([$posicion, $id]);
                    $posicion++;
                }
                $this->pdo->commit();
                return true;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /** Total de empresas (sin filtro). */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
        }

        /** Cantidad de empresas que coinciden con la búsqueda por nombre. */
        public function contarFiltrados($busqueda)
        {
            list($where, $params) = $this->construirFiltro($busqueda);

            if ($where === '') {
                return $this->contarTodos();
            }
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM empresas e $where");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }

        /**
         * WHERE del buscador (por nombre), campos prefijados con el alias `e`.
         * Compartido por contarFiltrados y listarPagina.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $condiciones[] = "e.nombre LIKE ?";
                $params[]      = '%' . $busqueda . '%';
            }

            $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';
            return [$where, $params];
        }

        /**
         * Devuelve una página de empresas para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en el nombre.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar ('nombre'|'fecha').
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'posicion' => 'e.posicion',
                'nombre'   => 'e.nombre',
                'fecha'    => 'e.created_at',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'e.posicion';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda);
            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT e.id,
                           e.nombre,
                           e.logo,
                           e.posicion,
                           DATE_FORMAT(e.created_at, '%d/%m/%Y %H:%i') AS fecha
                    FROM empresas e
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }

        /**
         * Busca una empresa por su id (para cargar el modal de edición).
         *
         * @return array|false ['id','nombre','logo'] o false si no existe.
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT id, nombre, empresa_wms, logo, posicion FROM empresas WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        /**
         * Actualiza el nombre y el logo de una empresa.
         * El logo que se pasa es el nombre de archivo FINAL (el nuevo si se reemplazó, o el
         * actual si se conserva); el manejo del archivo lo hace el controlador.
         *
         * @return int Filas afectadas.
         */
        public function actualizar($id, $nombre, $logo = null, $modificadoPor = null, $empresaWms = null)
        {
            $sql  = "UPDATE empresas SET nombre = ?, empresa_wms = ?, logo = ?, updated_by = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([trim($nombre), $empresaWms, $logo, $modificadoPor, $id]);
            return $stmt->rowCount();
        }

        /**
         * Datos de conexión SAP de una empresa (para cargar el modal "Conexión SAP").
         * La contraseña NO vive en la BD: se mantiene en la configuración del servidor.
         *
         * @return array|false ['id','nombre','sap_servidor','sap_base','sap_usuario'] o false.
         */
        public function obtenerConexionSap($id)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id, nombre, sap_servidor, sap_base, sap_usuario FROM empresas WHERE id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        /**
         * Guarda (actualiza) la conexión SAP de una empresa. Relación 1-a-1: los datos
         * viven en la propia fila de `empresas`. No toca la contraseña.
         *
         * @return int Filas afectadas.
         */
        public function guardarConexionSap($id, $servidor, $base, $usuario, $modificadoPor = null)
        {
            $sql  = "UPDATE empresas
                     SET sap_servidor = ?, sap_base = ?, sap_usuario = ?, updated_by = ?
                     WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([trim($servidor), trim($base), trim($usuario), $modificadoPor, $id]);
            return $stmt->rowCount();
        }
    }
?>
