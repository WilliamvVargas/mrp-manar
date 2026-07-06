@echo off
REM ============================================================================
REM  Configura el entorno y ejecuta el ejemplo de Prophet en otro equipo.
REM  Requisito: tener instalado Python 3.10, 3.11 o 3.12 (NO uses 3.14).
REM  Uso: abre cmd en esta carpeta (python\) y ejecuta:  instalar_y_ejecutar.bat
REM ============================================================================

echo [1/5] Creando entorno virtual con Python 3.12...
py -3.12 -m venv venv
if errorlevel 1 (
    echo ERROR: no se encontro Python 3.12. Instalalo desde https://www.python.org
    echo         o cambia "py -3.12" por la version que tengas ^(py --list^).
    pause
    exit /b 1
)

echo [2/5] Activando el entorno...
call venv\Scripts\activate.bat

echo [3/5] Instalando dependencias ^(puede tardar unos minutos^)...
python -m pip install --upgrade pip
pip install -r requirements.txt

echo [4/5] Generando los datos historicos ^(datos_historicos.txt^)...
python generar_datos.py

echo [5/5] Ejecutando el pronostico...
python ejemplo_prophet.py

echo.
echo LISTO. Se generaron: datos_historicos.txt, pronostico.png y componentes.png
pause
