"""
Genera datos sintéticos con DOS variables:
  - demanda     -> lo que se quiere pronosticar (columna 'y' de Prophet)
  - presupuesto -> variable externa (regresor) que INFLUYE en la demanda

El presupuesto se planifica "conociendo" las campañas del mes, así que incluye
parte de la desviación (shock) que NO se explica por el calendario. Gracias a eso
Prophet le puede sacar provecho como regresor (add_regressor).

Usa SOLO la librería estándar (math, random, datetime). Semilla fija (42) para que
el resultado sea siempre el mismo (reproducible).

Escribe DOS archivos:
  - datos_historicos.txt  : fecha,demanda,presupuesto   (48 meses de historia)
  - presupuesto_futuro.txt: fecha,presupuesto           (12 meses futuros)

Ojo: del futuro NO se conoce la demanda (es lo que se pronostica), pero el
presupuesto SÍ se conoce por adelantado (está planificado). Por eso el regresor
es utilizable a futuro.
"""

import math
import random
from datetime import date

ARCH_HIST  = "datos_historicos.txt"
ARCH_FUT   = "presupuesto_futuro.txt"
MESES_HIST = 48                    # 4 años de historia (2021-2024)
MESES_FUT  = 12                    # 12 meses a pronosticar (2025)
INICIO     = date(2021, 1, 1)
PRECIO     = 1000                  # $ por unidad, para expresar el presupuesto en pesos


def sumar_meses(d, n):
    """Devuelve la fecha 'd' desplazada n meses (día 1)."""
    total     = (d.year * 12 + (d.month - 1)) + n
    anio, mes = divmod(total, 12)
    return date(anio, mes + 1, 1)


def demanda_y_presupuesto(t):
    """
    Para el mes t devuelve (demanda, presupuesto).
      demanda      = señal_calendario + shock + ruido_pequeño
      presupuesto  = (señal_calendario + shock) * PRECIO
    El 'shock' es una desviación NO explicable por tendencia/estacionalidad
    (p. ej. una campaña). Como el presupuesto lo incluye, aporta información
    que el calendario por sí solo no tiene.
    """
    base       = 500
    tendencia  = 6 * t                                       # ~6 unidades más por mes
    estacional = 120 * math.sin(2 * math.pi * (t % 12) / 12) # pico anual
    senal      = base + tendencia + estacional

    shock = random.gauss(0, 60)     # desviación por campañas (conocida al presupuestar)

    demanda     = max(0, round(senal + shock + random.gauss(0, 15)))
    presupuesto = max(0, round((senal + shock) * PRECIO))
    return demanda, presupuesto


def main():
    random.seed(42)                 # reproducible

    historico = []
    for t in range(MESES_HIST):
        dem, pres = demanda_y_presupuesto(t)
        historico.append((sumar_meses(INICIO, t).isoformat(), dem, pres))

    futuro = []
    for t in range(MESES_HIST, MESES_HIST + MESES_FUT):
        _dem, pres = demanda_y_presupuesto(t)   # la demanda futura se descarta (no se conoce)
        futuro.append((sumar_meses(INICIO, t).isoformat(), pres))

    with open(ARCH_HIST, "w", encoding="utf-8") as f:
        f.write("fecha,demanda,presupuesto\n")
        for fecha, dem, pres in historico:
            f.write(f"{fecha},{dem},{pres}\n")

    with open(ARCH_FUT, "w", encoding="utf-8") as f:
        f.write("fecha,presupuesto\n")
        for fecha, pres in futuro:
            f.write(f"{fecha},{pres}\n")

    print(f"Guardado: {ARCH_HIST}  ({len(historico)} meses)  y  {ARCH_FUT}  ({len(futuro)} meses)")
    print("\nHistórico (primeros 3):  fecha  demanda  presupuesto")
    for fecha, dem, pres in historico[:3]:
        print(f"  {fecha}  {dem:>5}  {pres:>8}")
    print("Futuro (primeros 3):     fecha  presupuesto (la demanda se pronostica)")
    for fecha, pres in futuro[:3]:
        print(f"  {fecha}         {pres:>8}")


if __name__ == "__main__":
    main()
