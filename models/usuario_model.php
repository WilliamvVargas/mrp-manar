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
                                                password_hash,
                                                estado
                                         FROM usuarios
                                         WHERE usuario = ?
                                         LIMIT 1");
            $stmt->execute([$usuario]);
            $fila = $stmt->fetch();

            return $fila ?: null;
        }

        /**
         * Cantidad total de usuarios registrados (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        }

        /**
         * Cantidad de usuarios que coinciden con el filtro en usuario, nombres o apellidos.
         * Si el filtro está vacío, equivale al total.
         */
        public function contarFiltrados($busqueda)
        {
            if ($busqueda === '') {
                return $this->contarTodos();
            }

            $like = '%' . $busqueda . '%';
            $stmt = $this->pdo->prepare("SELECT COUNT(*)
                                         FROM usuarios
                                         WHERE usuario LIKE ?
                                            OR nombres LIKE ?
                                            OR apellidos LIKE ?");
            $stmt->execute([$like, $like, $like]);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Devuelve una página de usuarios para DataTables (server-side).
         *
         * @param string $busqueda     Texto a buscar en usuario, nombres o apellidos.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset (registro inicial).
         * @param int    $longitud     Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'usuario'   => 'u.usuario',
                'nombres'   => 'u.nombres',
                'apellidos' => 'u.apellidos',
                'perfil'    => 'p.nombre',
                'estado'    => 'u.estado',
                'fecha'     => 'u.created_at',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'u.created_at';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            // Casteo a entero: seguro para incrustar en el LIMIT (las prepares
            // nativas no aceptan parámetros enlazados en LIMIT).
            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            $where  = '';
            $params = [];
            if ($busqueda !== '') {
                $like   = '%' . $busqueda . '%';
                $where  = "WHERE u.usuario LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?";
                $params = [$like, $like, $like];
            }

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            // LEFT JOIN: los usuarios sin perfil (id_perfil NULL) igual aparecen, con perfil NULL.
            $sql = "SELECT u.id,
                           u.usuario,
                           u.nombres,
                           u.apellidos,
                           p.nombre AS perfil,
                           u.estado,
                           DATE_FORMAT(u.created_at, '%d/%m/%Y %H:%i') AS fecha
                    FROM usuarios u
                    LEFT JOIN perfiles p ON p.id = u.id_perfil
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        /**
         * Obtiene los datos de un usuario por su id (sin el hash de contraseña).
         * Retorna null si no existe.
         */
        public function buscarPorId($id)
        {
            $stmt = $this->pdo->prepare("SELECT u.id,
                                                u.usuario,
                                                u.nombres,
                                                u.apellidos,
                                                u.id_perfil,
                                                u.estado,
                                                p.nombre AS perfil
                                         FROM usuarios u
                                         LEFT JOIN perfiles p ON p.id = u.id_perfil
                                         WHERE u.id = ?");
            $stmt->execute([$id]);
            $fila = $stmt->fetch();

            return $fila ?: null;
        }

        /**
         * Inserta un nuevo usuario. El id (UUID) lo asigna el trigger de la BD.
         * $creadoPor es el id del usuario que realiza la creación (auditoría).
         * Retorna true si la inserción fue exitosa.
         */
        public function crear($usuario, $nombres, $apellidos, $passwordHash, $idPerfil = null, $estado = 1, $creadoPor = null)
        {
            $sql = "INSERT INTO usuarios (usuario,
                                          nombres,
                                          apellidos,
                                          password_hash,
                                          id_perfil,
                                          estado,
                                          created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                $usuario,
                $nombres,
                $apellidos,
                $passwordHash,
                ($idPerfil !== null && ctype_digit((string) $idPerfil)) ? (int) $idPerfil : null,
                (int) $estado,
                $creadoPor,
            ]);
        }

        /**
         * Actualiza los datos (sin contraseña) de un usuario.
         * $modificadoPor es el id del usuario que realiza el cambio (auditoría).
         * Retorna la cantidad de filas afectadas (0 si no hubo cambios).
         */
        public function actualizarDatos($id, $usuario, $nombres, $apellidos, $idPerfil = null, $estado = 1, $modificadoPor = null)
        {
            $sql = "UPDATE usuarios
                    SET usuario = ?,
                        nombres = ?,
                        apellidos = ?,
                        id_perfil = ?,
                        estado = ?,
                        updated_by = ?
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $usuario,
                $nombres,
                $apellidos,
                ($idPerfil !== null && ctype_digit((string) $idPerfil)) ? (int) $idPerfil : null,
                (int) $estado,
                $modificadoPor,
                $id,
            ]);

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
         * Alterna el estado de un usuario: activo (1) pasa a inactivo (0) y viceversa.
         * $modificadoPor es el id del usuario que realiza el cambio (auditoría).
         *
         * @return bool True si se actualizó algún registro.
         */
        public function cambiarEstado($id, $modificadoPor = null)
        {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET estado = 1 - estado, updated_by = ? WHERE id = ?");
            $stmt->execute([$modificadoPor, $id]);

            return $stmt->rowCount() > 0;
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
