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
    <div class="login-card">
        
        <!-- Robot Interactivo SVG -->
        <div class="robot-container">
            <svg id="robot-svg" viewBox="0 0 200 200" width="130" height="130" xmlns="http://www.w3.org/2000/svg">
              <style>
                #robot-svg {
                  display: block;
                  margin: 0 auto;
                  overflow: visible;
                }
                #robot-head-group {
                  transform-origin: 100px 110px;
                  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .robot-typing #robot-head-group {
                  transform: rotate(-10deg) translate(-6px, 4px);
                }
                #left-arm {
                  transform-origin: 55px 130px;
                  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                }
                #right-arm {
                  transform-origin: 145px 130px;
                  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .robot-blind #left-arm {
                  transform: rotate(165deg);
                }
                .robot-blind #right-arm {
                  transform: rotate(-165deg);
                }
                #left-pupil, #right-pupil {
                  transition: transform 0.1s ease-out;
                }
                .robot-blind #left-pupil, .robot-blind #right-pupil,
                .robot-typing #left-pupil, .robot-typing #right-pupil {
                  transition: transform 0.3s ease;
                }
              </style>
              
              <!-- Antena -->
              <g id="robot-antenna">
                <line x1="100" y1="40" x2="100" y2="15" stroke="#3F6355" stroke-width="4" stroke-linecap="round"/>
                <circle cx="100" cy="15" r="6" fill="#10B981">
                  <animate attributeName="fill" values="#10B981;#6EE7B7;#10B981" dur="2s" repeatCount="indefinite"/>
                </circle>
              </g>

              <!-- Cabeza y Cuello -->
              <g id="robot-head-group">
                <rect x="90" y="105" width="20" height="15" fill="#B4C9C1" stroke="#3F6355" stroke-width="3"/>
                <rect x="50" y="40" width="100" height="70" rx="15" fill="#FFFFFF" stroke="#3F6355" stroke-width="4"/>
                <rect x="40" y="60" width="10" height="30" rx="3" fill="#D2E3DC" stroke="#3F6355" stroke-width="3"/>
                <rect x="150" y="60" width="10" height="30" rx="3" fill="#D2E3DC" stroke="#3F6355" stroke-width="3"/>
                
                <!-- Ojos -->
                <circle cx="75" cy="70" r="14" fill="#F4F7F6" stroke="#3F6355" stroke-width="3"/>
                <circle cx="125" cy="70" r="14" fill="#F4F7F6" stroke="#3F6355" stroke-width="3"/>
                
                <!-- Pupilas -->
                <circle id="left-pupil" cx="75" cy="70" r="6" fill="#2C3E35"/>
                <circle id="right-pupil" cx="125" cy="70" r="6" fill="#2C3E35"/>
                
                <!-- Boca -->
                <rect x="85" y="92" width="30" height="5" rx="2.5" fill="#5C8D7B"/>
              </g>

              <!-- Cuerpo -->
              <path d="M 60 120 L 140 120 L 155 195 L 45 195 Z" fill="#F4F7F6" stroke="#3F6355" stroke-width="4" stroke-linejoin="round"/>
              <circle cx="100" cy="145" r="7" fill="#10B981"/>
              <rect x="80" y="162" width="40" height="6" rx="3" fill="#B4C9C1"/>

              <!-- Brazo Izquierdo -->
              <g id="left-arm">
                <path d="M 55 130 L 30 180" fill="none" stroke="#3F6355" stroke-width="16" stroke-linecap="round"/>
                <path d="M 55 130 L 30 180" fill="none" stroke="#D2E3DC" stroke-width="10" stroke-linecap="round"/>
                <circle cx="55" cy="130" r="8" fill="#5C8D7B"/>
                <circle cx="30" cy="180" r="14" fill="#FFFFFF" stroke="#3F6355" stroke-width="3"/>
              </g>

              <!-- Brazo Derecho -->
              <g id="right-arm">
                <path d="M 145 130 L 170 180" fill="none" stroke="#3F6355" stroke-width="16" stroke-linecap="round"/>
                <path d="M 145 130 L 170 180" fill="none" stroke="#D2E3DC" stroke-width="10" stroke-linecap="round"/>
                <circle cx="145" cy="130" r="8" fill="#5C8D7B"/>
                <circle cx="170" cy="180" r="14" fill="#FFFFFF" stroke="#3F6355" stroke-width="3"/>
              </g>
            </svg>
        </div>

        <h1 class="login-title"><?= e(PANEL_NAME) ?></h1>
        <p class="login-subtitle">Administración del Servidor Web</p>
        
        <?php require $PANEL_ROOT . '/partials/flash.php'; ?>

        <form method="POST" action="/" class="login-form">
            <input type="hidden" name="action" value="login">
            <?= panel_csrf_field() ?>
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" placeholder="Ingrese su usuario" autocomplete="username" required autofocus spellcheck="false">
            </div>
            <div class="form-group">
                <label for="login-password">Contraseña</label>
                <div class="pw-wrapper">
                    <input type="password" id="login-password" name="password" placeholder="Ingrese su contraseña" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-toggle-password="login-password" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn">Acceder al Servidor</button>
        </form>

        <div class="login-footer">
            <span>v<?= e(PANEL_VERSION) ?> · <?= e((string)gethostname()) ?></span>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script src="assets/js/robot.js"></script>
</body>
</html>
