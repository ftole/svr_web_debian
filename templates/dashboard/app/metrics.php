<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_metrics_state_path(): string
{
    return sys_get_temp_dir() . '/.srvctl-metrics-state.json';
}

function panel_metrics_cache_path(): string
{
    return sys_get_temp_dir() . '/.srvctl-metrics-cache.json';
}

function panel_read_state(): array
{
    $path = panel_metrics_state_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function panel_write_state(array $data): void
{
    $path = panel_metrics_state_path();
    $handle = @fopen($path, 'c');
    if ($handle === false) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function panel_cpu_snapshot(): array
{
    $lines = @file('/proc/stat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $cores = [];
    foreach ($lines as $line) {
        if (!preg_match('/^cpu(\d*)\s+(.*)$/', $line, $m)) {
            continue;
        }
        $values = array_map('intval', preg_split('/\s+/', trim($m[2])));
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        $cores[$m[1] === '' ? 'all' : $m[1]] = ['idle' => $idle, 'total' => array_sum($values)];
    }
    return $cores;
}

function panel_net_snapshot(): array
{
    $rx = 0;
    $tx = 0;
    foreach (@file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$iface, $rest] = explode(':', $line, 2);
        if (trim($iface) === 'lo') {
            continue;
        }
        $fields = preg_split('/\s+/', trim($rest));
        $rx += (int)($fields[0] ?? 0);
        $tx += (int)($fields[8] ?? 0);
    }
    return ['rx' => $rx, 'tx' => $tx];
}

function panel_disk_snapshot(): array
{
    $read = 0;
    $write = 0;
    foreach (@file('/proc/diskstats', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $fields = preg_split('/\s+/', trim($line));
        if (count($fields) < 10) {
            continue;
        }
        if (!preg_match('/^(sd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme\d+n\d+|mmcblk\d+)$/', $fields[2])) {
            continue;
        }
        $read += (int)$fields[5];
        $write += (int)$fields[9];
    }
    return ['r' => $read, 'w' => $write];
}

function panel_meminfo(): array
{
    $values = [];
    foreach (@file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
            $values[$m[1]] = (int)$m[2] * 1024;
        }
    }
    return $values;
}

function panel_temp_celsius(): ?float
{
    $max = null;
    foreach (glob('/sys/class/thermal/thermal_zone*/temp') ?: [] as $file) {
        $milli = (int)trim((string)@file_get_contents($file));
        if ($milli <= 0) {
            continue;
        }
        $celsius = $milli / 1000;
        if ($max === null || $celsius > $max) {
            $max = $celsius;
        }
    }
    return $max !== null ? round($max, 1) : null;
}

function panel_metrics_fast(): array
{
    $now = microtime(true);
    $state = panel_read_state();
    $cpu = panel_cpu_snapshot();
    $net = panel_net_snapshot();
    $disk = panel_disk_snapshot();

    $elapsed = $now - (float)($state['t'] ?? 0);
    if ($elapsed <= 0.05) {
        $elapsed = 1.0;
    }

    $cpuOut = ['total' => 0.0, 'cores' => []];
    foreach ($cpu as $key => $current) {
        $usage = 0.0;
        if (isset($state['cpu'][$key])) {
            $deltaTotal = $current['total'] - (int)$state['cpu'][$key]['total'];
            $deltaIdle = $current['idle'] - (int)$state['cpu'][$key]['idle'];
            if ($deltaTotal > 0) {
                $usage = round(max(0.0, min(100.0, 100 * (1 - $deltaIdle / $deltaTotal))), 1);
            }
        }
        if ($key === 'all') {
            $cpuOut['total'] = $usage;
        } else {
            $cpuOut['cores'][] = $usage;
        }
    }

    $netOut = ['rx_kbs' => 0.0, 'tx_kbs' => 0.0];
    if (isset($state['net'])) {
        $netOut['rx_kbs'] = round(max(0, ($net['rx'] - (int)$state['net']['rx']) / 1024 / $elapsed), 1);
        $netOut['tx_kbs'] = round(max(0, ($net['tx'] - (int)$state['net']['tx']) / 1024 / $elapsed), 1);
    }

    $diskOut = ['read_kbs' => 0.0, 'write_kbs' => 0.0];
    if (isset($state['disk'])) {
        $diskOut['read_kbs'] = round(max(0, ($disk['r'] - (int)$state['disk']['r']) * 512 / 1024 / $elapsed), 1);
        $diskOut['write_kbs'] = round(max(0, ($disk['w'] - (int)$state['disk']['w']) * 512 / 1024 / $elapsed), 1);
    }

    panel_write_state(['t' => $now, 'cpu' => $cpu, 'net' => $net, 'disk' => $disk]);

    $mem = panel_meminfo();
    $memTotal = (int)($mem['MemTotal'] ?? 0);
    $memAvailable = (int)($mem['MemAvailable'] ?? 0);
    $memUsed = max(0, $memTotal - $memAvailable);
    $swapTotal = (int)($mem['SwapTotal'] ?? 0);
    $swapFree = (int)($mem['SwapFree'] ?? 0);

    $diskTotal = (float)(@disk_total_space('/') ?: 0);
    $diskFree = (float)(@disk_free_space('/') ?: 0);
    $diskUsed = max(0, $diskTotal - $diskFree);

    $uptime = 0;
    if (is_readable('/proc/uptime')) {
        $uptime = (int)floatval(explode(' ', (string)@file_get_contents('/proc/uptime'))[0] ?? 0);
    }

    return [
        'cpu'    => $cpuOut,
        'load'   => array_map(static fn($v) => round((float)$v, 2), sys_getloadavg() ?: [0, 0, 0]),
        'mem'    => [
            'used'       => $memUsed,
            'total'      => $memTotal,
            'percent'    => $memTotal > 0 ? (int)round($memUsed / $memTotal * 100) : 0,
            'swap_used'  => max(0, $swapTotal - $swapFree),
            'swap_total' => $swapTotal,
        ],
        'disk'   => [
            'used'      => $diskUsed,
            'total'     => $diskTotal,
            'percent'   => $diskTotal > 0 ? (int)round($diskUsed / $diskTotal * 100) : 0,
            'read_kbs'  => $diskOut['read_kbs'],
            'write_kbs' => $diskOut['write_kbs'],
        ],
        'net'    => $netOut,
        'temp_c' => panel_temp_celsius(),
        'uptime' => $uptime,
    ];
}

function panel_metrics_redis(): array
{
    [$code, $output] = panel_run('redis-cli info 2>/dev/null', 5);
    if ($code !== 0) {
        return ['ok' => false];
    }
    $values = [];
    foreach (explode("\n", $output) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $values[trim($k)] = trim($v);
        }
    }
    return [
        'ok'      => true,
        'memory'  => $values['used_memory_human'] ?? 'N/D',
        'clients' => (int)($values['connected_clients'] ?? 0),
        'ops'     => (int)($values['instantaneous_ops_per_sec'] ?? 0),
    ];
}

function panel_metrics_mariadb(array $conf): array
{
    $data = panel_srvctl_api('database');
    if (!is_array($data) || !isset($data['status'])) {
        return ['ok' => false];
    }
    if (isset($data['ok']) && $data['ok'] === false) {
        return ['ok' => false];
    }
    return [
        'ok'      => true,
        'threads' => (int)($data['status']['threads'] ?? 0),
        'running' => (int)($data['status']['running'] ?? 0),
        'qps'     => (float)($data['status']['qps'] ?? 0),
    ];
}

function panel_metrics_top(int $limit = 5): array
{
    [$code, $output] = panel_run('ps -eo pid=,comm=,pcpu=,pmem= --sort=-pcpu 2>/dev/null | head -' . ($limit + 1), 5);
    if ($code !== 0) {
        return [];
    }
    $rows = [];
    foreach (explode("\n", trim($output)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 4) {
            continue;
        }
        $rows[] = [
            'pid'  => (int)$parts[0],
            'name' => (string)$parts[1],
            'cpu'  => (float)$parts[2],
            'mem'  => (float)$parts[3],
        ];
        if (count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}

function panel_metrics_slow(array $conf): array
{
    $cachePath = panel_metrics_cache_path();
    if (is_file($cachePath)) {
        $cached = json_decode((string)@file_get_contents($cachePath), true);
        if (is_array($cached) && (time() - (int)($cached['t'] ?? 0)) < 5) {
            return $cached['data'];
        }
    }
    $data = [
        'services' => panel_services(),
        'redis'    => panel_metrics_redis(),
        'mariadb'  => panel_metrics_mariadb($conf),
        'top'      => panel_metrics_top(),
    ];
    @file_put_contents($cachePath, (string)json_encode(['t' => time(), 'data' => $data]), LOCK_EX);
    return $data;
}
