<?php
declare(strict_types=1);
define('PANEL', true);
define('PANEL_NAME', 'Panel de Control');
define('PANEL_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$PANEL_ROOT = dirname(__DIR__);

require_once $PANEL_ROOT . '/app/helpers.php';
require_once $PANEL_ROOT . '/app/config.php';
require_once $PANEL_ROOT . '/app/srvctl.php';
require_once $PANEL_ROOT . '/app/data.php';
require_once $PANEL_ROOT . '/app/metrics.php';
require_once $PANEL_ROOT . '/app/auth.php';

$CONFIG = panel_load_config();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_name('SRVCTL_PANEL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
