<?php

  // Reglas de validación para el sistema

  //Usuario
  define('USER_MIN_LENGTH', 4);
  define('USER_MAX_LENGTH', 20);

  //Contraseñas
  define('PASS_MIN_LENGTH', 8);
  define('PASS_MAX_LENGTH', 20);
  define('PASS_GENERATED_MIN_LENGTH', 10);

  //Nombres y Apellidos
  define('NOMBRE_APELLIDO_MIN_LENGTH', 2);
  define('NOMBRE_APELLIDO_MAX_LENGTH', 128);

  //Menús
  define('MENU_NOMBRE_MIN_LENGTH', 2);
  define('MENU_NOMBRE_MAX_LENGTH', 30);

  //Seguridad de acceso (freno de fuerza bruta)
  define('LOGIN_MAX_INTENTOS', 5);      // Intentos fallidos permitidos por IP + usuario
  define('LOGIN_VENTANA_MINUTOS', 15);  // Ventana de tiempo y duración del bloqueo

  //Carga masiva de Forecast (.xlsx)
  define('FORECAST_MAX_FILAS', 5000);   // Máximo de filas de datos a procesar
  define('FORECAST_MAX_PESO_MB', 5);    // Peso máximo del archivo .xlsx (protege la memoria)

  //Carga masiva de Presupuesto (.xlsx, hoja "base")
  define('PRESUPUESTO_MAX_FILAS', 10000);  // Máximo de filas de datos a procesar
  define('PRESUPUESTO_MAX_PESO_MB', 10);   // Peso máximo del archivo .xlsx

  //Iconos personalizados (.svg)
  define('ICONO_SVG_MAX_PESO_KB', 200);    // Peso máximo del archivo SVG

?>
