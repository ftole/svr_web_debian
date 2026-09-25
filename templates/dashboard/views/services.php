<?php
$php = panel_php_version();
[$composerCode, $composerOut] = panel_run('composer --version');
$composerVersion = $composerCode === 0 ? trim(explode(' ', $composerOut)[2] ?? 'N/D') : 'N/D';
[$redisCode, $redisOut] = panel_run('redis-cli ping');
$redisOk = $redisCode === 0 && trim($redisOut) === 'PONG';
?>
<section class="card">
    <div class="card-head"><h2>Estado de servicios</h2></div>
    <ul class="list">
        <?php foreach ($services as $service): ?>
            <li class="list-row">
                <span><span class="dot <?= $service['active'] ? 'ok' : 'err' ?>"></span><?= e($service['label']) ?> <code class="muted"><?= e($service['unit']) ?></code></span>
                <span class="tag <?= $service['active'] ? 'tag-ok' : 'tag-err' ?>"><?= $service['active'] ? 'Activo' : 'Inactivo' ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="grid grid-3">
    <div class="card metric">
        <span class="metric-label">PHP</span>
        <span class="metric-value"><?= e($php) ?></span>
        <span class="metric-foot">FastCGI (FPM)</span>
    </div>
    <div class="card metric">
        <span class="metric-label">Composer</span>
        <span class="metric-value"><?= e($composerVersion) ?></span>
        <span class="metric-foot">/usr/local/bin/composer</span>
    </div>
    <div class="card metric">
        <span class="metric-label">Redis</span>
        <span class="metric-value"><?= $redisOk ? 'PONG' : 'N/D' ?></span>
        <span class="metric-foot">127.0.0.1:6379</span>
    </div>
</section>
