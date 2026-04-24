<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php'; // Importamos las constantes

    function validarDatosUsuario(string $usuario, string $password, bool $esRegistro = false): array {

        $errores = [];

        // Validación del nombre
        if (empty(trim($usuario))) {
            $errores['usuario'] = "El nombre de usuario es obligatorio.";
        } elseif ($esRegistro && !preg_match('/^[a-zA-Z0-9_]{' . USER_MIN_LENGTH . ',' . USER_MAX_LENGTH . '}$/', $usuario)) {
            $errores['usuario'] = "El usuario debe tener entre " . USER_MIN_LENGTH . " y " . USER_MAX_LENGTH . " caracteres (letras, números y guiones bajos).";
        }

        // Validación de la contraseña
        if (empty(trim($password))) {
            $errores['password'] = "La contraseña no puede estar vacía.";
        } elseif ($esRegistro && strlen($password) < PASS_MIN_LENGTH) {
            $errores['password'] = "La seguridad requiere al menos " . PASS_MIN_LENGTH . " caracteres.";
        }

        return $errores;
    }

?>