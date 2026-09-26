<?php
$overview = panel_overview($CONFIG);
$panelVersion = $CONFIG['PANEL_VERSION'] ?? '1.0.0';
$spec = [
    'title' => 'Módulo 10: Configuración Centralizada /etc/srvctl.conf',
    'endpoints' => [
        'POST /?action=settings_save_network (body: { server_hostname, server_ip, base_domain, prod_sub, stg_sub, db_sub })',
        'POST /?action=settings_change_password (body: { current_password, new_password })'
    ],
    'commands' => [
        'Sobrescritura atómica de /etc/srvctl.conf con permisos 600',
        'hostnamectl set-hostname <server_hostname>',
        'srvctl ssl renew (si se modifica el dominio base)'
    ],
    'paths' => [
        '/etc/srvctl.conf (archivo de configuración maestro)',
        '/etc/hosts (resolución local del servidor)',
        '/etc/apache2/sites-available/00-dashboard.conf'
    ],
    'notes' => 'Validar estrictamente formato IPv4 (0-255), dominios FQDN y hostname antes de persistir en /etc/srvctl.conf. El archivo debe pertenecer a root:root con permisos chmod 600 para proteger credenciales y contraseñas.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<div class="section-header">
    <div>
        <h2>Configuración del Sistema</h2>
        <div class="muted" style="margin-top: 4px;">
            Administración centralizada de red, dominios, seguridad y servicios en <code>/etc/srvctl.conf</code>
        </div>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="/downloads/srvctl.conf" download="srvctl.conf">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span>Descargar srvctl.conf</span>
        </a>
    </div>
</div>

<div class="grid grid-2">
    <!-- Tarjeta 1: Red y Dominios FQDN -->
    <div class="card">
        <div class="card-head">
            <h2>Red y Dominios FQDN</h2>
            <span class="badge">DNS / VirtualHosts</span>
        </div>
        <form method="POST" action="/" data-loading="Guardando parámetros de red...">
            <input type="hidden" name="action" value="settings_save_network">
            <?= panel_csrf_field() ?>

            <div class="field">
                <span>Nombre del Equipo / Hostname (SERVER_HOSTNAME)</span>
                <input type="text" name="server_hostname" class="input" required value="<?= e($CONFIG['SERVER_HOSTNAME'] ?? ($overview['hostname'] ?? 'debian13-server')) ?>" placeholder="debian13-server" pattern="^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]$">
                <small class="muted">Nombre identificador del equipo mostrado en la cabecera del panel.</small>
            </div>

            <div class="field">
                <span>Dirección IPv4 del Servidor (SERVER_IP)</span>
                <input type="text" name="server_ip" class="input" required value="<?= e($CONFIG['SERVER_IP']) ?>" placeholder="10.1.0.4" pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}$">
                <small class="muted">Dirección de enlace en la interfaz de red local o pública.</small>
            </div>

            <div class="field">
                <span>Dominio Base (BASE_DOMAIN)</span>
                <input type="text" name="base_domain" class="input" required value="<?= e($CONFIG['BASE_DOMAIN']) ?>" placeholder="empresa.local">
                <small class="muted">Dominio raíz asignado al servidor (debe resolver hacia la IP configurada).</small>
            </div>

            <div class="field">
                <span>Subdominios estándar del sistema</span>
                <div class="form-row-3">
                    <div>
                        <small class="muted" style="display:block; margin-bottom: 4px;">Producción</small>
                        <input type="text" name="prod_sub" class="input" required value="<?= e($CONFIG['PROD_SUB']) ?>" placeholder="prod">
                    </div>
                    <div>
                        <small class="muted" style="display:block; margin-bottom: 4px;">Staging</small>
                        <input type="text" name="stg_sub" class="input" required value="<?= e($CONFIG['STG_SUB']) ?>" placeholder="stg">
                    </div>
                    <div>
                        <small class="muted" style="display:block; margin-bottom: 4px;">phpMyAdmin</small>
                        <input type="text" name="db_sub" class="input" required value="<?= e($CONFIG['DB_SUB']) ?>" placeholder="webdev">
                    </div>
                </div>
            </div>

            <div class="kv kv-inline" style="margin-top: 12px; margin-bottom: 16px;">
                <div><dt>URL Producción</dt><dd><code>https://<?= e($CONFIG['PROD_FQDN']) ?></code></dd></div>
                <div><dt>URL Staging</dt><dd><code>https://<?= e($CONFIG['STG_FQDN']) ?></code></dd></div>
                <div><dt>URL phpMyAdmin</dt><dd><code>https://<?= e($CONFIG['DB_FQDN']) ?></code></dd></div>
            </div>

            <button type="submit" class="btn btn-primary">Guardar parámetros de red</button>
        </form>
    </div>

    <!-- Tarjeta 2: Credenciales y Seguridad -->
    <div class="card">
        <div class="card-head">
            <h2>Seguridad de Administrador</h2>
            <span class="badge">Acceso y Claves</span>
        </div>
        <form method="POST" action="/" data-loading="Actualizando credenciales...">
            <input type="hidden" name="action" value="settings_change_password">
            <?= panel_csrf_field() ?>

            <div class="field">
                <span>Usuario Administrador (ADMIN_USER)</span>
                <input type="text" name="admin_user" class="input" required value="<?= e($CONFIG['ADMIN_USER']) ?>">
            </div>

            <div class="field">
                <span>Contraseña Actual</span>
                <input type="password" name="current_password" class="input" required placeholder="Contraseña actual del sistema">
            </div>

            <div class="form-row-2">
                <div class="field">
                    <span>Nueva Contraseña</span>
                    <input type="password" name="new_password" class="input" required placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                <div class="field">
                    <span>Confirmar Nueva</span>
                    <input type="password" name="confirm_password" class="input" required placeholder="Repetir contraseña" minlength="6">
                </div>
            </div>

            <?php if ($CONFIG['ADMIN_PASS'] === 'Temp123#'): ?>
                <div class="warn-box">
                    <strong>Atención de seguridad:</strong> El servidor conserva la contraseña demo inicial (<code>Temp123#</code>). Te recomendamos actualizarla a una clave segura.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">Actualizar credenciales</button>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <!-- Tarjeta 3: Parámetros de PHP 8.4 FPM y Apache 2.4 -->
    <div class="card">
        <div class="card-head">
            <h2>Motor PHP 8.4 y Servidor Web</h2>
            <span class="badge">Rendimiento</span>
        </div>
        <form method="POST" action="/" data-loading="Guardando configuración PHP/Web...">
            <input type="hidden" name="action" value="settings_save_php">
            <?= panel_csrf_field() ?>

            <div class="form-row-3">
                <div class="field">
                    <span>Memoria límite</span>
                    <select name="php_memory_limit" class="input">
                        <option value="128M" <?= e($CONFIG['PHP_MEMORY_LIMIT'] === '128M' ? 'selected' : '') ?>>128M</option>
                        <option value="256M" <?= e((($CONFIG['PHP_MEMORY_LIMIT'] ?? '256M') === '256M') ? 'selected' : '') ?>>256M (Recomendado)</option>
                        <option value="512M" <?= e($CONFIG['PHP_MEMORY_LIMIT'] === '512M' ? 'selected' : '') ?>>512M</option>
                        <option value="1024M" <?= e($CONFIG['PHP_MEMORY_LIMIT'] === '1024M' ? 'selected' : '') ?>>1024M</option>
                    </select>
                </div>
                <div class="field">
                    <span>Tiempo máx. ejecución</span>
                    <select name="php_max_execution_time" class="input">
                        <option value="30" <?= e($CONFIG['PHP_MAX_EXEC_TIME'] === '30' ? 'selected' : '') ?>>30 segundos</option>
                        <option value="60" <?= e((($CONFIG['PHP_MAX_EXEC_TIME'] ?? '60') === '60') ? 'selected' : '') ?>>60 segundos</option>
                        <option value="120" <?= e($CONFIG['PHP_MAX_EXEC_TIME'] === '120' ? 'selected' : '') ?>>120 segundos</option>
                        <option value="300" <?= e($CONFIG['PHP_MAX_EXEC_TIME'] === '300' ? 'selected' : '') ?>>300 segundos</option>
                    </select>
                </div>
                <div class="field">
                    <span>Tamaño máx. subida</span>
                    <select name="php_upload_max_filesize" class="input">
                        <option value="16M" <?= e($CONFIG['PHP_UPLOAD_MAX'] === '16M' ? 'selected' : '') ?>>16M</option>
                        <option value="32M" <?= e($CONFIG['PHP_UPLOAD_MAX'] === '32M' ? 'selected' : '') ?>>32M</option>
                        <option value="64M" <?= e((($CONFIG['PHP_UPLOAD_MAX'] ?? '64M') === '64M') ? 'selected' : '') ?>>64M (Estándar)</option>
                        <option value="128M" <?= e($CONFIG['PHP_UPLOAD_MAX'] === '128M' ? 'selected' : '') ?>>128M</option>
                        <option value="256M" <?= e($CONFIG['PHP_UPLOAD_MAX'] === '256M' ? 'selected' : '') ?>>256M</option>
                    </select>
                </div>
            </div>

            <div class="switch">
                <div class="switch-label">
                    <span>Protocolo HTTP/2 en Apache</span>
                    <small>Habilita multiplexación y compresión de encabezados en Apache 2.4 MPM Event</small>
                </div>
                <input type="checkbox" name="http2_enabled" value="1" <?= e($CONFIG['HTTP2_ENABLED'] !== false ? 'checked' : '') ?>>
            </div>

            <div class="switch">
                <div class="switch-label">
                    <span>Mapeo Dinámico de Subdominios (mod_vhost_alias)</span>
                    <small>Resuelve automáticamente *.<?= e($CONFIG['BASE_DOMAIN']) ?> a /var/www/%1/public_html</small>
                </div>
                <input type="checkbox" name="mod_vhost_alias" value="1" <?= e($CONFIG['MOD_VHOST_ALIAS_ENABLED'] !== false ? 'checked' : '') ?>>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 8px;">Aplicar cambios PHP y Web</button>
        </form>
    </div>

    <!-- Tarjeta 4: Samba SMBv3 y Respaldos -->
    <div class="card">
        <div class="card-head">
            <h2>Samba SMBv3 y Respaldos</h2>
            <span class="badge">Almacenamiento</span>
        </div>
        <form method="POST" action="/" data-loading="Guardando políticas de almacenamiento...">
            <input type="hidden" name="action" value="settings_save_services">
            <?= panel_csrf_field() ?>

            <div class="form-row-2">
                <div class="field">
                    <span>Nombre del recurso Samba</span>
                    <input type="text" name="samba_share_name" class="input" required value="<?= e($CONFIG['SAMBA_SHARE_NAME'] ?? 'proyectos') ?>">
                    <small class="muted">Acceso: <code>\\<?= e($CONFIG['SERVER_IP']) ?>\<?= e($CONFIG['SAMBA_SHARE_NAME'] ?? 'proyectos') ?></code></small>
                </div>
                <div class="field">
                    <span>Ruta local del recurso</span>
                    <input type="text" name="samba_share_path" class="input" required value="<?= e($CONFIG['SAMBA_SHARE_PATH'] ?? '/var/www') ?>">
                    <small class="muted">Permisos SGID 2775 (www-data)</small>
                </div>
            </div>

            <div class="form-row-2">
                <div class="field">
                    <span>Retención de snapshots</span>
                    <select name="backup_retention" class="input">
                        <option value="3" <?= e($CONFIG['BACKUP_RETENTION_DAYS'] === 3 ? 'selected' : '') ?>>3 días</option>
                        <option value="7" <?= e((($CONFIG['BACKUP_RETENTION_DAYS'] ?? 7) == 7) ? 'selected' : '') ?>>7 días (daily.0 a daily.6)</option>
                        <option value="14" <?= e($CONFIG['BACKUP_RETENTION_DAYS'] === 14 ? 'selected' : '') ?>>14 días</option>
                        <option value="30" <?= e($CONFIG['BACKUP_RETENTION_DAYS'] === 30 ? 'selected' : '') ?>>30 días</option>
                    </select>
                </div>
                <div class="field">
                    <span>Hora programada de respaldo</span>
                    <input type="time" name="backup_time" class="input" value="<?= e($CONFIG['BACKUP_CRON_TIME'] ?? '02:00') ?>">
                    <small class="muted">Ejecución diaria en cron del sistema</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 8px;">Guardar almacenamiento y respaldos</button>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <!-- Tarjeta 5: Operaciones y Mantenimiento -->
    <div class="card">
        <div class="card-head">
            <h2>Mantenimiento del Servidor</h2>
            <span class="badge">Operaciones</span>
        </div>
        <p class="muted" style="margin-bottom: 16px;">Ejecuta tareas operativas en los servicios sin necesidad de acceder por terminal SSH.</p>

        <div class="actions" style="margin-bottom: 18px;">
            <form method="POST" action="/" data-loading="Reiniciando servicios web..." style="display:inline;">
                <input type="hidden" name="action" value="restart_web_services">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    <span>Reiniciar Apache y PHP</span>
                </button>
            </form>

            <form method="POST" action="/" data-loading="Purgando memoria de Redis..." style="display:inline;">
                <input type="hidden" name="action" value="flush_redis">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Purgar Caché Redis</span>
                </button>
            </form>
        </div>

        <div class="danger-zone-box">
            <h4>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                Restablecer configuración de fábrica
            </h4>
            <p>Restaura todas las variables de <code>/etc/srvctl.conf</code> a los valores predeterminados (empresa.local, prod, stg, webdev).</p>
            <button type="button" class="btn btn-danger btn-sm" data-open-modal="modal-reset-config">Restablecer valores por defecto</button>
        </div>
    </div>

    <!-- Tarjeta 6: Información del Entorno -->
    <div class="card">
        <div class="card-head">
            <h2>Información del Entorno</h2>
            <span class="tag tag-ok">Debian 13 Nativo</span>
        </div>
        <dl class="kv">
            <div><dt>Versión del Panel</dt><dd>v<?= e($panelVersion) ?></dd></div>
            <div><dt>Hostname</dt><dd><?= e($overview['hostname']) ?></dd></div>
            <div><dt>Sistema Operativo</dt><dd><?= e($overview['os']) ?></dd></div>
            <div><dt>Kernel</dt><dd><?= e($overview['kernel']) ?></dd></div>
            <div><dt>Intérprete PHP</dt><dd><?= e($overview['php']) ?></dd></div>
            <div><dt>Servidor Web</dt><dd>Apache 2.4.62 (MPM Event)</dd></div>
            <div><dt>Base de Datos</dt><dd>MariaDB 11.8 (InnoDB)</dd></div>
            <div><dt>Servicio Caché</dt><dd>Redis Server 7.2</dd></div>
            <div><dt>Archivo de Configuración</dt><dd><code>/etc/srvctl.conf</code></dd></div>
            <div><dt>Permisos del Archivo</dt><dd><code>0600 (Solo root)</code></dd></div>
        </dl>
    </div>
</div>

<!-- Modal de confirmación para restablecer valores de fábrica -->
<div class="modal-overlay" id="modal-reset-config">
    <div class="modal">
        <h3>¿Restablecer configuración de fábrica?</h3>
        <p>Esta acción sobreescribirá los parámetros de <code>/etc/srvctl.conf</code> con los valores iniciales de instalación (<code>empresa.local</code>, <code>webadmin</code>, etc.). Los archivos de tus proyectos no se eliminarán.</p>
        <form method="POST" action="/" data-loading="Restableciendo parámetros...">
            <input type="hidden" name="action" value="settings_reset_defaults">
            <?= panel_csrf_field() ?>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-modal>Cancelar</button>
                <button type="submit" class="btn btn-danger">Confirmar y restablecer</button>
            </div>
        </form>
    </div>
</div>
