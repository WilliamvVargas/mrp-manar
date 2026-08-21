<?php
    /*
     * Modelo de la relación N-a-N usuario <-> empresa (tabla puente `usuario_empresas`).
     * Una fila por empresa asignada al usuario; `por_defecto = 1` en la que se carga al
     * iniciar sesión (exactamente una por usuario).
     */
    class UsuarioEmpresa
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Reemplaza por completo las empresas asignadas a un usuario (transacción):
         * borra las anteriores e inserta la lista nueva, marcando la por defecto.
         *
         * @param string      $idUsuario
         * @param array       $empresaIds Ids de empresa seleccionadas.
         * @param string|null $porDefecto Id de la empresa por defecto (debe estar en la lista;
         *                                si no lo está, se usa la primera).
         * @param string|null $creadoPor  Id del usuario que hace el cambio (auditoría).
         * @return bool
         */
        public function sincronizar($idUsuario, array $empresaIds, $porDefecto = null, $creadoPor = null)
        {
            // Normaliza: quita vacíos y duplicados, reindexando.
            $empresaIds = array_values(array_unique(array_filter($empresaIds, function ($v) {
                return $v !== '' && $v !== null;
            })));

            // La por defecto debe estar entre las seleccionadas; si no, la primera.
            if (!in_array($porDefecto, $empresaIds, true)) {
                $porDefecto = $empresaIds[0] ?? null;
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare("DELETE FROM usuario_empresas WHERE id_usuario = ?")
                          ->execute([$idUsuario]);

                $ins = $this->pdo->prepare(
                    "INSERT INTO usuario_empresas (id_usuario, id_empresa, por_defecto, created_by)
                     VALUES (?, ?, ?, ?)"
                );
                foreach ($empresaIds as $idEmp) {
                    $ins->execute([$idUsuario, $idEmp, ($idEmp === $porDefecto ? 1 : 0), $creadoPor]);
                }

                $this->pdo->commit();
                return true;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Empresas asignadas a un usuario (para poblar el formulario de edición).
         *
         * @return array Filas ['id_empresa', 'por_defecto'].
         */
        public function empresasDe($idUsuario)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id_empresa, por_defecto FROM usuario_empresas WHERE id_usuario = ?"
            );
            $stmt->execute([$idUsuario]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Empresas del usuario CON su nombre (para el selector de empresa activa del navbar),
         * ordenadas por posición.
         *
         * @return array Filas ['id_empresa', 'nombre', 'logo', 'por_defecto'].
         */
        public function empresasConNombre($idUsuario)
        {
            $stmt = $this->pdo->prepare(
                "SELECT ue.id_empresa, e.nombre, e.logo, ue.por_defecto
                 FROM usuario_empresas ue
                 INNER JOIN empresas e ON e.id = ue.id_empresa
                 WHERE ue.id_usuario = ?
                 ORDER BY e.posicion ASC, e.nombre ASC"
            );
            $stmt->execute([$idUsuario]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * ¿La empresa está asignada a ese usuario? (para validar el cambio de empresa activa:
         * un usuario no puede activar una empresa que no tiene asignada).
         *
         * @return bool
         */
        public function perteneceA($idUsuario, $idEmpresa)
        {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM usuario_empresas WHERE id_usuario = ? AND id_empresa = ? LIMIT 1"
            );
            $stmt->execute([$idUsuario, $idEmpresa]);
            return (bool) $stmt->fetchColumn();
        }

        /**
         * Id de la empresa por defecto del usuario (para setear $_SESSION['empresa_id'] al
         * iniciar sesión). Si no hay ninguna marcada, devuelve cualquier asignada; null si
         * el usuario no tiene empresas.
         *
         * @return string|null
         */
        public function empresaPorDefecto($idUsuario)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id_empresa FROM usuario_empresas
                 WHERE id_usuario = ?
                 ORDER BY por_defecto DESC
                 LIMIT 1"
            );
            $stmt->execute([$idUsuario]);
            $id = $stmt->fetchColumn();
            return $id !== false ? $id : null;
        }
    }
?>
