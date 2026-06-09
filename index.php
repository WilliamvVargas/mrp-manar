<?php

    session_start();
    require_once __DIR__ . '/config/config.php';

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
            <div class="card-body p-4">
                <h3 class="text-center mb-4">Iniciar Sesión</h3>
                <form id="form-login" class="form-validado-estatico" novalidate>
                    <div id="modal-mensajes"></div>
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="usuario">Usuario</label>
                        <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Ingrese su nombre de usuario" maxlength="<?php echo USER_MAX_LENGTH;?>">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Ingrese su contraseña" autocomplete="new-password" maxlength="<?php echo PASS_MAX_LENGTH;?>">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye" id="iconEye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <button id="btn-login" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>