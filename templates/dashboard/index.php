<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$pages = panel_pages();
$titles = panel_page_titles();

if (($_GET['action'] ?? '') === 'metrics') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    panel_touch();
    $payload = [
        't'    => time(),
        'fast' => panel_metrics_fast(),
        'slow' => panel_metrics_slow($CONFIG),
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['action'] ?? '') === 'section') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    $name = (string)($_GET['name'] ?? 'overview');
    if (!in_array($name, $pages, true)) {
        $name = 'overview';
    }
    panel_touch();
    $_SESSION['page'] = $name;
    echo json_encode([
        'page'  => $name,
        'title' => $titles[$name] ?? 'Resumen',
        'html'  => panel_render_section($name, $CONFIG, $PANEL_ROOT),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (panel_is_admin() && panel_session_expired()) {
    panel_logout();
    panel_flash('warning', 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.');
    panel_redirect('login');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $back = (string)($_POST['_page'] ?? 'overview');
    if (!in_array($back, $pages, true)) {
        $back = 'overview';
    }

    if ($action === 'logout') {
        panel_logout();
        panel_redirect('login');
    }

    if ($action === 'login') {
        $ip = panel_client_ip();
        if (!panel_csrf_valid()) {
            panel_flash('error', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
            panel_redirect('login');
        }
        if (panel_login_blocked($ip)) {
            panel_flash('error', 'Demasiados intentos fallidos. Espera unos minutos antes de reintentar.');
            panel_redirect('login');
        }
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (panel_login($user, $pass, $CONFIG)) {
            panel_login_clear($ip);
            panel_flash('success', 'Sesión iniciada correctamente.');
            panel_redirect('overview');
        }
        panel_login_record_fail($ip);
        usleep(300000);
        panel_flash('error', 'Credenciales administrativas inválidas.');
        panel_redirect('login');
    }

    if (!panel_is_admin()) {
        panel_flash('error', 'Debes iniciar sesión como administrador.');
        panel_redirect('login');
    }
    panel_csrf_check();
    panel_touch();

    if ($action === 'clear_verify') {
        unset($_SESSION['verify_result']);
        panel_redirect('diagnostics');
    }

    if ($action === 'project_create') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        $createDb = (($_POST['create_db'] ?? '0') === '1');
        $customDb = (($_POST['custom_db'] ?? '0') === '1');
        $dbName = strtolower(trim((string)($_POST['db_name'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            panel_flash('error', 'Nombre de proyecto inválido. Usa solo minúsculas, números y guiones.');
        } elseif (is_dir('/var/www/' . $name)) {
            panel_flash('error', "El proyecto '{$name}' ya existe.");
        } else {
            [$code, $output] = panel_srvctl(['project', 'create', $name], 30);
            if ($code !== 0) {
                panel_flash('error', 'Error al crear el proyecto: ' . $output);
            } else {
                panel_flash('success', "Proyecto '{$name}' creado. Disponible en https://{$name}.{$CONFIG['BASE_DOMAIN']}");
                if ($createDb) {
                    $args = ['project', 'db', $name];
                    if ($customDb && preg_match('/^[a-z0-9_]{1,64}$/', $dbName)) {
                        $args[] = $dbName;
                    }
                    [$dbCode, $dbOut] = panel_srvctl($args, 30);
                    if ($dbCode === 0) {
                        $_SESSION['db_result'] = panel_read_env_db($name);
                        panel_flash('success', "Base de datos aprovisionada para '{$name}'.");
                    } else {
                        panel_flash('warning', 'Proyecto creado, pero falló el aprovisionamiento de la base de datos: ' . $dbOut);
                    }
                }
            }
        }
        panel_redirect('projects');
    }

    if ($action === 'project_db') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            panel_flash('error', 'Nombre de proyecto inválido para base de datos.');
        } else {
            [$code, $output] = panel_srvctl(['project', 'db', $name], 30);
            if ($code === 0) {
                $_SESSION['db_result'] = panel_read_env_db($name);
                panel_flash('success', "Base de datos aprovisionada para '{$name}'.");
            } else {
                panel_flash('error', 'Error al aprovisionar la base de datos: ' . $output);
            }
        }
        panel_redirect('projects');
    }

    if ($action === 'project_delete') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        $force = (($_POST['force'] ?? '0') === '1');
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            panel_flash('error', 'Nombre de proyecto inválido.');
        } elseif (!is_dir('/var/www/' . $name)) {
            panel_flash('error', "El proyecto '{$name}' no existe.");
        } else {
            $args = ['project', 'delete', $name];
            if ($force) {
                $args[] = '--force';
            }
            [$code, $output] = panel_srvctl($args, 30);
            $code === 0
                ? panel_flash('success', "Proyecto '{$name}' eliminado.")
                : panel_flash('error', 'Error al eliminar el proyecto: ' . $output);
        }
        panel_redirect('projects');
    }

    if ($action === 'backup_run') {
        [$code, $output] = panel_srvctl(['backup', 'run'], 120);
        $code === 0
            ? panel_flash('success', 'Respaldo completado correctamente.')
            : panel_flash('error', 'Error al ejecutar el respaldo: ' . $output);
        panel_redirect('backups');
    }

    if ($action === 'backup_rollback') {
        $snap = (string)($_POST['snapshot'] ?? 'daily.0');
        if (!preg_match('/^daily\.[0-6]$/', $snap)) {
            panel_flash('error', 'Snapshot inválido.');
        } else {
            [$code, $output] = panel_srvctl(['backup', 'rollback', $snap], 120);
            $code === 0
                ? panel_flash('success', "Archivos restaurados desde {$snap}.")
                : panel_flash('error', 'Error al restaurar: ' . $output);
        }
        panel_redirect('backups');
    }

    if ($action === 'verify') {
        [$code, $output] = panel_srvctl(['verify'], 120);
        $_SESSION['verify_result'] = [
            'output'    => $output,
            'code'      => $code,
            'pass'      => substr_count($output, '[ OK ]'),
            'fail'      => substr_count($output, '[FAIL]'),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $fail = (int)$_SESSION['verify_result']['fail'];
        $fail === 0
            ? panel_flash('success', 'Diagnóstico completado: todas las comprobaciones pasaron.')
            : panel_flash('warning', "Diagnóstico finalizado con {$fail} fallo(s).");
        panel_redirect('diagnostics');
    }

    panel_redirect($back);
}

$flashes = panel_take_flashes();

if (!panel_is_admin()) {
    $loginStatus = panel_services();
    $activeCount = count(array_filter($loginStatus, static fn($s) => $s['active']));
    $totalCount = count($loginStatus);
    require $PANEL_ROOT . '/views/login.php';
    exit;
}

$page = (string)($_SESSION['page'] ?? 'overview');
if (!in_array($page, $pages, true)) {
    $page = 'overview';
}

$services = panel_services();
$activeServices = count(array_filter($services, static fn($s) => $s['active']));

panel_touch();

require $PANEL_ROOT . '/partials/head.php';
require $PANEL_ROOT . '/partials/sidebar.php';
?>
<main class="content">
<?php require $PANEL_ROOT . '/partials/topbar.php'; ?>
<?php require $PANEL_ROOT . '/partials/flash.php'; ?>
<div id="section">
<?php require $PANEL_ROOT . '/views/' . $page . '.php'; ?>
</div>
</main>
<?php require $PANEL_ROOT . '/partials/foot.php'; ?>
