"""
============================================================================
 PASO 2/3 — Pronóstico de demanda por grupo (familia, sub-familia) con Prophet.
----------------------------------------------------------------------------
 Lee las series que exportó forecast_export.php (carpeta forecast/):
   - grupos_demanda.csv      (ds, y = demanda en unidades)
   - grupos_presupuesto.csv  (regresor: presupuesto $, historia + 12 meses futuros)
   - meta.csv                (los 12 meses a pronosticar)

 Para cada grupo entrena Prophet (estacionalidad anual) con el PRESUPUESTO como
 regresor externo y pronostica los 12 meses. Si la serie es corta (<24 meses) o
 Prophet falla, usa un fallback estacional (promedio del mismo mes) para que
 igual haya pronóstico.

 Escribe: forecast/grupos_forecast.csv  (grupo_id, ym, yhat, yhat_lower, yhat_upper, metodo)

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

# Carpeta de datos: 'forecast' (real) por defecto, o 'backtest' (validación) por argumento.
SUB = sys.argv[1] if len(sys.argv) > 1 else 'forecast'
DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), SUB)

# Modo del regresor. BASE = SIN presupuesto (el backtest mostró que el presupuesto
# 2026 sucio subestimaba los grupos grandes). 2do argumento:
#   (sin arg) -> base, NO usa el presupuesto  -> grupos_forecast.csv
#   'reg'     -> SÍ usa el presupuesto (para cuando esté limpio) -> grupos_forecast_reg.csv
#   'noreg'   -> NO usa el presupuesto (explícito) -> grupos_forecast_noreg.csv
MODO      = sys.argv[2] if len(sys.argv) > 2 else None
USAR_REG  = (MODO == 'reg')
SALIDA    = 'grupos_forecast.csv' if MODO is None else ('grupos_forecast_%s.csv' % MODO)

dem  = pd.read_csv(os.path.join(DIR, 'grupos_demanda.csv'))
pres = pd.read_csv(os.path.join(DIR, 'grupos_presupuesto.csv'))
meta = pd.read_csv(os.path.join(DIR, 'meta.csv'))

fmeses = meta[meta['clave'].str.startswith('forecast_')].sort_values('clave')['valor'].tolist()
HOR    = len(fmeses)


def to_ds(ym):
    return pd.to_datetime(str(ym) + '-01')


fut_ds = pd.DataFrame({'ds': [to_ds(m) for m in fmeses]})

resultados = []
grupos = sorted(dem['grupo_id'].unique())

for gid in grupos:
    # Serie del grupo, meses continuos (los faltantes = 0 demanda).
    g = dem[dem['grupo_id'] == gid][['ym', 'demanda']].copy()
    g['ds'] = g['ym'].apply(to_ds)
    g = g[['ds', 'demanda']].sort_values('ds')
    idx = pd.date_range(g['ds'].min(), g['ds'].max(), freq='MS')
    g = g.set_index('ds').reindex(idx, fill_value=0).rename_axis('ds').reset_index()
    g = g.rename(columns={'demanda': 'y'})

    # Regresor: presupuesto por mes (historia + futuro).
    pg = pres[pres['grupo_id'] == gid][['ym', 'presupuesto']].copy()
    pg['ds'] = pg['ym'].apply(to_ds)
    pg = pg[['ds', 'presupuesto']]

    df = g.merge(pg, on='ds', how='left')
    df['presupuesto'] = df['presupuesto'].fillna(0)

    metodo = 'prophet'
    try:
        if len(df) < 24:
            raise ValueError('serie corta (%d meses)' % len(df))
        modelo = Prophet(
            yearly_seasonality=True,
            weekly_seasonality=False,
            daily_seasonality=False,
            interval_width=0.80,
        )
        if USAR_REG:
            modelo.add_regressor('presupuesto')
        modelo.fit(df)

        fut = fut_ds.merge(pg, on='ds', how='left')
        fut['presupuesto'] = fut['presupuesto'].fillna(0)
        fc = modelo.predict(fut)[['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
    except Exception as e:
        # Fallback estacional: promedio de los últimos años del mismo mes.
        metodo = 'fallback'
        filas = []
        for m_ym in fmeses:
            ds = to_ds(m_ym)
            mismos = df[df['ds'].dt.month == ds.month]['y']
            val = mismos.tail(3).mean() if len(mismos) else df['y'].tail(6).mean()
            val = float(val) if pd.notna(val) else 0.0
            filas.append({'ds': ds, 'yhat': val, 'yhat_lower': val * 0.8, 'yhat_upper': val * 1.2})
        fc = pd.DataFrame(filas)

    for _, r in fc.iterrows():
        resultados.append({
            'grupo_id':   int(gid),
            'ym':         r['ds'].strftime('%Y-%m'),
            'yhat':       max(0.0, round(float(r['yhat']), 4)),
            'yhat_lower': max(0.0, round(float(r['yhat_lower']), 4)),
            'yhat_upper': max(0.0, round(float(r['yhat_upper']), 4)),
            'metodo':     metodo,
        })
    print('  grupo %s: %s (%d meses)' % (gid, metodo, len(df)))

out = pd.DataFrame(resultados)
out.to_csv(os.path.join(DIR, SALIDA), index=False)
print('Regresor: %s | Grupos: %d | filas: %d -> %s' % ('SI' if USAR_REG else 'NO', len(grupos), len(out), SALIDA))
