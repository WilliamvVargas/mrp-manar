# Forecast de demanda por producto — Guía de instalación y uso

Guía para dejar funcionando el cálculo del forecast en otro equipo, más la explicación
del método. El motor de pronóstico es **Python/Prophet**; el resto (datos, desagregación,
tablas) es **PHP nativo + MySQL**. Los datos de venta real salen de **SAP (SQL Server)** y
el presupuesto de **MySQL** (`mrp_manar`).

---

## 1. Qué hace y cómo funciona (explicación)

Pronostica la **demanda por producto** (unidades) para los **próximos 12 meses**, con un
enfoque **top-down**:

1. **Prophet por grupo `(familia, sub-familia)`**: entrena con la demanda mensual real del
   grupo (suma de sus productos) y pronostica 12 meses. Captura tendencia + estacionalidad.
   Si la serie es corta (<24 meses) usa un *fallback* estacional.
2. **Desagregación a productos**: el pronóstico del grupo se reparte entre sus productos
   según la **participación** de cada uno = tasa ponderada (peso exponencial α=0.85, más
   peso a lo reciente) de los **últimos 12 meses**, renormalizada entre los productos
   **activos**.
   - `demanda_forecast(producto, mes) = forecast_grupo(mes) × participación(producto)`
3. **Productos inactivos** (SAP `OITM`: `validFor='Y'` y no `frozenFor`) se **excluyen**; su
   parte se redistribuye a los activos.
4. **Backtesting**: se ocultan los últimos 12 meses reales, se pronostican y se comparan.
   De ahí sale, por grupo: **bias** (sesgo), **MAPE** (error mensual) y un **factor** de
   corrección (`suma_real / suma_forecast`, acotado 0.25–4) que ajusta el sesgo residual →
   columna `demanda_forecast_corr`.

**Nota de diseño**: la base va **SIN el presupuesto como regresor**. El backtest mostró que
el presupuesto 2026 incompleto subestimaba los grupos grandes (Dulce de Leche: bias −50% con
regresor → −2% sin él). El regresor quedó opcional (`... reg`) para re-testear cuando el
presupuesto esté limpio y alineado con la taxonomía de SAP.

**Salida**:
- Tabla `forecast_x_producto` — `demanda_forecast` + `demanda_forecast_corr` (con factor,
  en unidades enteras) + `forecast_grupo`, `participacion`, `factor`, `metodo`.
- Tabla `forecast_backtest` — bias / MAPE / factor por grupo.
- Se visualiza en el **gráfico de producto** de *Consultas SAP → v3* (demanda forecast en
  morado, presupuesto futuro en amarillo).

---

## 2. Requisitos del equipo nuevo

- **XAMPP** (Apache + PHP 8.x + MySQL/MariaDB en el puerto **3307**), o equivalente.
- Extensión PHP **`pdo_sqlsrv`** + **Microsoft ODBC Driver 18 for SQL Server** (para leer SAP).
- Extensión PHP **`pdo_mysql`** (viene con XAMPP).
- **Python 3.10–3.12** (se usó **3.12**; **NO** sirve 3.14 para Prophet).
- Acceso de red al **servidor SQL Server de SAP** y a la base **MySQL `mrp_manar`** (con la
  tabla `presupuestos` poblada).

---

## 3. Instalación paso a paso

### 3.1 Código
Copiar/clonar el proyecto en `C:\xampp\htdocs\manar` (o el htdocs del equipo).

### 3.2 Archivos de configuración (NO vienen en git)
Copiar los `.example` y poner las credenciales reales:

```
copy config\config_mysql.example.php     config\config_mysql.php
copy config\config_sqlserver.example.php config\config_sqlserver.php
```

- `config_mysql.php`: `MYSQL_HOST` (ej. `127.0.0.1:3307`), `MYSQL_DB` = `mrp_manar`,
  `MYSQL_USER` = `root`, `MYSQL_PASS`.
- `config_sqlserver.php`: `SQLSRV_HOST`, `SQLSRV_DB`, `SQLSRV_USER`, `SQLSRV_PASS` de SAP.

### 3.3 Base de datos MySQL
Crear la base `mrp_manar` e importar la estructura + datos base:

```
mysql -u root -P 3307 -e "CREATE DATABASE IF NOT EXISTS mrp_manar CHARACTER SET utf8mb4"
mysql -u root -P 3307 mrp_manar < sql\manar.sql
```
(o importar `sql/manar.sql` por phpMyAdmin). Asegurarse de que la tabla `presupuestos` tenga datos.

### 3.4 Entorno Python (Prophet)
Desde `C:\xampp\htdocs\manar\python`:

```
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
venv\Scripts\python.exe -m pip install -r requirements.txt
```

> La instalación de Prophet **compila cmdstan** y tarda varios minutos; necesita internet.
> `requirements.txt` = `prophet`, `pandas`, `matplotlib`.

Verificar:
```
venv\Scripts\python.exe -c "import prophet, pandas; print(prophet.__version__)"
```

---

## 4. Crear las tablas del forecast

Ejecutar una vez (phpMyAdmin o mysql):
```
mysql -u root -P 3307 mrp_manar < sql\forecast_x_producto.sql
mysql -u root -P 3307 mrp_manar < sql\forecast_backtest.sql
```

---

## 5. Correr el pipeline

Los `.php` se abren en el **navegador** (`http://localhost/manar/pruebas/<archivo>.php`) o por
**CLI** (`C:\xampp\php\php.exe pruebas\<archivo>.php`). Solo funcionan desde `localhost`.
`PY` = `python\venv\Scripts\python.exe`.

### 5.1 Forecast (12 meses futuros)
```
1) pruebas/forecast_export.php          -> escribe python/forecast/*.csv
2) PY python/forecast_prophet.py        -> pronostica por grupo (sin regresor) -> grupos_forecast.csv
3) pruebas/forecast_cargar.php          -> desagrega a productos -> llena forecast_x_producto
```

### 5.2 Backtesting (error + factor de corrección)
```
4) pruebas/forecast_backtest_export.php -> escribe python/backtest/*.csv (oculta últimos 12 meses)
5) PY python/forecast_prophet.py backtest
6) pruebas/forecast_backtest_cargar.php -> llena forecast_backtest y aplica el factor a forecast_x_producto
```

> El **orden importa**: primero 5.1 (crea el forecast), luego 5.2 (calcula el factor y lo aplica).

### 5.3 (Opcional) Comparar con/sin presupuesto-regresor
```
PY python/forecast_prophet.py backtest reg
PY python/forecast_prophet.py backtest noreg
pruebas/forecast_backtest_comparar.php
```

---

## 6. Dónde ver los resultados

- **Tabla** `forecast_x_producto`: usar `demanda_forecast_corr` como la demanda oficial (unidades).
- **Tabla** `forecast_backtest`: confiabilidad por grupo (MAPE bajo = confiable).
- **Gráfico**: *Consultas SAP → Consulta Facs. y NCs v3 → botón Gráfico producto*. Muestra
  historia (demanda azul / venta roja) + futuro (demanda forecast **morado** / presupuesto **amarillo**).

---

## 7. Salvedades

- La BD está **incompleta**: presupuesto 2026 parcial en varios grupos y la taxonomía
  familia/sub-familia **no cruza del todo** entre presupuesto y SAP. Por eso la base va sin
  regresor. Al completarla, re-correr y re-evaluar el regresor (`... reg`).
- El forecast lee **datos en vivo** de SAP y del presupuesto: el equipo nuevo necesita acceso
  a ambos.
- Los grupos **erráticos/chicos** (MAPE muy alto) tienen forecast poco confiable → usar con
  stock de seguridad.
