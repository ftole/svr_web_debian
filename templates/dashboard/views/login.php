<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(PANEL_NAME) ?> · Acceso</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="brand brand-lg">
            <span class="brand-mark">srvctl</span>
            <span class="brand-name"><?= e(PANEL_NAME) ?></span>
        </div>
        <p class="login-sub">Administración del servidor web</p>

        <div class="login-status">
            <span class="status <?= $activeCount === $totalCount ? 'ok' : 'warn' ?>">
                <span class="dot"></span><?= (int)$activeCount ?>/<?= (int)$totalCount ?> servicios operativos
            </span>
        </div>

        <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

        <form method="POST" action="/" class="login-form">
            <input type="hidden" name="action" value="login">
            <?= panel_csrf_field() ?>
            <label>
                <span>Usuario</span>
                <input type="text" name="username" autocomplete="username" required autofocus placeholder="webadmin">
            </label>
            <label>
                <span>Contraseña</span>
                <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>

        <div class="login-meta">
            v<?= e(PANEL_VERSION) ?> · <?= e((string)gethostname()) ?> · <?= e((string)$CONFIG['SERVER_IP']) ?>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
