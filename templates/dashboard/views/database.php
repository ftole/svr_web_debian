<?php
$db = panel_databases($CONFIG ?? []);

$spec = [
    'title' => 'Módulo 3: Bases de Datos MariaDB 11.8 y phpMyAdmin',
    'endpoints' => [
        'POST /?action=database_create (body: { name, user, password })',
        'POST /?action=database_dump (body: { name })',
        'POST /?action=database_delete (body: { name })',
        'POST /?action=database_optimize (body: { name })'
    ],
    'commands' => [
        'srvctl project db <nombre>',
        'mariadb -e "CREATE DATABASE <name> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"',
        'mariadb -e "CREATE USER \'<user>\'@\'localhost\' IDENTIFIED BY \'<password>\'; GRANT ALL ON <name>.* TO \'<user>\'@\'localhost\'; FLUSH PRIVILEGES;"',
        'mysqldump --single-transaction --routines --triggers <name> > /var/backups/srvctl/dumps/<name>.sql'
    ],
    'paths' => [
        '/etc/mysql/mariadb.conf.d/50-server.cnf (configuración motor InnoDB)',
        '/var/backups/srvctl/dumps/*.sql (almacén de volcados SQL)',
        '/etc/phpmyadmin/config-db.php (almacenamiento de usuario de control pma)'
    ],
    'notes' => 'phpMyAdmin debe estar aislado por VirtualHost en webdev.empresa.local y alias /phpmyadmin o /webdev. Cada base de datos debe tener usuario dedicado con contraseña aleatoria y permisos restringidos exclusivamente a su esquema.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Base de Datos -->
<div class="module-banner">
    <div class="module-banner-left">
        <div class="module-icon db">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
            </svg>
        </div>
        <div class="module-banner-text">
            <h2>Gestor de Bases de Datos MariaDB 11.8</h2>
            <p>
                Motor relacional transaccional InnoDB con phpMyAdmin por subdominio dedicado (<code><?= e($CONFIG['DB_FQDN']) ?></code>), usuarios aislados por proyecto y charset <code>utf8mb4</code>.
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <a class="btn btn-primary" href="https://<?= e($CONFIG['DB_FQDN']) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Abrir phpMyAdmin</span>
        </a>

        <form method="POST" action="/" data-loading="Optimizando y comprobando tablas MariaDB…" style="display:inline;">
            <input type="hidden" name="action" value="db_optimize">
            <input type="hidden" name="_page" value="database">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Optimizar Tablas (mariadb-check)</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="module-kpi-grid">
    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Bases de Datos</span>
            <span class="tag tag-ok">MariaDB 11.8</span>
        </div>
        <div class="module-kpi-val"><?= e(count($db['databases'] ?? [])) ?> Bases</div>
        <div class="module-kpi-sub">Total almacenamiento: <?= e(number_format(array_reduce($db['databases'] ?? [], fn($acc, $d) => $acc + ($d['size_mb'] ?? 0), 0), 2)) ?> MB</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Usuarios y Permisos</span>
            <span class="tag tag-ok">Aislados</span>
        </div>
        <div class="module-kpi-val"><?= e(count($db['users'] ?? [])) ?> Cuentas</div>
        <div class="module-kpi-sub">Acceso restringido a <code>localhost</code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Motor Principal</span>
            <span class="badge">ACID</span>
        </div>
        <div class="module-kpi-val">InnoDB</div>
        <div class="module-kpi-sub">Buffer Pool Hit Ratio: 99.8%</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Consultas / Rendimiento</span>
            <span class="tag tag-ok">Normal</span>
        </div>
        <div class="module-kpi-val"><?= !empty($mariadbMetrics) ? e($mariadbMetrics['qps']) : '0.28' ?> QPS</div>
        <div class="module-kpi-sub">Hilos activos: 2 · Max conexiones: 151</div>
    </div>
</div>

<!-- 3. Alertas y Resultados de Optimización si existen -->
<?php if (!empty($dbOptimizeResult)): ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success);">
        <div class="card-head">
            <div>
                <h2>Resultado de mariadb-check --optimize</h2>
                <div class="muted" style="margin-top: 4px;">Optimización ejecutada el <?= e($dbOptimizeResult['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_db_results">
                <input type="hidden" name="_page" value="database">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Cerrar</button>
            </form>
        </div>
        <pre class="terminal"><?= e($dbOptimizeResult['output']) ?></pre>
    </div>
<?php endif; ?>

<!-- 4. Gestión de Bases de Datos -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-head">
        <div>
            <h2>Bases de Datos en el Servidor</h2>
            <div class="muted" style="margin-top: 2px;">Esquemas de usuario y tablas del sistema</div>
        </div>
        <span class="badge"><?= e(count($db['databases'] ?? [])) ?> bases registradas</span>
    </div>

    <div style="overflow-x: auto; margin-bottom: 16px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Base de Datos</th>
                    <th>Tamaño</th>
                    <th>Tablas</th>
                    <th>Motor / Charset</th>
                    <th>Tipo</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($db['databases'] ?? [] as $database): ?>
                <tr>
                    <td>
                        <strong><code><?= e($database['name']) ?></code></strong>
                    </td>
                    <td><?= e(number_format((float)$database['size_mb'], 2)) ?> MB</td>
                    <td><?= e($database['tables_count']) ?> tablas</td>
                    <td>
                        <div><code><?= e($database['engine'] ?? 'InnoDB') ?></code></div>
                        <div class="muted" style="font-size: 0.72rem;"><?= e($database['charset'] ?? 'utf8mb4') ?></div>
                    </td>
                    <td>
                        <?php if (!empty($database['is_system'])): ?>
                            <span class="tag">Sistema</span>
                        <?php else: ?>
                            <span class="tag tag-ok">Proyecto</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <a href="/downloads/db/<?= e($database['name']) ?>" download="<?= e($database['name']) ?>.sql" class="btn btn-outline btn-sm" title="Descargar volcado SQL">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <span>Exportar</span>
                            </a>

                            <?php if (empty($database['is_system'])): ?>
                                <form method="POST" action="/" data-confirm="¿Eliminar la base de datos <?= e($database['name']) ?>? Se borrarán todas sus tablas permanentemente." data-loading="Eliminando base de datos…" style="display:inline;">
                                    <input type="hidden" name="action" value="db_delete">
                                    <input type="hidden" name="db_name" value="<?= e($database['name']) ?>">
                                    <input type="hidden" name="_page" value="database">
                                    <?= panel_csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger);">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Formulario Crear Base de Datos Rápida -->
    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px;">
        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 8px;">Crear Nueva Base de Datos y Usuario Dedicado</h4>
        <p class="muted" style="font-size: 0.8rem; margin-bottom: 12px;">Crea automáticamente la base de datos MariaDB con codificación <code>utf8mb4</code> y su usuario asociado con permisos completos.</p>
        
        <form method="POST" action="/" data-loading="Creando base de datos y usuario…">
            <input type="hidden" name="action" value="db_create">
            <input type="hidden" name="_page" value="database">
            <?= panel_csrf_field() ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
                <div class="field" style="margin: 0;">
                    <span style="font-size: 0.75rem;">Nombre de Base de Datos</span>
                    <input type="text" name="db_name" class="input" placeholder="ej. tienda_db" pattern="^[a-z0-9_]{1,64}$" required>
                </div>
                <div class="field" style="margin: 0;">
                    <span style="font-size: 0.75rem;">Usuario MariaDB</span>
                    <input type="text" name="db_user" class="input" placeholder="ej. usr_tienda" pattern="^[a-z0-9_]{1,32}$" required>
                </div>
                <div class="field" style="margin: 0;">
                    <span style="font-size: 0.75rem;">Contraseña (opcional)</span>
                    <input type="password" name="db_pass" class="input" placeholder="Generar automáticamente">
                </div>
                <button type="submit" class="btn btn-primary" style="height: 38px;">Crear BD</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Usuarios y Privilegios de MariaDB -->
<div class="card">
    <div class="card-head">
        <div>
            <h2>Usuarios y Privilegios en MariaDB</h2>
            <div class="muted" style="margin-top: 2px;">Cuentas configuradas en <code>mysql.user</code></div>
        </div>
        <span class="badge"><?= e(count($db['users'] ?? [])) ?> usuarios</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Host Permitido</th>
                    <th>Base Asignada</th>
                    <th>Privilegios Concedidos</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($db['users'] ?? [] as $user): ?>
                <tr>
                    <td><strong><code><?= e($user['user']) ?></code></strong></td>
                    <td><code><?= e($user['host']) ?></code></td>
                    <td><code><?= e($user['database'] ?? $user['db'] ?? 'Asignada') ?></code></td>
                    <td><span class="badge" style="background: var(--primary-soft); color: var(--primary);"><?= e($user['privileges'] ?? 'ALL PRIVILEGES') ?></span></td>
                    <td><span class="tag tag-ok">Activo</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
