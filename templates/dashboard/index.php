<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * templates/dashboard/index.php
 * Centro de Administracion y Control Web para Servidor Nativo Debian 13 (srvctl)
 * ==============================================================================
 */

// 1. Carga de configuracion priorizando /etc/srvctl.conf con fallback a /etc/asistente_servidor.conf
$confFiles = ['/etc/srvctl.conf', '/etc/asistente_servidor.conf'];
$conf = [];
foreach ($confFiles as $cf) {
    if (file_exists($cf) && is_readable($cf)) {
        $lines = @file($cf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $trimmed, 2);
                $conf[trim($k)] = trim($v, " '\t\n\r\0\x0B\"");
            }
            if (!empty($conf)) {
                break;
            }
        }
    }
}

$serverIp   = $conf['SERVER_IP'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$baseDomain = $conf['BASE_DOMAIN'] ?? 'empresa.local';
$adminUser  = $conf['ADMIN_USER'] ?? 'webadmin';
$adminPass  = $conf['ADMIN_PASS'] ?? 'Temp123#';
$dbSub      = $conf['DB_SUB'] ?? 'webdev';
$dbFqdn     = $conf['DB_FQDN'] ?? "{$dbSub}.{$baseDomain}";

// 2. Gestion de sesion y autenticacion administrativa
if (session_status() === PHP_SESSION_NONE) {
    session_name('SRVCTL_SESSID');
    @session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = (string)$_SESSION['csrf_token'];
$isAdmin = !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Procesar cierre de sesion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Limpiar salida de diagnostico
if (isset($_GET['action']) && $_GET['action'] === 'clear_verify') {
    unset($_SESSION['verify_result']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#diagnostico');
    exit;
}

// 3. Procesamiento de acciones administrativas POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // Accion: Inicio de sesion
    if ($action === 'login') {
        $inputUser = (string)($_POST['username'] ?? '');
        $inputPass = (string)($_POST['password'] ?? '');

        if (hash_equals($adminUser, $inputUser) && hash_equals($adminPass, $inputPass)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['admin_user'] = $adminUser;
            $_SESSION['flash_success'] = 'Sesión administrativa iniciada correctamente. Panel de control desbloqueado.';
        } else {
            $_SESSION['flash_error'] = 'Credenciales administrativas inválidas. Verifica usuario y contraseña.';
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Acciones restringidas al administrador
    if (!$isAdmin) {
        $_SESSION['flash_error'] = 'Acceso denegado. Debes iniciar sesión como administrador para realizar esta acción.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Validacion de token CSRF
    $postToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postToken)) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido o expirado. Operación cancelada.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Accion a: Crear Nuevo Proyecto
    if ($action === 'project_create') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            $_SESSION['flash_error'] = 'Nombre de proyecto inválido. Usa solo letras minúsculas, números y guiones (ej. tienda, api-rest).';
        } elseif (is_dir("/var/www/{$name}")) {
            $_SESSION['flash_error'] = "El proyecto '{$name}' ya existe en el servidor (/var/www/{$name}).";
        } else {
            $cmd = 'sudo /usr/local/bin/srvctl project create ' . escapeshellarg($name) . ' 2>&1';
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            $rawOut = implode("\n", $output);

            if ($code === 0) {
                $_SESSION['flash_success'] = "Proyecto '{$name}' creado exitosamente. Disponible de inmediato en https://{$name}.{$baseDomain}";
            } else {
                $_SESSION['flash_error'] = "Error al crear el proyecto (código {$code}): " . $rawOut;
            }
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#proyectos');
        exit;
    }

    // Accion b: Aprovisionar Base de Datos MariaDB
    if ($action === 'project_db') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            $_SESSION['flash_error'] = 'Nombre de proyecto inválido para base de datos.';
        } else {
            $cmd = 'sudo /usr/local/bin/srvctl project db ' . escapeshellarg($name) . ' 2>&1';
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            $rawOut = implode("\n", $output);

            if ($code === 0) {
                $dbName = str_replace('-', '_', $name) . '_db';
                $dbUser = substr(str_replace('-', '_', $name) . '_usr', 0, 32);
                $dbPass = '';

                // Leer contraseña depositada en .env
                $envFile = "/var/www/{$name}/public_html/.env";
                if (file_exists($envFile) && is_readable($envFile)) {
                    $envLines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($envLines) {
                        foreach ($envLines as $el) {
                            if (str_starts_with(trim($el), 'DB_PASSWORD=')) {
                                $dbPass = trim(explode('=', $el, 2)[1]);
                            }
                            if (str_starts_with(trim($el), 'DB_DATABASE=')) {
                                $dbName = trim(explode('=', $el, 2)[1]);
                            }
                            if (str_starts_with(trim($el), 'DB_USERNAME=')) {
                                $dbUser = trim(explode('=', $el, 2)[1]);
                            }
                        }
                    }
                }

                // Fallback por analisis de texto de salida si .env no esta accesible
                if (empty($dbPass) && preg_match('/Contrasena:\s+([^\r\n]+)/', $rawOut, $m)) {
                    $dbPass = trim($m[1]);
                }

                $_SESSION['db_result'] = [
                    'project'  => $name,
                    'host'     => '127.0.0.1',
                    'port'     => '3306',
                    'database' => $dbName,
                    'user'     => $dbUser,
                    'pass'     => $dbPass ?: '(Ver .env en public_html)',
                ];
                $_SESSION['flash_success'] = "Base de datos y usuario creados exitosamente para el proyecto '{$name}'.";
            } else {
                $_SESSION['flash_error'] = "Error al aprovisionar la base de datos (código {$code}): " . $rawOut;
            }
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#proyectos');
        exit;
    }

    // Accion c: Eliminar Proyecto
    if ($action === 'project_delete') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (in_array($name, ['prod', 'stg', 'html'], true) || str_starts_with($name, '_')) {
            $_SESSION['flash_error'] = "No se permite eliminar proyectos base del sistema ({$name}).";
        } elseif (!is_dir("/var/www/{$name}")) {
            $_SESSION['flash_error'] = "El proyecto '{$name}' no existe en el servidor.";
        } else {
            $cmd = 'sudo /usr/local/bin/srvctl project delete ' . escapeshellarg($name) . ' 2>&1';
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            $rawOut = implode("\n", $output);

            if ($code === 0) {
                $_SESSION['flash_success'] = "Proyecto '{$name}' y sus bases de datos eliminados correctamente.";
            } else {
                $_SESSION['flash_error'] = "Error al eliminar el proyecto (código {$code}): " . $rawOut;
            }
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#proyectos');
        exit;
    }

    // Accion d: Ejecutar Diagnostico del Servidor
    if ($action === 'verify') {
        $cmd = 'sudo /usr/local/bin/srvctl verify 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        $rawOut = implode("\n", $output);

        $passCount = substr_count($rawOut, '[ OK ]');
        $failCount = substr_count($rawOut, '[FAIL]');

        $_SESSION['verify_result'] = [
            'output'    => $rawOut,
            'code'      => $code,
            'pass'      => $passCount,
            'fail'      => $failCount,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($code === 0 && $failCount === 0) {
            $_SESSION['flash_success'] = "Diagnóstico completado exitosamente: todas las comprobaciones han pasado ({$passCount} OK).";
        } else {
            $_SESSION['flash_warning'] = "Diagnóstico finalizado con advertencias: {$failCount} fallo(s) detectado(s) de " . ($passCount + $failCount) . " comprobaciones.";
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#diagnostico');
        exit;
    }

    // Accion d: Crear Respaldo Inmediato
    if ($action === 'backup_run') {
        $cmd = 'sudo /usr/local/bin/srvctl backup run 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        $rawOut = implode("\n", $output);

        if ($code === 0) {
            $_SESSION['flash_success'] = 'Respaldo del servidor completado exitosamente. Se actualizaron los snapshots y volcados MariaDB.';
        } else {
            $_SESSION['flash_error'] = "Error al ejecutar el respaldo (código {$code}): " . $rawOut;
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#respaldos');
        exit;
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Mensajes Flash y Datos Temporales
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;
$dbResult     = $_SESSION['db_result'] ?? null;
$verifyResult = $_SESSION['verify_result'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning'], $_SESSION['db_result']);

// 4. Telemetria y Estado del Servidor
function checkService(string $name): bool {
    $out = [];
    $code = 0;
    exec("systemctl is-active --quiet " . escapeshellarg($name), $out, $code);
    return $code === 0;
}

$phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

$services = [
    'Apache 2.4 (MPM Event)' => checkService('apache2'),
    "PHP {$phpVer} FPM"       => file_exists("/run/php/php{$phpVer}-fpm.sock") || checkService("php{$phpVer}-fpm"),
    'MariaDB 11.8'           => checkService('mariadb'),
    'Redis Server'           => checkService('redis-server') || checkService('redis'),
    'Samba SMBv3'            => checkService('smbd'),
    'Cortafuegos UFW'        => checkService('ufw'),
    'Fail2ban Hardening'     => checkService('fail2ban'),
];

// Metricas RAM
$freeMem = 'N/D';
$totalMem = 'N/D';
$memPercent = 0;
if (is_readable('/proc/meminfo')) {
    $meminfo = @file_get_contents('/proc/meminfo') ?: '';
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mTot);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $mAvail);
    if (!empty($mTot[1]) && !empty($mAvail[1])) {
        $totMb = round((int)$mTot[1] / 1024);
        $availMb = round((int)$mAvail[1] / 1024);
        $usedMb = $totMb - $availMb;
        $totalMem = $totMb . ' MB';
        $memPercent = (int)round(($usedMb / $totMb) * 100);
    }
}

// Metricas Almacenamiento
$diskTotal = disk_total_space('/') ?: 1;
$diskFree  = disk_free_space('/') ?: 0;
$diskUsedPercent = (int)round((($diskTotal - $diskFree) / $diskTotal) * 100);
$diskFreeGb = round($diskFree / (1024 * 1024 * 1024), 1);
$diskTotalGb = round($diskTotal / (1024 * 1024 * 1024), 1);

// Uptime del Servidor
$serverUptime = 'N/D';
if (is_readable('/proc/uptime')) {
    $upRaw = @file_get_contents('/proc/uptime') ?: '0';
    $uptimeSec = (int)floatval(explode(' ', $upRaw)[0]);
    $days = floor($uptimeSec / 86400);
    $hours = floor(($uptimeSec % 86400) / 3600);
    $mins = floor(($uptimeSec % 3600) / 60);
    $serverUptime = ($days > 0 ? "{$days}d " : '') . "{$hours}h {$mins}m";
}

// Certificado SSL Comodin
$sslExpire = 'No configurado';
$sslValid = false;
$certFile = '/etc/ssl/localcerts/webserver.crt';
if (file_exists($certFile) && is_readable($certFile)) {
    $certData = openssl_x509_parse(@file_get_contents($certFile) ?: '');
    if ($certData && isset($certData['validTo_time_t'])) {
        $days = (int)round(($certData['validTo_time_t'] - time()) / 86400);
        $sslExpire = "{$days} días restantes";
        $sslValid = $days > 0;
    }
}

// 5. Escaneo Dinamico de Proyectos en /var/www
$projects = [];
$wwwDir = '/var/www';
if (is_dir($wwwDir)) {
    $items = scandir($wwwDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'html' || str_starts_with($item, '_')) {
                continue;
            }
            $projPath = "{$wwwDir}/{$item}";
            if (is_dir($projPath)) {
                $hasPublic = is_dir("{$projPath}/public_html");
                $hasGit = is_dir("{$projPath}/public_html/.git") || is_dir("{$projPath}/.git");
                $gitCommit = '';
                if ($hasGit) {
                    $gitDir = is_dir("{$projPath}/public_html/.git") ? "{$projPath}/public_html" : $projPath;
                    $lastCommit = @exec("git -C " . escapeshellarg($gitDir) . " log -1 --pretty=format:'%h - %s' 2>/dev/null");
                    $gitCommit = $lastCommit ?: 'Sin commits';
                }

                // Deteccion de Base de Datos MariaDB asociada
                $envFile = "{$projPath}/public_html/.env";
                $hasDb = false;
                $dbName = '';
                if (file_exists($envFile)) {
                    $hasDb = true;
                    if (is_readable($envFile)) {
                        $envTxt = @file_get_contents($envFile) ?: '';
                        if (preg_match('/DB_DATABASE=([^\r\n]+)/', $envTxt, $dbMatch)) {
                            $dbName = trim($dbMatch[1]);
                        }
                    }
                    if (empty($dbName)) {
                        $dbName = str_replace('-', '_', $item) . '_db';
                    }
                }

                $mtime = filemtime($hasPublic ? "{$projPath}/public_html" : $projPath);
                $projects[] = [
                    'name'      => $item,
                    'fqdn'      => "{$item}.{$baseDomain}",
                    'path'      => $projPath,
                    'hasPublic' => $hasPublic,
                    'hasGit'    => $hasGit,
                    'gitCommit' => $gitCommit,
                    'hasDb'     => $hasDb,
                    'dbName'    => $dbName,
                    'updated'   => date('Y-m-d H:i', $mtime),
                ];
            }
        }
    }
}

// 6. Deteccion de Respaldos y Snapshots
$backupDir = is_dir('/var/backups/srvctl') ? '/var/backups/srvctl' : (is_dir('/backup') ? '/backup' : null);
$snapshots = [];
$dbDumps   = [];

if ($backupDir) {
    $snapDir = "{$backupDir}/snapshots";
    if (is_dir($snapDir)) {
        $snapEntries = @scandir($snapDir) ?: [];
        foreach ($snapEntries as $entry) {
            if (str_starts_with($entry, 'daily.')) {
                $path = "{$snapDir}/{$entry}";
                $mtime = @filemtime($path) ?: 0;
                $snapshots[] = [
                    'name'    => $entry,
                    'isToday' => ($entry === 'daily.0'),
                    'date'    => date('Y-m-d H:i', $mtime),
                ];
            }
        }
        usort($snapshots, fn($a, $b) => strcmp($a['name'], $b['name']));
    }

    $dbDir = "{$backupDir}/database";
    if (is_dir($dbDir)) {
        $dbEntries = @scandir($dbDir) ?: [];
        foreach ($dbEntries as $entry) {
            if (str_ends_with($entry, '.sql.gz')) {
                $path = "{$dbDir}/{$entry}";
                $size = @filesize($path) ?: 0;
                $mtime = @filemtime($path) ?: 0;
                $sizeMb = round($size / (1024 * 1024), 2);
                $dbDumps[] = [
                    'name' => $entry,
                    'size' => $sizeMb > 0 ? "{$sizeMb} MB" : round($size / 1024, 1) . ' KB',
                    'date' => date('Y-m-d H:i', $mtime),
                    'time' => $mtime,
                ];
            }
        }
        usort($dbDumps, fn($a, $b) => $b['time'] <=> $a['time']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Administración &mdash; <?= htmlspecialchars($baseDomain) ?></title>
    <style>
        :root {
            --bg: #090d16;
            --card-bg: #111827;
            --card-border: #1f2937;
            --card-hover: #374151;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --primary: #38bdf8;
            --primary-hover: #0ea5e9;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --terminal-bg: #030712;
            --code-bg: #1f2937;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 24px 16px;
            line-height: 1.5;
        }
        .container { max-width: 1240px; margin: 0 auto; }

        /* Encabezado Principal */
        header {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .brand-title { font-size: 1.6rem; font-weight: 700; color: #fff; letter-spacing: -0.02em; }
        .brand-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 2px; }
        .header-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .badge {
            background: #030712;
            border: 1px solid var(--card-border);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--primary);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .badge.ok { color: var(--success); }
        .badge-admin {
            background: #064e3b;
            border: 1px solid #059669;
            color: #6ee7b7;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Botones */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: #030712;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--card-border);
            color: var(--text-main);
        }
        .btn-outline:hover { background: var(--card-hover); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: var(--success); color: #030712; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

        /* Alertas y Feedback */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-success { background: #064e3b; border: 1px solid #059669; color: #a7f3d0; }
        .alert-danger  { background: #450a0a; border: 1px solid #b91c1c; color: #fecaca; }
        .alert-warning { background: #451a03; border: 1px solid #d97706; color: #fde68a; }

        /* Tarjeta de Credenciales de Base de Datos */
        .cred-card {
            background: #0f2338;
            border: 1px solid #0284c7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);
        }
        .cred-title { font-size: 1.1rem; color: #7dd3fc; font-weight: 700; margin-bottom: 12px; }
        .cred-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            background: #071524;
            padding: 14px;
            border-radius: 6px;
            border: 1px solid #0c4a6e;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.9rem;
        }
        .cred-label { color: var(--text-muted); font-size: 0.8rem; display: block; }
        .cred-val { color: #f0f9ff; font-weight: 600; word-break: break-all; }
        .cred-actions { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }

        /* Titulos de Seccion */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 28px 0 16px 0;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-title {
            font-size: 1.25rem;
            color: #fff;
            border-left: 4px solid var(--primary);
            padding-left: 12px;
            font-weight: 700;
        }

        /* Grids y Tarjetas */
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 18px; }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 20px;
            transition: border-color 0.15s ease;
        }
        .card:hover { border-color: var(--card-hover); }
        .card-header-sm {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Filas de Servicios */
        .service-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #1f2937;
        }
        .service-row:last-child { border-bottom: none; }
        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .dot.ok { background: var(--success); box-shadow: 0 0 6px var(--success); }
        .dot.err { background: var(--danger); box-shadow: 0 0 6px var(--danger); }

        /* Barras de Metricas */
        .metric-block { margin-bottom: 14px; }
        .metric-header { display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 6px; }
        .metric-bar-bg { background: #030712; height: 8px; border-radius: 4px; overflow: hidden; }
        .metric-bar-fill { background: var(--primary); height: 100%; border-radius: 4px; }

        /* Proyectos */
        .proj-create-bar {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .input-text {
            background: #030712;
            border: 1px solid var(--card-border);
            color: #fff;
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 260px;
            flex: 1;
        }
        .input-text:focus { outline: none; border-color: var(--primary); }
        .project-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 20px;
            transition: all 0.15s ease;
        }
        .project-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .project-title { font-size: 1.2rem; font-weight: 700; color: #fff; }
        .project-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            word-break: break-all;
            display: inline-block;
            margin-top: 4px;
        }
        .project-link:hover { text-decoration: underline; }
        .project-footer {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tag {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            background: #1e293b;
            color: #cbd5e1;
        }
        .tag-db { background: #0c4a6e; color: #7dd3fc; border: 1px solid #0284c7; }
        .tag-no-db { background: #374151; color: #9ca3af; }

        /* Consola de Diagnostico / Terminal */
        .terminal-box {
            background: var(--terminal-bg);
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            max-height: 420px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.45;
            color: #e5e7eb;
            margin-top: 14px;
        }
        .term-ok   { color: #4ade80; font-weight: 700; }
        .term-fail { color: #f87171; font-weight: 700; }
        .term-info { color: #38bdf8; }

        /* Tablas de Respaldos */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-top: 8px;
        }
        .table-custom th {
            text-align: left;
            padding: 8px 10px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--card-border);
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        .table-custom td {
            padding: 10px;
            border-bottom: 1px solid #1f2937;
        }
        .table-custom tr:last-child td { border-bottom: none; }

        /* Cuadro de Instrucciones */
        .instruction-box {
            background: #022c22;
            border: 1px solid #065f46;
            border-radius: 10px;
            padding: 18px 22px;
            margin-top: 30px;
            color: #a7f3d0;
            font-size: 0.92rem;
            line-height: 1.6;
        }
        .instruction-box code {
            background: #064e3b;
            padding: 2px 6px;
            border-radius: 4px;
            color: #fff;
            font-family: ui-monospace, monospace;
        }

        /* Modal de Autenticacion */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay:target { display: flex; }
        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 28px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 14px;
            right: 18px;
            color: var(--text-muted);
            font-size: 1.4rem;
            text-decoration: none;
        }
        .modal-close:hover { color: #fff; }
    </style>
</head>
<body>
<div class="container">

    <!-- Encabezado y Navegacion Principal -->
    <header>
        <div>
            <div class="brand-title">Servidor Web Nativo &mdash; Debian 13</div>
            <div class="brand-subtitle">Centro de Control, Administración y Aprovisionamiento Ágil</div>
        </div>
        <div class="header-meta">
            <span class="badge">IP: <?= htmlspecialchars($serverIp) ?></span>
            <span class="badge">*.<?= htmlspecialchars($baseDomain) ?></span>
            <span class="badge <?= $sslValid ? 'ok' : '' ?>">SSL: <?= htmlspecialchars($sslExpire) ?></span>
            <span class="badge">Uptime: <?= htmlspecialchars($serverUptime) ?></span>

            <?php if ($isAdmin): ?>
                <span class="badge-admin">Admin: <?= htmlspecialchars($adminUser) ?></span>
                <a href="?action=logout" class="btn btn-outline btn-sm">Cerrar Sesión</a>
            <?php else: ?>
                <a href="#modal-login" class="btn btn-sm">Iniciar Sesión Administrativa</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Alertas Flash -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">
            <strong>✓ Éxito:</strong> <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger">
            <strong>✕ Error:</strong> <?= nl2br(htmlspecialchars($flashError)) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashWarning): ?>
        <div class="alert alert-warning">
            <strong>⚠ Aviso:</strong> <?= nl2br(htmlspecialchars($flashWarning)) ?>
        </div>
    <?php endif; ?>

    <!-- Tarjeta de Credenciales de Base de Datos Generadas -->
    <?php if ($dbResult): ?>
        <div class="cred-card">
            <div class="cred-title">✓ Base de Datos Aprovisionada &mdash; <?= htmlspecialchars($dbResult['project']) ?></div>
            <p style="color: #93c5fd; font-size: 0.88rem; margin-bottom: 12px;">
                Se ha creado la base de datos MariaDB y las credenciales han sido depositadas en <code>public_html/.env</code>.
            </p>
            <div class="cred-grid">
                <div><span class="cred-label">Host:</span> <span class="cred-val"><?= htmlspecialchars($dbResult['host']) ?></span></div>
                <div><span class="cred-label">Puerto:</span> <span class="cred-val"><?= htmlspecialchars($dbResult['port']) ?></span></div>
                <div><span class="cred-label">Base de Datos:</span> <span class="cred-val"><?= htmlspecialchars($dbResult['database']) ?></span></div>
                <div><span class="cred-label">Usuario:</span> <span class="cred-val"><?= htmlspecialchars($dbResult['user']) ?></span></div>
                <div style="grid-column: 1 / -1;">
                    <span class="cred-label">Contraseña:</span>
                    <span class="cred-val" id="raw-pass"><?= htmlspecialchars($dbResult['pass']) ?></span>
                </div>
            </div>
            <div class="cred-actions">
                <a href="https://<?= htmlspecialchars($dbFqdn) ?>" target="_blank" class="btn btn-sm">
                    Abrir phpMyAdmin &rarr;
                </a>
                <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('raw-pass').innerText); alert('Contraseña copiada al portapapeles');">
                    Copiar Contraseña
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Seccion 1: Telemetria y Servicios -->
    <div class="section-header">
        <h2 class="section-title">Salud del Sistema y Telemetría</h2>
    </div>

    <div class="grid-3">
        <!-- Servicios del Sistema -->
        <div class="card">
            <div class="card-header-sm">
                <span>Servicios de la Pila</span>
                <span>Debian 13</span>
            </div>
            <?php foreach ($services as $srvName => $isOk): ?>
                <div class="service-row">
                    <span style="font-size: 0.9rem;">
                        <span class="dot <?= $isOk ? 'ok' : 'err' ?>"></span><?= htmlspecialchars($srvName) ?>
                    </span>
                    <span style="font-size: 0.82rem; font-weight: 600; color: <?= $isOk ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $isOk ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recursos de Hardware -->
        <div class="card">
            <div class="card-header-sm">
                <span>Recursos del Host</span>
                <span>En Tiempo Real</span>
            </div>

            <div class="metric-block">
                <div class="metric-header">
                    <span>Memoria RAM</span>
                    <span style="color: var(--primary); font-weight: 600;"><?= $memPercent ?>% (<?= $totalMem ?>)</span>
                </div>
                <div class="metric-bar-bg">
                    <div class="metric-bar-fill" style="width: <?= $memPercent ?>%;"></div>
                </div>
            </div>

            <div class="metric-block">
                <div class="metric-header">
                    <span>Almacenamiento (/)</span>
                    <span style="color: var(--primary); font-weight: 600;"><?= $diskUsedPercent ?>% (<?= $diskFreeGb ?> GB libres)</span>
                </div>
                <div class="metric-bar-bg">
                    <div class="metric-bar-fill" style="width: <?= $diskUsedPercent ?>%;"></div>
                </div>
            </div>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--card-border); font-size: 0.85rem; color: var(--text-muted);">
                Tiempo activo del servidor: <strong style="color: #fff;"><?= htmlspecialchars($serverUptime) ?></strong>
            </div>
        </div>

        <!-- Acceso Rapido y Descargas -->
        <div class="card">
            <div class="card-header-sm">
                <span>Conectividad y Red</span>
                <span>Acceso Rápido</span>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 12px;">
                Recurso Maestro Samba (SMBv3):<br>
                <code style="color: var(--primary); font-family: ui-monospace, monospace; font-size: 0.95rem;">\\<?= htmlspecialchars($serverIp) ?>\proyectos</code>
            </p>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a href="https://<?= htmlspecialchars($dbFqdn) ?>" target="_blank" class="btn btn-sm">
                    Abrir phpMyAdmin (<?= htmlspecialchars($dbSub) ?>) &rarr;
                </a>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 4px;">
                    <a href="/downloads/rootCA.crt" download class="btn btn-outline btn-sm">Certificado SSL</a>
                    <a href="/downloads/configurar-cliente.bat" download class="btn btn-outline btn-sm">Cliente (.bat)</a>
                    <a href="/downloads/configurar-desarrollador.bat" download class="btn btn-outline btn-sm">Dev (.bat)</a>
                    <a href="/downloads/configurar-desarrollador.ps1" download class="btn btn-outline btn-sm">Dev (.ps1)</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Seccion 2: Proyectos Web y Subdominios Dinamicos -->
    <div class="section-header" id="proyectos">
        <h2 class="section-title">Proyectos Web y Subdominios Dinámicos</h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Ruteo automático vía <code>mod_vhost_alias</code> hacia <code>/var/www/&lt;proyecto&gt;/public_html/</code>
        </span>
    </div>

    <!-- Barra de Creacion de Proyectos (Solo Administradores) -->
    <?php if ($isAdmin): ?>
        <form method="POST" action="?#proyectos" class="proj-create-bar">
            <input type="hidden" name="action" value="project_create">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <span style="font-weight: 600; font-size: 0.92rem; color: #fff;">Nuevo Proyecto Web:</span>
            <input type="text" name="project_name" class="input-text" placeholder="nombre-del-proyecto (ej. blog, api, tienda)" pattern="^[a-z0-9]([a-z0-9-]*[a-z0-9])?$" required title="Solo minúsculas, números y guiones">
            <button type="submit" class="btn">
                + Crear Proyecto
            </button>
        </form>
    <?php endif; ?>

    <!-- Grid de Proyectos -->
    <div class="grid-3">
        <?php if (empty($projects)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 36px;">
                <p style="color: var(--text-muted); font-size: 1rem;">No hay proyectos creados aún en <code>/var/www/</code>.</p>
                <?php if ($isAdmin): ?>
                    <p style="margin-top: 10px; color: var(--primary);">Usa el formulario superior para crear tu primer entorno.</p>
                <?php else: ?>
                    <p style="margin-top: 10px; color: var(--text-muted);">Inicia sesión como administrador para crear proyectos directamente desde la web.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $proj): ?>
                <div class="project-card">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div class="project-title"><?= htmlspecialchars($proj['name']) ?></div>
                            <?php if ($proj['hasDb']): ?>
                                <span class="tag tag-db" title="Base de datos activa: <?= htmlspecialchars($proj['dbName']) ?>">
                                    BD: <?= htmlspecialchars($proj['dbName']) ?>
                                </span>
                            <?php else: ?>
                                <span class="tag tag-no-db">Sin BD</span>
                            <?php endif; ?>
                        </div>

                        <a href="https://<?= htmlspecialchars($proj['fqdn']) ?>" target="_blank" class="project-link">
                            https://<?= htmlspecialchars($proj['fqdn']) ?> &rarr;
                        </a>

                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 10px;">
                            Modificado: <?= htmlspecialchars($proj['updated']) ?>
                        </div>

                        <?php if ($proj['hasGit']): ?>
                            <div style="margin-top: 10px; font-size: 0.78rem; background: #070d17; padding: 6px 8px; border-radius: 4px; color: #94a3b8; font-family: ui-monospace, monospace;">
                                Git: <?= htmlspecialchars($proj['gitCommit']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="project-footer">
                        <a href="https://<?= htmlspecialchars($proj['fqdn']) ?>" target="_blank" class="btn btn-outline btn-sm">
                            Visitar Sitio
                        </a>

                        <?php if ($isAdmin): ?>
                            <form method="POST" action="?#proyectos" style="display: inline;">
                                <input type="hidden" name="action" value="project_db">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="project_name" value="<?= htmlspecialchars($proj['name']) ?>">
                                <?php if ($proj['hasDb']): ?>
                                    <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('¿Regenerar credenciales para la base de datos de <?= htmlspecialchars($proj['name']) ?>?');" title="Regenera usuario y contraseña en .env">
                                        ↻ Regenerar BD
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success">
                                        + Crear BD
                                    </button>
                                <?php endif; ?>
                            </form>

                            <?php if ($proj['name'] !== 'prod' && $proj['name'] !== 'stg'): ?>
                                <form method="POST" action="?#proyectos" style="display: inline;" onsubmit="return confirm('¿Estás seguro de ELIMINAR el proyecto <?= htmlspecialchars($proj['name']) ?> y su base de datos? Esta acción es irreversible.');">
                                    <input type="hidden" name="action" value="project_delete">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="project_name" value="<?= htmlspecialchars($proj['name']) ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: rgba(239,68,68,0.4);" title="Eliminar proyecto y base de datos">
                                        ✕ Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Seccion 3: Diagnostico del Servidor (Verify) -->
    <div class="section-header" id="diagnostico">
        <h2 class="section-title">Diagnóstico del Servidor (40 Puntos)</h2>
        <?php if ($isAdmin): ?>
            <form method="POST" action="?#diagnostico" style="display: inline;">
                <input type="hidden" name="action" value="verify">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn">
                    ▶ Ejecutar Diagnóstico (verify)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Verificación integral de servicios activos, puertos UFW, sockets PHP-FPM, certificados SSL comodín SAN, redirecciones HTTP/HTTPS, conectividad Samba y almacenamiento phpMyAdmin.
            </p>
            <?php if ($verifyResult): ?>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge ok"><?= (int)$verifyResult['pass'] ?> OK</span>
                    <?php if ((int)$verifyResult['fail'] > 0): ?>
                        <span class="badge" style="color: var(--danger); border-color: var(--danger);"><?= (int)$verifyResult['fail'] ?> FALLOS</span>
                    <?php endif; ?>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($verifyResult['timestamp']) ?></span>
                    <a href="?action=clear_verify" class="btn btn-outline btn-sm">Limpiar</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($verifyResult): ?>
            <div class="terminal-box"><?php
                $lines = explode("\n", (string)$verifyResult['output']);
                foreach ($lines as $line) {
                    $esc = htmlspecialchars($line);
                    if (str_contains($line, '[ OK ]')) {
                        echo str_replace('[ OK ]', '<span class="term-ok">[ OK ]</span>', $esc) . "\n";
                    } elseif (str_contains($line, '[FAIL]')) {
                        echo str_replace('[FAIL]', '<span class="term-fail">[FAIL]</span>', $esc) . "\n";
                    } elseif (str_starts_with($line, '===') || str_starts_with($line, '---') || str_starts_with($line, 'TOTAL:')) {
                        echo '<span class="term-info">' . $esc . "</span>\n";
                    } else {
                        echo $esc . "\n";
                    }
                }
            ?></div>
        <?php else: ?>
            <div style="background: #030712; border: 1px dashed var(--card-border); border-radius: 8px; padding: 24px; text-align: center; margin-top: 14px;">
                <p style="color: var(--text-muted); font-size: 0.9rem;">
                    <?php if ($isAdmin): ?>
                        Presiona <strong>"Ejecutar Diagnóstico (verify)"</strong> para correr la suite de verificación automatizada.
                    <?php else: ?>
                        Inicia sesión como administrador para ejecutar y visualizar el diagnóstico completo del servidor.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Seccion 4: Respaldos y Snapshots -->
    <div class="section-header" id="respaldos">
        <h2 class="section-title">Respaldos del Sistema (Snapshots y MariaDB)</h2>
        <?php if ($isAdmin): ?>
            <form method="POST" action="?#respaldos" style="display: inline;">
                <input type="hidden" name="action" value="backup_run">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-outline">
                    💾 Crear Respaldo Ahora
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="grid-2">
        <!-- Snapshots Web -->
        <div class="card">
            <div class="card-header-sm">
                <span>Snapshots Web (7 Días)</span>
                <span>/var/backups/srvctl/snapshots</span>
            </div>
            <?php if (empty($snapshots)): ?>
                <p style="font-size: 0.88rem; color: var(--text-muted); padding: 12px 0;">No se encontraron snapshots web generados aún.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Snapshot</th>
                            <th>Estado</th>
                            <th>Última Actualización</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshots as $snap): ?>
                            <tr>
                                <td style="font-family: ui-monospace, monospace; font-weight: 600; color: #fff;">
                                    <?= htmlspecialchars($snap['name']) ?>
                                </td>
                                <td>
                                    <?php if ($snap['isToday']): ?>
                                        <span class="tag tag-db">Más Reciente</span>
                                    <?php else: ?>
                                        <span class="tag">Rotativo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($snap['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Volcados de Base de Datos -->
        <div class="card">
            <div class="card-header-sm">
                <span>Volcados MariaDB (.sql.gz)</span>
                <span>/var/backups/srvctl/database</span>
            </div>
            <?php if (empty($dbDumps)): ?>
                <p style="font-size: 0.88rem; color: var(--text-muted); padding: 12px 0;">No se encontraron volcados SQL de bases de datos aún.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Archivo de Volcado</th>
                            <th>Tamaño</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($dbDumps, 0, 7) as $dump): ?>
                            <tr>
                                <td style="font-family: ui-monospace, monospace; font-size: 0.82rem; color: #fff;">
                                    <?= htmlspecialchars($dump['name']) ?>
                                </td>
                                <td><span class="tag"><?= htmlspecialchars($dump['size']) ?></span></td>
                                <td style="color: var(--text-muted); font-size: 0.82rem;"><?= htmlspecialchars($dump['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Guia de Uso Rapido -->
    <div class="instruction-box">
        <strong style="color: #fff; font-size: 1rem;">¿Cómo conectar tu entorno y desarrollar?</strong><br>
        1. <strong>Instala el Certificado SSL:</strong> Descarga <code>rootCA.crt</code> o ejecuta <code>configurar-cliente.bat</code> para confiar en el comodín SSL sin advertencias del navegador.<br>
        2. <strong>Mapea el Recurso Samba:</strong> Ejecuta <code>configurar-desarrollador.bat</code> (o mapea <code>\\<?= htmlspecialchars($serverIp) ?>\proyectos</code> como unidad <code>Z:\</code>).<br>
        3. <strong>Desarrolla al instante:</strong> Crea una carpeta en la unidad compartida con su subcarpeta <code>public_html</code> (o usa el botón <em>+ Crear Proyecto</em> en este panel). Tu proyecto estará disponible inmediatamente en <code>https://&lt;nombre&gt;.<?= htmlspecialchars($baseDomain) ?></code>.
    </div>

</div>

<!-- Modal de Inicio de Sesion Administrativa -->
<div id="modal-login" class="modal-overlay">
    <div class="modal-card">
        <a href="#" class="modal-close">&times;</a>
        <h3 style="font-size: 1.3rem; margin-bottom: 6px; color: #fff;">Iniciar Sesión</h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px;">
            Ingresa las credenciales maestras configuradas en el servidor.
        </p>

        <form method="POST" action="?">
            <input type="hidden" name="action" value="login">
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Usuario Administrador</label>
                <input type="text" name="username" class="input-text" style="width: 100%;" required autofocus placeholder="webadmin">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Contraseña Maestra</label>
                <input type="password" name="password" class="input-text" style="width: 100%;" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn" style="width: 100%; justify-content: center; padding: 10px;">
                Entrar al Panel de Control
            </button>
        </form>
    </div>
</div>

</body>
</html>
