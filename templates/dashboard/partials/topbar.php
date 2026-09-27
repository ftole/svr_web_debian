<?php
$titles = panel_page_titles();
$title = $titles[$page] ?? 'Resumen';
?>
<header class="topbar">
    <div class="topbar-left">
        <h1 id="page-title"><?= e($title) ?></h1>
        <span class="muted"><?= e($CONFIG['SERVER_IP']) ?> · <?= e($CONFIG['BASE_DOMAIN']) ?></span>
    </div>
    <div class="topbar-right">
        <span class="status <?= $activeServices === count($services) ? 'ok' : 'warn' ?>">
            <span class="dot"></span><?= (int)$activeServices ?>/<?= count($services) ?> servicios
        </span>
        <span class="badge badge-soft"><?= e((string)($_SESSION['admin_user'] ?? $CONFIG['ADMIN_USER'])) ?></span>
        <button id="theme-toggle" class="btn btn-outline btn-sm" aria-label="Cambiar tema" style="padding: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
        </button>
    </div>
</header>
