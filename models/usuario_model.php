<?php

    /*
     * Modelo de acceso a datos de la tabla `usuarios`.
     *
     * Centraliza todas las consultas SQL relacionadas con usuarios para evitar
     * duplicación entre controladores y facilitar las pruebas: basta con
     * instanciar la clase pasándole una conexión PDO.
     */
    class Usuario
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Indica si ya existe un usuario con ese nombre.
         * Si se pasa $idExcluir, ese registro se omite de la búsqueda
         * (útil al editar para no chocar consigo mismo).
         */
        public function existePorUsuario($usuario, $idExcluir = null)
        {
            if ($idExcluir) {

                $sql = "SELECT COUNT(*)
                        FROM usuarios
                        WHERE usuario = ? AND id != ?
                        LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$usuario, $idExcluir]);

            } 
            else {
                $sql = "SELECT COUNT(*)
                        FROM usuarios
                        WHERE usuario = ?
                        LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$usuario]);
            }

            return $stmt->fetchColumn() > 0;
        }

        /**
         * Indica si existe un usuario con ese id.
         */
        public function existePorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT 1 
                                         FROM usuarios 
                                         WHERE id = ? 
                                         LIMIT 1");
            $stmt->execute([$id]);

            return (bool) $stmt->fetchColumn();
        }

        /**
         * Devuelve el id y el hash de la contraseña para validar el login.
         * Retorna null si el usuario no existe.
         */
        public function obtenerCredenciales($usuario)
        {
            $stmt = $this->pdo->prepare("SELECT id, 
                                                password_hash
                                         FROM usuarios
                                         WHERE usuario = ?
                                         LIMIT 1");
            $stmt->execute([$usuario]);
            $fila = $stmt->fetch();

            return $fila ?: null;
        }

        /**
         * Lista todos los usuarios con la fecha de creación ya formateada,
         * ordenados del más reciente al más antiguo.
         */
        public function listarTodos()
        {
            $sql = "SELECT id,
                           usuario,
                           nombres,
                           apellidos,
                           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS fecha
                    FROM usuarios
                    ORDER BY created_at DESC";

            $stmt = $this->pdo->query($sql);

            return $stmt->fetchAll();
        }

        /**
         * Obtiene los datos de un usuario por su id (sin el hash de contraseña).
         * Retorna null si no existe.
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT id,
                                                usuario,
                                                nombres,
                                                apellidos
                                         FROM usuarios
                                         WHERE id = ?");
            $stmt->execute([$id]);
            $fila = $stmt->fetch();

            return $fila ?: null;
        }

        /**
         * Inserta un nuevo usuario. El id (UUID) lo asigna el trigger de la BD.
         * $creadoPor es el id del usuario que realiza la creación (auditoría).
         * Retorna true si la inserción fue exitosa.
         */
        public function crear($usuario, $nombres, $apellidos, $passwordHash, $creadoPor = null)
        {
            $sql = "INSERT INTO usuarios (usuario,
                                          nombres,
                                          apellidos,
                                          password_hash,
                                          created_by)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([$usuario, $nombres, $apellidos, $passwordHash, $creadoPor]);
        }

        /**
         * Actualiza los datos (sin contraseña) de un usuario.
         * $modificadoPor es el id del usuario que realiza el cambio (auditoría).
         * Retorna la cantidad de filas afectadas (0 si no hubo cambios).
         */
        public function actualizarDatos($id, $usuario, $nombres, $apellidos, $modificadoPor = null)
        {
            $sql = "UPDATE usuarios
                    SET usuario = ?,
                        nombres = ?,
                        apellidos = ?,
                        updated_by = ?
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuario, $nombres, $apellidos, $modificadoPor, $id]);

            return $stmt->rowCount();
        }

        /**
         * Actualiza el hash de la contraseña de un usuario.
         * $modificadoPor es el id del usuario que realiza el cambio (auditoría).
         * Retorna la cantidad de filas afectadas (0 si no hubo cambios).
         */
        public function actualizarPassword($id, $passwordHash, $modificadoPor = null)
        {
            $stmt = $this->pdo->prepare("UPDATE usuarios
                                         SET password_hash = ?,
                                             updated_by = ?
                                         WHERE id = ?");
            $stmt->execute([$passwordHash, $modificadoPor, $id]);

            return $stmt->rowCount();
        }

        /**
         * Elimina un usuario por su id. Retorna true si la sentencia se ejecutó.
         */
        public function eliminar($id)
        {
            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");

            return $stmt->execute([$id]);
        }
    }

?>
