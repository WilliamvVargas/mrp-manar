"""
==============================================================================
 Ejemplo autocontenido de pronóstico con Prophet (Meta / Facebook).
==============================================================================

Qué hace:
  1. Lee la DEMANDA MENSUAL histórica desde 'datos_historicos.txt'
     (ese archivo lo crea generar_datos.py con datos sintéticos; ejecútalo primero).
  2. Entrena un modelo Prophet con esos datos.
  3. Pronostica los próximos 12 meses (con intervalo de confianza).
  4. Imprime una tabla con el resultado y guarda dos gráficos PNG.

Requisitos (instalar una sola vez):
    pip install prophet pandas numpy matplotlib

Ejecutar:
    python ejemplo_prophet.py

Prophet exige un DataFrame con exactamente dos columnas:
    ds -> fecha (datetime)
    y  -> valor numérico a pronosticar (aquí, la demanda)
==============================================================================

Comandos en python en caso que la version sea 3.12

py -3.12 -m venv venv
venv\Scripts\activate
python -m pip install --upgrade pip
pip install -r requirements.txt
python generar_datos.py
python ejemplo_prophet.py

"""

import pandas as pd
import matplotlib
matplotlib.use("Agg")            # backend sin ventana: solo guarda a archivo
import matplotlib.pyplot as plt
from prophet import Prophet


# ---------------------------------------------------------------------------
# 1) Cargar la demanda histórica desde el archivo generado por generar_datos.py.
# ---------------------------------------------------------------------------
def cargar_datos(archivo="datos_historicos.txt"):
    """Lee 'fecha,demanda' del .txt y lo deja como lo pide Prophet: columnas 'ds' e 'y'."""
    df = pd.read_csv(archivo)
    df = df.rename(columns={"fecha": "ds", "demanda": "y"})
    df["ds"] = pd.to_datetime(df["ds"])
    return df


# ---------------------------------------------------------------------------
# 2) y 3) Entrenar y pronosticar.
# ---------------------------------------------------------------------------
def main():
    df = cargar_datos()
    print("=== Datos históricos (últimos 6 meses) ===")
    print(df.tail(6).to_string(index=False))
    print(f"\nTotal de meses de historia: {len(df)}\n")

    # Modelo: activamos estacionalidad anual; la mensual/semanal no aplican a datos mensuales.
    modelo = Prophet(
        yearly_seasonality=True,
        weekly_seasonality=False,
        daily_seasonality=False,
        interval_width=0.90,     # intervalo de confianza del 90%
    )
    modelo.fit(df)

    # Horizonte: 12 meses hacia el futuro (2025).
    futuro     = modelo.make_future_dataframe(periods=12, freq="MS")
    pronostico = modelo.predict(futuro)

    # ---------------------------------------------------------------------
    # 4) Mostrar y guardar resultados.
    # ---------------------------------------------------------------------
    # Solo las columnas útiles del pronóstico, para los 12 meses futuros.
    futuros = pronostico.tail(12)[["ds", "yhat", "yhat_lower", "yhat_upper"]].copy()
    futuros.columns = ["Mes", "Pronóstico", "Mínimo (90%)", "Máximo (90%)"]
    futuros["Mes"]          = futuros["Mes"].dt.strftime("%Y-%m")
    for col in ["Pronóstico", "Mínimo (90%)", "Máximo (90%)"]:
        futuros[col] = futuros[col].round().astype(int)

    print("=== Pronóstico de demanda para los próximos 12 meses ===")
    print(futuros.to_string(index=False))
    print(f"\nDemanda total pronosticada (12 meses): {int(futuros['Pronóstico'].sum())}")

    # Gráfico 1: histórico + pronóstico + banda de confianza.
    fig1 = modelo.plot(pronostico)
    plt.title("Pronóstico de demanda mensual (Prophet)")
    plt.xlabel("Fecha")
    plt.ylabel("Demanda")
    fig1.savefig("pronostico.png", dpi=110, bbox_inches="tight")

    # Gráfico 2: componentes descompuestos (tendencia y estacionalidad anual).
    fig2 = modelo.plot_components(pronostico)
    fig2.savefig("componentes.png", dpi=110, bbox_inches="tight")

    print("\nGráficos guardados: pronostico.png  y  componentes.png")


if __name__ == "__main__":
    main()
