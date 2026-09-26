<?php
$services = panel_services();
$php_version = panel_php_version();
$spec = [

        'title' => 'Módulo 4: Servicios del Sistema (Systemd)',
        'endpoints' => [
            'POST /?action=service_restart (body: { service: "apache2|php8.4-fpm|mariadb|redis-server|smbd|ufw|fail2ban" })',
            'POST /?action=service_reload (body: { service: "apache2|php8.4-fpm" })'
        ],
        'commands' => [
            'systemctl is-active <servicio>',
            'systemctl restart <servicio>',
            'systemctl reload apache2 && systemctl reload php8.4-fpm'
        ],
        'paths' => [
            '/lib/systemd/system/*.service',
            '/var/log/apache2/error.log',
            '/var/log/php8.4-fpm.log'
        ],
        'notes' => 'Las acciones sobre Systemd requieren privilegios de sudo para el backend sin solicitud interactiva de contraseña (sudoers). Limitar timeouts de ejecución para evitar colapsar la respuesta HTTP si un servicio tarda en arrancar.'
    
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Servicios -->
<div class="module-banner">
    <div class="module-banner-left">
        <div class="module-icon srv">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
        </div>
        <div class="module-banner-text">
            <h2>Gestión de Servicios y Pila de Software</h2>
            <p>
                Servicios nativos gestionados por Systemd: Apache 2.4 (MPM Event), PHP 8.4 FPM, MariaDB 11.8, Redis 7.2 y Samba SMBv3.
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <form method="POST" action="/" data-loading="Reiniciando Apache 2.4 y PHP 8.4 FPM…" style="display:inline;">
            <input type="hidden" name="action" value="services_restart_web">
            <input type="hidden" name="_page" value="services">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                <span>Reiniciar Pila Web (Apache + FPM)</span>
            </button>
        </form>

        <form method="POST" action="/" data-loading="Recargando configuración de Apache 2.4…" style="display:inline;">
            <input type="hidden" name="action" value="service_reload">
            <input type="hidden" name="unit" value="apache2">
            <input type="hidden" name="_page" value="services">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                </svg>
                <span>Recargar Apache</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="module-kpi-grid">
    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Servidor Web</span>
            <span class="tag tag-ok">HTTP/2 TLS</span>
        </div>
        <div class="module-kpi-val">Apache 2.4.62</div>
        <div class="module-kpi-sub">MPM Event · Puertos 80, 443 · mod_vhost_alias</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Motor de Aplicación</span>
            <span class="tag tag-ok">FastCGI</span>
        </div>
        <div class="module-kpi-val">PHP 8.4.4 FPM</div>
        <div class="module-kpi-sub">Socket UNIX <code>/run/php/php8.4-fpm.sock</code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Base de Datos</span>
            <span class="tag tag-ok">InnoDB</span>
        </div>
        <div class="module-kpi-val">MariaDB 11.8</div>
        <div class="module-kpi-sub">Puerto 3306 · phpMyAdmin disponible</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Caché & Dependencias</span>
            <span class="tag tag-ok">PONG</span>
        </div>
        <div class="module-kpi-val">Redis 7.2.5</div>
        <div class="module-kpi-sub">Composer 2.7.2 en <code>/usr/local/bin/composer</code></div>
    </div>
</div>

<!-- 3. Alertas de Resultado si existen -->
<?php if (!empty($serviceResult)) { ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
        <div class="card-head">
            <div>
                <h2>Operación en Servicio: <?= e($serviceResult['unit']) ?> (<?= e($serviceResult['action']) ?>)</h2>
                <div class="muted" style="margin-top: 4px;">Comando ejecutado el <?= e($serviceResult['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_service_results">
                <input type="hidden" name="_page" value="services">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Cerrar</button>
            </form>
        </div>
        <pre class="terminal"><?= e($serviceResult['output']) ?></pre>
    </div>
<?php } ?>

<!-- 4. Tabla Completa de Servicios del Sistema (Systemd) -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-head">
        <div>
            <h2>Servicios del Sistema (Systemd Units)</h2>
            <div class="muted" style="margin-top: 2px;">Control individual de procesos, monitoreo de memoria y reinicio en caliente</div>
        </div>
        <span class="badge"><?= e(count(array_filter($services, fn($s) => !empty($s['active'])))) ?> / <?= e(count($services)) ?> activos</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Servicio / Unidad Systemd</th>
                    <th>Versión</th>
                    <th>PID / Uptime</th>
                    <th>Recursos</th>
                    <th>Estado</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $s) { ?>
                <tr>
                    <td>
                        <div style="font-weight: 700;"><?= e($s['label']) ?></div>
                        <div class="muted" style="font-size: 0.78rem;"><code><?= e($s['unit']) ?>.service</code> · <?= e($s['description'] ?? '') ?></div>
                    </td>
                    <td><code><?= e($s['version'] ?? '-') ?></code></td>
                    <td>
                        <div>PID: <code><?= e($s['pid'] ?? '-') ?></code></div>
                        <div class="muted" style="font-size: 0.75rem;">Uptime: <?= e($s['uptime'] ?? 'Activo') ?></div>
                    </td>
                    <td>
                        <div>RAM: <?= e($s['memory'] ?? '-') ?></div>
                        <div class="muted" style="font-size: 0.75rem;">CPU: <?= e($s['cpu'] ?? '0.0%') ?></div>
                    </td>
                    <td>
                        <?php if (!empty($s['active'])) { ?>
                            <span class="service-pill-active">
                                <span class="dot ok"></span> Activo
                            </span>
                        <?php } else { ?>
                            <span class="service-pill-inactive">
                                <span class="dot err"></span> Inactivo
                            </span>
                        <?php } ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <form method="POST" action="/" data-loading="Reiniciando <?= e($s['unit']) ?>…" style="display:inline;">
                                <input type="hidden" name="action" value="service_restart">
                                <input type="hidden" name="unit" value="<?= e($s['unit']) ?>">
                                <input type="hidden" name="_page" value="services">
                                <?= panel_csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm">Reiniciar</button>
                            </form>

                            <form method="POST" action="/" data-loading="Recargando <?= e($s['unit']) ?>…" style="display:inline;">
                                <input type="hidden" name="action" value="service_reload">
                                <input type="hidden" name="unit" value="<?= e($s['unit']) ?>">
                                <input type="hidden" name="_page" value="services">
                                <?= panel_csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm">Recargar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 5. Entorno de Ejecución PHP 8.4 Stack y Configuración -->
<div class="grid grid-2">
    <!-- PHP Extensions -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Extensiones PHP 8.4 FPM Habilitadas</h2>
                <div class="muted" style="margin-top: 2px;">Módulos compilados para compatibilidad total</div>
            </div>
            <span class="tag tag-ok">12 extensiones</span>
        </div>

        <p class="muted" style="font-size: 0.82rem; margin-bottom: 12px;">
            Extensiones nativas instaladas en <code>/etc/php/8.4/fpm/conf.d/</code>:
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach (['curl', 'mbstring', 'xml', 'zip', 'mysqli', 'pdo_mysql', 'redis', 'gd', 'bcmath', 'intl', 'opcache', 'soap'] as $ext) { ?>
                <span class="cert-san-chip">
                    <span class="san-type">EXT</span>
                    <span>php8.4-<?= e($ext) ?></span>
                </span>
            <?php } ?>
        </div>
    </div>

    <!-- PHP.ini Directives -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Directivas de Producción php.ini</h2>
                <div class="muted" style="margin-top: 2px;">Ajustes optimizados para alto rendimiento</div>
            </div>
            <span class="badge">PHP-FPM Pool</span>
        </div>

        <dl class="kv">
            <div>
                <dt>memory_limit</dt>
                <dd><code>512M</code> (Alto rendimiento para Composer/Frameworks)</dd>
            </div>
            <div>
                <dt>upload_max_filesize</dt>
                <dd><code>128M</code> (Subida de archivos sin truncado)</dd>
            </div>
            <div>
                <dt>post_max_size</dt>
                <dd><code>128M</code></dd>
            </div>
            <div>
                <dt>max_execution_time</dt>
                <dd><code>300s</code> (5 minutos para tareas pesadas)</dd>
            </div>
            <div>
                <dt>opcache.enable</dt>
                <dd><span class="tag tag-ok">1 (Activo)</span> Precompilación de bytecode en memoria</dd>
            </div>
        </dl>
    </div>
</div>
