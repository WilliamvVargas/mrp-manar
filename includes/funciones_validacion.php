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
        
        $errores_usuarios = validarUsuario($usuario);
        $errores_password = validarPassword($password, $confirmPassword);

        return array_merge($errores_usuarios, $errores_password);
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
            $errores['password'] = "La contraseña debe tener al menos una letra minúscula, una letra mayúscula y un número. No debe tener caracteres especiales o espacios.";
        }


        if (!empty($password) && empty($confirmPassword)) {
            $errores['confirm-password'] = "Debes ingresar nuevamente la contraseña.";
        } elseif ($password !== $confirmPassword) {
            $errores['confirm-password'] = "Las contraseñas no coinciden.";
        }

        return $errores;
    }


    function validarFormatoPassword($password) {

        $patronComplejidad = '/^(?=.*[a-zñáéíóú])(?=.*[A-ZÑÁÉÍÓÚ])(?=.*\d)[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ]*$/u';
    
        return preg_match($patronComplejidad, $password) === 1;

    }


    function validarFormatoUsuario($usuario) {

        return preg_match('/^[a-zA-Z0-9._ñÑ]+$/', $usuario) === 1;
    }


    function generarPasswordInteligente($usuario) {

        // Limpiamos el parámetro de usuario
        $usuarioInput = trim($usuario ?? '');
        $usuarioInput = preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ]/u', '', $usuarioInput);

        if (empty($usuarioInput)) {
            $usuarioInput = "user";
        }

        // Diccionario Leet Speak para vocales
        $diccionarioLeet = [
            'a' => '4', 'A' => '4', 'á' => '4', 'Á' => '4',
            'e' => '3', 'E' => '3', 'é' => '3', 'É' => '3',
            'i' => '1', 'I' => '1', 'í' => '1', 'Í' => '1',
            'o' => '0', 'O' => '0', 'ó' => '0', 'Ó' => '0'
        ];

        // Creamos un diccionario con mayusculas y minusculas
        $abecedarioMin = range('a', 'z');
        $abecedarioMay = range('A', 'Z');
        $abecedarioGeneral = array_merge($abecedarioMin, $abecedarioMay);

        // Convertimos el string en un array de caracteres reales respetando la Ñ
        $letrasUsuario = mb_str_split($usuarioInput, 1, 'UTF-8');
        $totalLetras = count($letrasUsuario);

        // Procesar TODO el nombre + Letras aleatorias intercaladas
        $passwordStr = '';
        for ($i = 0; $i < $totalLetras; $i++) {
            $letra = $letrasUsuario[$i];

            // Traducimos la letra actual del usuario
            if (array_key_exists($letra, $diccionarioLeet)) {
                $passwordStr .= $diccionarioLeet[$letra];
            } else {
                $passwordStr .= (random_int(0, 1) === 1) ? mb_strtoupper($letra, 'UTF-8') : mb_strtolower($letra, 'UTF-8');
            }

            // Factor Azar: 30% de probabilidad de intercalar una letra aleatoria del abecedario general
            if (random_int(1, 10) <= 3) {
                $letraExterna = $abecedarioGeneral[random_int(0, count($abecedarioGeneral) - 1)];
                
                if (array_key_exists($letraExterna, $diccionarioLeet)) {
                    $passwordStr .= $diccionarioLeet[$letraExterna];
                } else {
                    $passwordStr .= $letraExterna;
                }
            }
        }

        // Espejado multibyte seguro
        $usuarioEspejoArr = array_reverse($letrasUsuario);
        $totalEspejo = count($usuarioEspejoArr);
        $indiceEspejo = 0;

        while (mb_strlen($passwordStr, 'UTF-8') < PASS_GENERATED_MIN_LENGTH) {
            if ($indiceEspejo >= $totalEspejo) {
                $indiceEspejo = 0;
            }

            $letraRelleno = $usuarioEspejoArr[$indiceEspejo];

            if (array_key_exists($letraRelleno, $diccionarioLeet)) {
                $passwordStr .= $diccionarioLeet[$letraRelleno];
            } else {
                $passwordStr .= (random_int(0, 1) === 1) ? mb_strtoupper($letraRelleno, 'UTF-8') : mb_strtolower($letraRelleno, 'UTF-8');
            }

            $indiceEspejo++;
        }

        // Recorte preliminar al tamaño exacto
        $passwordFinal = mb_substr($passwordStr, 0, PASS_GENERATED_MIN_LENGTH, 'UTF-8');

        // VALIDACIÓN ESTRICTA (Al menos 1 Mayúscula, 1 Minúscula y 1 Número)
        $tieneMayuscula = preg_match('/[A-ZÑ]/u', $passwordFinal);
        $tieneMinuscula = preg_match('/[a-zñ]/u', $passwordFinal);
        $tieneNumero    = preg_match('/[0-9]/', $passwordFinal);

        $finalArr = mb_str_split($passwordFinal, 1, 'UTF-8');
        $posicionReemplazo = count($finalArr) - 1;

        // ¿Falta número?
        if (!$tieneNumero) {
            $finalArr[$posicionReemplazo] = (string)random_int(0, 9);
            $posicionReemplazo--;
        }

        // ¿Falta minúscula?
        if (!$tieneMinuscula && $posicionReemplazo >= 0) {
            $finalArr[$posicionReemplazo] = $abecedarioMin[random_int(0, count($abecedarioMin) - 1)];
            $posicionReemplazo--;
        }

        // ¿Falta mayúscula?
        if (!$tieneMayuscula && $posicionReemplazo >= 0) {
            $finalArr[$posicionReemplazo] = $abecedarioMay[random_int(0, count($abecedarioMay) - 1)];
        }

        return implode('', $finalArr);
    }


?>