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
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-mark">SYSTEM_PANEL</div>
                <h2>Autenticación Requerida</h2>
                <p class="subtitle">Acceso seguro al nodo de infraestructura</p>
            </div>
            
            <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

            <form method="POST" action="/" class="login-form">
                <input type="hidden" name="action" value="login">
                <?= panel_csrf_field() ?>
                
                <div class="input-group">
                    <label for="username">IDENTIFICADOR DE USUARIO</label>
                    <input type="text" id="username" name="username" autocomplete="username" required autofocus spellcheck="false" placeholder="root / admin">
                </div>
                
                <div class="input-group">
                    <label for="login-password">CREDENCIAL DE ACCESO</label>
                    <div class="login-input-wrapper">
                        <input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="••••••••">
                        <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary login-btn">INICIAR SESIÓN</button>

                <div class="login-status-modern <?= $activeCount === $totalCount ? 'ok' : 'warn' ?>">
                    <span class="status-dot"></span>
                    <span class="mono-text">SRV: <?= (int)$activeCount ?>/<?= (int)$totalCount ?> OK</span>
                </div>
            </form>
        </div>
        
        <div class="login-footer-info">
            <p class="mono-text">NODE: <?= e((string)gethostname()) ?> | IP: <?= e((string)$CONFIG['SERVER_IP']) ?> | VER: <?= e(PANEL_VERSION) ?></p>
            <p class="mono-text warning-text">ACCESO RESTRINGIDO · AUDITORÍA ACTIVADA</p>
        </div>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
