<?php
$downloads = panel_downloads();
?>
<section class="card">
    <div class="card-head"><h2>Recursos descargables</h2></div>
    <p class="muted">Instaladores y certificados para equipos cliente.</p>
    <ul class="list">
        <?php foreach ($downloads as $file): ?>
            <li class="list-row">
                <span><?= e($file['label']) ?> <code class="muted"><?= e($file['name']) ?></code></span>
                <?php if ($file['exists']): ?>
                    <a class="btn btn-outline btn-sm" href="/downloads/<?= e($file['name']) ?>" download>Descargar</a>
                <?php else: ?>
                    <span class="tag tag-err">No disponible</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="card">
    <div class="card-head"><h2>Conexión al servidor</h2></div>
    <dl class="kv">
        <div><dt>Recurso Samba</dt><dd><code>\\<?= e($CONFIG['SERVER_IP']) ?>\proyectos</code></dd></div>
        <div><dt>Dashboard</dt><dd><code>https://<?= e($CONFIG['BASE_DOMAIN']) ?></code></dd></div>
        <div><dt>Producción</dt><dd><code>https://<?= e($CONFIG['PROD_FQDN']) ?></code></dd></div>
        <div><dt>Staging</dt><dd><code>https://<?= e($CONFIG['STG_FQDN']) ?></code></dd></div>
        <div><dt>phpMyAdmin</dt><dd><code>https://<?= e($CONFIG['DB_FQDN']) ?></code></dd></div>
    </dl>
</section>
