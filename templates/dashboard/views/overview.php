<?php
$overview = panel_overview($CONFIG);
$projects = panel_projects($CONFIG);
$backups = panel_backups();
$ssl = panel_ssl();
?>
<section class="grid grid-4">
    <div class="card metric">
        <span class="metric-label">Tiempo activo</span>
        <span class="metric-value"><?= e($overview['uptime_text']) ?></span>
        <span class="metric-foot">Load <?= e(implode(' · ', $overview['load'])) ?></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Memoria RAM</span>
        <span class="metric-value"><?= (int)$overview['mem_percent'] ?>%</span>
        <div class="bar"><span style="width:<?= (int)$overview['mem_percent'] ?>%"></span></div>
        <span class="metric-foot"><?= e(panel_format_bytes($overview['mem_used'])) ?> de <?= e(panel_format_bytes($overview['mem_total'])) ?></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Disco (/)</span>
        <span class="metric-value"><?= (int)$overview['disk_percent'] ?>%</span>
        <div class="bar"><span style="width:<?= (int)$overview['disk_percent'] ?>%"></span></div>
        <span class="metric-foot"><?= e(panel_format_bytes($overview['disk_used'])) ?> de <?= e(panel_format_bytes($overview['disk_total'])) ?></span>
    </div>
    <div class="card metric">
        <span class="metric-label">Certificado SSL</span>
        <span class="metric-value <?= $ssl['days_left'] > 0 ? '' : 'danger' ?>"><?= $ssl['exists'] ? (int)$ssl['days_left'] . ' días' : 'N/D' ?></span>
        <span class="metric-foot"><?= $ssl['exists'] ? e($ssl['valid_to']) : 'No configurado' ?></span>
    </div>
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
        <div class="card-head"><h2>Servicios de la pila</h2></div>
        <ul class="list">
            <?php foreach ($services as $service): ?>
                <li class="list-row">
                    <span><span class="dot <?= $service['active'] ? 'ok' : 'err' ?>"></span><?= e($service['label']) ?></span>
                    <span class="tag <?= $service['active'] ? 'tag-ok' : 'tag-err' ?>"><?= $service['active'] ? 'Activo' : 'Inactivo' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="grid grid-3">
    <div class="card stat">
        <span class="stat-value"><?= count($projects) ?></span>
        <span class="stat-label">Proyectos</span>
    </div>
    <div class="card stat">
        <span class="stat-value"><?= count($backups['snapshots']) ?></span>
        <span class="stat-label">Snapshots</span>
    </div>
    <div class="card stat">
        <span class="stat-value"><?= count($backups['dumps']) ?></span>
        <span class="stat-label">Volcados SQL</span>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Acciones rápidas</h2>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="<?= e(panel_url('projects')) ?>">Gestionar proyectos</a>
        <a class="btn btn-outline" href="<?= e(panel_url('database')) ?>">Bases de datos</a>
        <a class="btn btn-outline" href="<?= e(panel_url('backups')) ?>">Respaldos</a>
        <a class="btn btn-outline" href="<?= e(panel_url('diagnostics')) ?>">Ejecutar diagnóstico</a>
    </div>
</section>
