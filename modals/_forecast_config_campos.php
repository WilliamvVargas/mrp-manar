<?php
    /*
     * Cuerpo del formulario de Configuración Forecast, compartido por el modal de creación y el
     * de edición. El includer define $suf (sufijo para IDs: '' en crear, '_ed' en editar). Los
     * name= NO llevan sufijo (cada modal es un form aparte). Los switches con parámetro llevan
     * data-param apuntando a su input; el slider lleva data-out con su lectura. Así el JS opera
     * por atributos y sirve para ambos modales sin duplicar lógica.
     */
    $suf = $suf ?? '';
?>
<input type="hidden" id="csrf_token_forecast_config<?php echo $suf; ?>" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<div class="mensaje-wrapper" style="min-height: 5px; transition: all 0.3s ease;">
    <div id="modal-forecast-config-mensajes<?php echo $suf; ?>"></div>
</div>

<!-- Nombre -->
<div class="mb-3">
    <label class="form-label fw-bold small mb-1" for="input_config_nombre<?php echo $suf; ?>">Nombre <span class="text-danger">*</span></label>
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-tag"></i></span>
        <input type="text"
               class="form-control form-control-sm"
               id="input_config_nombre<?php echo $suf; ?>"
               name="nombre"
               placeholder="Ej: Configuración estándar"
               maxlength="100">
    </div>
    <div class="invalid-feedback small" id="error-nombre<?php echo $suf; ?>"></div>
</div>

<!-- Opciones de limpieza del forecast: paneles plegables (título + switch en la cabecera,
     explicación oculta que se revela al hacer clic). Todos cerrados. -->
