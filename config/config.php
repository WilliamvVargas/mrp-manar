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

  //Ítem Menús
  define('ITEM_MENU_NOMBRE_MIN_LENGTH', 2);
  define('ITEM_MENU_NOMBRE_MAX_LENGTH', 60);
  define('ITEM_MENU_ICONO_MAX_LENGTH', 60);
  define('ITEM_MENU_ENLACE_MAX_LENGTH', 100);

  //Perfiles
  define('PERFIL_NOMBRE_MIN_LENGTH', 2);
  define('PERFIL_NOMBRE_MAX_LENGTH', 50);
  define('PERFIL_ADMIN_ID', 1);   // Perfil Administrador fijo: no se puede editar ni eliminar

  //Empresas
  define('EMPRESA_NOMBRE_MIN_LENGTH', 2);
  define('EMPRESA_NOMBRE_MAX_LENGTH', 50);   // debe coincidir con empresas.nombre (varchar 50)
  define('EMPRESA_LOGO_MAX_PESO_MB', 2);     // peso máximo del logo (imagen)

  //Seguridad de acceso (freno de fuerza bruta)
  define('LOGIN_MAX_INTENTOS', 5);      // Intentos fallidos permitidos por IP + usuario
  define('LOGIN_VENTANA_MINUTOS', 15);  // Ventana de tiempo y duración del bloqueo

  //Carga masiva de Presupuesto (.xlsx, hoja "base")
  define('PRESUPUESTO_MAX_FILAS', 10000);  // Máximo de filas de datos a procesar
  define('PRESUPUESTO_MAX_PESO_MB', 10);   // Peso máximo del archivo .xlsx

  //Iconos personalizados (.svg)
  define('ICONO_SVG_MAX_PESO_KB', 200);    // Peso máximo del archivo SVG

  //Carga masiva de Ventas Históricas (.xlsx)
  define('VENTAS_MAX_FILAS', 100000);      // Máximo de filas de datos a procesar
  define('VENTAS_MAX_PESO_MB', 25);        // Peso máximo del archivo .xlsx

  //IA local (Ollama) — resúmenes en lenguaje natural del forecast
  define('IA_PROVEEDOR', 'ollama');                 // 'ollama' (local) — dejado configurable a futuro
  define('OLLAMA_URL',   'http://localhost:11434'); // servidor Ollama (mismo equipo)
  define('OLLAMA_MODEL', 'qwen2.5:7b-instruct');    // modelo instalado (buen español; usa GPU dedicada)
  define('IA_TIMEOUT_SEG', 120);                    // timeout amplio (CPU puede tardar)
  define('OLLAMA_KEEP_ALIVE', '30m');               // mantiene el modelo cargado en RAM (evita recargas)
  define('OLLAMA_NUM_PREDICT', 220);                // tope de tokens a generar (resumen corto)

?>
