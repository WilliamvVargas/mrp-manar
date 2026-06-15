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
                ->query("SELECT id, nombre, posicion FROM menus ORDER BY posicion ASC")
                ->fetchAll();
        }
    }
