<?php

    declare(strict_types=1);
    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/sesion.php';

    $tokenPost = $_POST['csrf_token'] ?? '';
    $tokenSession = $_SESSION['csrf_token'] ?? '';
    $response = ['status' => 'error', 'errors' => []];

    $action = $_REQUEST['action'] ?? '';

    if (empty($tokenPost) || !hash_equals($tokenSession, $tokenPost)) {

            echo json_encode([
                'status' => 'warning',
                'type' => 'auth',
                'message' => "Su sesión ha expirado, por favor inicie sesión nuevamente.",
                'errors' => []
            ]);
            exit;

    }

    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../includes/funciones_validacion.php';
    require_once __DIR__ . '/../includes/control_acceso.php';

    switch ($action) {

        case 'iniciar_sesion':

            retrasar();

            $usuario = isset($_POST['usuario']) ? strtolower(trim($_POST['usuario'])) : '';
            $password = $_POST['password'] ?? '';

            $errores = [];

            if ($err = validarCampoTexto($usuario, 'Usuario', ['requerido' => true]))
                $errores['usuario'] = $err;

            if ($err = validarCampoTexto($password, 'Contraseña', ['requerido' => true]))
                $errores['password'] = $err;

            if (!empty($errores)) {
                echo json_encode([
                    'status' => 'error',
                    'type' => 'fields',
                    'message' => "Usuario o Contraseña incorrectos.",
                    'errors' => $errores
                ]);
                exit;
            }
            else{

                try {

                    $ip = obtenerIpCliente();

                    // Freno de fuerza bruta: bloqueo temporal por IP + usuario
                    $segundosBloqueo = segundosBloqueoLogin($pdo, $ip, $usuario);
                    if ($segundosBloqueo > 0) {
                        $minutos = (int) ceil($segundosBloqueo / 60);
                        echo json_encode([
                            'status' => 'error',
                            'type' => 'auth',
                            'message' => "Demasiados intentos fallidos. Intente nuevamente en {$minutos} minuto(s).",
                            'errors' => []
                        ]);
                        exit;
                    }

                    // Validación contra Base de Datos
                    $usuarioModel = new Usuario($pdo);
                    $usuario_db = $usuarioModel->obtenerCredenciales($usuario);

                    if ($usuario_db && password_verify($password, $usuario_db['password_hash'])) {

                        limpiarIntentosFallidos($pdo, $ip, $usuario);

                        // Se regenera el ID de sesión para evitar fijación de sesión
                        session_regenerate_id(true);

                        // Se guardan datos importantes en la sesión
                        $_SESSION['usuario_id'] = $usuario_db['id'];
                        $_SESSION['usuario_nombre'] = $usuario;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $url_defecto = 'dashboard';

                        // Se añade una ruta de redirección en caso de haber ingresado por un url
                        if (isset($_SESSION['redirect_to'])) {
                            $url_redireccion = $_SESSION['redirect_to'];
                            unset($_SESSION['redirect_to']);
                            $url_defecto = $url_redireccion;
                        }

                        echo json_encode(['status' => 'success',
                                          'redirect' => $url_defecto]);
                        exit;

                    }
                    else {

                        // Credenciales incorrectas: se registra el intento fallido
                        registrarIntentoFallido($pdo, $ip, $usuario);

                        echo json_encode([
                            'status' => 'error',
                            'type' => 'auth',
                            'message' => "Usuario o Contraseña incorrectos.",
                            'errors' => ['usuario' => '', 'password' => '']
                        ]);
                        exit;

                    }
                }
                catch (PDOException $e) {

                    echo json_encode([
                        'status' => 'error',
                        'type' => 'db',
                        'message' => "Error de base de datos.",
                        'errors' => []
                    ]);
                    exit;
                }

            }

            exit;

        case 'validar_campo':

            $campo = $_POST['campo'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $extra = $_POST['extra'] ?? '';

            $nombresLegibles = [
                'usuario'   => 'Usuario',
                'password'   => 'Contraseña',
            ];

            $nombreFormulario = $nombresLegibles[$campo] ?? ucfirst($campo);

            $errores = [];
            $errores[$campo] = validarCampoTexto($valor, $nombreFormulario, ['requerido' => true]);

            if (!empty($errores[$campo])) {
                echo json_encode([
                    'status' => 'error',
                    'type' => 'fields',
                    'errors' => $errores
                ]);
                exit;
            }

            echo json_encode(['status' => 'success']);
            exit;

        default:

            echo json_encode($response);
            exit;
    }

?>
