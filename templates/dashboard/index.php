<?php
declare(strict_types=1);

$confFile = '/etc/asistente_servidor.conf';
$conf = [];
if (file_exists($confFile)) {
    $lines = file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $conf[trim($k)] = trim($v, " '\t\n\r\0\x0B\"");
    }
}

$serverIp   = $conf['SERVER_IP'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$baseDomain = $conf['BASE_DOMAIN'] ?? 'empresa.local';
$adminUser  = $conf['ADMIN_USER'] ?? 'webadmin';

function checkService(string $name): bool {
    $out = [];
    $code = 0;
    exec("systemctl is-active --quiet " . escapeshellarg($name), $out, $code);
    return $code === 0;
}

$phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

$services = [
    'Apache 2.4 (MPM Event)' => checkService('apache2'),
    "PHP {$phpVer} FPM"       => file_exists("/run/php/php{$phpVer}-fpm.sock"),
    'MariaDB 11.8'           => checkService('mariadb'),
    'Redis Cache'            => checkService('redis-server') || checkService('redis'),
    'Samba SMBv3'            => checkService('smbd'),
    'Cortafuegos UFW'        => checkService('ufw'),
];

// Metricas del sistema
$freeMem = 'N/D';
$totalMem = 'N/D';
$memPercent = 0;
if (is_readable('/proc/meminfo')) {
    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mTot);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $mAvail);
    if (!empty($mTot[1]) && !empty($mAvail[1])) {
        $totMb = round((int)$mTot[1] / 1024);
        $availMb = round((int)$mAvail[1] / 1024);
        $usedMb = $totMb - $availMb;
        $totalMem = $totMb . ' MB';
        $memPercent = round(($usedMb / $totMb) * 100);
    }
}

$diskTotal = disk_total_space('/') ?: 1;
$diskFree  = disk_free_space('/') ?: 0;
$diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100);
$diskFreeGb = round($diskFree / (1024 * 1024 * 1024), 1);
$diskTotalGb = round($diskTotal / (1024 * 1024 * 1024), 1);

// Certificado SSL
$sslExpire = 'Valido';
$certFile = '/etc/ssl/localcerts/webserver.crt';
if (file_exists($certFile)) {
    $certData = openssl_x509_parse(file_get_contents($certFile));
    if ($certData && isset($certData['validTo_time_t'])) {
        $days = (int)round(($certData['validTo_time_t'] - time()) / 86400);
        $sslExpire = "{$days} dias restantes";
    }
}

