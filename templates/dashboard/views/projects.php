<?php
$projects = panel_projects($CONFIG);

$spec = [
    'title' => 'Módulo 2: Gestión de Proyectos y Alojamiento Multisitio',
    'endpoints' => [
        'POST /?action=project_create (body: { name, php_version, create_db })',
        'POST /?action=project_env_save (body: { name, content })',
        'POST /?action=project_delete (body: { name, delete_db })',
        'POST /?action=project_restore_snapshot (body: { name, snapshot_id })'
    ],
    'commands' => [
        'srvctl project create <nombre>',
        'chown -R www-data:www-data /var/www/<nombre>',
        'chmod -R 2775 /var/www/<nombre> (SGID para sincronización Samba)'
    ],
    'paths' => [
        '/var/www/<nombre>/public_html/ (raíz pública del sitio)',
        '/var/www/<nombre>/public_html/.env (archivo de entorno dedicado)',
        '/var/www/_dashboard (directorio reservado del sistema)'
    ],
    'notes' => 'Soporte de ruteo dual obligatorio: Apache mod_vhost_alias mapea *.empresa.local a /var/www/%1/public_html, y 00-dashboard.conf mapea ruta directa empresa.local/<nombre>/ con forzado de barra final 301. Si el directorio no existe deriva a /not_found.php.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Proyectos -->
<div class="module-banner">
    <div class="module-banner-left">
        <div class="module-icon prj">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <div class="module-banner-text">
            <h2>Gestión de Proyectos y Alojamiento Multisitio</h2>
            <p>
                Ruteo dual simultáneo en Apache 2.4: subdominios dinámicos sin configuración (<code>https://&lt;proyecto&gt;.<?= e($CONFIG['BASE_DOMAIN']) ?></code>) y acceso por ruta (<code>https://<?= e($CONFIG['BASE_DOMAIN']) ?>/&lt;proyecto&gt;/</code>).
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <button type="button" class="btn btn-primary" data-open-modal="modal-create">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>+ Nuevo Proyecto</span>
        </button>

        <form method="POST" action="/" data-loading="Auditando permisos y ruteo de proyectos…" style="display:inline;">
            <input type="hidden" name="action" value="projects_verify_permissions">
            <input type="hidden" name="_page" value="projects">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                <span>Verificar Permisos SGID</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="module-kpi-grid">
    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Proyectos Alojados</span>
            <span class="tag tag-ok">/var/www</span>
        </div>
        <div class="module-kpi-val"><?= e(count($projects)) ?> Proyectos</div>
        <div class="module-kpi-sub">DocumentRoot dinámico en <code>public_html</code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Ruteo Dinámico</span>
            <span class="tag tag-ok">mod_vhost_alias</span>
        </div>
        <div class="module-kpi-val">Acceso Dual</div>
        <div class="module-kpi-sub">Subdominio comodín y ruta con barra final (301)</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Bases de Datos Aprovisionadas</span>
            <span class="badge">MariaDB</span>
        </div>
        <div class="module-kpi-val"><?= e(count(array_filter($projects, fn($p) => !empty($p['has_db'])))) ?> Activas</div>
        <div class="module-kpi-sub">Credenciales automáticas en <code>.env</code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Permisos y Samba</span>
            <span class="tag tag-ok">2775</span>
        </div>
        <div class="module-kpi-val">SGID www-data</div>
        <div class="module-kpi-sub">Mapeado en recurso de red <code>[proyectos]</code></div>
    </div>
</div>

<!-- 3. Resultado de Verificación de Permisos si existe -->
<?php if (!empty($projectVerifyResult)): ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success);">
        <div class="card-head">
            <div>
                <h2>Auditoría de Permisos y Ruteo de Proyectos</h2>
                <div class="muted" style="margin-top: 4px;">Comprobación realizada el <?= e($projectVerifyResult['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_project_results">
                <input type="hidden" name="_page" value="projects">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Cerrar</button>
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
                <?php foreach ($projectVerifyResult['checks'] ?? [] as $c): ?>
                    <tr>
                        <td><strong><?= e($c['item']) ?></strong></td>
                        <td><span class="tag tag-ok"><?= e($c['status']) ?></span></td>
                        <td><code><?= e($c['detail']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- 4. Barra de Búsqueda y Filtro de Proyectos -->
<div class="project-search-container">
    <div style="flex: 1; min-width: 260px;">
        <input type="text" id="project-filter-input" class="input" placeholder="Buscar proyectos por nombre, subdominio o base de datos…">
    </div>
    <div class="muted" style="font-size: 0.85rem;" id="project-count-text">
        Mostrando <?= e(count($projects)) ?> proyectos en <code>/var/www/</code>
    </div>
</div>

<!-- 5. Grid de Tarjetas de Proyecto -->
<section class="grid grid-3" id="projects-card-grid">
    <?php if (empty($projects)): ?>
        <div class="card empty" style="grid-column: 1 / -1; padding: 40px; text-align: center;">
            <p class="muted">No hay proyectos creados aún en <code>/var/www/</code>.</p>
            <button type="button" class="btn btn-primary" data-open-modal="modal-create">+ Crear el primer proyecto</button>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <div class="card project project-card-item" data-project-name="<?= e($project['name']) ?>" data-project-db="<?= e($project['db_name'] ?? '') ?>">
                <div class="project-top">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <h3 style="margin: 0;"><?= e($project['name']) ?></h3>
                            <span class="project-dual-badge">Dual Access</span>
                        </div>
                        <div style="margin-bottom: 2px;">
                            <a class="link" href="https://<?= e($project['fqdn']) ?>" target="_blank" rel="noopener" style="font-weight: 700;">
                                https://<?= e($project['fqdn']) ?>
                            </a>
                        </div>
                        <div class="muted" style="font-size: 0.8rem;">
                            Ruta base: <a href="https://<?= e($CONFIG['BASE_DOMAIN']) ?>/<?= e($project['name']) ?>/" target="_blank" rel="noopener">/<?= e($project['name']) ?>/</a>
                        </div>
                    </div>
                    <?php if (!empty($project['has_db'])): ?>
                        <span class="tag tag-ok" title="Base de datos MariaDB activa">BD</span>
                    <?php else: ?>
                        <span class="tag" title="Sin base de datos asociada">Sin BD</span>
                    <?php endif; ?>
                </div>

                <ul class="meta" style="margin-top: 12px; margin-bottom: 16px;">
                    <li><strong>Ruta:</strong> <code>/var/www/<?= e($project['name']) ?>/public_html</code></li>
                    <?php if (!empty($project['has_db'])): ?><li><strong>Base:</strong> <code><?= e($project['db_name']) ?></code></li><?php endif; ?>
                    <?php if (!empty($project['has_git'])): ?><li><strong>Git:</strong> <code><?= e($project['git']) ?></code></li><?php endif; ?>
                    <li><strong>Tamaño:</strong> <?= e($project['size'] ?? '24.5M') ?></li>
                    <li><strong>Modificado:</strong> <?= e($project['updated']) ?></li>
                </ul>

                <div class="project-actions" style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <a href="https://<?= e($project['fqdn']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" title="Abrir sitio web">
                        Abrir
                    </a>
                    <button type="button" class="btn btn-outline btn-sm" data-open-modal="modal-env-<?= e($project['name']) ?>" title="Editar configuración .env">
                        .env
                    </button>
                    <?php if (!empty($project['has_db'])): ?>
                        <a href="/downloads/db/<?= e($project['name']) ?>" download="<?= e($project['db_name'] ?? $project['name']) ?>.sql" class="btn btn-outline btn-sm" title="Descargar copia SQL">
                            SQL
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline btn-sm" data-open-modal="modal-restore-<?= e($project['name']) ?>" title="Restaurar desde snapshot">
                        Restaurar
                    </button>
                    <form method="POST" action="/" data-loading="Aprovisionando base de datos…" style="display:inline;">
                        <input type="hidden" name="action" value="project_db">
                        <input type="hidden" name="_page" value="projects">
                        <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                        <?= panel_csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm"><?= !empty($project['has_db']) ? 'Regenerar BD' : 'Crear BD' ?></button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm" data-delete-project="<?= e($project['name']) ?>" data-base="<?= !empty($project['is_base']) ? '1' : '0' ?>">
                        Eliminar
                    </button>
                </div>
            </div>

            <!-- Modal Env para <?= e($project['name']) ?> -->
            <div id="modal-env-<?= e($project['name']) ?>" class="modal-overlay">
                <div class="modal">
                    <h3>Configuración de Entorno (.env) - <?= e($project['name']) ?></h3>
                    <p class="muted" style="margin-bottom: 12px;">Variables de entorno almacenadas de forma aislada en <code>/var/www/<?= e($project['name']) ?>/public_html/.env</code>.</p>
                    <form method="POST" action="/" data-loading="Guardando .env…">
                        <input type="hidden" name="action" value="project_env_save">
                        <input type="hidden" name="_page" value="projects">
                        <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                        <?= panel_csrf_field() ?>
                        <div class="field">
                            <textarea name="env_content" class="input" rows="12" style="font-family: var(--font-mono); font-size: 0.85rem;"><?= e($project['env'] ?? '') ?></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Restore para <?= e($project['name']) ?> -->
            <div id="modal-restore-<?= e($project['name']) ?>" class="modal-overlay">
                <div class="modal">
                    <h3>Restaurar Archivos desde Snapshot - <?= e($project['name']) ?></h3>
                    <p class="muted" style="margin-bottom: 12px;">Se restaurará exclusivamente el directorio de este proyecto desde el snapshot seleccionado.</p>
                    <form method="POST" action="/" data-loading="Restaurando snapshot…">
                        <input type="hidden" name="action" value="backup_restore_project">
                        <input type="hidden" name="_page" value="projects">
                        <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                        <?= panel_csrf_field() ?>
                        <div class="field">
                            <span>Snapshot de Origen</span>
                            <select name="snapshot_name" class="input" required>
                                <?php foreach ($backups['snapshots'] ?? [] as $s): ?>
                                    <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?> (<?= e($s['date']) ?> <?= !empty($s['today']) ? '· Hoy' : '' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                            <button type="submit" class="btn btn-primary">Restaurar Proyecto</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Modal Create -->
<div id="modal-create" class="modal-overlay">
    <div class="modal">
        <h3>Crear Nuevo Proyecto Web</h3>
        <p class="muted" style="margin-bottom: 14px;">Aprovisiona el subdominio dinámico, la carpeta <code>public_html/</code> con permisos SGID 2775 y, opcionalmente, la base de datos MariaDB.</p>
        <form method="POST" action="/" data-loading="Creando proyecto…">
            <input type="hidden" name="action" value="project_create">
            <input type="hidden" name="_page" value="projects">
            <?= panel_csrf_field() ?>
            <div class="field">
                <span>Nombre del proyecto / Subdominio</span>
                <input type="text" name="project_name" class="input" pattern="^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$" required placeholder="ej. tienda, portal, api-crm">
            </div>

            <div class="switch" style="margin-bottom: 12px;">
                <span class="switch-label">Crear base de datos MariaDB dedicada
                    <small>Genera la base, usuario dedicado y archivo <code>.env</code> automáticamente.</small>
                </span>
                <input type="checkbox" name="create_db" id="create_db" value="1" checked>
            </div>

            <div id="db-options">
                <div class="switch">
                    <span class="switch-label">Nombre de base de datos personalizado
                        <small>Por defecto se usa <code>&lt;proyecto&gt;_db</code>.</small>
                    </span>
                    <input type="checkbox" name="custom_db" id="custom_db" value="1">
                </div>
                <div class="field hidden" id="db-name-field" style="margin-top: 10px;">
                    <span>Nombre de la base de datos</span>
                    <input type="text" name="db_name" class="input" pattern="^[a-z0-9_]{1,64}$" placeholder="mi_base_datos">
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 20px;">
                <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Proyecto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Delete -->
<div id="modal-delete" class="modal-overlay">
    <div class="modal">
        <h3>Eliminar Proyecto</h3>
        <div id="delete-warning" class="warn-box" style="margin-bottom: 14px;"></div>
        <form method="POST" action="/" data-loading="Eliminando proyecto…">
            <input type="hidden" name="action" value="project_delete">
            <input type="hidden" name="_page" value="projects">
            <input type="hidden" name="project_name" id="delete-name" value="">
            <input type="hidden" name="force" id="delete-force" value="0">
            <?= panel_csrf_field() ?>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-danger">Sí, eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal DB Result si se acaba de crear -->
<?php if (!empty($dbResult)): ?>
    <div id="modal-dbresult" class="modal-overlay" data-auto-open>
        <div class="modal">
            <h3>Base de Datos Aprovisionada con Éxito</h3>
            <p>El proyecto <strong><?= e($dbResult['project']) ?></strong> ya está configurado y listo para conectarse.</p>
            <dl class="kv kv-inline" style="margin-bottom: 14px;">
                <div>
                    <dt>Subdominio</dt>
                    <dd><a href="https://<?= e($dbResult['project']) ?>.<?= e($CONFIG['BASE_DOMAIN']) ?>" target="_blank" rel="noopener">https://<?= e($dbResult['project']) ?>.<?= e($CONFIG['BASE_DOMAIN']) ?></a></dd>
                </div>
                <div><dt>Base de datos</dt><dd><code><?= e($dbResult['database']) ?></code></dd></div>
                <div><dt>Usuario</dt><dd><code><?= e($dbResult['user']) ?></code></dd></div>
                <div><dt>Contraseña</dt><dd><code><?= e($dbResult['pass']) ?></code></dd></div>
                <div><dt>Puerto</dt><dd><code><?= e($dbResult['port']) ?></code></dd></div>
                <div><dt>Host</dt><dd><code><?= e($dbResult['host']) ?></code></dd></div>
            </dl>
            <div class="warn-box" style="margin-bottom: 16px;">
                Las credenciales han sido inyectadas en <code>public_html/.env</code>. El archivo <code>.env</code> está protegido contra accesos externos vía Apache.
            </div>
            <div class="modal-actions">
                <a class="btn btn-outline" href="https://<?= e($CONFIG['DB_FQDN']) ?>" target="_blank" rel="noopener">Abrir phpMyAdmin</a>
                <button type="button" class="btn btn-primary" data-close-modal>Entendido</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Script de Filtrado Rápido de Proyectos -->
<script>
(function() {
    var searchInput = document.getElementById('project-filter-input');
    var countText = document.getElementById('project-count-text');
    var cards = document.querySelectorAll('.project-card-item');

    if (!searchInput || !cards.length) return;

    searchInput.addEventListener('input', function() {
        var query = searchInput.value.toLowerCase().trim();
        var visibleCount = 0;

        cards.forEach(function(card) {
            var name = card.getAttribute('data-project-name') || '';
            var db = card.getAttribute('data-project-db') || '';
            var text = card.textContent.toLowerCase();

            if (!query || name.indexOf(query) !== -1 || db.indexOf(query) !== -1 || text.indexOf(query) !== -1) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (countText) {
            countText.textContent = 'Mostrando ' + visibleCount + ' de ' + cards.length + ' proyectos';
        }
    });
})();
</script>
