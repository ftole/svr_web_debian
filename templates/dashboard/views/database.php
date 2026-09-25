<?php
$db = panel_databases($CONFIG);
?>
<section class="card">
    <div class="card-head">
        <h2>Bases de datos MariaDB</h2>
        <a class="btn btn-outline btn-sm" href="https://<?= e($CONFIG['DB_FQDN']) ?>" target="_blank" rel="noopener">Abrir phpMyAdmin</a>
    </div>
    <?php if (!$db['ok']): ?>
        <div class="alert alert-warning"><?= e($db['error']) ?></div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Base de datos</th><th>Tamaño</th><th>Tablas</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($db['databases'] as $database): ?>
                <tr>
                    <td><code><?= e($database['name']) ?></code></td>
                    <td><?= number_format((float)$database['size_mb'], 2) ?> MB</td>
                    <td><?= (int)$database['tables_count'] ?></td>
                    <td><?= $database['is_system'] ? '<span class="tag">Sistema</span>' : '<span class="tag tag-ok">Usuario</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Usuarios de base de datos</h2></div>
    <?php if (!$db['ok'] || $db['users'] === []): ?>
        <p class="muted">Sin usuarios de proyecto registrados.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Usuario</th><th>Origen</th></tr></thead>
            <tbody>
            <?php foreach ($db['users'] as $user): ?>
                <tr><td><code><?= e($user['user']) ?></code></td><td><?= e($user['host']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
