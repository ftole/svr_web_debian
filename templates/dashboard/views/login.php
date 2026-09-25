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
    <div class="split-layout">
        <div class="brand-section">
            <h1>Bienvenido al futuro.</h1>
            <p>Administra tus proyectos con la máxima velocidad, seguridad y estilo.</p>
        </div>
        
        <div class="form-section">
            <div class="form-wrapper">
                <h2>Iniciar Sesión</h2>
                <p class="subtitle">Ingresa a tu panel de control web.</p>
                
                <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

                <form method="POST" action="/">
                    <input type="hidden" name="action" value="login">
                    <?= panel_csrf_field() ?>
                    
                    <div class="input-group">
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" autocomplete="username" required autofocus spellcheck="false" placeholder="ej. webadmin">
                    </div>
                    
                    <div class="input-group">
                        <label for="login-password">Contraseña</label>
                        <div class="login-input-wrapper">
                            <input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="••••••••">
                            <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Iniciar Sesión &rarr;</button>

                    <div class="login-status-modern <?= $activeCount === $totalCount ? 'ok' : 'warn' ?>">
                        <span class="status-dot"></span>
                        <?= (int)$activeCount ?>/<?= (int)$totalCount ?> servicios operativos
                    </div>
                </form>
            </div>
            
            <div class="login-footer-info">
                <p>v<?= e(PANEL_VERSION) ?> · <?= e((string)gethostname()) ?> · <?= e((string)$CONFIG['SERVER_IP']) ?></p>
                <p>Acceso restringido · solo personal autorizado</p>
            </div>
        </div>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
