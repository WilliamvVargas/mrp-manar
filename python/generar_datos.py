"""
Genera datos sintéticos de DEMANDA MENSUAL y los guarda en 'datos_historicos.txt'.

Usa SOLO la librería estándar de Python (math, random, datetime): no requiere
numpy ni pandas, así que corre con cualquier instalación de Python.

La fórmula por mes es:  base + tendencia + estacionalidad_anual + ruido
Con semilla fija (42) para que el resultado sea siempre el mismo (reproducible).

Formato del archivo (CSV dentro de un .txt, fácil de leer con pandas):
    fecha,demanda
    2021-01-01,512
    2021-02-01,...
"""

import math
import random
from datetime import date

ARCHIVO = "datos_historicos.txt"
MESES   = 48                    # 4 años de historia
INICIO  = date(2021, 1, 1)      # primer mes


def sumar_meses(d, n):
    """Devuelve la fecha 'd' desplazada n meses (día 1)."""
    total     = (d.year * 12 + (d.month - 1)) + n
    anio, mes = divmod(total, 12)
    return date(anio, mes + 1, 1)


def generar():
    """Crea la lista de (fecha_iso, demanda) para los MESES meses."""
    random.seed(42)             # reproducible: siempre los mismos números
    filas = []
    for t in range(MESES):
        base       = 500
        tendencia  = 6 * t                                   # ~6 unidades más por mes
        estacional = 120 * math.sin(2 * math.pi * (t % 12) / 12)  # pico anual
        ruido      = random.gauss(0, 25)                     # variabilidad aleatoria

        demanda = base + tendencia + estacional + ruido
        demanda = max(0, round(demanda))                     # sin negativos, entero

        fecha = sumar_meses(INICIO, t)
        filas.append((fecha.isoformat(), demanda))
    return filas


def main():
    filas = generar()

    with open(ARCHIVO, "w", encoding="utf-8") as f:
        f.write("fecha,demanda\n")
        for fecha, demanda in filas:
            f.write(f"{fecha},{demanda}\n")

    print(f"Guardado: {ARCHIVO}  ({len(filas)} filas)")
    for fecha, demanda in filas[:3]:
        print(f"  {fecha}  {demanda}")
    print("  ...")
    for fecha, demanda in filas[-3:]:
        print(f"  {fecha}  {demanda}")


if __name__ == "__main__":
    main()
