<?php
$downloads = panel_downloads();
$spec = [
    'title' => 'Módulo 9: Descargas de Clientes y Scripts de Aprovisionamiento',
    'endpoints' => [
        'GET /downloads/rootCA.crt (Descarga del certificado de la Autoridad Raíz)',
        'GET /downloads/configurar-cliente.bat (Script Windows cliente 1-clic)',
        'GET /downloads/configurar-desarrollador.bat (Script Windows desarrollador 1-clic)',
        'GET /downloads/dumps/:filename (Descarga directa de respaldos .sql)'
    ],
    'commands' => [
        'Inyección dinámica de variables SERVER_IP y BASE_DOMAIN en plantillas .bat'
    ],
    'paths' => [
        'templates/windows/configurar-cliente.bat',
        'templates/windows/configurar-desarrollador.bat',
        '/etc/ssl/certs/rootCA.crt'
    ],
    'notes' => 'Los scripts .bat deben generarse con finales de línea CRLF (estándar Windows) e inyectar en el archivo C:\Windows\System32\drivers\etc\hosts del cliente la IP del servidor asociada a los subdominios. El script desarrollador añade EnableLinkedConnections y mapea Z:\ a \\IP\proyectos.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<div class="section-header">
    <div>
        <h2>Centro de Descargas</h2>
        <div class="muted" style="margin-top: 4px;">
            Certificado SSL raíz comodín, scripts de aprovisionamiento en 1 clic (.bat) y volcados de bases de datos MariaDB (.sql).
        </div>
    </div>
</div>

<!-- 1. Certificados y Scripts Windows (.bat) con iconos clickeables -->
<div class="download-grid">
    <!-- Paquete Completo de la Maqueta (.zip) -->
    <div class="download-card" style="border: 2px solid var(--primary); grid-column: 1 / -1;">
        <div class="download-card-body">
            <a href="/downloads/maqueta-template-srvctl.zip" download="maqueta-template-srvctl.zip" class="download-icon-btn" style="background: var(--primary-soft); color: var(--primary);" title="Descargar Maqueta Completa (.zip)">
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </a>
            <div class="download-meta">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3 class="download-title" style="color: var(--primary);">Paquete Completo de la Maqueta (Frontend Template + Guía Backend)</h3>
                    <span class="badge" style="background: var(--primary); color: #fff;">ZIP Listo</span>
                </div>
                <span class="download-filename">maqueta-template-srvctl.zip (~158 KB)</span>
                <p class="download-desc">
                    Paquete completo listo para entregar al desarrollador: incluye todas las vistas EJS maquetadas, hojas de estilo CSS (Modo Claro / Modo Oscuro), scripts interactivos JS, archivos de configuración, módulos Debian 13 y el documento de especificación técnica <strong>GUIA_DESARROLLO_BACKEND.md</strong>.
                </p>
            </div>
        </div>
        <div class="download-actions" style="gap: 8px; flex-wrap: wrap;">
            <a href="/downloads/maqueta-template-srvctl.zip" download="maqueta-template-srvctl.zip" class="btn btn-primary btn-sm" style="font-weight: 700;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar .ZIP (Windows/Mac)</span>
            </a>
            <a href="/downloads/maqueta-template-srvctl.tar.gz" download="maqueta-template-srvctl.tar.gz" class="btn btn-outline btn-sm" style="font-weight: 600;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar .TAR.GZ (Linux)</span>
            </a>
        </div>
    </div>
    <!-- Certificado CA Raíz -->
    <div class="download-card">
        <div class="download-card-body">
            <a href="/downloads/rootCA.crt" download="rootCA.crt" class="download-icon-btn download-icon-cert" title="Clic para descargar rootCA.crt">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
            </a>
            <div class="download-meta">
                <h3 class="download-title">Certificado CA Raíz</h3>
                <span class="download-filename">rootCA.crt</span>
                <p class="download-desc">
                    Instala este certificado en las <em>Entidades de certificación raíz de confianza</em> de tu equipo para validar el SSL comodín sin advertencias en el navegador.
                </p>
            </div>
        </div>
        <div class="download-actions">
            <span class="badge">X.509 PEM</span>
            <a href="/downloads/rootCA.crt" download="rootCA.crt" class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar .crt</span>
            </a>
        </div>
    </div>

    <!-- Script Cliente Windows (.bat) -->
    <div class="download-card">
        <div class="download-card-body">
            <a href="/downloads/configurar-cliente.bat" download="configurar-cliente.bat" class="download-icon-btn download-icon-bat" title="Clic para descargar configurar-cliente.bat">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="4 17 10 11 4 5"></polyline>
                    <line x1="12" y1="19" x2="20" y2="19"></line>
                </svg>
            </a>
            <div class="download-meta">
                <h3 class="download-title">Configuración Cliente</h3>
                <span class="download-filename">configurar-cliente.bat</span>
                <p class="download-desc">
                    Aprovisionamiento en 1 clic para clientes: instala el certificado CA raíz en Windows y mapea la resolución de subdominios en el archivo <code>hosts</code>.
                </p>
            </div>
        </div>
        <div class="download-actions">
            <span class="badge">Windows CMD / BAT</span>
            <a href="/downloads/configurar-cliente.bat" download="configurar-cliente.bat" class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar .bat</span>
            </a>
        </div>
    </div>

    <!-- Script Desarrollador Windows (.bat) -->
    <div class="download-card">
        <div class="download-card-body">
            <a href="/downloads/configurar-desarrollador.bat" download="configurar-desarrollador.bat" class="download-icon-btn download-icon-bat" title="Clic para descargar configurar-desarrollador.bat">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </a>
            <div class="download-meta">
                <h3 class="download-title">Entorno Desarrollador</h3>
                <span class="download-filename">configurar-desarrollador.bat</span>
                <p class="download-desc">
                    Instala CA raíz, resolución <code>hosts</code>, habilita <code>EnableLinkedConnections</code>, mapea la unidad <code>Z:\</code> a <code>\\<?= e($CONFIG[\'SERVER_IP\']) ?>\proyectos</code> y configura SSH.
                </p>
            </div>
        </div>
        <div class="download-actions">
            <span class="badge">Windows Dev / Samba</span>
            <a href="/downloads/configurar-desarrollador.bat" download="configurar-desarrollador.bat" class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar .bat</span>
            </a>
        </div>
    </div>
</div>

<!-- 2. Descargas de Bases de Datos de Proyectos (.sql) -->
<div class="card" style="margin-top: 10px;">
    <div class="card-head">
        <h2>Descarga de Bases de Datos MariaDB por Proyecto</h2>
        <span class="tag tag-ok">Exportación SQL Nativa</span>
    </div>
    <p class="muted" style="margin-bottom: 20px;">
        Descarga el volcado completo en formato <code>.sql</code> de la base de datos asociada a cualquier proyecto del servidor.
    </p>

    <div class="download-grid">
        <?php
        $dbProjects = array_filter($projects ?? [], function($p) { return !empty($p['has_db']) || !empty($p['db_name']); });
        if (empty($dbProjects)): 
        ?>
            <p class="muted">No hay proyectos con base de datos configurada.</p>
        <?php
        else:
            foreach ( as ):
                 = !empty(['db_name']) ? ['db_name'] : (['name'] . '_db');
        ?>
            <div class="download-card">
                <div class="download-card-body">
                    <a href="/downloads/db/<?= e($proj[\'name\']) ?>" download="<?= e(dbName) ?>.sql" class="download-icon-btn download-icon-sql" title="Clic para descargar <?= e(dbName) ?>.sql">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                        </svg>
                    </a>
                    <div class="download-meta">
                        <h3 class="download-title">Proyecto: <code><?= e($proj[\'name\']) ?></code></h3>
                        <span class="download-filename"><?= e(dbName) ?>.sql</span>
                        <p class="download-desc">
                            Volcado estructurado de MariaDB 11.8 (UTF-8 mb4) con esquema y datos del proyecto.
                        </p>
                    </div>
                </div>
                <div class="download-actions">
                    <span class="badge">BD MariaDB</span>
                    <a href="/downloads/db/<?= e($proj[\'name\']) ?>" download="<?= e(dbName) ?>.sql" class="btn btn-outline btn-sm">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span>Descargar SQL</span>
                    </a>
                </div>
            </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <!-- Selector rápido para cualquier proyecto -->
    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; margin-top: 8px;">
        <h4 style="font-size: 0.9rem; margin-bottom: 8px; font-weight: 600;">Descargar volcado de otro proyecto o base de datos:</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <select id="quick-db-select" class="input" style="max-width: 280px;">
                <?php foreach ( ?? [] as ): ?>
                    <option value="<?= e($p[\'name\']) ?>"><?= e($p[\'name\']) ?> (<?= e($p[\'db_name\'] ?? ($p[\'name\'] + '_db')) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-primary btn-sm" id="btn-quick-download-db">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar Base de Datos (.sql)</span>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var btn = document.getElementById('btn-quick-download-db');
    var select = document.getElementById('quick-db-select');
    if (btn && select) {
        btn.addEventListener('click', function() {
            var proj = select.value;
            if (proj) {
                var a = document.createElement('a');
                a.href = '/downloads/db/' + encodeURIComponent(proj);
                a.download = proj + '_db.sql';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        });
    }
})();
</script>
