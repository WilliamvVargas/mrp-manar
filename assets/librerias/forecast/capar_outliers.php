<?php
/**
 * ============================================================================
 *  Limpieza de OUTLIERS (pedidos-lote one-off) en la demanda del forecast.
 * ----------------------------------------------------------------------------
 *  Complemento de la imputación de censura (imputar_censura.php): aquella LLENA los
 *  hoyos de los quiebres; esta CAPA los picos raros (un pedido-lote puntual) que
 *  distorsionan el nivel/tendencia que aprende Prophet. Se aplica a la serie del
 *  GRUPO (donde Prophet pronostica), DESPUÉS de la imputación.
 *
 *  Fence robusto por grupo: mediana + K · MADn (MADn = 1.4826 · MAD de las semanas con
 *  venta). Los valores por encima del fence se recortan al fence. Validado por backtest:
 *  K≈10 baja el WAPE ~2,8 pts dejando el bias ~0 (recorta ~1% del volumen, solo picos
 *  extremos). K más chico baja más el WAPE pero empuja a sub-pronóstico (riesgoso).
 *
 *  Se activa POR EMPRESA (columna empresas.forecast_capar_outliers), igual que la censura.
 * ============================================================================
 */

if (!function_exists('caparOutliersHabilitado')) {
    /**
     * ¿La empresa tiene habilitado el capado de outliers? Ante la duda: false.
     */
    function caparOutliersHabilitado(PDO $pdo, $empresaId)
    {
        if (empty($empresaId)) { return false; }
        try {
            $st = $pdo->prepare("SELECT forecast_capar_outliers FROM empresas WHERE id = ?");
            $st->execute([$empresaId]);
            return (int) $st->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('[caparOutliersHabilitado] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('caparOutliersGrupo')) {
    /**
     * Capa los picos de la serie semanal de cada grupo.
     *
     * @param array $grupoDem [grupo_id => [lunes 'Y-m-d' => demanda]]
     * @param float $K        multiplicador del fence robusto (default 10 = conservador)
     * @return array ['corregido'=>[id=>[lunes=>dem]], 'stats'=>['grupos','semanas','recortado']]
     */
    function caparOutliersGrupo(array $grupoDem, $K = 10.0)
    {
        $median = function ($a) { if (!$a) { return 0.0; } sort($a); $n = count($a); return $n % 2 ? $a[intdiv($n, 2)] : ($a[$n/2 - 1] + $a[$n/2]) / 2; };

        $corr = $grupoDem; $nGrupos = 0; $nSem = 0; $recorte = 0.0;
        foreach ($grupoDem as $id => $semanas) {
            $vals = array_values($semanas);
            $nz   = array_values(array_filter($vals, function ($v) { return $v > 0; }));
            if (count($nz) < 8) { continue; }   // serie muy corta: no capar
            $med    = $median($nz);
            $absdev = array_map(function ($v) use ($med) { return abs($v - $med); }, $nz);
            $madn   = 1.4826 * $median($absdev);
            if ($madn <= 0) { continue; }       // sin dispersión medible: no capar
            $fence  = $med + $K * $madn;

            $tocado = false;
            foreach ($semanas as $w => $v) {
                if ($v > $fence) { $recorte += ($v - $fence); $corr[$id][$w] = $fence; $nSem++; $tocado = true; }
            }
            if ($tocado) { $nGrupos++; }
        }
        return ['corregido' => $corr, 'stats' => ['grupos' => $nGrupos, 'semanas' => $nSem, 'recortado' => $recorte]];
    }
}
