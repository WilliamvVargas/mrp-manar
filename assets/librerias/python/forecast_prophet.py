"""
============================================================================
 PASO 2/3 — Pronóstico de demanda por grupo (familia, sub-familia) con Prophet.
            GRANO SEMANAL (semana ISO, identificada por la fecha de su lunes).
----------------------------------------------------------------------------
 Lee las series que exportó forecast_export.php (carpeta forecast/):
   - grupos_demanda.csv      (semana, demanda en unidades)
   - grupos_presupuesto.csv  (regresor: presupuesto $, historia + 52 semanas futuras)
   - meta.csv                (las 52 semanas a pronosticar; cada valor es un lunes)

 Para cada grupo entrena Prophet (estacionalidad anual) y pronostica 52 semanas.
 Usa el PRESUPUESTO semanal como regresor SOLO si el grupo tiene presupuesto en el
 horizonte (no todo cero); si no lo tiene, lo pronostica igual pero SIN presupuesto y
 lo marca (usa_presupuesto=0) para advertirlo en la UI. Si la serie es corta
 (<104 semanas ~ 2 años) o Prophet falla, usa un fallback estacional (promedio de la
 misma semana ISO).

 Escribe: forecast/grupos_forecast.csv
          (grupo_id, semana, yhat, yhat_lower, yhat_upper, metodo, usa_presupuesto)

 Ejecutar con el venv:  python/venv/Scripts/python.exe python/forecast_prophet.py
============================================================================
"""

import os
import sys
import logging
import warnings

warnings.filterwarnings('ignore')
logging.getLogger('prophet').setLevel(logging.ERROR)
logging.getLogger('cmdstanpy').setLevel(logging.ERROR)

import pandas as pd
from prophet import Prophet

# Mínimo de semanas para intentar Prophet con estacionalidad anual (2 años).
MIN_SEMANAS = 104

# changepoint_prior_scale: más flexibilidad de tendencia que el default (0.05), para captar
# crecimientos recientes (ej. grupos que subieron en el último año, como Chocolatería/Premium).
CPS = 0.10
# Semanas de validación interna para decidir, POR GRUPO, si conviene el regresor de presupuesto.
VAL_HOLDOUT = 52

# Carpeta de datos: 'forecast' (real) por defecto, o 'backtest' (validación) por argumento.
SUB = sys.argv[1] if len(sys.argv) > 1 else 'forecast'
DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), SUB)

# Modo del regresor. Por defecto DECIDE por grupo (usa el presupuesto solo si mejora la
# validación interna). 2do argumento:
#   (sin arg) -> producción, decide por grupo            -> grupos_forecast.csv
#   'reg'     -> permite el regresor (decide por grupo)  -> grupos_forecast_reg.csv
#   'noreg'   -> NO usa el presupuesto (base, comparar)  -> grupos_forecast_noreg.csv
MODO      = sys.argv[2] if len(sys.argv) > 2 else None
USAR_REG  = (MODO != 'noreg')
SALIDA    = 'grupos_forecast.csv' if MODO is None else ('grupos_forecast_%s.csv' % MODO)

dem  = pd.read_csv(os.path.join(DIR, 'grupos_demanda.csv'))
pres = pd.read_csv(os.path.join(DIR, 'grupos_presupuesto.csv'))
meta = pd.read_csv(os.path.join(DIR, 'meta.csv'))

fsemanas = meta[meta['clave'].str.startswith('forecast_')].sort_values('clave')['valor'].tolist()
HOR      = len(fsemanas)


def to_ds(semana):
    # 'semana' ya es la fecha del lunes ('yyyy-MM-dd').
    return pd.to_datetime(str(semana))


fut_ds = pd.DataFrame({'ds': [to_ds(s) for s in fsemanas]})


def nuevo_prophet():
    return Prophet(yearly_seasonality=True, weekly_seasonality=False,
                   daily_seasonality=False, interval_width=0.80,
                   changepoint_prior_scale=CPS)


def sesgo_total(real, pred):
    return abs(pred / real - 1.0) if real > 0 else abs(pred)


