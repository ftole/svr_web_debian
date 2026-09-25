<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_is_admin(): bool
{
    return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function panel_start_session(array $conf): void
{
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['admin_user'] = (string)($conf['ADMIN_USER'] ?? 'admin');
    $_SESSION['last_activity'] = time();
    $_SESSION['created_at'] = time();
}

function panel_login(string $user, string $pass, array $conf): bool
{
    if (!hash_equals((string)($conf['ADMIN_USER'] ?? ''), $user)) {
        return false;
    }

    $hash = (string)($conf['PANEL_PASS_HASH'] ?? '');
    if ($hash !== '' && password_verify($pass, $hash)) {
        panel_start_session($conf);
        return true;
    }

    $plain = (string)($conf['ADMIN_PASS'] ?? '');
    if ($plain !== '' && hash_equals($plain, $pass)) {
        panel_start_session($conf);
        return true;
    }

    return false;
}

function panel_touch(): void
{
    $_SESSION['last_activity'] = time();
}

function panel_session_expired(int $ttl = 1800): bool
{
    if (!panel_is_admin()) {
        return false;
    }
    $last = (int)($_SESSION['last_activity'] ?? 0);
    return $last > 0 && (time() - $last) > $ttl;
}

function panel_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function panel_csrf_valid(?string $token = null): bool
{
    $token = $token ?? (string)($_POST['csrf_token'] ?? '');
    return $token !== '' && hash_equals(panel_csrf_token(), $token);
}

function panel_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function panel_throttle_path(string $ip): string
{
    return sys_get_temp_dir() . '/.srvctl-login-' . hash('sha256', $ip) . '.json';
}

function panel_login_blocked(string $ip, int $max = 5, int $window = 900): bool
{
    $path = panel_throttle_path($ip);
    if (!is_file($path)) {
        return false;
    }
    $data = json_decode((string)@file_get_contents($path), true);
    if (!is_array($data)) {
        return false;
    }
    if ((time() - (int)($data['first'] ?? 0)) > $window) {
        @unlink($path);
        return false;
    }
    return (int)($data['count'] ?? 0) >= $max;
}

function panel_login_record_fail(string $ip, int $window = 900): void
{
    $path = panel_throttle_path($ip);
    $data = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (!is_array($data) || (time() - (int)($data['first'] ?? 0)) > $window) {
        $data = ['first' => time(), 'count' => 0];
    }
    $data['count'] = (int)($data['count'] ?? 0) + 1;
    @file_put_contents($path, (string)json_encode($data), LOCK_EX);
}

function panel_login_clear(string $ip): void
{
    @unlink(panel_throttle_path($ip));
}
