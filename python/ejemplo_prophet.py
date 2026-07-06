"""
==============================================================================
 Ejemplo de pronóstico con Prophet + una VARIABLE EXTERNA (regresor).
==============================================================================

Igual que el ejemplo básico, pero además del histórico de demanda usamos el
PRESUPUESTO como regresor (add_regressor). El presupuesto influye en la demanda
y —a diferencia de la demanda— se conoce a futuro, así que sirve para pronosticar.

Qué hace:
  1. Lee la demanda + presupuesto histórico de 'datos_historicos.txt'.
  2. Lee el presupuesto FUTURO de 'presupuesto_futuro.txt' (12 meses).
  3. Entrena Prophet con la estacionalidad anual + el regresor 'presupuesto'.
  4. Pronostica 12 meses usando el presupuesto planificado de esos meses.
  5. Imprime el efecto estimado del presupuesto y la tabla de pronóstico, y
     guarda los gráficos.

Ejecuta primero:  python generar_datos.py

Requisitos: pip install prophet pandas matplotlib
(Python 3.10 - 3.12; NO 3.14)

Idea clave del regresor:
  - La columna 'presupuesto' debe estar poblada en TODAS las filas que ve el
    modelo: el histórico (para aprender la relación) y el futuro (para predecir).
==============================================================================
"""

import pandas as pd
import matplotlib
matplotlib.use("Agg")            # backend sin ventana: solo guarda a archivo
import matplotlib.pyplot as plt
from prophet import Prophet
from prophet.utilities import regressor_coefficients


# ---------------------------------------------------------------------------
# 1) Cargar histórico (demanda + presupuesto) y presupuesto futuro.
# ---------------------------------------------------------------------------
def cargar_historico(archivo="datos_historicos.txt"):
    """Lee 'fecha,demanda,presupuesto' y lo deja como lo pide Prophet: ds, y, presupuesto."""
    df = pd.read_csv(archivo)
    df = df.rename(columns={"fecha": "ds", "demanda": "y"})
    df["ds"] = pd.to_datetime(df["ds"])
    return df


def cargar_presupuesto_futuro(archivo="presupuesto_futuro.txt"):
    """Lee 'fecha,presupuesto' de los meses a pronosticar."""
    pf = pd.read_csv(archivo)
    pf = pf.rename(columns={"fecha": "ds"})
    pf["ds"] = pd.to_datetime(pf["ds"])
    return pf


# ---------------------------------------------------------------------------
# 2) Entrenar y pronosticar con el regresor.
# ---------------------------------------------------------------------------
def main():
    df         = cargar_historico()
    presup_fut = cargar_presupuesto_futuro()

    print("=== Histórico (últimos 6 meses) ===")
    print(df.tail(6).to_string(index=False))
    print(f"\nMeses de historia: {len(df)}   |   Meses a pronosticar: {len(presup_fut)}\n")

    # Modelo con estacionalidad anual + el presupuesto como regresor externo.
    modelo = Prophet(
        yearly_seasonality=True,
        weekly_seasonality=False,
        daily_seasonality=False,
        interval_width=0.90,     # intervalo de confianza del 90%
    )
    modelo.add_regressor("presupuesto")     # <-- LA VARIABLE EXTRA
    modelo.fit(df)

    # Horizonte de 12 meses; hay que poblar 'presupuesto' en TODAS las filas.
    futuro       = modelo.make_future_dataframe(periods=len(presup_fut), freq="MS")
    presup_todo  = pd.concat(
        [df[["ds", "presupuesto"]], presup_fut[["ds", "presupuesto"]]],
        ignore_index=True,
    )
    futuro = futuro.merge(presup_todo, on="ds", how="left")

    pronostico = modelo.predict(futuro)

    # ---------------------------------------------------------------------
    # 3) Efecto estimado del regresor.
    # ---------------------------------------------------------------------
    coefs = regressor_coefficients(modelo)
    print("=== Efecto estimado del presupuesto (regresor) ===")
    print(coefs.to_string(index=False))
    print("  (coef > 0: a más presupuesto, más demanda pronosticada)\n")

    # ---------------------------------------------------------------------
    # 4) Tabla de pronóstico (con el presupuesto usado en cada mes).
    # ---------------------------------------------------------------------
    futuros = pronostico.tail(len(presup_fut))[["ds", "yhat", "yhat_lower", "yhat_upper"]].copy()
    futuros = futuros.merge(presup_fut, on="ds", how="left")
    futuros = futuros[["ds", "presupuesto", "yhat", "yhat_lower", "yhat_upper"]]
    futuros.columns = ["Mes", "Presupuesto", "Pronóstico", "Mínimo (90%)", "Máximo (90%)"]
    futuros["Mes"] = futuros["Mes"].dt.strftime("%Y-%m")
    for col in ["Presupuesto", "Pronóstico", "Mínimo (90%)", "Máximo (90%)"]:
        futuros[col] = futuros[col].round().astype(int)

    print("=== Pronóstico de demanda (usando el presupuesto de cada mes) ===")
    print(futuros.to_string(index=False))
    print(f"\nDemanda total pronosticada (12 meses): {int(futuros['Pronóstico'].sum())}")

    # ---------------------------------------------------------------------
    # 5) Gráficos.
    # ---------------------------------------------------------------------
    fig1 = modelo.plot(pronostico)
    plt.title("Pronóstico de demanda con presupuesto como regresor (Prophet)")
    plt.xlabel("Fecha")
    plt.ylabel("Demanda")
    fig1.savefig("pronostico.png", dpi=110, bbox_inches="tight")

    # Componentes: ahora incluye la contribución del regresor 'presupuesto'.
    fig2 = modelo.plot_components(pronostico)
    fig2.savefig("componentes.png", dpi=110, bbox_inches="tight")

    print("\nGráficos guardados: pronostico.png  y  componentes.png")


if __name__ == "__main__":
    main()
