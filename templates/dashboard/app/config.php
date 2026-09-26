<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_load_config(): array
{
    $files = ['/etc/srvctl-panel.conf', '/etc/srvctl.conf', '/etc/asistente_servidor.conf'];
    $conf = [];
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $conf[trim($key)] = trim($value, " '\"\t\n\r\0\x0B");
        }
        if ($conf !== []) {
            break;
        }
    }

    $defaults = [
        'SERVER_IP'      => (string)($_SERVER['SERVER_ADDR'] ?? '127.0.0.1'),
        'BASE_DOMAIN'    => 'empresa.local',
        'PROD_SUB'       => 'prod',
        'STG_SUB'        => 'stg',
        'DB_SUB'         => 'webdev',
        'ADMIN_USER'     => 'webadmin',
        'ADMIN_PASS'     => '',
        'PANEL_PASS_HASH' => '',
    ];
    foreach ($defaults as $key => $value) {
        if (!isset($conf[$key]) || $conf[$key] === '') {
            $conf[$key] = $value;
        }
    }

    // Aplicar sobreescrituras guardadas en sesion si existen
    if (!empty($_SESSION['config_overrides']) && is_array($_SESSION['config_overrides'])) {
        foreach ($_SESSION['config_overrides'] as $key => $value) {
            $conf[$key] = $value;
        }
    }

    $conf['PROD_FQDN'] = $conf['PROD_SUB'] . '.' . $conf['BASE_DOMAIN'];
    $conf['STG_FQDN']  = $conf['STG_SUB'] . '.' . $conf['BASE_DOMAIN'];
    $conf['DB_FQDN']   = $conf['DB_SUB'] . '.' . $conf['BASE_DOMAIN'];

    return $conf;
}

function panel_save_config(array $params): bool
{
    if (!isset($_SESSION['config_overrides']) || !is_array($_SESSION['config_overrides'])) {
        $_SESSION['config_overrides'] = [];
    }
    foreach ($params as $k => $v) {
        $_SESSION['config_overrides'][$k] = $v;
    }

    $files = ['/etc/srvctl.conf', '/etc/srvctl-panel.conf'];
    foreach ($files as $file) {
        if (is_file($file) && is_writable($file)) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
            $updated = [];
            $newLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '' && $trimmed[0] !== '#' && str_contains($trimmed, '=')) {
                    [$k, $v] = explode('=', $trimmed, 2);
                    $k = trim($k);
                    if (array_key_exists($k, $params)) {
                        $newLines[] = $k . '="' . addcslashes((string)$params[$k], '"\\') . '"';
                        $updated[] = $k;
                        continue;
                    }
                }
                $newLines[] = $line;
            }
            foreach ($params as $k => $v) {
                if (!in_array($k, $updated, true)) {
                    $newLines[] = $k . '="' . addcslashes((string)$v, '"\\') . '"';
                }
            }
            @file_put_contents($file, implode("\n", $newLines) . "\n", LOCK_EX);
        }
    }
    return true;
}
