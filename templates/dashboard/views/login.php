<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(PANEL_NAME) ?> · Acceso</title>
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-brand-top">
            <div class="login-logo-modern">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
        </div>

        <main class="login-panel">
            <header class="login-header">
                <h1 class="login-title">Bienvenido a <?= e(PANEL_NAME) ?></h1>
                <p class="login-subtitle">Administración del servidor web</p>
            </header>

            <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

            <form method="POST" action="/" class="login-form">
                <input type="hidden" name="action" value="login">
                <?= panel_csrf_field() ?>
                
                <div class="login-field-group">
                    <label class="login-label" for="username">Usuario</label>
                    <input type="text" id="username" name="username" class="login-input" autocomplete="username" required autofocus spellcheck="false" placeholder="ej. webadmin">
                </div>

                <div class="login-field-group">
                    <label class="login-label" for="login-password">Contraseña</label>
                    <div class="login-input-wrapper">
                        <input type="password" id="login-password" name="password" class="login-input" autocomplete="current-password" required placeholder="••••••••">
                        <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-modern btn-block">Continuar &rarr;</button>
            </form>
        </main>

        <div class="login-status-modern <?= $activeCount === $totalCount ? 'ok' : 'warn' ?>">
            <span class="status-dot"></span>
            <?= (int)$activeCount ?>/<?= (int)$totalCount ?> servicios operativos
        </div>

        <footer class="login-footer">
            <p>v<?= e(PANEL_VERSION) ?> · <?= e((string)gethostname()) ?> · <?= e((string)$CONFIG['SERVER_IP']) ?></p>
            <p class="login-restricted">Acceso restringido · solo personal autorizado</p>
        </footer>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
