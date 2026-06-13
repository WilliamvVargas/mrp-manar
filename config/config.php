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

  //Seguridad de acceso (freno de fuerza bruta)
  define('LOGIN_MAX_INTENTOS', 5);      // Intentos fallidos permitidos por IP + usuario
  define('LOGIN_VENTANA_MINUTOS', 15);  // Ventana de tiempo y duración del bloqueo

?>
