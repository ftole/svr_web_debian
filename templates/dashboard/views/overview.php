<?php
$overview = panel_overview($CONFIG);
$ssl = panel_ssl();
$backups = panel_backups();
?>
<div class="live-bar">
    <span class="live-dot"></span>
    <span class="muted">Datos en vivo · actualizado <span id="live-updated">—</span></span>
    <span class="muted" id="live-status">conectando…</span>
</div>

<section class="grid grid-4">
    <div class="card metric">
        <span class="metric-label">CPU</span>
        <span class="metric-value"><span id="live-cpu"><?= e((string)$overview['load'][0]) ?></span>%</span>
        <div class="bar"><span id="live-cpu-bar" style="width:0%"></span></div>
        <div class="cores" id="live-cores"></div>
        <span class="metric-foot">Load: <span id="live-load"><?= e(implode(' · ', $overview['load'])) ?></span></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Memoria RAM</span>
        <span class="metric-value"><span id="live-mem"><?= (int)$overview['mem_percent'] ?></span>%</span>
        <div class="bar"><span id="live-mem-bar" style="width:<?= (int)$overview['mem_percent'] ?>%"></span></div>
        <span class="metric-foot"><span id="live-mem-used"><?= e(panel_format_bytes($overview['mem_used'])) ?></span> de <?= e(panel_format_bytes($overview['mem_total'])) ?></span>
        <span class="metric-foot">Swap: <span id="live-swap">—</span></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Disco (/)</span>
        <span class="metric-value"><span id="live-disk"><?= (int)$overview['disk_percent'] ?></span>%</span>
        <div class="bar"><span id="live-disk-bar" style="width:<?= (int)$overview['disk_percent'] ?>%"></span></div>
        <span class="metric-foot"><span id="live-disk-used"><?= e(panel_format_bytes($overview['disk_used'])) ?></span> de <?= e(panel_format_bytes($overview['disk_total'])) ?></span>
        <span class="metric-foot">I/O: <span id="live-disk-io">—</span></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Red</span>
        <span class="metric-value small"><span id="live-net-rx">—</span> ↓</span>
        <span class="metric-value small"><span id="live-net-tx">—</span> ↑</span>
        <span class="metric-foot">Temp: <span id="live-temp">—</span> · Uptime: <span id="live-uptime"><?= e($overview['uptime_text']) ?></span></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Conexiones Web</span>
        <span class="metric-value"><span><?= panel_get_active_connections() ?></span></span>
        <span class="metric-foot">Activas en puertos 80/443</span>
    </div>
</section>

<section class="grid grid-3">
    <div class="card">
        <div class="card-head"><h2>CPU</h2></div>
        <canvas id="chart-cpu" class="spark" width="320" height="54"></canvas>
    </div>
    <div class="card">
        <div class="card-head"><h2>Memoria</h2></div>
        <canvas id="chart-mem" class="spark" width="320" height="54"></canvas>
    </div>
    <div class="card">
        <div class="card-head"><h2>Red (KB/s)</h2></div>
        <canvas id="chart-net" class="spark" width="320" height="54"></canvas>
    </div>
</section>

<section class="grid grid-3">
    <div class="card">
        <div class="card-head"><h2>Servicios</h2></div>
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
        <div class="card-head"><h2>Redis</h2></div>
        <dl class="kv" id="live-redis">
            <div><dt>Memoria</dt><dd id="live-redis-mem">—</dd></div>
            <div><dt>Clientes</dt><dd id="live-redis-clients">—</dd></div>
            <div><dt>Ops/s</dt><dd id="live-redis-ops">—</dd></div>
        </dl>
    </div>
    <div class="card">
        <div class="card-head"><h2>MariaDB</h2></div>
        <dl class="kv" id="live-mariadb">
            <div><dt>Conexiones</dt><dd id="live-db-threads">—</dd></div>
            <div><dt>En ejecución</dt><dd id="live-db-running">—</dd></div>
            <div><dt>Consultas/s</dt><dd id="live-db-qps">—</dd></div>
        </dl>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>Procesos con más CPU</h2></div>
    <table class="table">
        <thead><tr><th>PID</th><th>Proceso</th><th>CPU %</th><th>RAM %</th></tr></thead>
        <tbody id="live-top"><tr><td colspan="4" class="muted">Cargando…</td></tr></tbody>
    </table>
</section>

<section class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Información del sistema</h2></div>
        <dl class="kv">
            <div><dt>Hostname</dt><dd><?= e($overview['hostname']) ?></dd></div>
            <div><dt>Dirección IP</dt><dd><?= e($overview['ip']) ?></dd></div>
            <div><dt>Dominio base</dt><dd><?= e($overview['base_domain']) ?></dd></div>
            <div><dt>Sistema</dt><dd><?= e($overview['os']) ?></dd></div>
            <div><dt>Kernel</dt><dd><?= e($overview['kernel']) ?></dd></div>
            <div><dt>PHP</dt><dd><?= e($overview['php']) ?></dd></div>
        </dl>
    </div>
    <div class="card">
        <div class="card-head"><h2>Certificado SSL</h2>
            <span class="tag <?= ($ssl['exists'] && $ssl['days_left'] > 0) ? 'tag-ok' : 'tag-err' ?>"><?= $ssl['exists'] ? (int)$ssl['days_left'] . ' días' : 'N/D' ?></span>
        </div>
        <dl class="kv">
            <div><dt>Titular</dt><dd><?= e($ssl['subject']) ?></dd></div>
            <div><dt>Válido hasta</dt><dd><?= e($ssl['valid_to']) ?></dd></div>
            <div><dt>Snapshots</dt><dd><?= count($backups['snapshots']) ?></dd></div>
            <div><dt>Volcados SQL</dt><dd><?= count($backups['dumps']) ?></dd></div>
        </dl>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>Acciones rápidas</h2></div>
    <div class="actions">
        <a class="btn btn-outline" href="/" data-section="projects">Gestionar proyectos</a>
        <a class="btn btn-outline" href="/" data-section="database">Bases de datos</a>
        <a class="btn btn-outline" href="/" data-section="backups">Respaldos</a>
        <a class="btn btn-outline" href="/" data-section="diagnostics">Ejecutar diagnóstico</a>
    </div>
</section>
