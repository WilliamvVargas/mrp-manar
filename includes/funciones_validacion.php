<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php'; // Importamos las constantes

    /**
     * Valida los datos del usuario según el contexto o formulario del sistema.
     * * @param string $usuario          El nombre de usuario recibido por POST.
     * @param string $password         La contraseña recibida.
     * @param string $confirm_password La confirmación de la contraseña (opcional).
     * @param string $modo             El contexto de validación: 'login', 'registro' o 'edicion'.
     * @return array                   Un arreglo asociativo con los errores encontrados.
     */
    function validarDatosUsuario(string $usuario, string $password, string $confirm_password = '', string $modo = 'registro'): array {

        $errores = [];
        $usuario = trim($usuario);

        // =================================================================
        // 1. VALIDACIÓN DEL NOMBRE DE USUARIO
        // ==========================================================registro=======
        if (empty($usuario)) {
            $errores['usuario'] = "El nombre de usuario es obligatorio.";
        } 
        elseif ($modo === 'registro' || $modo === 'edicion') {

            if (strlen($usuario) < USER_MIN_LENGTH || strlen($usuario) > USER_MAX_LENGTH) {
                $errores['usuario'] = "El nombre de usuario debe tener entre " . USER_MIN_LENGTH . " y " . USER_MAX_LENGTH . " caracteres.";
            }
            if (!preg_match('/^[a-zA-Z0-9\._-]+$/', $usuario)) {
                $errores['usuario'] = "El nombre de usuario puede usar letras, números, los caracteres especiales punto ( . ), guión ( - ), guión bajo ( _ ) y sin espacios.";
            }

        }

        // =================================================================
        // 2. VALIDACIÓN DE LA CONTRASEÑA
        // =================================================================
        
        // Caso A: La contraseña es estrictamente obligatoria en el Login y en el Registro
        if (($modo === 'login' || $modo === 'registro') && empty($password)) {
            $errores['password'] = "La contraseña es obligatoria.";
        }
        
        // Caso B: Si estamos registrando un nuevo usuario, evaluamos las políticas complejas de seguridad.
        // Nota: El modo 'edicion' ignora este bloque por completo ya la clave en otro formulario.
        elseif ($modo === 'registro') {

            if (strlen($password) < PASS_MIN_LENGTH || strlen($password) > PASS_MAX_LENGTH ) {
                $errores['password'] = "La contraseña requiere de al menos " . PASS_MIN_LENGTH . " caracteres y no debe ser superior a los " . PASS_MAX_LENGTH . " caracteres.";
            }

            $patron = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>._-])[a-zA-Z0-9!@#$%^&*(),.?":{}|<>._-]*$/';

            if (!preg_match($patron, $password)) {
                $errores['password'] = "La contraseña debe tener al menos una letra minúscula, una letra mayúscula, un número y un carácter especial.";
            }
            
            if ($password !== $confirm_password) {
                $errores['confirm_password'] = "Las contraseñas no coinciden.";
            }
        }

        return $errores;
    }

?>