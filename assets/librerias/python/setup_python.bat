@echo off
REM ============================================================================
REM  Bootstrap del entorno Python del forecast (Prophet).
REM  Crea el venv e instala requirements.txt SI el venv no existe. Idempotente:
REM  si el entorno ya existe, no hace nada. Se puede correr a mano o lo dispara
REM  el controlador de la Explosion de Forecast cuando falta el entorno.
REM
REM  Requisito: Python instalado en el servidor (en el PATH, o el launcher 'py').
REM ============================================================================
setlocal
set "DIR=%~dp0"
set "VENV=%DIR%venv"
set "PYV=%VENV%\Scripts\python.exe"

if exist "%PYV%" (echo [setup_python] El entorno ya existe: %PYV% & exit /b 0)

REM --- Detectar el Python del sistema ---
set "SYS_PY="
where python >nul 2>&1 && set "SYS_PY=python"
if not defined SYS_PY where py >nul 2>&1 && set "SYS_PY=py -3"
if not defined SYS_PY (echo [setup_python] ERROR: Python no esta instalado o no esta en el PATH. & exit /b 1)

echo [setup_python] Creando entorno virtual con %SYS_PY% ...
%SYS_PY% -m venv "%VENV%"
if not exist "%PYV%" (echo [setup_python] ERROR: no se pudo crear el venv. & exit /b 1)

echo [setup_python] Instalando dependencias (requirements.txt). Puede tardar varios minutos...
"%PYV%" -m pip install --upgrade pip
"%PYV%" -m pip install -r "%DIR%requirements.txt"
if errorlevel 1 (echo [setup_python] ERROR: fallo la instalacion de dependencias. & exit /b 1)

echo [setup_python] Entorno listo.
exit /b 0
