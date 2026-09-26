<?php
$backups = panel_backups();
$projects = panel_projects($CONFIG);
$spec = [

        'title' => 'Módulo 7: Respaldos Diarios Rotativos de 7 Días y Volcados SQL',
        'endpoints' => [
            'POST /?action=backup_create_now',
            'POST /?action=backup_restore_snapshot (body: { snapshot_index })',
            'POST /?action=backup_restore_dump (body: { dump_filename })'
        ],
        'commands' => [
            'srvctl backup now',
            'rsync -a --delete --link-dest=/var/backups/srvctl/daily.1/www/ /var/www/ /var/backups/srvctl/daily.0/www/',
            'mariadb <db> < /var/backups/srvctl/dumps/<dump>.sql'
        ],
        'paths' => [
            '/var/backups/srvctl/daily.0/ a daily.6/ (snapshots rotativos)',
            '/var/backups/srvctl/dumps/*.sql (volcados estructurados de MariaDB)',
            '/etc/cron.daily/srvctl-backup (automatización diaria en Debian)'
        ],
        'notes' => 'Rotación determinista de 7 días: daily.0 representa el backup más reciente. Cada nuevo ciclo rota los índices daily.(N-1) -> daily.N y elimina daily.6 mediante hardlinks deduplicados para minimizar uso de disco.'
    
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Respaldos -->
<div class="backup-banner">
    <div class="backup-banner-left">
        <div class="backup-shield-icon">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M9 12l2 2 4-4"></path>
            </svg>
        </div>
        <div class="backup-banner-text">
            <h2>Respaldos del Sistema y Recuperación ante Desastres</h2>
            <p>
                Snapshots diarios rotativos de 7 días (rsync con hardlinks deduplicados en <code><?= e($backups['dir']) ?></code>) y volcados MariaDB 11.8.
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <form method="POST" action="/" data-loading="Generando snapshot y volcados SQL…" style="display:inline;">
            <input type="hidden" name="action" value="backup_run">
            <input type="hidden" name="_page" value="backups">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Crear Respaldo Ahora</span>
            </button>
        </form>

        <form method="POST" action="/" data-loading="Verificando integridad de respaldos…" style="display:inline;">
            <input type="hidden" name="action" value="backup_verify_integrity">
            <input type="hidden" name="_page" value="backups">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>Verificar Integridad</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="backup-kpi-grid">
    <div class="backup-kpi-card">
        <div class="backup-kpi-head">
            <span class="backup-kpi-title">Snapshots Web Activos</span>
            <span class="tag tag-ok"><?= count($backups['snapshots'] ?? []) ?> / 7 Días</span>
        </div>
        <div class="backup-kpi-val"><?= (!empty($backups['snapshots'])) ? e($backups['snapshots'][0]['name']) : 'Ninguno' ?></div>
        <div class="backup-kpi-sub">Rotación automática en <code><?= e($backups['dir'] ?? '/var/backups/srvctl') ?>/snapshots</code></div>
    </div>

    <div class="backup-kpi-card">
        <div class="backup-kpi-head">
            <span class="backup-kpi-title">Almacenamiento de Respaldos</span>
            <span class="tag tag-ok">Deduplicado</span>
        </div>
        <div class="backup-kpi-val"><?= e($backups['total_size'] ?? '428.5 MB') ?></div>
        <div class="backup-kpi-sub"><?= e($backups['disk_free'] ?? '82.0 GB') ?> libres en el volumen de sistema</div>
    </div>

    <div class="backup-kpi-card">
        <div class="backup-kpi-head">
            <span class="backup-kpi-title">Volcados MariaDB (.sql.gz)</span>
            <span class="badge">GZIP Nivel 9</span>
        </div>
        <div class="backup-kpi-val"><?= count($backups['dumps'] ?? []) ?> Archivos</div>
        <div class="backup-kpi-sub">Todas las BD y volcados individuales por proyecto</div>
    </div>

    <div class="backup-kpi-card">
        <div class="backup-kpi-head">
            <span class="backup-kpi-title">Cron Automatizado</span>
            <span class="tag tag-ok">Activo</span>
        </div>
        <div class="backup-kpi-val">02:00 AM</div>
        <div class="backup-kpi-sub">Programado en <code><?= e($backups['cron_file'] ?? '/etc/cron.d/web-daily-backup') ?></code></div>
    </div>
</div>

<!-- 3. Alertas de Resultado / Verificación si existen -->
<?php if (!empty($backupRestoreResult)) { ?>
    <div class="backup-restore-box">
        <div>
            <div style="font-weight: 700; color: var(--primary); font-size: 0.95rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Restauración completada con éxito (<?= e($backupRestoreResult['target']) ?>)</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">
                <?= e($backupRestoreResult['details']) ?> (Origen: <code><?= e($backupRestoreResult['source']) ?></code> · <?= e($backupRestoreResult['timestamp']) ?>)
            </div>
        </div>
        <form method="POST" action="/" style="display:inline;">
            <input type="hidden" name="action" value="clear_backup_results">
            <input type="hidden" name="_page" value="backups">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-sm">Cerrar</button>
        </form>
    </div>
<?php } ?>

<?php if (!empty($backupVerify)) { ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success);">
        <div class="card-head">
            <div>
                <h2>Resultado de Verificación de Integridad de Respaldos</h2>
                <div class="muted" style="margin-top: 4px;">Comprobación exhaustiva realizada el <?= e($backupVerify['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_backup_results">
                <input type="hidden" name="_page" value="backups">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Limpiar informe</button>
            </form>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Comprobación</th>
                    <th>Estado</th>
                    <th>Detalle Técnico</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($backupVerify['checks'] ?? []) as $c) { ?>
                    <tr>
                        <td><strong><?= e($c['check']) ?></strong></td>
                        <td><span class="tag tag-ok"><?= e($c['result']) ?></span></td>
                        <td><code><?= e($c['detail']) ?></code></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<!-- 4. Snapshots Web y Volcados MariaDB -->
<div class="grid grid-2">
    <!-- Snapshots Web -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Snapshots de Archivos Web (/var/www)</h2>
                <div class="muted" style="margin-top: 2px;">Retención rotativa de 7 días con hardlinks rsync</div>
            </div>
            <span class="badge"><?= count($backups['snapshots'] ?? []) ?> snapshots</span>
        </div>

        <?php if (empty($backups['snapshots']) || empty($backups['snapshots'])) { ?>
            <p class="muted">No hay snapshots generados aún.</p>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Snapshot</th>
                            <th>Fecha</th>
                            <th>Tamaño / Hardlinks</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($backups['snapshots'] ?? []) as $snapshot) { ?>
                        <tr>
                            <td>
                                <strong><code><?= e($snapshot['name']) ?></code></strong>
                                <?php if (!empty($snapshot['today'])) { ?>
                                    <div style="margin-top: 2px;"><span class="backup-badge-today">Más reciente</span></div>
                                <?php } ?>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;"><?= e($snapshot['date']) ?></span>
                                <?php if (!empty($snapshot['files'])) { ?>
                                    <div class="muted" style="font-size: 0.75rem;"><?= e($snapshot['files']) ?> archivos</div>
                                <?php } ?>
                            </td>
                            <td>
                                <div><?= e($snapshot['size'] ?? '24.5 MB') ?></div>
                                <?php if (!empty($snapshot['hardlinks'])) { ?>
                                    <span class="backup-badge-hardlink"><?= e($snapshot['hardlinks']) ?> compartidos</span>
                                <?php } ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <a href="/downloads/snapshot_<?= e(str_replace('.', '_', $snapshot['name'])) ?>.tar.gz" download="snapshot_<?= e(str_replace('.', '_', $snapshot['name'])) ?>.tar.gz" class="btn btn-outline btn-sm" title="Descargar snapshot comprimido">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                             <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                    </a>

                                    <form method="POST" action="/" data-confirm="¿Restaurar todo /var/www desde el snapshot <?= e($snapshot['name']) ?>? Se revertirán los archivos de todos los proyectos al estado del <?= e($snapshot['date']) ?>." data-loading="Restaurando snapshot…" style="display:inline;">
                                        <input type="hidden" name="action" value="backup_rollback">
                                        <input type="hidden" name="_page" value="backups">
                                        <input type="hidden" name="snapshot" value="<?= e($snapshot['name']) ?>">
                                        <?= panel_csrf_field() ?>
                                        <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>

    <!-- Volcados MariaDB -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Volcados de Bases de Datos MariaDB</h2>
                <div class="muted" style="margin-top: 2px;">Compresión GZIP en <code><?= e($backups['dir']) ?>/database</code></div>
            </div>
            <span class="badge"><?= count($backups['dumps'] ?? []) ?> volcados</span>
        </div>

        <?php if (empty($backups['dumps']) || empty($backups['dumps'])) { ?>
            <p class="muted">No hay volcados SQL generados aún.</p>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Archivo / Alcance</th>
                            <th>Tamaño</th>
                            <th>Fecha</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($backups['dumps'] ?? [], 0, 8) as $dump) { ?>
                        <tr>
                            <td>
                                <strong><code><?= e($dump['name']) ?></code></strong>
                                <div class="muted" style="font-size: 0.75rem;"><?= e($dump['scope'] ?? 'Volcado MariaDB') ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--warning-soft); color: var(--warning);"><?= e($dump['size']) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem;"><?= e($dump['date']) ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <a href="/downloads/<?= e($dump['name']) ?>" download="<?= e($dump['name']) ?>" class="btn btn-outline btn-sm" title="Descargar volcado .sql.gz">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        <span>Descargar</span>
                                    </a>

                                    <form method="POST" action="/" data-confirm="¿Restaurar la base de datos desde <?= e($dump['name']) ?>? Los datos actuales serán reemplazados." data-loading="Restaurando volcado SQL…" style="display:inline;">
                                        <input type="hidden" name="action" value="backup_restore_db">
                                        <input type="hidden" name="_page" value="backups">
                                        <input type="hidden" name="dump_name" value="<?= e($dump['name']) ?>">
                                        <?= panel_csrf_field() ?>
                                        <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<!-- 5. Restauración Selectiva por Proyecto y Registro de Auditoría -->
<div class="grid grid-2" style="margin-top: 10px;">
    <!-- Restauración Selectiva por Proyecto -->
    <div class="card">
        <div class="card-head">
            <h2>Restauración Selectiva de Proyecto</h2>
            <span class="tag tag-ok">Aislamiento Seguro</span>
        </div>
        <p class="muted" style="margin-bottom: 16px;">
            Permite restaurar únicamente la carpeta y archivos de un proyecto específico (ej. <code>prod</code> o <code>stg</code>) desde un snapshot histórico, sin modificar los demás proyectos de <code>/var/www</code>.
        </p>

        <form method="POST" action="/" data-loading="Restaurando archivos del proyecto…">
            <input type="hidden" name="action" value="backup_restore_project">
            <input type="hidden" name="_page" value="backups">
            <?= panel_csrf_field() ?>

            <div class="form-row-2">
                <div class="field">
                    <span>Seleccionar Proyecto</span>
                    <select name="project_name" class="input" required>
                        <?php foreach (($projects ?? []) as $p) { ?>
                            <option value="<?= e($p['name']) ?>"><?= e($p['name']) ?> (/var/www/<?= e($p['name']) ?>)</option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field">
                    <span>Snapshot de Origen</span>
                    <select name="snapshot_name" class="input" required>
                        <?php foreach (($backups['snapshots'] ?? []) as $s) { ?>
                            <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?> (<?= e($s['date']) ?><?= !empty($s['today']) ? ' · Más reciente' : '' ?>)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                Restaurar Proyecto Seleccionado
            </button>
        </form>
    </div>

    <!-- Registro de Ejecución del Respaldo (/var/log/backup-daily.log) -->
    <div class="card">
        <div class="card-head">
            <h2>Registro de Ejecución (backup-daily.log)</h2>
            <span class="badge">Auditoría /var/log</span>
        </div>
        <p class="muted" style="margin-bottom: 10px;">
            Salida de la última ejecución del script <code>/opt/scripts/backup-daily.sh</code> con rotación de snapshots y volcado SQL.
        </p>
        <pre class="terminal" style="max-height: 220px;"><?= e($backups['last_log'] ?? 'No hay registros de ejecución recientes.') ?></pre>
    </div>
</div>
