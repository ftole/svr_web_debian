<?php
$overview = panel_overview($CONFIG);
$ssl = panel_ssl();
$backups = panel_backups();
$services = panel_services();
$activeServices = 0;
foreach ($services as $s) {
    if ($s['active']) $activeServices++;
}
$redisMetrics = panel_metrics_redis();
$mariadbMetrics = panel_metrics_mariadb($CONFIG);
$topProcesses = panel_metrics_top();

$spec = [
    'title' => 'Módulo 1: Resumen y Telemetría del Sistema',
    'endpoints' => [
        'GET /?action=metrics (Polling en tiempo real cada 1s/2s)',
        'GET /?action=server_status (Telemetría de CPU, RAM, Disco y Ping)',
        'GET /?action=section&name=overview (Renderizado dinámico SPA)'
    ],
    'commands' => [
        'ps -eo pid,comm,%cpu,%mem --sort=-%cpu | head -n 6',
        'systemctl is-active apache2 php8.4-fpm mariadb redis-server ufw fail2ban smbd',
        'redis-cli info memory && redis-cli info stats',
        'mariadb -e "SHOW GLOBAL STATUS LIKE \'Threads_connected\';"'
    ],
    'paths' => [
        '/proc/loadavg, /proc/meminfo, /proc/net/dev',
        '/var/www (monitoreo de espacio en disco vía statvfs / df)'
    ],
    'notes' => 'El backend expone telemetría y métricas en JSON con campos { cpu, mem, disk, services, top, redis, mariadb }. El pool PHP-FPM dedicado aísla la administración de las aplicaciones web.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- Componente nativo de telemetría y gráficos en tiempo real -->
<div id="material-status-monitor"></div>

<section class="grid grid-3">
    <div class="card">
        <div class="card-head"><h2>Servicios Principales</h2></div>
        <ul class="list" id="live-services">
            <?php foreach ($services as $service): ?>
                <li class="list-row">
                    <span><span class="dot <?= $service['active'] ? 'ok' : 'err' ?>"></span><?= e($service['label']) ?></span>
                    <span class="tag <?= $service['active'] ? 'tag-ok' : 'tag-err' ?>"><?= $service['active'] ? 'Activo' : 'Inactivo' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Caché Redis 7.2</h2></div>
        <dl class="kv" id="live-redis">
            <div><dt>Memoria</dt><dd id="live-redis-mem"><?= e((string)($redisMetrics['memory'] ?? '—')) ?></dd></div>
            <div><dt>Clientes</dt><dd id="live-redis-clients"><?= e((string)($redisMetrics['clients'] ?? '—')) ?></dd></div>
            <div><dt>Operaciones/s</dt><dd id="live-redis-ops"><?= e((string)($redisMetrics['ops'] ?? '—')) ?></dd></div>
        </dl>
    </div>
    <div class="card">
        <div class="card-head"><h2>Base de Datos MariaDB 11.8</h2></div>
        <dl class="kv" id="live-mariadb">
            <div><dt>Conexiones</dt><dd id="live-db-threads"><?= e((string)($mariadbMetrics['threads'] ?? '—')) ?></dd></div>
            <div><dt>Hilos en ejecución</dt><dd id="live-db-running"><?= e((string)($mariadbMetrics['running'] ?? '—')) ?></dd></div>
            <div><dt>Consultas/s (QPS)</dt><dd id="live-db-qps"><?= e((string)($mariadbMetrics['qps'] ?? '—')) ?></dd></div>
        </dl>
    </div>
</section>

<section class="grid grid-3" style="margin-top: 16px;">
    <div class="card">
        <div class="card-head"><h2>Gráfico CPU (60s)</h2></div>
        <canvas id="chart-cpu" class="spark" width="320" height="54"></canvas>
    </div>
    <div class="card">
        <div class="card-head"><h2>Gráfico Memoria RAM</h2></div>
        <canvas id="chart-mem" class="spark" width="320" height="54"></canvas>
    </div>
    <div class="card">
        <div class="card-head"><h2>Tráfico de Red (KB/s)</h2></div>
        <canvas id="chart-net" class="spark" width="320" height="54"></canvas>
    </div>
</section>

<section class="card" style="margin-top: 16px;">
    <div class="card-head"><h2>Procesos de mayor consumo de CPU</h2></div>
    <table class="table">
        <thead><tr><th>PID</th><th>Proceso</th><th>CPU %</th><th>RAM %</th></tr></thead>
        <tbody id="live-top">
            <?php if (!empty($topProcesses)): ?>
                <?php foreach ($topProcesses as $row): ?>
                    <tr>
                        <td><code><?= (int)$row['pid'] ?></code></td>
                        <td><?= e($row['name']) ?></td>
                        <td><?= (float)$row['cpu'] ?> %</td>
                        <td><?= (float)$row['mem'] ?> %</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="muted">Cargando procesos…</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="grid grid-2" style="margin-top: 16px;">
    <div class="card">
        <div class="card-head"><h2>Stack & Plataforma Web</h2></div>
        <dl class="kv">
            <div><dt>Servidor Web</dt><dd>Apache 2.4 (MPM Event)</dd></div>
            <div><dt>Ruteo Dinámico</dt><dd>mod_vhost_alias (*.<?= e($CONFIG['BASE_DOMAIN']) ?>)</dd></div>
            <div><dt>Motor PHP</dt><dd>PHP <?= e($overview['php']) ?> FPM (Pool Aislado)</dd></div>
            <div><dt>Gestor Paquetes</dt><dd>Composer 2.x global</dd></div>
            <div><dt>Recurso Samba</dt><dd>[proyectos] (\\<?= e($CONFIG['SERVER_IP']) ?>\proyectos)</dd></div>
            <div><dt>phpMyAdmin</dt><dd>webdev.<?= e($CONFIG['BASE_DOMAIN']) ?></dd></div>
        </dl>
    </div>
    <div class="card">
        <div class="card-head">
            <h2>Seguridad & Certificado SSL</h2>
            <span class="tag <?= ($ssl['exists'] && $ssl['days_left'] > 0) ? 'tag-ok' : 'tag-err' ?>">
                <?= $ssl['exists'] ? (int)$ssl['days_left'] . ' días restantes' : 'N/D' ?>
            </span>
        </div>
        <dl class="kv">
            <div><dt>Dominio Comodín</dt><dd><?= e($ssl['subject']) ?></dd></div>
            <div><dt>Emisor Certificado</dt><dd>CA Raíz Interna Debian (SAN)</dd></div>
            <div><dt>Válido hasta</dt><dd><?= e($ssl['valid_to']) ?></dd></div>
            <div><dt>Respaldos Rotativos</dt><dd>7 Días (daily.0 a daily.6)</dd></div>
            <div><dt>Snapshots Locales</dt><dd><?= count($backups['snapshots'] ?? []) ?> snapshots</dd></div>
            <div><dt>Volcados SQL</dt><dd><?= count($backups['dumps'] ?? []) ?> respaldos de MariaDB</dd></div>
        </dl>
    </div>
</section>

<section class="card" style="margin-top: 16px;">
    <div class="card-head"><h2>Acciones rápidas</h2></div>
    <div class="actions">
        <a class="btn btn-outline" href="/" data-section="projects">Gestionar proyectos</a>
        <a class="btn btn-outline" href="/" data-section="database">Bases de datos</a>
        <a class="btn btn-outline" href="/" data-section="backups">Respaldos</a>
        <a class="btn btn-outline" href="/" data-section="diagnostics">Ejecutar diagnóstico</a>
    </div>
</section>
