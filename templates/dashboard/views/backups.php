<?php
$backups = panel_backups();
?>
<section class="card">
    <div class="card-head">
        <h2>Respaldos del sistema</h2>
        <form method="POST" action="/" data-loading="Generando respaldo…">
            <input type="hidden" name="action" value="backup_run">
            <input type="hidden" name="_page" value="backups">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">Crear respaldo ahora</button>
        </form>
    </div>
    <p class="muted">Directorio: <code><?= e($backups['dir'] ?: 'N/D') ?></code> · snapshots rotativos de 7 días.</p>
</section>

<section class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Snapshots web</h2></div>
        <?php if ($backups['snapshots'] === []): ?>
            <p class="muted">No hay snapshots generados.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Snapshot</th><th>Fecha</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($backups['snapshots'] as $snapshot): ?>
                    <tr>
                        <td><code><?= e($snapshot['name']) ?></code></td>
                        <td><?= e($snapshot['date']) ?></td>
                        <td>
                            <?php if ($snapshot['today']): ?>
                                <span class="tag tag-ok">Más reciente</span>
                            <?php else: ?>
                                <form method="POST" action="/" data-confirm="¿Restaurar /var/www desde <?= e($snapshot['name']) ?>? Se sobrescribirán los archivos actuales." data-loading="Restaurando snapshot…">
                                    <input type="hidden" name="action" value="backup_rollback">
                                    <input type="hidden" name="_page" value="backups">
                                    <input type="hidden" name="snapshot" value="<?= e($snapshot['name']) ?>">
                                    <?= panel_csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Volcados MariaDB</h2></div>
        <?php if ($backups['dumps'] === []): ?>
            <p class="muted">No hay volcados generados.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($backups['dumps'], 0, 10) as $dump): ?>
                    <tr>
                        <td><code><?= e($dump['name']) ?></code></td>
                        <td><?= e($dump['size']) ?></td>
                        <td><?= e($dump['date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
