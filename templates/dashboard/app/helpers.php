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
    $params = array_merge(['page' => $page], $params);
    return '?' . http_build_query($params);
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
    header('Location: ' . panel_url($page, $params));
    exit;
}

function panel_run(string $command, int $timeout = 0): array
{
    $out = [];
    $code = 0;
    if ($timeout > 0 && function_exists('proc_open')) {
        $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($proc)) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $buffer = '';
            $start = time();
            while (true) {
                $buffer .= (string)stream_get_contents($pipes[1]);
                $buffer .= (string)stream_get_contents($pipes[2]);
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    $code = (int)$status['exitcode'];
                    break;
                }
                if ((time() - $start) > $timeout) {
                    proc_terminate($proc, 9);
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
    @exec($command . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}
