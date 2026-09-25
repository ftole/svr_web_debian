<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_srvctl(array $args, int $timeout = 0): array
{
    $command = 'sudo /usr/local/bin/srvctl';
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg((string)$arg);
    }
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
