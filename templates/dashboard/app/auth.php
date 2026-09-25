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

function panel_login(string $user, string $pass, array $conf): bool
{
    $okUser = hash_equals((string)$conf['ADMIN_USER'], $user);
    $okPass = hash_equals((string)$conf['ADMIN_PASS'], $pass);
    if ($okUser && $okPass) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['admin_user'] = (string)$conf['ADMIN_USER'];
        return true;
    }
    return false;
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
