<?php

    declare(strict_types=1);
    require_once __DIR__ . '/../config/config.php'; // Importamos las constantes

   /*
    * Valida un campo de texto basado en un set de reglas dinámicas.
    *
    * @param string $valor El texto a validar.
    * @param string $nombreCampo Nombre amigable para el mensaje (ej: 'Nombres').
    * @param array $reglas Array asociativo con las reglas (ej: ['requerido' => true, 'min' => 3])
    * @return string|null Devuelve el string del error si falla, o null si pasa limpio.
    *
    */

    function validarCampoTexto($valor, $nombreCampo, $reglas = []) {
        $valor = trim($valor);
        $reglasFinales = [];

        // 1. Si $reglas es un String, leemos directamente de la constante
        if (is_string($reglas)) {
            $reglasFinales = isset(DICCIONARIO_REGLAS[$reglas]) ? DICCIONARIO_REGLAS[$reglas] : [];
        } 
        // 2. Si es un array, usamos directamente las reglas personalizadas
        else if (is_array($reglas)) {
            $reglasFinales = $reglas;
        }

        // 3. Validación de campo Obligatorio, si no lo es y está en blanco no se aplica ninguna validación
        if (!empty($reglasFinales['requerido']) && $valor === '') {
            return "El campo {$nombreCampo} es obligatorio.";
        }

        if ($valor === '') 
            return null;

        // 4. Otras validaciones según las reglas indicadas
        if (isset($reglasFinales['min']) && mb_strlen($valor) < $reglasFinales['min']) {
            return "El campo {$nombreCampo} debe tener al menos {$reglasFinales['min']} caracteres.";
        }

        if (isset($reglasFinales['max']) && mb_strlen($valor) > $reglasFinales['max']) {
            return "El campo {$nombreCampo} no puede superar los {$reglasFinales['max']} caracteres.";
        }

        if (isset($reglasFinales['patron']) && !preg_match($reglasFinales['patron'], $valor)) {
            return isset($reglasFinales['mensaje_patron']) 
                ? $reglasFinales['mensaje_patron'] 
                : "El formato del campo {$nombreCampo} no es válido.";
        }

        if (isset($reglasFinales['coincide_con']) && $valor !== trim($reglasFinales['coincide_con'])) {
            return "El campo {$nombreCampo} no coincide con la contraseña ingresada.";
        }
        
        // 5. Si no exiten ningún error se retorna nulo
        return null;
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