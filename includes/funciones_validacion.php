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
        elseif ($esRegistro && (strlen($usuario) < USER_MIN_LENGTH || strlen($usuario) > USER_MAX_LENGTH)) {
            $errores['usuario'] = "El nombre de usuario debe tener entre " . USER_MIN_LENGTH . " y " . USER_MAX_LENGTH ." caracteres.";
        }
        elseif ($esRegistro) {
            if (!preg_match('/^[a-zA-Z0-9\._-]+$/', $usuario)) {
                $errores['usuario'] = "El nombre de usuario puede usar letras, números, los caracteres especiales punto ( . ), guión ( - ), guión bajo ( _ ) y sin espacios.";
            }
        }

        if (empty($password)) {
            $errores['password'] = "La contraseña es obligatoria.";
        } 
        elseif ($esRegistro) {

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