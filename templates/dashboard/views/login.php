<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(PANEL_NAME) ?> · Acceso</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        
        <div class="robot-container">
            <!-- Robot SVG -->
            <svg id="robot" width="120" height="110" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="overflow: visible;">
              <rect x="50" y="50" width="100" height="80" rx="15" fill="#e0e6ed" stroke="#27ae60" stroke-width="4"/>
              <rect x="65" y="70" width="70" height="30" rx="8" fill="#1f2937"/>
              <g transform="translate(77, 85)">
                <circle cx="0" cy="0" r="8" fill="#ffffff"/>
                <circle class="pupil" id="pupil-left" cx="0" cy="0" r="4" fill="#1f2937" style="transition: transform 0.1s ease-out;"/>
              </g>
              <g transform="translate(123, 85)">
                <circle cx="0" cy="0" r="8" fill="#ffffff"/>
                <circle class="pupil" id="pupil-right" cx="0" cy="0" r="4" fill="#1f2937" style="transition: transform 0.1s ease-out;"/>
              </g>
              <line x1="85" y1="115" x2="115" y2="115" stroke="#27ae60" stroke-width="3" stroke-linecap="round"/>
              <g id="arm-left" style="transform-origin: 40px 90px; transform: rotate(0deg); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                <rect x="30" y="90" width="20" height="60" rx="10" fill="#cbd5e1" stroke="#27ae60" stroke-width="3"/>
              </g>
              <g id="arm-right" style="transform-origin: 160px 90px; transform: rotate(0deg); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                <rect x="150" y="90" width="20" height="60" rx="10" fill="#cbd5e1" stroke="#27ae60" stroke-width="3"/>
              </g>
            </svg>
        </div>

        <h1 class="login-title"><?= e(PANEL_NAME) ?></h1>
        <p class="login-subtitle">Administración del servidor web</p>
        
        <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

        <form method="POST" action="/" class="login-form">
            <input type="hidden" name="action" value="login">
            <?= panel_csrf_field() ?>
            <div class="input-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" placeholder="Ingrese su usuario" autocomplete="username" required autofocus spellcheck="false">
            </div>
            <div class="input-group">
                <label for="login-password">Contraseña</label>
                <div class="pw-wrapper">
                    <input type="password" id="login-password" name="password" placeholder="Ingrese su contraseña" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn">Iniciar Sesión</button>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
    const robot = document.getElementById('robot');
    const pupilLeft = document.getElementById('pupil-left');
    const pupilRight = document.getElementById('pupil-right');
    const armLeft = document.getElementById('arm-left');
    const armRight = document.getElementById('arm-right');
    const passwordInput = document.getElementById('login-password');
    let isCoveringEyes = false;
    
    document.addEventListener('mousemove', (e) => {
        if (isCoveringEyes || !robot) return;
        const rect = robot.getBoundingClientRect();
        const robotCenterX = rect.left + rect.width / 2;
        const robotCenterY = rect.top + rect.height / 2 - 20;
        const angle = Math.atan2(e.clientY - robotCenterY, e.clientX - robotCenterX);
        const maxDistance = 4;
        const moveX = Math.cos(angle) * maxDistance;
        const moveY = Math.sin(angle) * maxDistance;
        pupilLeft.style.transform = `translate(${moveX}px, ${moveY}px)`;
        pupilRight.style.transform = `translate(${moveX}px, ${moveY}px)`;
    });
    
    passwordInput.addEventListener('focus', () => {
        isCoveringEyes = true;
        pupilLeft.style.transform = `translate(0px, 0px)`;
        pupilRight.style.transform = `translate(0px, 0px)`;
        armLeft.style.transform = 'rotate(135deg) translate(-25px, -15px)';
        armRight.style.transform = 'rotate(-135deg) translate(25px, -15px)';
    });
    
    passwordInput.addEventListener('blur', () => {
        isCoveringEyes = false;
        armLeft.style.transform = 'rotate(0deg) translate(0px, 0px)';
        armRight.style.transform = 'rotate(0deg) translate(0px, 0px)';
    });
</script>
</body>
</html>
