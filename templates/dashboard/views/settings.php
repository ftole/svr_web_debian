<?php
$overview = panel_overview($CONFIG);
$safeConfig = [
    'SERVER_IP'   => $CONFIG['SERVER_IP'],
    'BASE_DOMAIN' => $CONFIG['BASE_DOMAIN'],
    'PROD_SUB'    => $CONFIG['PROD_SUB'],
    'STG_SUB'     => $CONFIG['STG_SUB'],
    'DB_SUB'      => $CONFIG['DB_SUB'],
    'ADMIN_USER'  => $CONFIG['ADMIN_USER'],
];
?>
<section class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Parámetros de configuración</h2></div>
        <p class="muted">Origen: <code>/etc/srvctl.conf</code> (la contraseña no se muestra).</p>
        <dl class="kv">
            <?php foreach ($safeConfig as $key => $value): ?>
                <div><dt><?= e($key) ?></dt><dd><code><?= e((string)$value) ?></code></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>

    <div class="card">
        <div class="card-head"><h2>Entorno</h2></div>
        <dl class="kv">
            <div><dt>Panel</dt><dd>v<?= e(PANEL_VERSION) ?></dd></div>
            <div><dt>Hostname</dt><dd><?= e($overview['hostname']) ?></dd></div>
            <div><dt>Sistema</dt><dd><?= e($overview['os']) ?></dd></div>
            <div><dt>Kernel</dt><dd><?= e($overview['kernel']) ?></dd></div>
            <div><dt>PHP</dt><dd><?= e($overview['php']) ?></dd></div>
        </dl>
    </div>
</section>
