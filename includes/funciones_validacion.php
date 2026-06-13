<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php';

    function retrasar(){
        time_nanosleep(0, 500000000);
    }

   /*
    * Valida un campo de texto basado en un set de reglas dinámicas.
    *
    * @param string $valor El texto a validar.
    * @param string $nombreCampo Nombre amigable para el mensaje (ej: 'Nombres').
    * @param array $reglas Array asociativo con las reglas (ej: ['requerido' => true, 'min' => 3])
    * @return string|null Devuelve el string del error si falla, o null si pasa limpio.
    *
    */

    function validarCampoTexto($valor, $nombreCampo, $reglas = [], $conexion = null) {
        $valor = trim($valor);
        $reglasFinales = [];

        // 1. Revisamos primero si requerimos usar las reglas por defecto o personalizadas
        if (is_string($reglas)) {
            $diccionario = obtenerDiccionarioReglas($conexion);
            $reglasFinales = isset($diccionario[$reglas]) ? $diccionario[$reglas] : [];
        } 
        else if (is_array($reglas)) {
            $reglasFinales = $reglas;
        }

        // 2. Validación de campo Obligatorio, si no lo es y está en blanco no se aplica ninguna validación
        if (!empty($reglasFinales['requerido']) && $valor === '') {
            return "El campo <b>{$nombreCampo}</b> es obligatorio.";
        }

        if ($valor === '') 
            return null;

        // 3. Otras validaciones según las reglas indicadas
        if (isset($reglasFinales['min']) && mb_strlen($valor) < $reglasFinales['min']) {
            return "El campo <b>{$nombreCampo}</b> debe tener al menos {$reglasFinales['min']} caracteres.";
        }

        if (isset($reglasFinales['max']) && mb_strlen($valor) > $reglasFinales['max']) {
            return "El campo <b>{$nombreCampo}</b> no puede superar los {$reglasFinales['max']} caracteres.";
        }

        if (isset($reglasFinales['patron']) && !preg_match($reglasFinales['patron'], $valor)) {
            return isset($reglasFinales['mensaje_patron']) 
                ? $reglasFinales['mensaje_patron'] 
                : "El formato del campo <b>{$nombreCampo}</b> no es válido.";
        }

        if (isset($reglasFinales['coincide_con']) && $valor !== trim($reglasFinales['coincide_con'])) {
            return "El campo <b>{$nombreCampo}</b> no coincide con la contraseña ingresada.";
        }

        // valida casos de existencia de un campo
        if (isset($reglasFinales['verificacion_externa']) && is_array($reglasFinales['verificacion_externa'])) {

            $verificador = $reglasFinales['verificacion_externa']['callback'] ?? null;
            $mensajeError = $reglasFinales['verificacion_externa']['mensaje'] ?? "El valor ingresado para <b>{$nombreCampo}</b> no es válido.";

            if (is_callable($verificador)) {
                if ($verificador($valor) === true) {
                    return $mensajeError;
                }
            }
            
        }
        
        // 4. Si no exiten ningún error se retorna nulo
        return null;
    }

    /**
     * Retorna un password generedo con valores de forma aleatoria.
     * Al pasarle un string como parámetro trabaja con los caracteres del mismo para facilitar un password similar
     * * @param string|null $usuario Nombre de usuario.
     * @return array
     */
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



    /**
     * Retorna el diccionario de reglas del sistema.
     * Al pasarle la conexión opcional, inyecta automáticamente las validaciones de BD.
     * * @param PDO|null $conexion Objeto de conexión a la BD.
     * @return array
     */
    
    function obtenerDiccionarioReglas($conexion = null) {
        return [
            'usuario' => [
                'requerido' => true, 
                'min' => USER_MIN_LENGTH, 
                'max' => USER_MAX_LENGTH, 
                'patron' => '/^[a-z0-9_ñ]+$/',
                'mensaje_patron' => 'El campo <b>Usuario</b> solo permite letras minúsculas, números y guiones bajos.',
                'verificacion_externa' => [
                    'mensaje' => "El <b>Usuario</b> ya se encuentra registrado. Utilice otro.",
                    'callback' => function($val) use ($conexion) {
                        if (!$conexion) 
                            return false; 

                        $idRegistro = $_POST['id_registro'] ?? null;
                        return existeUsuario($conexion, $val, $idRegistro);

                    }
                ]
            ],
            'nombres' => [
                'requerido' => true, 
                'min' => NOMBRE_APELLIDO_MIN_LENGTH, 
                'max' => NOMBRE_APELLIDO_MAX_LENGTH,
                'patron' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/',
                'mensaje_patron' => 'El campo <b>Nombres</b> solo permite letras.'
            ],
            'apellidos' => [
                'requerido' => true, 
                'min' => NOMBRE_APELLIDO_MIN_LENGTH, 
                'max' => NOMBRE_APELLIDO_MAX_LENGTH,
                'patron' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/',
                'mensaje_patron' => 'El campo <b>Apellidos</b> solo permite letras.'
            ],
            'password' => [
                'requerido' => true,
                'min' => PASS_MIN_LENGTH,
                'max' => PASS_MAX_LENGTH,
                'patron' => '/^(?=.*[a-zñáéíóú])(?=.*[A-ZÑÁÉÍÓÚ])(?=.*\d)[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ]*$/u',
                'mensaje_patron' => 'La <b>Contraseña</b> debe tener al menos una letra minúscula, una letra mayúscula y un número. No debe tener caracteres especiales o espacios.'
            ]
        ];
    }

    /**
     * Retorna un mensaje la cantidad de errores de los campos de un formulario y termina el programa.
     * Al pasarle la conexión opcional, inyecta automáticamente las validaciones de BD.
     * * @param array|null $errores Arreglo con errores.
     * @return array
     */
    function enviarErrorCamposFormulario($errores) {

        $cantidad_errores = count($errores);

        if($cantidad_errores == 1)
            $message = 'El formulario tiene un campo con error.';
        else if ($cantidad_errores > 1)
            $message = "El formulario tiene $cantidad_errores campos con error.";

        if (!empty($errores)) {
            echo json_encode([
                'status' => 'error',
                'type' => 'fields',
                'message' => $message,
                'errors' => $errores
            ]);
            exit;
        }

    }

?>