<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$pages = ['overview', 'projects', 'database', 'services', 'security', 'ssl', 'backups', 'diagnostics', 'downloads', 'settings'];

$requested = (string)($_GET['page'] ?? '');
$page = in_array($requested, $pages, true) ? $requested : (panel_is_admin() ? 'overview' : 'login');

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    panel_logout();
    panel_redirect('login');
}

if (isset($_GET['action']) && $_GET['action'] === 'clear_verify') {
    unset($_SESSION['verify_result']);
    panel_redirect('diagnostics');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $back = (string)($_POST['_page'] ?? 'overview');
    if (!in_array($back, $pages, true)) {
        $back = 'overview';
    }

    if ($action === 'login') {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (panel_login($user, $pass, $CONFIG)) {
            panel_flash('success', 'Sesión iniciada correctamente.');
            panel_redirect('overview');
        }
        panel_flash('error', 'Credenciales administrativas inválidas.');
        panel_redirect('login');
    }

    if (!panel_is_admin()) {
        panel_flash('error', 'Debes iniciar sesión como administrador.');
        panel_redirect('login');
    }
    panel_csrf_check();

    if ($action === 'project_create') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            panel_flash('error', 'Nombre de proyecto inválido. Usa solo minúsculas, números y guiones.');
        } elseif (is_dir('/var/www/' . $name)) {
            panel_flash('error', "El proyecto '{$name}' ya existe.");
        } else {
            [$code, $output] = panel_srvctl(['project', 'create', $name], 30);
            $code === 0
                ? panel_flash('success', "Proyecto '{$name}' creado. Disponible en https://{$name}.{$CONFIG['BASE_DOMAIN']}")
                : panel_flash('error', "Error al crear el proyecto: " . $output);
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
                $envFile = '/var/www/' . $name . '/public_html/.env';
                $db = ['project' => $name, 'host' => '127.0.0.1', 'port' => '3306', 'database' => '', 'user' => '', 'pass' => ''];
                if (is_readable($envFile)) {
                    $env = (string)@file_get_contents($envFile);
                    foreach (['DB_DATABASE' => 'database', 'DB_USERNAME' => 'user', 'DB_PASSWORD' => 'pass', 'DB_HOST' => 'host', 'DB_PORT' => 'port'] as $key => $field) {
                        if (preg_match('/^' . $key . '=(.*)$/m', $env, $m)) {
                            $db[$field] = trim($m[1]);
                        }
                    }
                }
                $_SESSION['db_result'] = $db;
                panel_flash('success', "Base de datos aprovisionada para '{$name}'.");
            } else {
                panel_flash('error', 'Error al aprovisionar la base de datos: ' . $output);
            }
        }
        panel_redirect('projects');
    }

    if ($action === 'project_delete') {
        $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
        if (in_array($name, [$CONFIG['PROD_SUB'], $CONFIG['STG_SUB'], 'html'], true) || str_starts_with($name, '_')) {
            panel_flash('error', "No se permite eliminar proyectos base del sistema ({$name}).");
        } elseif (!is_dir('/var/www/' . $name)) {
            panel_flash('error', "El proyecto '{$name}' no existe.");
        } else {
            [$code, $output] = panel_srvctl(['project', 'delete', $name], 30);
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

$viewFile = $PANEL_ROOT . '/views/' . $page . '.php';
if (!is_file($viewFile)) {
    $page = 'overview';
    $viewFile = $PANEL_ROOT . '/views/overview.php';
}

$services = panel_services();
$activeServices = count(array_filter($services, static fn($s) => $s['active']));

require $PANEL_ROOT . '/partials/head.php';
require $PANEL_ROOT . '/partials/sidebar.php';
?>
<main class="content">
<?php require $PANEL_ROOT . '/partials/topbar.php'; ?>
<?php require $PANEL_ROOT . '/partials/flash.php'; ?>
<?php require $viewFile; ?>
</main>
<?php require $PANEL_ROOT . '/partials/foot.php'; ?>
