<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function panel_url(string $page = 'overview', array $params = []): string
{
    return '/';
}

function panel_render_section(string $page, array $CONFIG, string $PANEL_ROOT): string
{
    $services = panel_services();
    $flashes = [];
    $serviceResult = $_SESSION['service_result'] ?? null;
    $securityScan = $_SESSION['security_result'] ?? null;
    $sslResult = $_SESSION['ssl_result'] ?? null;
    $sslVerify = $_SESSION['ssl_verify_result'] ?? null;
    $dbOptimizeResult = $_SESSION['db_result'] ?? null;
    $backupRestoreResult = $_SESSION['backup_result'] ?? null;
    $backupVerify = $_SESSION['backup_verify_result'] ?? null;
    $projectVerifyResult = $_SESSION['project_result'] ?? null;
    $verify = $_SESSION['verify_result'] ?? null;
    $updateResult = $_SESSION['update_result'] ?? null;
    $pendingUpdates = $_SESSION['pending_updates'] ?? [];
    $updateHistory = $_SESSION['update_history'] ?? [];
    $lastAptCheck = $_SESSION['last_apt_check'] ?? 'Al día';
    $liveLogs = panel_live_logs();
    ob_start();
    require $PANEL_ROOT . '/views/' . $page . '.php';
    return (string)ob_get_clean();
}

function panel_pages(): array
{
    return ['overview', 'projects', 'database', 'services', 'security', 'ssl', 'backups', 'diagnostics', 'downloads', 'settings'];
}

function panel_page_titles(): array
{
    return [
        'overview'    => 'Resumen',
        'projects'    => 'Proyectos',
        'database'    => 'Bases de datos',
        'services'    => 'Servicios',
        'security'    => 'Seguridad',
        'ssl'         => 'Certificados',
        'backups'     => 'Respaldos',
        'diagnostics' => 'Diagnóstico',
        'downloads'   => 'Descargas',
        'settings'    => 'Configuración',
    ];
}

function panel_format_bytes($bytes): string
{
    $bytes = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)(int)$bytes : number_format($bytes, 1)) . ' ' . $units[$i];
}

function panel_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function panel_take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function panel_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['csrf'];
}

function panel_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(panel_csrf_token()) . '">';
}

function panel_csrf_check(): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(panel_csrf_token(), $sent)) {
        panel_flash('error', 'Token de seguridad inválido o expirado. Operación cancelada.');
        panel_redirect((string)($_POST['_page'] ?? 'overview'));
    }
}

function panel_redirect(string $page, array $params = []): void
{
    $_SESSION['page'] = $page;
    header('Location: /');
    exit;
}

function panel_run(string $command, int $timeout = 10): array
{
    $out = [];
    $code = 0;
    if (function_exists('proc_open')) {
        $wrappedCmd = 'setsid ' . $command;
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($wrappedCmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $buffer = '';
            $start = time();
            $timedOut = false;
            while (true) {
                $buffer .= (string)stream_get_contents($pipes[1]);
                $buffer .= (string)stream_get_contents($pipes[2]);
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    $code = (int)$status['exitcode'];
                    break;
                }
                if ($timeout > 0 && (time() - $start) > $timeout) {
                    $pgid = (int)$status['pid'];
                    if ($pgid > 0) {
                        @shell_exec('kill -9 -' . $pgid . ' 2>/dev/null');
                    }
                    proc_terminate($proc, 9);
                    $timedOut = true;
                    $code = 124;
                    break;
                }
                usleep(100000);
            }
            $buffer .= (string)stream_get_contents($pipes[1]);
            $buffer .= (string)stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            return [$code, trim($buffer)];
        }
    }
    if (function_exists('exec')) {
        exec($command . ' 2>&1', $out, $code);
    }
    return [$code, implode("\n", $out)];
}

function panel_run_async(string $command): string
{
    $taskId = uniqid('task_');
    $logFile = '/var/www/_dashboard/tasks/' . $taskId . '.log';
    
    // Asegurar que el directorio de tareas existe
    if (!is_dir('/var/www/_dashboard/tasks')) {
        @mkdir('/var/www/_dashboard/tasks', 0755, true);
    }
    
    $fullCommand = 'nohup ' . $command . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
    exec($fullCommand);
    
    return $taskId;
}

function panel_read_env(string $project): string
{
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $project)) {
        return '';
    }
    $path = '/var/www/' . $project . '/public_html/.env';
    if (is_file($path) && is_readable($path)) {
        return (string)file_get_contents($path);
    }
    return '';
}

function panel_write_env(string $project, string $content): bool
{
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $project)) {
        return false;
    }
    $path = '/var/www/' . $project . '/public_html/.env';
    return file_put_contents($path, $content) !== false;
}

