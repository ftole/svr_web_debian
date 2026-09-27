<?php
$services = panel_services();
$activeServices = 0;
foreach ($services as $s) {
    if ($s['active']) $activeServices++;
}
$redisMetrics = panel_metrics_redis();
$mariadbMetrics = panel_metrics_mariadb($CONFIG);
?>

<!-- Componente nativo de telemetria y graficos en tiempo real -->
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

<section class="card" style="margin-top: 16px;">
    <div class="card-head"><h2>Acciones rápidas</h2></div>
    <div class="actions">
        <a class="btn btn-outline" href="/" data-section="projects">Gestionar proyectos</a>
        <a class="btn btn-outline" href="/" data-section="database">Bases de datos</a>
        <a class="btn btn-outline" href="/" data-section="backups">Respaldos</a>
        <a class="btn btn-outline" href="/" data-section="diagnostics">Ejecutar diagnóstico</a>
    </div>
</section>
