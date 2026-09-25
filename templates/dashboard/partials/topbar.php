<?php
$titles = [
    'overview'    => 'Resumen',
    'projects'    => 'Proyectos',
    'database'    => 'Bases de datos',
    'services'    => 'Servicios',
    'security'    => 'Seguridad',
    'ssl'         => 'Certificados',
    'backups'     => 'Respaldos',
    'diagnostics' => 'Diagnóstico',
    'downloads'   => 'Descargas',
    'settings'    => 'Configuración',
];
$title = $titles[$page] ?? 'Resumen';
?>
<header class="topbar">
    <div class="topbar-left">
        <h1><?= e($title) ?></h1>
        <span class="muted"><?= e($CONFIG['SERVER_IP']) ?> · <?= e($CONFIG['BASE_DOMAIN']) ?></span>
    </div>
    <div class="topbar-right">
        <span class="status <?= $activeServices === count($services) ? 'ok' : 'warn' ?>">
            <span class="dot"></span><?= (int)$activeServices ?>/<?= count($services) ?> servicios
        </span>
        <span class="badge badge-soft"><?= e((string)($_SESSION['admin_user'] ?? $CONFIG['ADMIN_USER'])) ?></span>
    </div>
</header>
