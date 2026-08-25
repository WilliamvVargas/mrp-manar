<?php
    /*
     * Cliente de IA para generar texto (resúmenes en lenguaje natural).
     *
     * Aislado a propósito: hoy usa OLLAMA local (gratuito, on-premise, los datos NO salen
     * de la red). Si a futuro se quiere otro proveedor (p. ej. Claude), se cambia solo aquí
     * sin tocar los llamadores. Configurable por constantes en config/config.php.
     */
    require_once __DIR__ . '/../config/config.php';

    /**
     * Genera un texto a partir de un prompt. Devuelve un arreglo uniforme:
     *   ['ok' => bool, 'texto' => string, 'error' => string]
     *
     * @param string $prompt        Instrucción + datos.
     * @param float  $temperatura   Creatividad (0.2-0.4 para resúmenes fieles).
     */
    function iaGenerarTexto($prompt, $temperatura = 0.3)
    {
        if (defined('IA_PROVEEDOR') && IA_PROVEEDOR === 'ollama') {
            return iaOllamaChat($prompt, $temperatura);
        }
        return ['ok' => false, 'texto' => '', 'error' => 'Proveedor de IA no configurado.'];
    }

    /**
     * Llama a Ollama (endpoint /api/chat) por HTTP. No requiere librerías (usa cURL).
     */
    function iaOllamaChat($prompt, $temperatura)
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'texto' => '', 'error' => 'La extensión cURL de PHP no está disponible.'];
        }

        $payload = json_encode([
            'model'      => OLLAMA_MODEL,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
            'stream'     => false,
            // keep_alive mantiene el modelo en RAM entre llamadas (evita el costo de recarga en frío).
            'keep_alive' => defined('OLLAMA_KEEP_ALIVE') ? OLLAMA_KEEP_ALIVE : '30m',
            'options'    => [
                'temperature' => $temperatura,
                // num_predict acota la longitud de la respuesta (menos tokens = menos tiempo).
                'num_predict' => defined('OLLAMA_NUM_PREDICT') ? OLLAMA_NUM_PREDICT : 256,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(rtrim(OLLAMA_URL, '/') . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => defined('IA_TIMEOUT_SEG') ? IA_TIMEOUT_SEG : 120,
        ]);
        $resp  = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['ok' => false, 'texto' => '',
                    'error' => 'No se pudo conectar a Ollama (' . $err . '). ¿Está corriendo en ' . OLLAMA_URL . '?'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'texto' => '', 'error' => 'Ollama respondió HTTP ' . $code . '.'];
        }

        $data  = json_decode($resp, true);
        $texto = trim($data['message']['content'] ?? '');
        if ($texto === '') {
            return ['ok' => false, 'texto' => '', 'error' => 'El modelo devolvió una respuesta vacía.'];
        }
        return ['ok' => true, 'texto' => $texto, 'error' => ''];
    }
?>