def elegir_regresor(df):
    """¿Conviene el regresor de presupuesto para este grupo? Oculta las últimas H semanas del
    entrenamiento, ajusta CON y SIN presupuesto y elige el de menor sesgo en el TOTAL oculto
    (mismo criterio que el factor del backtest). Si no hay datos suficientes para una validación
    fiable, devuelve False (sin regresor, que es lo más seguro)."""
    H = VAL_HOLDOUT
    if len(df) < H + MIN_SEMANAS:
        H = 26
    if len(df) < H + MIN_SEMANAS:
        return False
    tr = df.iloc[:-H]
    te = df.iloc[-H:]
    try:
        m_reg = nuevo_prophet(); m_reg.add_regressor('presupuesto'); m_reg.fit(tr)
        p_reg = m_reg.predict(te[['ds', 'presupuesto']])['yhat'].clip(lower=0).sum()
        m_no = nuevo_prophet(); m_no.fit(tr[['ds', 'y']])
        p_no = m_no.predict(te[['ds']])['yhat'].clip(lower=0).sum()
        real = float(te['y'].sum())
        return sesgo_total(real, p_reg) <= sesgo_total(real, p_no)
    except Exception:
        return False


resultados = []
grupos = sorted(dem['grupo_id'].unique())

for gid in grupos:
    # Serie del grupo, semanas continuas ancladas en lunes (las faltantes = 0 demanda).
    g = dem[dem['grupo_id'] == gid][['semana', 'demanda']].copy()
    g['ds'] = g['semana'].apply(to_ds)
    g = g[['ds', 'demanda']].sort_values('ds')
    idx = pd.date_range(g['ds'].min(), g['ds'].max(), freq='W-MON')
    g = g.set_index('ds').reindex(idx, fill_value=0).rename_axis('ds').reset_index()
    g = g.rename(columns={'demanda': 'y'})

    # Regresor: presupuesto por semana (historia + futuro).
    pg = pres[pres['grupo_id'] == gid][['semana', 'presupuesto']].copy()
    pg['ds'] = pg['semana'].apply(to_ds)
    pg = pg[['ds', 'presupuesto']]

    df = g.merge(pg, on='ds', how='left')
    df['presupuesto'] = df['presupuesto'].fillna(0)

    # Candidato al regresor: solo grupos con presupuesto en el horizonte (no todo cero).
    # El modo 'noreg' (USAR_REG=False) fuerza base en todos, para comparar.
    bud_fwd    = float(pg[pg['ds'].isin(fut_ds['ds'])]['presupuesto'].sum())
    tiene_pres = USAR_REG and bud_fwd > 0
    uso_reg    = False

    metodo = 'prophet'
    try:
        if len(df) < MIN_SEMANAS:
            raise ValueError('serie corta (%d semanas)' % len(df))
        # Decisión POR GRUPO: usa el presupuesto solo si lo tiene Y mejora la validación interna.
        uso_reg = tiene_pres and elegir_regresor(df)
        modelo = nuevo_prophet()
        if uso_reg:
            modelo.add_regressor('presupuesto')
        modelo.fit(df)

        fut = fut_ds.merge(pg, on='ds', how='left')
        fut['presupuesto'] = fut['presupuesto'].fillna(0)
        fc = modelo.predict(fut)[['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
    except Exception as e:
        # Fallback estacional: promedio de las últimas apariciones de la MISMA semana ISO.
        # El fallback no usa el presupuesto -> usa_presupuesto=0.
        uso_reg = False
        metodo = 'fallback'
        semana_iso = df['ds'].dt.strftime('%V').astype(int)
        filas = []
        for s_sem in fsemanas:
            ds = to_ds(s_sem)
            wk = int(ds.strftime('%V'))
            mismos = df[semana_iso == wk]['y']
            val = mismos.tail(3).mean() if len(mismos) else df['y'].tail(8).mean()
            val = float(val) if pd.notna(val) else 0.0
            filas.append({'ds': ds, 'yhat': val, 'yhat_lower': val * 0.8, 'yhat_upper': val * 1.2})
        fc = pd.DataFrame(filas)

    for _, r in fc.iterrows():
        resultados.append({
            'grupo_id':        int(gid),
            'semana':          r['ds'].strftime('%Y-%m-%d'),
            'yhat':            max(0.0, round(float(r['yhat']), 4)),
            'yhat_lower':      max(0.0, round(float(r['yhat_lower']), 4)),
            'yhat_upper':      max(0.0, round(float(r['yhat_upper']), 4)),
            'metodo':          metodo,
            'usa_presupuesto': int(uso_reg),
        })
    print('  grupo %s: %s | presupuesto: %s (%d semanas)'
          % (gid, metodo, 'SI' if uso_reg else 'no', len(df)))

out = pd.DataFrame(resultados)
out.to_csv(os.path.join(DIR, SALIDA), index=False)
print('Regresor: %s | Grupos: %d | filas: %d -> %s' % ('SI' if USAR_REG else 'NO', len(grupos), len(out), SALIDA))
