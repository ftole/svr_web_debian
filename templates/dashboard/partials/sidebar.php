<?php
$nav = [
    'overview'    => ['Resumen', 'M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    'projects'    => ['Proyectos', 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
    'database'    => ['Bases de datos', 'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zm8 6v6c0 1.7-3.6 3-8 3s-8-1.3-8-3V9c0 1.7 3.6 3 8 3s8-1.3 8-3z'],
    'services'    => ['Servicios', 'M4 7h16M4 12h16M4 17h16'],
    'security'    => ['Seguridad', 'M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z'],
    'ssl'         => ['Certificados', 'M12 3a4 4 0 0 1 4 4v2h1a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h1V7a4 4 0 0 1 4-4zm0 2a2 2 0 0 0-2 2v2h4V7a2 2 0 0 0-2-2z'],
    'backups'     => ['Respaldos', 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3v10c0 1.7-3.6 3-8 3s-8-1.3-8-3zm0 5c0 1.7 3.6 3 8 3s8-1.3 8-3'],
    'diagnostics' => ['Diagnóstico', 'M3 12h4l2-5 4 10 2-5h6'],
    'downloads'   => ['Descargas', 'M12 3v12m0 0l-4-4m4 4l4-4M4 21h16'],
    'settings'    => ['Configuración', 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.9 4a7 7 0 0 0-.1-1.1l2-1.6-2-3.4-2.4 1a7 7 0 0 0-1.9-1.1L16 3H8l-.5 2.8a7 7 0 0 0-1.9 1.1l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 1.9 1.1L8 21h8l.5-2.8a7 7 0 0 0 1.9-1.1l2.4 1 2-3.4-2-1.6c.1-.4.1-.7.1-1.1z'],
];
?>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">srvctl</span>
        <span class="brand-name"><?= e(PANEL_NAME) ?></span>
    </div>
    <nav class="nav">
        <?php foreach ($nav as $key => [$label, $path]): ?>
            <a class="nav-item <?= $page === $key ? 'active' : '' ?>" href="<?= e(panel_url($key)) ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="<?= e($path) ?>"/></svg>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <span class="badge badge-soft">v<?= e(PANEL_VERSION) ?></span>
        <a class="nav-item" href="?action=logout">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H4m0 0l3-3m-3 3l3 3M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5"/></svg>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>
