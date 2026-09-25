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
    <main class="login-panel">
        <header class="login-brand">
            <span class="login-logo" aria-hidden="true">s</span>
            <span class="login-brand-text">
                <span class="login-title"><?= e(PANEL_NAME) ?></span>
                <span class="login-sub">Administración del servidor web</span>
            </span>
        </header>

        <div class="login-status">
            <span class="status <?= $activeCount === $totalCount ? 'ok' : 'warn' ?>">
                <span class="dot"></span><?= (int)$activeCount ?>/<?= (int)$totalCount ?> servicios operativos
            </span>
        </div>

        <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

        <form method="POST" action="/" class="login-form">
            <input type="hidden" name="action" value="login">
            <?= panel_csrf_field() ?>
            <label class="login-field">
                <span>Usuario</span>
                <input type="text" name="username" autocomplete="username" required autofocus spellcheck="false" placeholder="webadmin">
            </label>
            <label class="login-field">
                <span>Contraseña</span>
                <span class="login-password">
                    <input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="••••••••">
                    <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>
            <button type="submit" class="btn btn-primary btn-block login-submit">Entrar</button>
        </form>

        <footer class="login-meta">
            v<?= e(PANEL_VERSION) ?> · <?= e((string)gethostname()) ?> · <?= e((string)$CONFIG['SERVER_IP']) ?>
        </footer>
    </main>
    <p class="login-foot">Acceso restringido · solo personal autorizado</p>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
