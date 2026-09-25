<?php
$projects = panel_projects($CONFIG);
$dbResult = $_SESSION['db_result'] ?? null;
unset($_SESSION['db_result']);
?>
<div class="section-header">
    <h2>Proyectos</h2>
    <button type="button" class="btn btn-primary" data-open-modal="modal-create">+ Nuevo proyecto</button>
</div>

<section class="grid grid-3">
    <?php if ($projects === []): ?>
        <div class="card empty">
            <p>No hay proyectos creados aún en <code>/var/www/</code>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <div class="card project">
                <div class="project-top">
                    <div>
                        <h3><?= e($project['name']) ?></h3>
                        <a class="link" href="https://<?= e($project['fqdn']) ?>" target="_blank" rel="noopener">https://<?= e($project['fqdn']) ?></a>
                    </div>
                    <?php if ($project['has_db']): ?>
                        <span class="tag tag-ok" title="Base de datos">BD</span>
                    <?php else: ?>
                        <span class="tag">Sin BD</span>
                    <?php endif; ?>
                </div>
                <ul class="meta">
                    <?php if ($project['has_git']): ?><li><strong>Git:</strong> <?= e($project['git']) ?></li><?php endif; ?>
                    <?php if ($project['has_db']): ?><li><strong>Base:</strong> <?= e($project['db_name']) ?></li><?php endif; ?>
                    <?php if ($project['size'] !== ''): ?><li><strong>Tamaño:</strong> <?= e($project['size']) ?></li><?php endif; ?>
                    <li><strong>Actualizado:</strong> <?= e($project['updated']) ?></li>
                </ul>
                <div class="project-actions">
                    <form method="POST" action="/" data-loading="Aprovisionando base de datos…">
                        <input type="hidden" name="action" value="project_db">
                        <input type="hidden" name="_page" value="projects">
                        <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                        <?= panel_csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm"><?= $project['has_db'] ? 'Regenerar BD' : 'Crear BD' ?></button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm" data-delete-project="<?= e($project['name']) ?>" data-base="<?= $project['is_base'] ? '1' : '0' ?>">Eliminar</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<div id="modal-create" class="modal-overlay">
    <div class="modal">
        <h3>Nuevo proyecto</h3>
        <p>Crea un subdominio con su carpeta <code>public_html</code> y, opcionalmente, su base de datos.</p>
        <form method="POST" action="/" data-loading="Creando proyecto…">
            <input type="hidden" name="action" value="project_create">
            <input type="hidden" name="_page" value="projects">
            <?= panel_csrf_field() ?>
            <div class="field">
                <span>Nombre del proyecto (subdominio)</span>
                <input type="text" name="project_name" class="input" pattern="^[a-z0-9]([a-z0-9-]*[a-z0-9])?$" required placeholder="ej. tienda, api-rest">
            </div>

            <div class="switch">
                <span class="switch-label">Crear base de datos
                    <small>Genera la base MariaDB, el usuario y el archivo <code>.env</code>.</small>
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
                <div class="field hidden" id="db-name-field">
                    <span>Nombre de la base de datos</span>
                    <input type="text" name="db_name" class="input" pattern="^[a-z0-9_]{1,64}$" placeholder="mi_base_datos">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear proyecto</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-delete" class="modal-overlay">
    <div class="modal">
        <h3>Eliminar proyecto</h3>
        <div id="delete-warning" class="warn-box"></div>
        <form method="POST" action="/" data-loading="Eliminando proyecto…">
            <input type="hidden" name="action" value="project_delete">
            <input type="hidden" name="_page" value="projects">
            <input type="hidden" name="project_name" id="delete-name" value="">
            <input type="hidden" name="force" id="delete-force" value="0">
            <?= panel_csrf_field() ?>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-danger">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($dbResult): ?>
    <div id="modal-dbresult" class="modal-overlay" data-auto-open>
        <div class="modal">
            <h3>Base de datos aprovisionada</h3>
            <p>El proyecto <strong><?= e($dbResult['project']) ?></strong> ya está listo para conectarse.</p>
            <dl class="kv kv-inline">
                <div>
                    <dt>Proyecto</dt>
                    <dd><a href="https://<?= e($dbResult['project'] . '.' . $CONFIG['BASE_DOMAIN']) ?>" target="_blank" rel="noopener">https://<?= e($dbResult['project'] . '.' . $CONFIG['BASE_DOMAIN']) ?></a></dd>
                </div>
                <div><dt>Base de datos</dt><dd><code><?= e($dbResult['database']) ?></code></dd></div>
                <div><dt>Usuario</dt><dd><code><?= e($dbResult['user']) ?></code></dd></div>
                <div><dt>Contraseña</dt><dd><code><?= e($dbResult['pass']) ?></code></dd></div>
                <div><dt>Puerto</dt><dd><code><?= e($dbResult['port']) ?></code></dd></div>
                <div><dt>Host</dt><dd><code><?= e($dbResult['host']) ?></code></dd></div>
            </dl>
            <div class="warn-box">
                Las credenciales quedan guardadas en la raíz del proyecto (<code>public_html/.env</code>). Mantén ese archivo protegido, no lo subas a repositorios públicos y usa esta contraseña únicamente para este proyecto.
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-copy="<?= e($dbResult['pass']) ?>">Copiar contraseña</button>
                <a class="btn btn-outline" href="https://<?= e($CONFIG['DB_FQDN']) ?>" target="_blank" rel="noopener">Abrir phpMyAdmin</a>
                <button type="button" class="btn btn-primary" data-close-modal>Entendido</button>
            </div>
        </div>
    </div>
<?php endif; ?>