<label class="form-label fw-bold small mb-1">Opciones</label>
<div class="opciones-forecast border rounded">

    <!-- Imputar demanda censurada (sin parámetro) -->
    <div class="opcion-item border-bottom">
        <div class="d-flex align-items-center gap-2 px-2 py-2">
            <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                    aria-expanded="false" aria-controls="op-body-censura<?php echo $suf; ?>">
                <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                <span class="small fw-semibold">Imputar demanda censurada por quiebre</span>
            </button>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="cfg_imputar_censura<?php echo $suf; ?>" name="imputar_censura" value="1">
            </div>
        </div>
        <div class="opcion-body" id="op-body-censura<?php echo $suf; ?>" style="display: none;">
            <p class="small text-secondary mb-0 px-2 pb-2 ps-4">
                Cuando hubo quiebre de stock, la venta registrada es menor que la demanda real: el quiebre la censura.
                Esta opción reconstruye el inventario histórico e imputa lo que se habría vendido, para que el modelo no
                aprenda a subestimar los productos que se quiebran seguido.
            </p>
        </div>
    </div>

    <!-- Suavizar outliers (parámetro K) -->
    <div class="opcion-item border-bottom">
        <div class="d-flex align-items-center gap-2 px-2 py-2">
            <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                    aria-expanded="false" aria-controls="op-body-outliers<?php echo $suf; ?>">
                <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                <span class="small fw-semibold">Suavizar outliers (pedidos-lote)</span>
            </button>
            <div class="form-check form-switch m-0">
                <input class="form-check-input opcion-param-switch" type="checkbox" role="switch"
                       id="cfg_capar_outliers<?php echo $suf; ?>" name="capar_outliers" value="1"
                       data-param="#cfg_capar_k<?php echo $suf; ?>">
            </div>
        </div>
        <div class="opcion-body" id="op-body-outliers<?php echo $suf; ?>" style="display: none;">
            <div class="px-2 pb-2 ps-4">
                <p class="small text-secondary mb-2">
                    Pedidos puntuales muy grandes (compras por contrato o evento) inflan el histórico y el modelo los toma
                    como demanda recurrente. Se recortan por sobre un múltiplo de la mediana; un K más alto suaviza menos.
                </p>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <label class="form-label fw-bold small mb-0" for="cfg_capar_k<?php echo $suf; ?>">Umbral (K)</label>
                    <input type="number" step="0.5" min="1" max="50"
                           class="form-control form-control-sm opcion-param"
                           id="cfg_capar_k<?php echo $suf; ?>" name="capar_k" value="10"
                           style="width: 90px;" disabled>
                    <span class="small text-muted">× la mediana</span>
                    <div class="invalid-feedback small w-100"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ensamble (parámetro: peso del ensamble) -->
    <div class="opcion-item border-bottom">
        <div class="d-flex align-items-center gap-2 px-2 py-2">
            <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                    aria-expanded="false" aria-controls="op-body-ensamble<?php echo $suf; ?>">
                <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                <span class="small fw-semibold">Ensamble Prophet + Pronóstico Estacional Ingenuo</span>
            </button>
            <div class="form-check form-switch m-0">
                <input class="form-check-input opcion-param-switch" type="checkbox" role="switch"
                       id="cfg_ensamble<?php echo $suf; ?>" name="ensamble" value="1"
                       data-param="#cfg_ensamble_peso<?php echo $suf; ?>">
            </div>
        </div>
        <div class="opcion-body" id="op-body-ensamble<?php echo $suf; ?>" style="display: none;">
            <div class="px-2 pb-2 ps-4">
                <p class="small text-secondary mb-2">
                    Promedia el pronóstico de Prophet con un pronóstico estacional ingenuo (repetir la temporada del año anterior).
                    Ajusta cuánto pesa cada uno; es más robusto ante el ruido de timing semanal.
                </p>
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label fw-bold small mb-0" for="cfg_ensamble_peso<?php echo $suf; ?>">Peso del ensamble</label>
                    <span class="small fw-semibold text-primary" id="cfg_ensamble_peso_out<?php echo $suf; ?>">Prophet 50% · Estacional 50%</span>
                </div>
                <input type="range" min="0" max="100" step="5" value="50"
                       class="form-range opcion-param opcion-peso"
                       id="cfg_ensamble_peso<?php echo $suf; ?>" name="ensamble_peso_prophet"
                       data-out="#cfg_ensamble_peso_out<?php echo $suf; ?>" disabled>
            </div>
        </div>
    </div>

    <!-- Estabilizar productos de poco histórico (parámetro N) -->
    <div class="opcion-item">
        <div class="d-flex align-items-center gap-2 px-2 py-2">
            <button type="button" class="opcion-toggle btn p-0 border-0 bg-transparent d-flex align-items-center gap-2 flex-grow-1 text-start"
                    aria-expanded="false" aria-controls="op-body-estabilizar<?php echo $suf; ?>">
                <i class="bi bi-chevron-right opcion-chevron text-muted"></i>
                <span class="small fw-semibold">Estabilizar productos de poco histórico</span>
            </button>
            <div class="form-check form-switch m-0">
                <input class="form-check-input opcion-param-switch" type="checkbox" role="switch"
                       id="cfg_estabilizar<?php echo $suf; ?>" name="estabilizar_poco_historico" value="1"
                       data-param="#cfg_estabilizar_n<?php echo $suf; ?>">
            </div>
        </div>
        <div class="opcion-body" id="op-body-estabilizar<?php echo $suf; ?>" style="display: none;">
            <div class="px-2 pb-2 ps-4">
                <p class="small text-secondary mb-2">
                    Un producto con pocas semanas de venta tiene una participación ruidosa dentro de su grupo.
                    Esta opción suaviza su participación hacia el reparto neutro del grupo: mientras menos historia
                    tenga, más se apoya en el grupo; al acercarse a N semanas, usa su propia tasa.
                </p>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <label class="form-label fw-bold small mb-0" for="cfg_estabilizar_n<?php echo $suf; ?>">Semanas de referencia (N)</label>
                    <input type="number" step="1" min="4" max="104"
                           class="form-control form-control-sm opcion-param"
                           id="cfg_estabilizar_n<?php echo $suf; ?>" name="estabilizar_n_semanas" value="52"
                           style="width: 90px;" disabled>
                    <span class="small text-muted">semanas</span>
                    <div class="invalid-feedback small w-100"></div>
                </div>
            </div>
        </div>
    </div>

</div>
