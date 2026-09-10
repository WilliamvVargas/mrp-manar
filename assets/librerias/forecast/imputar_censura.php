<?php
/**
 * ============================================================================
 *  Imputación de DEMANDA CENSURADA por quiebre de stock (estacional del producto).
 * ----------------------------------------------------------------------------
 *  El forecast se entrena con lo que se VENDIÓ, no con lo que se habría vendido. En
 *  semanas donde el producto quebró (stock 0), la venta cae y sesga el modelo a la baja.
 *  Esta función reconstruye el stock semanal desde OINM (bodega 010) y, en las semanas
 *  en quiebre con venta suprimida, sube la demanda a la MEDIANA ESTACIONAL del propio
 *  producto (semanas limpias, misma semana ISO ±1), acotada a [venta, P95].
 *
 *  Opera a nivel PRODUCTO-SEMANA (el stock es por producto); el llamador re-agrega a grupo.
 *  Reutilizable por forecast_export.php y forecast_backtest_export.php (mismo criterio).
 * ============================================================================
 */

if (!function_exists('imputarCensuraHabilitado')) {
    /**
     * ¿La empresa tiene habilitada la imputación de demanda censurada? Se decide por empresa
     * (columna empresas.forecast_imputar_censura): validado que ayuda en unas (perecibles de
     * alta rotación) y no en otras (durables de demanda grumosa). Ante la duda: false.
     *
     * @param PDO $pdo MySQL
     * @param string|null $empresaId
     * @return bool
     */
    function imputarCensuraHabilitado(PDO $pdo, $empresaId)
    {
        if (empty($empresaId)) { return false; }
        try {
            $st = $pdo->prepare("SELECT forecast_imputar_censura FROM empresas WHERE id = ?");
            $st->execute([$empresaId]);
            return (int) $st->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('[imputarCensuraHabilitado] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('imputarCensuraProducto')) {

    /**
     * @param PDO    $sap        conexión SAP (SQL Server) de la empresa
     * @param array  $prodWeekly [cod => [lunes 'Y-m-d' => demanda]] (serie del export)
     * @param string $finMonday  lunes de la última semana a considerar
     * @param string $finSunday  domingo tope para OINM (evita usar stock futuro; clave en backtest)
     * @param array  $opts        ['umbral'=>0.25, 'winIso'=>1, 'cap'=>95]
     * @return array ['corregido'=>[cod=>[lunes=>dem]], 'stats'=>[...]]
     */
    function imputarCensuraProducto(PDO $sap, array $prodWeekly, $finMonday, $finSunday, array $opts = [])
    {
        $umbral = $opts['umbral'] ?? 0.25;   // venta < umbral*mediana limpia => suprimida
        $winIso = $opts['winIso'] ?? 1;      // ventana ISO ± para el pool estacional
        $capP   = $opts['cap']    ?? 95;     // percentil de corte (evita años-lote raros)

        $codigos = array_keys($prodWeekly);
        $vacio   = ['corregido' => $prodWeekly, 'stats' => ['prod'=>0,'semanas'=>0,'seasonal'=>0,'fallback'=>0,'uplift'=>0.0]];
        if (!$codigos) { return $vacio; }

        $lunes  = function ($ymd) { $d = new DateTime(substr($ymd, 0, 10)); $o = (int) $d->format('N') - 1; if ($o > 0) { $d->modify("-$o days"); } return $d->format('Y-m-d'); };
        $wkAdd  = function ($w, $n) { $d = new DateTime($w); $d->modify(($n * 7) . ' days'); return $d->format('Y-m-d'); };
        $isoW   = function ($w) { return (int) date('W', strtotime($w)); };
        $median = function ($a) { if (!$a) { return 0.0; } sort($a); $n = count($a); return $n % 2 ? $a[intdiv($n, 2)] : ($a[$n/2 - 1] + $a[$n/2]) / 2; };
        $pctl   = function ($a, $p) { if (!$a) { return 0.0; } sort($a); $i = (int) ceil($p / 100 * count($a)) - 1; return $a[max(0, min(count($a) - 1, $i))]; };

        // --- Reconstruir stock semanal desde OINM (saldo corrido = Σ InQty − OutQty), hasta finSunday.
        $wkEnd = []; $wkMin = [];   // [cod][lunes] => saldo fin / mínimo de la semana
        foreach (array_chunk($codigos, 500) as $chunk) {
            $in  = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT LTRIM(RTRIM(ItemCode)) c, CONVERT(char(10),DocDate,126) f, InQty i, OutQty o
                    FROM OINM WHERE Warehouse='010' AND ItemCode IN ($in) AND DocDate <= ?
                    ORDER BY ItemCode, DocDate, TransNum";
            $st = $sap->prepare($sql);
            $st->execute(array_merge($chunk, [$finSunday]));
            $bal = 0.0; $cur = null;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $c = $m['c'];
                if ($c !== $cur) { $cur = $c; $bal = 0.0; }
                $bal += (float) $m['i'] - (float) $m['o'];
                $w = $lunes($m['f']);
                $wkMin[$c][$w] = isset($wkMin[$c][$w]) ? min($wkMin[$c][$w], $bal) : $bal;
                $wkEnd[$c][$w] = $bal;
            }
        }

        $corr = $prodWeekly;
        $nprod = 0; $nsem = 0; $nseas = 0; $nfb = 0; $uplift = 0.0;
        foreach ($prodWeekly as $cod => $ventas) {
            if (!$ventas) { continue; }
            $wks = array_keys($ventas); sort($wks); $primera = $wks[0];

            // Semanas LIMPIAS (en stock): base del nivel y del perfil estacional del producto.
            $limpAll = []; $limpIso = []; $flag = []; $balCarry = 0.0;
            for ($w = $primera; $w <= $finMonday; $w = $wkAdd($w, 1)) {
                if (isset($wkEnd[$cod][$w])) { $balCarry = $wkEnd[$cod][$w]; }
                $minSem = $wkMin[$cod][$w] ?? $balCarry;
                $stock0 = ($minSem <= 0.0001);
                $flag[$w] = $stock0;
                $dem = $ventas[$w] ?? 0.0;
                if (!$stock0) { $limpAll[] = $dem; $limpIso[$isoW($w)][] = $dem; }
            }
            if (!$limpAll) { continue; }
            $medClean = $median($limpAll);
            if ($medClean <= 0) { continue; }
            $cap = $pctl($limpAll, $capP);

            $huboProd = false;
            foreach ($flag as $w => $stock0) {
                if (!$stock0) { continue; }
                $dem = $ventas[$w] ?? 0.0;
                $activo = false;
                for ($k = -3; $k <= 3; $k++) { if (($ventas[$wkAdd($w, $k)] ?? 0) > 0) { $activo = true; break; } }
                if (!$activo) { continue; }
                if ($dem >= $umbral * $medClean) { continue; }   // no suprimida

                $k0 = $isoW($w); $pool = [];
                for ($dk = -$winIso; $dk <= $winIso; $dk++) { $kk = (($k0 - 1 + $dk) % 53 + 53) % 53 + 1; if (!empty($limpIso[$kk])) { $pool = array_merge($pool, $limpIso[$kk]); } }
                if ($pool) { $imp = $median($pool); $nseas++; } else { $imp = $medClean; $nfb++; }
                $imp = min(max($imp, $dem), $cap);
                if ($imp > $dem) { $corr[$cod][$w] = $imp; $uplift += ($imp - $dem); $nsem++; $huboProd = true; }
            }
            if ($huboProd) { $nprod++; }
        }

        return ['corregido' => $corr, 'stats' => ['prod'=>$nprod,'semanas'=>$nsem,'seasonal'=>$nseas,'fallback'=>$nfb,'uplift'=>$uplift]];
    }
}
