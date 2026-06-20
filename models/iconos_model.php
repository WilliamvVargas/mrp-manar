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
        public function existePorValor($valor)
        {
            $stmt = $this->pdo->prepare("SELECT 1 FROM iconos WHERE valor = ? LIMIT 1");
            $stmt->execute([$valor]);

            return (bool) $stmt->fetchColumn();
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
    }
