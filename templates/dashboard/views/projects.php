<?php
$projects = panel_projects($CONFIG);
$dbResult = $_SESSION['db_result'] ?? null;
unset($_SESSION['db_result']);
?>
<?php if ($dbResult): ?>
    <section class="card cred">
        <div class="card-head"><h2>Base de datos aprovisionada · <?= e($dbResult['project']) ?></h2></div>
        <p class="muted">Credenciales depositadas en <code>public_html/.env</code>.</p>
        <dl class="kv kv-inline">
            <div><dt>Host</dt><dd><?= e($dbResult['host']) ?></dd></div>
            <div><dt>Puerto</dt><dd><?= e($dbResult['port']) ?></dd></div>
            <div><dt>Base de datos</dt><dd><?= e($dbResult['database']) ?></dd></div>
            <div><dt>Usuario</dt><dd><?= e($dbResult['user']) ?></dd></div>
            <div><dt>Contraseña</dt><dd><code><?= e($dbResult['pass']) ?></code></dd></div>
        </dl>
    </section>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2>Nuevo proyecto</h2></div>
    <form method="POST" action="?page=projects" class="form-row">
        <input type="hidden" name="action" value="project_create">
        <input type="hidden" name="_page" value="projects">
        <?= panel_csrf_field() ?>
        <input type="text" name="project_name" class="input" pattern="^[a-z0-9]([a-z0-9-]*[a-z0-9])?$" required placeholder="nombre-del-proyecto (ej. tienda, api-rest)">
        <button type="submit" class="btn btn-primary">Crear proyecto</button>
    </form>
</section>

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
                    <form method="POST" action="?page=projects">
                        <input type="hidden" name="action" value="project_db">
                        <input type="hidden" name="_page" value="projects">
                        <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                        <?= panel_csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm"><?= $project['has_db'] ? 'Regenerar BD' : 'Crear BD' ?></button>
                    </form>
                    <?php if (!$project['is_base']): ?>
                        <form method="POST" action="?page=projects" data-confirm="¿Eliminar el proyecto <?= e($project['name']) ?> y su base de datos? Esta acción es irreversible.">
                            <input type="hidden" name="action" value="project_delete">
                            <input type="hidden" name="_page" value="projects">
                            <input type="hidden" name="project_name" value="<?= e($project['name']) ?>">
                            <?= panel_csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
