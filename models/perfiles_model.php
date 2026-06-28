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
    }
