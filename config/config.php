<?php

  // Reglas de validación para el sistema

  //Usuario
  define('USER_MIN_LENGTH', 4);
  define('USER_MAX_LENGTH', 20);

  //Contraseñas
  define('PASS_MIN_LENGTH', 8);
  define('PASS_MAX_LENGTH', 20);
  define('PASS_GENERATED_MIN_LENGTH', 10);

  const DICCIONARIO_REGLAS = [
    'usuario' => [
        'requerido' => true, 
        'min' => USER_MIN_LENGTH, 
        'max' => USER_MAX_LENGTH, 
        'patron' => '/^[a-zA-Z0-9_]+$/',
        'mensaje_patron' => 'El campo usuario solo permite letras, números y guiones bajos.'
    ],
    'nombres' => [
        'requerido' => true, 
        'min' => 3, 
        'max' => 50,
        'patron' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/',
        'mensaje_patron' => 'El campo nombres solo deben contener letras.'
    ],
    'apellidos' => [
        'requerido' => true, 
        'min' => 3, 
        'max' => 50,
        'patron' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/',
        'mensaje_patron' => 'El campo apellidos solo deben contener letras.'
    ],
    'password' => [
        'requerido' => true,
        'min' => PASS_MIN_LENGTH,
        'max' => PASS_MAX_LENGTH,
        'patron' => '/^(?=.*[a-zñáéíóú])(?=.*[A-ZÑÁÉÍÓÚ])(?=.*\d)[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ]*$/u',
        'mensaje_patron' => 'La contraseña debe tener al menos una letra minúscula, una letra mayúscula y un número. No debe tener caracteres especiales o espacios.'
    ]
];

?>
