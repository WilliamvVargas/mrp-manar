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
        public function crear($nombre, $estado)
        {
            $posicion = $this->siguientePosicion();

            $stmt = $this->pdo->prepare("INSERT INTO menus (nombre, estado, posicion)
                                         VALUES (?, ?, ?)");

            return $stmt->execute([$nombre, $estado, $posicion]);
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
    }
