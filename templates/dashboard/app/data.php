<?php
declare(strict_types=1);
if (!defined('PANEL')) {
    http_response_code(403);
    exit;
}

function panel_php_version(): string
{
    return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
}

function panel_services(): array
{
    $php = panel_php_version();
    $units = [
        'apache2'              => 'Apache 2.4',
        'php' . $php . '-fpm'  => 'PHP ' . $php . ' FPM',
        'mariadb'              => 'MariaDB',
        'redis-server'         => 'Redis',
        'smbd'                 => 'Samba',
        'ufw'                  => 'UFW',
        'fail2ban'             => 'Fail2ban',
    ];
    $services = [];
    foreach ($units as $unit => $label) {
        [$code, $output] = panel_run('systemctl is-active ' . escapeshellarg($unit));
        $services[] = [
            'unit'   => $unit,
            'label'  => $label,
            'active' => ($code === 0 && trim($output) === 'active'),
        ];
    }
    return $services;
}

function panel_overview(array $conf): array
{
    $hostname = gethostname() ?: 'servidor';
    $kernel = trim((string)@shell_exec('uname -r'));
    $os = 'Debian GNU/Linux';
    if (is_readable('/etc/os-release')) {
        $release = parse_ini_file('/etc/os-release') ?: [];
        if (!empty($release['PRETTY_NAME'])) {
            $os = (string)$release['PRETTY_NAME'];
        }
    }

    $uptime = 0;
    if (is_readable('/proc/uptime')) {
        $raw = (string)@file_get_contents('/proc/uptime');
        $uptime = (int)floatval(explode(' ', $raw)[0] ?? 0);
    }
    $days = intdiv($uptime, 86400);
    $hours = intdiv($uptime % 86400, 3600);
    $minutes = intdiv($uptime % 3600, 60);

    $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];

    $memTotal = 0;
    $memAvailable = 0;
    if (is_readable('/proc/meminfo')) {
        $meminfo = (string)@file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
            $memTotal = (int)$m[1] * 1024;
        }
        if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
            $memAvailable = (int)$m[1] * 1024;
        }
    }
    $memUsed = max(0, $memTotal - $memAvailable);
    $memPercent = $memTotal > 0 ? (int)round($memUsed / $memTotal * 100) : 0;

    $diskTotal = (float)(@disk_total_space('/') ?: 0);
    $diskFree = (float)(@disk_free_space('/') ?: 0);
    $diskUsed = max(0, $diskTotal - $diskFree);
    $diskPercent = $diskTotal > 0 ? (int)round($diskUsed / $diskTotal * 100) : 0;

    return [
        'hostname'      => $hostname,
        'ip'            => (string)$conf['SERVER_IP'],
        'base_domain'   => (string)$conf['BASE_DOMAIN'],
        'os'            => $os,
        'kernel'        => $kernel,
        'php'           => panel_php_version(),
        'uptime_seconds'=> $uptime,
        'uptime_text'   => ($days > 0 ? $days . 'd ' : '') . $hours . 'h ' . $minutes . 'm',
        'load'          => array_map(static fn($v) => round((float)$v, 2), $load),
        'mem_total'     => $memTotal,
        'mem_used'      => $memUsed,
        'mem_percent'   => $memPercent,
        'disk_total'    => $diskTotal,
        'disk_used'     => $diskUsed,
        'disk_percent'  => $diskPercent,
    ];
}

function panel_projects(array $conf): array
{
    $www = '/var/www';
    if (!is_dir($www)) {
        return [];
    }
    $items = @scandir($www) ?: [];
    $projects = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'html' || str_starts_with($item, '_')) {
            continue;
        }
        $path = $www . '/' . $item;
        if (!is_dir($path)) {
            continue;
        }
        $publicPath = $path . '/public_html';
        $hasPublic = is_dir($publicPath);
        $docRoot = $hasPublic ? $publicPath : $path;

        $gitCommit = '';
        $hasGit = is_dir($docRoot . '/.git');
        if ($hasGit) {
            [$code, $output] = panel_run('git -C ' . escapeshellarg($docRoot) . ' log -1 --pretty=format:"%h %s"', 8);
            $gitCommit = ($code === 0 && trim($output) !== '') ? trim($output) : 'Sin commits';
        }

        $envFile = $publicPath . '/.env';
        $hasDb = is_file($envFile);
        $dbName = '';
        if ($hasDb) {
            $envTxt = (string)@file_get_contents($envFile);
            if (preg_match('/DB_DATABASE=([^\r\n]+)/', $envTxt, $m)) {
                $dbName = trim($m[1]);
            }
            if ($dbName === '') {
                $dbName = str_replace('-', '_', $item) . '_db';
            }
        }

        $size = '';
        [$duCode, $duOut] = panel_run('du -sh ' . escapeshellarg($path) . ' 2>/dev/null', 6);
        if ($duCode === 0 && trim($duOut) !== '') {
            $size = explode("\t", trim($duOut))[0];
        }

        $mtime = @filemtime($docRoot) ?: 0;
        $projects[] = [
            'name'       => $item,
            'fqdn'       => $item . '.' . $conf['BASE_DOMAIN'],
            'path'       => $path,
            'doc_root'   => $docRoot,
            'has_public' => $hasPublic,
            'has_git'    => $hasGit,
            'git'        => $gitCommit,
            'has_db'     => $hasDb,
            'db_name'    => $dbName,
            'size'       => $size,
            'updated'    => $mtime > 0 ? date('Y-m-d H:i', $mtime) : 'N/D',
            'is_base'    => in_array($item, [$conf['PROD_SUB'], $conf['STG_SUB']], true),
        ];
    }
    usort($projects, static fn($a, $b) => strcmp($a['name'], $b['name']));
    return $projects;
}

