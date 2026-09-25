<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_srvctl(array $args, int $timeout = 0): array
{
    // Unir argumentos con espacio (el wrapper hace read -r ACTION TARGET EXTRA)
    $cmd_str = implode(' ', $args);
    // Ejecutar pasando la variable por entorno al wrapper (usando env)
    $command = 'env SRVCTL_CMD=' . escapeshellarg($cmd_str) . ' sudo /usr/local/bin/srvctl-web-wrapper';
    return panel_run($command, $timeout);
}

function panel_srvctl_api(string $section): ?array
{
    [$code, $output] = panel_srvctl(['api', $section], 15);
    if ($code !== 0) {
        return null;
    }
    $data = json_decode($output, true);
    return is_array($data) ? $data : null;
}

function panel_read_env_db(string $name): array
{
    $db = ['project' => $name, 'host' => '127.0.0.1', 'port' => '3306', 'database' => '', 'user' => '', 'pass' => ''];
    $envFile = '/var/www/' . $name . '/public_html/.env';
    if (is_readable($envFile)) {
        $env = (string)@file_get_contents($envFile);
        $map = ['DB_DATABASE' => 'database', 'DB_USERNAME' => 'user', 'DB_PASSWORD' => 'pass', 'DB_HOST' => 'host', 'DB_PORT' => 'port'];
        foreach ($map as $key => $field) {
            if (preg_match('/^' . $key . '=(.*)$/m', $env, $m)) {
                $db[$field] = trim($m[1]);
            }
        }
    }
    return $db;
}