// Escaneo dinamico de proyectos en /var/www
$projects = [];
$wwwDir = '/var/www';
if (is_dir($wwwDir)) {
    $items = scandir($wwwDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'html' || str_starts_with($item, '_')) continue;
        $projPath = "{$wwwDir}/{$item}";
        if (is_dir($projPath)) {
            $hasPublic = is_dir("{$projPath}/public_html");
            $hasGit = is_dir("{$projPath}/public_html/.git") || is_dir("{$projPath}/.git");
            $gitBranch = 'main';
            $gitCommit = '';
            if ($hasGit) {
                $gitDir = is_dir("{$projPath}/public_html/.git") ? "{$projPath}/public_html" : $projPath;
                $lastCommit = @exec("git -C " . escapeshellarg($gitDir) . " log -1 --pretty=format:'%h - %s' 2>/dev/null");
                $gitCommit = $lastCommit ?: 'Sin commits';
            }
            $mtime = filemtime($hasPublic ? "{$projPath}/public_html" : $projPath);
            $projects[] = [
                'name'      => $item,
                'fqdn'      => "{$item}.{$baseDomain}",
                'path'      => $projPath,
                'hasPublic' => $hasPublic,
                'hasGit'    => $hasGit,
                'gitCommit' => $gitCommit,
                'updated'   => date('Y-m-d H:i', $mtime),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Servidor &mdash; <?= htmlspecialchars($baseDomain) ?></title>
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --success: #22c55e;
            --danger: #ef4444;
            --accent: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 30px 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        header {
            margin-bottom: 30px;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        h1 { font-size: 1.8rem; font-weight: 700; color: #fff; }
        .meta-badges { display: flex; gap: 10px; flex-wrap: wrap; }
        .badge {
            background: #0f172a;
            border: 1px solid var(--card-border);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--primary);
        }
        .section-title {
            font-size: 1.25rem;
            margin: 25px 0 15px 0;
            color: #fff;
            border-left: 4px solid var(--primary);
            padding-left: 10px;
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 18px;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .card:hover { border-color: var(--primary); }
        .service-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .dot {
            width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;
        }
        .dot.ok { background: var(--success); box-shadow: 0 0 6px var(--success); }
        .dot.err { background: var(--danger); box-shadow: 0 0 6px var(--danger); }
        .metric-bar-bg {
            background: #0f172a; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 8px;
        }
        .metric-bar-fill {
            background: var(--primary); height: 100%; border-radius: 4px;
        }
        .project-card { display: flex; flex-direction: column; justify-content: space-between; }
        .project-title { font-size: 1.15rem; font-weight: 600; color: #fff; margin-bottom: 6px; }
        .project-link {
            color: var(--primary); text-decoration: none; word-break: break-all; font-weight: 500;
        }
        .project-link:hover { text-decoration: underline; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: #0284c7; color: #fff; text-decoration: none;
            padding: 8px 14px; border-radius: 6px; font-weight: 500; font-size: 0.9rem;
            border: none; cursor: pointer; transition: background 0.15s ease;
        }
        .btn:hover { background: #0369a1; }
        .btn-outline {
            background: transparent; border: 1px solid var(--card-border); color: var(--text-main);
        }
        .btn-outline:hover { background: #334155; }
        .actions-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        .instruction-box {
            background: #022c22; border: 1px solid #065f46; border-radius: 8px; padding: 15px; margin-top: 25px;
            color: #a7f3d0; font-size: 0.95rem;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>Servidor Web Nativo &mdash; Debian 13</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Plataforma de desarrollo ágil con subdominios dinámicos</p>
        </div>
        <div class="meta-badges">
            <span class="badge">IP: <?= htmlspecialchars($serverIp) ?></span>
            <span class="badge">Dominio: *.<?= htmlspecialchars($baseDomain) ?></span>
            <span class="badge">SSL: <?= htmlspecialchars($sslExpire) ?></span>
        </div>
    </header>

    <h2 class="section-title">Salud del Sistema y Servicios</h2>
    <div class="grid">
        <div class="card">
            <h3 style="font-size: 1rem; margin-bottom: 12px; color: var(--text-muted);">Servicios Base</h3>
            <?php foreach ($services as $srvName => $isOk): ?>
                <div class="service-row">
                    <span><span class="dot <?= $isOk ? 'ok' : 'err' ?>"></span><?= htmlspecialchars($srvName) ?></span>
                    <span style="font-size: 0.85rem; color: <?= $isOk ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $isOk ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3 style="font-size: 1rem; margin-bottom: 12px; color: var(--text-muted);">Recursos del Servidor</h3>
            <div style="margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                    <span>Memoria RAM</span>
                    <span><?= $memPercent ?>% (<?= $totalMem ?>)</span>
                </div>
                <div class="metric-bar-bg"><div class="metric-bar-fill" style="width: <?= $memPercent ?>%;"></div></div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                    <span>Almacenamiento (/)</span>
                    <span><?= $diskUsedPercent ?>% (<?= $diskFreeGb ?> GB libres)</span>
                </div>
                <div class="metric-bar-bg"><div class="metric-bar-fill" style="width: <?= $diskUsedPercent ?>%;"></div></div>
            </div>
        </div>

        <div class="card">
            <h3 style="font-size: 1rem; margin-bottom: 12px; color: var(--text-muted);">Acceso Rápido y Red</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px;">
                Red Compartida (Samba):<br>
                <code style="color: var(--primary);">\\<?= htmlspecialchars($serverIp) ?>\proyectos</code>
            </p>
            <div class="actions-bar">
                <a href="https://webdev.<?= htmlspecialchars($baseDomain) ?>" target="_blank" class="btn">Abrir phpMyAdmin</a>
                <a href="/downloads/rootCA.crt" download class="btn btn-outline">Certificado SSL</a>
                <a href="/downloads/configurar-desarrollador.bat" download class="btn btn-outline">Script Dev (.bat)</a>
                <a href="/downloads/configurar-desarrollador.ps1" download class="btn btn-outline">Script Dev (.ps1)</a>
            </div>
        </div>
    </div>

    <h2 class="section-title">Proyectos Activos (Subdominios Dinámicos)</h2>
    <div class="grid">
        <?php if (empty($projects)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                <p style="color: var(--text-muted);">No hay proyectos creados aún en <code>/var/www/</code>.</p>
                <p style="margin-top: 10px;">Crea una carpeta en la unidad compartida <code>Z:\</code> y aparecerá aquí automáticamente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $proj): ?>
                <div class="card project-card">
                    <div>
                        <div class="project-title"><?= htmlspecialchars($proj['name']) ?></div>
                        <a href="https://<?= htmlspecialchars($proj['fqdn']) ?>" target="_blank" class="project-link">
                            https://<?= htmlspecialchars($proj['fqdn']) ?> &rarr;
                        </a>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                            Ultima modificación: <?= htmlspecialchars($proj['updated']) ?>
                        </p>
                    </div>
                    <?php if ($proj['hasGit']): ?>
                        <div style="margin-top: 12px; font-size: 0.8rem; background: #0f172a; padding: 6px 10px; border-radius: 4px; color: #cbd5e1;">
                            Git: <?= htmlspecialchars($proj['gitCommit']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="instruction-box">
        <strong>¿Cómo crear un nuevo proyecto web?</strong><br>
        1. Abre la red compartida <code>\\<?= htmlspecialchars($serverIp) ?>\proyectos</code> en tu equipo Windows.<br>
        2. Crea una nueva carpeta con el nombre de tu proyecto (ej. <code>tienda</code>) y dentro de ella la carpeta <code>public_html</code>.<br>
        3. Coloca tu archivo <code>index.php</code> o tu proyecto web dentro. Inmediatamente quedará en línea en <code>https://tienda.<?= htmlspecialchars($baseDomain) ?></code> sin reiniciar servicios ni configurar nada más.
    </div>
</div>
</body>
</html>