function panel_databases(array $conf): array
{
    $result = ['ok' => false, 'error' => '', 'databases' => [], 'users' => []];
    if (!class_exists('mysqli')) {
        $result['error'] = 'Extensión mysqli no disponible.';
        return $result;
    }
    $mysqli = @new mysqli('127.0.0.1', (string)$conf['ADMIN_USER'], (string)$conf['ADMIN_PASS'], '', 3306);
    if ($mysqli->connect_errno) {
        $result['error'] = 'No se pudo conectar a MariaDB: ' . $mysqli->connect_error;
        return $result;
    }
    $mysqli->set_charset('utf8mb4');
    $system = ['information_schema', 'performance_schema', 'mysql', 'sys'];
    $query = 'SELECT table_schema AS name, '
        . 'ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb, '
        . 'COUNT(*) AS tables_count '
        . 'FROM information_schema.tables GROUP BY table_schema ORDER BY table_schema';
    if ($res = $mysqli->query($query)) {
        while ($row = $res->fetch_assoc()) {
            $result['databases'][] = [
                'name'         => (string)$row['name'],
                'size_mb'      => (float)($row['size_mb'] ?? 0),
                'tables_count' => (int)($row['tables_count'] ?? 0),
                'is_system'    => in_array($row['name'], $system, true),
            ];
        }
        $res->free();
    }
    if ($res = $mysqli->query("SELECT User, Host FROM mysql.user WHERE User NOT IN ('', 'root', 'pma') ORDER BY User, Host")) {
        while ($row = $res->fetch_assoc()) {
            $result['users'][] = ['user' => (string)$row['User'], 'host' => (string)$row['Host']];
        }
        $res->free();
    }
    $mysqli->close();
    $result['ok'] = true;
    return $result;
}

function panel_backups(): array
{
    $dir = is_dir('/var/backups/srvctl') ? '/var/backups/srvctl' : (is_dir('/backup') ? '/backup' : '');
    $snapshots = [];
    $dumps = [];
    if ($dir === '') {
        return ['dir' => '', 'snapshots' => [], 'dumps' => []];
    }
    $snapDir = $dir . '/snapshots';
    if (is_dir($snapDir)) {
        foreach (@scandir($snapDir) ?: [] as $entry) {
            if (!str_starts_with($entry, 'daily.')) {
                continue;
            }
            $path = $snapDir . '/' . $entry;
            $mtime = @filemtime($path) ?: 0;
            $snapshots[] = [
                'name'  => $entry,
                'date'  => $mtime > 0 ? date('Y-m-d H:i', $mtime) : 'N/D',
                'today' => $entry === 'daily.0',
            ];
        }
        usort($snapshots, static fn($a, $b) => strcmp($a['name'], $b['name']));
    }
    $dbDir = $dir . '/database';
    if (is_dir($dbDir)) {
        foreach (@scandir($dbDir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.sql.gz')) {
                continue;
            }
            $path = $dbDir . '/' . $entry;
            $mtime = @filemtime($path) ?: 0;
            $dumps[] = [
                'name' => $entry,
                'size' => panel_format_bytes((int)(@filesize($path) ?: 0)),
                'date' => $mtime > 0 ? date('Y-m-d H:i', $mtime) : 'N/D',
                'time' => $mtime,
            ];
        }
        usort($dumps, static fn($a, $b) => $b['time'] <=> $a['time']);
    }
    return ['dir' => $dir, 'snapshots' => $snapshots, 'dumps' => $dumps];
}

function panel_ssl(): array
{
    $certFile = '/etc/ssl/localcerts/webserver.crt';
    $caFile = '/etc/ssl/localcerts/rootCA.crt';
    $info = [
        'exists'      => is_file($certFile) && is_readable($certFile),
        'subject'     => '',
        'issuer'      => '',
        'valid_from'  => '',
        'valid_to'    => '',
        'days_left'   => 0,
        'sans'        => [],
        'ca_exists'   => is_file($caFile),
        'ca_path'     => $caFile,
    ];
    if ($info['exists']) {
        $parsed = openssl_x509_parse((string)@file_get_contents($certFile));
        if (is_array($parsed)) {
            $info['subject'] = (string)($parsed['subject']['CN'] ?? '');
            $info['issuer'] = (string)($parsed['issuer']['CN'] ?? '');
            $info['valid_from'] = date('Y-m-d', (int)$parsed['validFrom_time_t']);
            $info['valid_to'] = date('Y-m-d', (int)$parsed['validTo_time_t']);
            $info['days_left'] = (int)round(((int)$parsed['validTo_time_t'] - time()) / 86400);
            $sans = $parsed['extensions']['subjectAltName'] ?? '';
            if ($sans !== '') {
                foreach (explode(',', $sans) as $san) {
                    $info['sans'][] = trim($san);
                }
            }
        }
    }
    return $info;
}

function panel_downloads(): array
{
    $dir = '/var/www/_dashboard/downloads';
    $files = [
        'rootCA.crt' => 'Certificado CA raíz (.crt)',
        'configurar-cliente.bat' => 'Configurador de cliente (.bat)',
        'configurar-desarrollador.bat' => 'Configurador de desarrollador (.bat)',
    ];
    $out = [];
    foreach ($files as $name => $label) {
        $out[] = ['name' => $name, 'label' => $label, 'exists' => is_file($dir . '/' . $name)];
    }
    return $out;
}

function panel_security(): ?array
{
    return panel_srvctl_api('security');
}

function panel_samba(): ?array
{
    return panel_srvctl_api('samba');
}
