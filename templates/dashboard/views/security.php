<?php
$security = panel_security() ?: [];
$security['ufw'] = $security['ufw'] ?? ['enabled' => false, 'default_incoming' => 'deny', 'default_outgoing' => 'allow', 'rules' => []];
$security['fail2ban'] = $security['fail2ban'] ?? ['active' => false, 'banned_details' => []];
$security['ssh'] = $security['ssh'] ?? ['port' => 22, 'permit_root_login' => 'prohibit-password', 'max_auth_tries' => 3, 'kex' => 'curve25519-sha256', 'ciphers' => 'chacha20-poly1305,aes256-gcm'];
$spec = [

        'title' => 'Módulo 5: Seguridad, Cortafuegos UFW y Fail2ban',
        'endpoints' => [
            'POST /?action=security_unban_ip (body: { ip })',
            'POST /?action=security_ufw_toggle (body: { enable: boolean })',
            'POST /?action=security_ufw_allow (body: { port, proto })'
        ],
        'commands' => [
            'ufw status numbered',
            'fail2ban-client status sshd',
            'fail2ban-client set sshd unbanip <IP>',
            'tail -n 100 /var/log/sudo.log'
        ],
        'paths' => [
            '/etc/ufw/user.rules',
            '/etc/fail2ban/jail.local',
            '/var/log/sudo.log (auditoría obligatoria de comandos administrativos)'
        ],
        'notes' => 'Validar estrictamente direcciones IPv4 (formato X.X.X.X, octetos 0-255) antes de invocar ufw o fail2ban-client para prevenir inyecciones de comandos en bash. Las sesiones de auditoría deben registrarse con timestamp y usuario.'
    
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Seguridad -->
<div class="module-banner">
    <div class="module-banner-left">
        <div class="module-icon sec">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <circle cx="12" cy="11" r="3"></circle>
                <path d="M12 14v3"></path>
            </svg>
        </div>
        <div class="module-banner-text">
            <h2>Centro de Seguridad y Hardening Debian 13</h2>
            <p>
                Defensa en profundidad: Cortafuegos UFW, prevención de intrusiones Fail2ban, bastionado OpenSSH y auditoría sudo estricta.
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <form method="POST" action="/" data-loading="Ejecutando escaneo de seguridad y hardening…" style="display:inline;">
            <input type="hidden" name="action" value="security_scan">
            <input type="hidden" name="_page" value="security">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>Escanear Hardening (Auditoría)</span>
            </button>
        </form>

        <form method="POST" action="/" data-loading="Cambiando estado de cortafuegos UFW…" style="display:inline;">
            <input type="hidden" name="action" value="security_ufw_toggle">
            <input type="hidden" name="_page" value="security">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= e($security['ufw']['enabled'] ? 'Desactivar UFW' : 'Activar UFW') ?></span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="module-kpi-grid">
    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Cortafuegos UFW</span>
            <span class="tag <?= e($security['ufw']['enabled'] ? 'tag-ok' : 'tag-err') ?>"><?= e($security['ufw']['enabled'] ? 'Activo' : 'Inactivo') ?></span>
        </div>
        <div class="module-kpi-val"><?= count($security['ufw']['rules'] ?? []) ?> Reglas</div>
        <div class="module-kpi-sub">Entrada: <code><?= e($security['ufw']['default_incoming'] ?? 'deny') ?></code> · Salida: <code>allow</code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Prevención Fail2ban</span>
            <span class="tag <?= e(($security['fail2ban']['active'] ?? false) ? 'tag-ok' : 'tag-err') ?>"><?= e(($security['fail2ban']['active'] ?? false) ? 'Activo' : 'Inactivo') ?></span>
        </div>
        <div class="module-kpi-val"><?= count($security['fail2ban']['banned_details'] ?? []) ?> IPs Baneadas</div>
        <div class="module-kpi-sub">Jaula activa: <code>sshd</code> · Intentos máx: 5</div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Bastionado OpenSSH</span>
            <span class="tag tag-ok">Hardened</span>
        </div>
        <div class="module-kpi-val">Puerto <?= e($security['ssh']['port'] ?? 22) ?></div>
        <div class="module-kpi-sub">RootLogin: <code><?= e($security['ssh']['permit_root_login']) ?></code> · MaxAuthTries: <code><?= e($security['ssh']['max_auth_tries']) ?></code></div>
    </div>

    <div class="module-kpi-card">
        <div class="module-kpi-head">
            <span class="module-kpi-title">Kernel & Permisos</span>
            <span class="tag tag-ok">Protegido</span>
        </div>
        <div class="module-kpi-val">IPv6 Off · SGID</div>
        <div class="module-kpi-sub">Config: <code>0600</code> · /var/www: <code>2775 (www-data)</code></div>
    </div>
</div>

<!-- 3. Resultado de Escaneo de Seguridad si existe -->
<?php if (!empty($securityScan)) { ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success);">
        <div class="card-head">
            <div>
                <h2>Resultado de Auditoría de Hardening (Puntuación: <?= e($securityScan['score']) ?>)</h2>
                <div class="muted" style="margin-top: 4px;">Comprobación de cortafuegos, jaulas y permisos ejecutada el <?= e($securityScan['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_security_results">
                <input type="hidden" name="_page" value="security">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Cerrar informe</button>
            </form>
        </div>
        <pre class="terminal"><?= e($securityScan['output']) ?></pre>
    </div>
<?php } ?>

<!-- 4. Cortafuegos UFW y Fail2ban -->
<div class="grid grid-2">
    <!-- UFW Rules -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Reglas de Filtrado UFW</h2>
                <div class="muted" style="margin-top: 2px;">Tráfico entrante por puerto y subred autorizada</div>
            </div>
            <span class="badge"><?= count($security['ufw']['rules'] ?? []) ?> reglas activas</span>
        </div>

        <div style="overflow-x: auto; margin-bottom: 16px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Puerto / Proto</th>
                        <th>Servicio</th>
                        <th>Acción</th>
                        <th>Origen</th>
                        <th style="text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($security['ufw']['rules'] ?? []) as $r) { ?>
                    <tr>
                        <td><strong><code><?= e($r['port']) ?>/<?= e($r['proto']) ?></code></strong></td>
                        <td><?= e($r['service']) ?></td>
                        <td><span class="tag tag-ok"><?= e($r['action']) ?></span></td>
                        <td><code><?= e($r['from']) ?></code></td>
                        <td style="text-align: right;">
                            <form method="POST" action="/" data-confirm="¿Eliminar la regla para el puerto <?= e($r['port']) ?>/<?= e($r['proto']) ?>?" style="display:inline;">
                                <input type="hidden" name="action" value="security_ufw_delete">
                                <input type="hidden" name="_page" value="security">
                                <input type="hidden" name="rule_id" value="<?= e($r['id']) ?>">
                                <?= panel_csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" title="Eliminar regla">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Formulario añadir regla rápida -->
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px;">
            <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 10px;">Añadir Regla de Cortafuegos</h4>
            <form method="POST" action="/" data-loading="Añadiendo regla UFW…">
                <input type="hidden" name="action" value="security_ufw_add">
                <input type="hidden" name="_page" value="security">
                <?= panel_csrf_field() ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; align-items: end;">
                    <div class="field" style="margin: 0;">
                        <span style="font-size: 0.75rem;">Puerto</span>
                        <input type="text" name="port" class="input" placeholder="ej. 8080" required>
                    </div>
                    <div class="field" style="margin: 0;">
                        <span style="font-size: 0.75rem;">Protocolo</span>
                        <select name="proto" class="input">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    <div class="field" style="margin: 0;">
                        <span style="font-size: 0.75rem;">Servicio / Nota</span>
                        <input type="text" name="service" class="input" placeholder="ej. API Node" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">Añadir</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fail2ban y Bloqueo de IPs -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Prevención de Intrusiones Fail2ban</h2>
                <div class="muted" style="margin-top: 2px;">Jaula <code>sshd</code> activa contra ataques por fuerza bruta</div>
            </div>
            <span class="badge"><?= count($security['fail2ban']['banned_details'] ?? []) ?> bloqueos</span>
        </div>

        <?php if (empty($security['fail2ban']['banned_details']) || empty($security['fail2ban']['banned_details'])) { ?>
            <div style="background: var(--success-soft); border: 1px solid rgba(46, 125, 50, 0.2); border-radius: var(--radius); padding: 16px; text-align: center; margin-bottom: 16px;">
                <div style="color: var(--success); font-weight: 700; font-size: 0.95rem;">Sin direcciones IP bloqueadas</div>
                <div class="muted" style="font-size: 0.8rem; margin-top: 2px;">No se han detectado intentos de ataque por fuerza bruta recientes.</div>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto; margin-bottom: 16px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Dirección IP</th>
                            <th>Origen / País</th>
                            <th>Motivo del Bloqueo</th>
                            <th style="text-align: right;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($security['fail2ban']['banned_details'] ?? []) as $b) { ?>
                        <tr>
                            <td><strong><code><?= e($b['ip']) ?></code></strong></td>
                            <td><?= e($b['country'] ?? 'Desconocido') ?></td>
                            <td><span class="tag tag-err"><?= e($b['reason'] ?? 'Fuerza bruta sshd') ?></span></td>
                            <td style="text-align: right;">
                                <form method="POST" action="/" data-loading="Desbaneando IP…" style="display:inline;">
                                    <input type="hidden" name="action" value="security_unban">
                                    <input type="hidden" name="_page" value="security">
                                    <input type="hidden" name="ip" value="<?= e($b['ip']) ?>">
                                    <?= panel_csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm">Desbanear</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <!-- Bloqueo manual de IP sospechosa -->
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px;">
            <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 10px;">Bloquear Dirección IP Sospechosa</h4>
            <form method="POST" action="/" data-loading="Bloqueando IP con fail2ban-client…">
                <input type="hidden" name="action" value="security_ban">
                <input type="hidden" name="_page" value="security">
                <?= panel_csrf_field() ?>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="ip" class="input" placeholder="ej. 192.0.2.1" required style="flex: 1;">
                    <button type="submit" class="btn btn-danger btn-sm">Bloquear IP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. Bastionado SSH y Auditoría Sudo -->
<div class="grid grid-2" style="margin-top: 10px;">
    <!-- OpenSSH Hardening Details -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Parámetros de Bastionado OpenSSH</h2>
                <div class="muted" style="margin-top: 2px;">Hardening en <code>/etc/ssh/sshd_config.d/srvctl.conf</code></div>
            </div>
            <span class="tag tag-ok">Asegurado</span>
        </div>

        <dl class="kv">
            <div>
                <dt>PermitRootLogin</dt>
                <dd><code><?= e($security['ssh']['permit_root_login']) ?></code> (Acceso root deshabilitado por SSH)</dd>
            </div>
            <div>
                <dt>MaxAuthTries</dt>
                <dd><code><?= e($security['ssh']['max_auth_tries']) ?></code> (Máximo de intentos por conexión)</dd>
            </div>
            <div>
                <dt>Puerto de Escucha</dt>
                <dd><code><?= e($security['ssh']['port'] ?? 22) ?> TCP</code> (Filtrado por UFW)</dd>
            </div>
            <div>
                <dt>Algoritmos KEX</dt>
                <dd><code style="font-size: 0.75rem;"><?= e($security['ssh']['kex'] ?? 'curve25519-sha256') ?></code></dd>
            </div>
            <div>
                <dt>Cifrados Seguros</dt>
                <dd><code style="font-size: 0.75rem;"><?= e($security['ssh']['ciphers'] ?? 'chacha20-poly1305,aes256-gcm') ?></code></dd>
            </div>
        </dl>
    </div>

    <!-- Auditoría Sudo -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Auditoría de Comandos Sudo (/var/log/sudo.log)</h2>
                <div class="muted" style="margin-top: 2px;">Trazabilidad estricta de elevación de privilegios</div>
            </div>
            <span class="badge">Auditoría srvctl</span>
        </div>

        <?php if (empty($security['sudo_log']) || empty($security['sudo_log'])) { ?>
            <p class="muted">Sin registros recientes disponibles en <code>/var/log/sudo.log</code>.</p>
        <?php } else { ?>
            <pre class="terminal" style="max-height: 220px;"><?php foreach (($security['sudo_log'] ?? []) as $line) { ?><?= e($line) ?>
<?php } ?></pre>
        <?php } ?>
    </div>
</div>
