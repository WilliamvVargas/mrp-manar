<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php'; // Importamos las constantes

    function validarDatosLogin($usuario, $password) {
        $errores = [];

        if (empty($usuario)) {
            $errores['usuario'] = "El nombre de usuario es obligatorio.";
        }

        if (empty($password)) {
            $errores['password'] = "La contraseña es obligatoria.";
        }

        return $errores;
    }

    function validarDatosNuevoUsuario($usuario, $password, $confirmPassword) {
        
        $errores = validarUsuario($usuario);
        $errores = validarPassword($password, $confirmPassword);

        return $errores;
    }

    function validarUsuario($usuario) {
        $errores = [];

        if (empty($usuario)) {
            $errores['usuario'] = "El nombre de usuario es obligatorio.";
        }
        elseif (strlen($usuario) < USER_MIN_LENGTH) {
             $errores['usuario'] = "El usuario es muy corto. Mínimo " . USER_MIN_LENGTH . " caracteres.";
        } 
        elseif (strlen($usuario) > USER_MAX_LENGTH) {
            $errores['usuario'] = "El usuario no puede superar los " . USER_MAX_LENGTH . " caracteres.";
        } 
        elseif (!validarFormatoUsuario($usuario)) {
            $errores['usuario'] = "El usuario solo puede contener letras, números, puntos (.) o guiones bajos (_).";
        }
        return $errores;
    }

    function validarPassword($password, $confirmPassword) {
        $errores = [];

        if (empty($password)) {
            $errores['password'] = "La contraseña es obligatoria.";
        }
        elseif (strlen($password) < PASS_MIN_LENGTH) {
            $errores['password'] = "La contraseña debe tener al menos " . PASS_MIN_LENGTH . " caracteres.";
        } 
        elseif (strlen($password) > PASS_MAX_LENGTH) {
            $errores['password'] = "La contraseña no puede superar los " . PASS_MAX_LENGTH . " caracteres.";
        } 
        elseif (!validarFormatoPassword($password)) {
            $errores['password'] = "La contraseña debe tener al menos una letra minúscula, una letra mayúscula, un número y un carácter especial.";
        }


        if (!empty($password) && empty($confirmPassword)) {
            $errores['confirm-password'] = "Debes ingresar nuevamente la contraseña.";
        } elseif ($password !== $confirmPassword) {
            $errores['confirm-password'] = "Las contraseñas no coinciden.";
        }

        return $errores;
    }


    function validarFormatoPassword($password) {

        $patronComplejidad = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>._-])[a-zA-Z0-9!@#$%^&*(),.?":{}|<>._-]*$/';
    
        return preg_match($patronComplejidad, $password) === 1;

    }


    function validarFormatoUsuario($usuario) {

        return preg_match('/^[a-zA-Z0-9._ñÑ]+$/', $usuario) === 1;
    }


?>