<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php'; // Importamos las constantes

    function validarDatosUsuario(string $usuario, string $password, string $confirm_password = '', bool $esRegistro = false): array {

        $errores = [];
        $usuario = trim($usuario);

        //Validar Usuario
        if (empty($usuario)) {
            $errores['usuario'] = "El nombre de usuario es obligatorio.";
        } 
        elseif ($esRegistro) {
            if (!preg_match('/^[a-zA-Z0-9\._-]{' . USER_MIN_LENGTH . ',' . USER_MAX_LENGTH . '}$/', $usuario)) {
                $errores['usuario'] = "El usuario debe tener entre " . USER_MIN_LENGTH . " y " . USER_MAX_LENGTH . " caracteres (letras, números, especiales '.', '-' o '_') y sin espacios.";
            }
        }

        if (empty($password)) {
            $errores['password'] = "La contraseña es obligatoria.";
        } 
        elseif ($esRegistro) {

            if (strlen($password) < PASS_MIN_LENGTH) {
                $errores['password'] = "La seguridad requiere al menos " . PASS_MIN_LENGTH . " caracteres.";
            }
            
            if ($password !== $confirm_password) {
                $errores['confirm_password'] = "Las contraseñas no coinciden.";
            }

        }

        return $errores;
    }

?>