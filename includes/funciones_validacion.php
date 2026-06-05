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


    function generarPasswordInteligente($usuario) {

        $usuarioInput = trim($usuario ?? '');
            if (empty($usuarioInput)) {
                $usuarioInput = "user";
            }

            // 3. Diccionario Leet Speak para vocales (incluimos tildes por seguridad)
            $diccionarioLeet = [
                'a' => '4', 'A' => '4', 'á' => '4', 'Á' => '4',
                'e' => '3', 'E' => '3', 'é' => '3', 'É' => '3',
                'i' => '1', 'I' => '1', 'í' => '1', 'Í' => '1',
                'o' => '0', 'O' => '0', 'ó' => '0', 'Ó' => '0'
            ];

            // 4. GENERACIÓN DINÁMICA DEL ABECEDARIO
            $abecedario = array_merge(range('a', 'z'), range('A', 'Z'));

            // Convertimos el string en un array de caracteres reales (no de bytes) respetando la Ñ
            $letrasUsuario = mb_str_split($usuarioInput, 1, 'UTF-8');
            $totalLetras = count($letrasUsuario);

            // 5. PRIMERA ETAPA: Procesar TODO el nombre + Letras aleatorias intercaladas
            $passwordStr = '';
            for ($i = 0; $i < $totalLetras; $i++) {
                $letra = $letrasUsuario[$i];

                // Traducimos la letra actual del usuario
                if (array_key_exists($letra, $diccionarioLeet)) {
                    $passwordStr .= $diccionarioLeet[$letra];
                } else {
                    // Nota: mb_strtoupper y mb_strtolower manejan correctamente la Ñ y la ñ
                    $passwordStr .= (random_int(0, 1) === 1) ? mb_strtoupper($letra, 'UTF-8') : mb_strtolower($letra, 'UTF-8');
                }

                // Factor Azar: 30% de probabilidad de intercalar una letra aleatoria del abecedario general
                if (random_int(1, 10) <= 3) {
                    $letraExterna = $abecedario[random_int(0, count($abecedario) - 1)];
                    
                    if (array_key_exists($letraExterna, $diccionarioLeet)) {
                        $passwordStr .= $diccionarioLeet[$letraExterna];
                    } else {
                        $passwordStr .= $letraExterna;
                    }
                }
            }

            // Añadimos el guión bajo como separador intermedio
            $passwordStr .= '_';

            // 6. SEGUNDA ETAPA: Espejado multibyte seguro
            // Invertimos el array de caracteres directamente para evitar que strrev() rompa la Ñ
            $usuarioEspejoArr = array_reverse($letrasUsuario);
            $totalEspejo = count($usuarioEspejoArr);
            $indiceEspejo = 0;

            // Usamos mb_strlen para medir el largo real del string en caracteres de forma segura
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

            // 7. Recorte final usando mb_substr para no mochar caracteres multibyte por la mitad
            return mb_substr($passwordStr, 0, PASS_GENERATED_MIN_LENGTH, 'UTF-8');
        }


?>