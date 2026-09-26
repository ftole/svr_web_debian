<?php
$verify = $verify ?? ($_SESSION['verify_result'] ?? null);
$pendingUpdates = $pendingUpdates ?? [];
$updateResult = $updateResult ?? ($_SESSION['update_result'] ?? null);
$updateHistory = $updateHistory ?? [];
$liveLogs = $liveLogs ?? [];
$lastAptCheck = $lastAptCheck ?? 'Al día';
$spec = [
    'title' => 'Módulo 8: Diagnóstico, Auditoría srvctl verify y Logs en Vivo',
    'endpoints' => [
        'POST /?action=verify (Ejecución de auditoría de sistema)',
        'POST /?action=clear_verify (Limpiar reporte de verificación)',
        'GET /?action=stream_logs (Server-Sent Events / SSE para logs en tiempo real)',
        'POST /?action=apt_upgrade (Aprovisionamiento de parches del SO)'
    ],
    'commands' => [
        'srvctl verify (comprueba DNS, Apache, FPM, InnoDB, permisos SGID, certificados)',
        'journalctl -f -n 50 -u apache2 -u php8.4-fpm -u mariadb -u redis-server',
        'apt update && apt list --upgradable'
    ],
    'paths' => [
        '/var/log/srvctl.log (registro centralizado de la CLI)',
        '/var/log/syslog y /var/log/daemon.log',
        '/var/log/apache2/error.log'
    ],
    'notes' => 'El subcomando srvctl verify genera salida estructurada con etiquetas [ OK ] y [FAIL]. El visor de logs del backend debe enviar líneas SSE con campos { time, source, badge, level, message } para alimentar la consola visual interactiva.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Encabezado y Puntuación de Salud del Servidor -->
<div class="diag-score-banner">
    <div class="diag-score-left">
        <div class="diag-score-circle">
            <span>99%</span>
            <small>Salud</small>
        </div>
        <div class="diag-score-text">
            <h2>Diagnóstico y Salud del Sistema</h2>
            <p>Monitoreo continuo en tiempo real, auditoría srvctl verify, gestor de parches APT y visor de eventos nativo Debian 13.</p>
        </div>
    </div>
    <div class="diag-score-actions">
        <form method="POST" action="/" data-loading="Ejecutando auditoría srvctl verify…" style="display:inline;">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="_page" value="diagnostics">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                <span>Auditar Sistema (srvctl verify)</span>
            </button>
        </form>

        <form method="POST" action="/" data-loading="Consultando repositorios Debian 13…" style="display:inline;">
            <input type="hidden" name="action" value="check_system_updates">
            <input type="hidden" name="_page" value="diagnostics">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                <span>Buscar Actualizaciones</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 6 Indicadores de Salud en Tiempo Real -->
<div class="health-checks-grid">
    <!-- 1. Servicios -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Servicios Críticos
            </span>
            <span class="tag tag-ok">7 / 7 Activos</span>
        </div>
        <div class="health-check-val">Apache · PHP · MariaDB · Redis</div>
        <div class="health-check-sub">Samba, UFW y Fail2ban respondiendo correctamente en systemd.</div>
    </div>

    <!-- 2. Almacenamiento -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Almacenamiento (/)
            </span>
            <span class="tag tag-ok">18% Uso</span>
        </div>
        <div class="health-check-val">82.0 GB Libres de 100.0 GB</div>
        <div class="health-check-sub">Tabla de inodos al 4% de ocupación. Espacio óptimo para backups.</div>
    </div>

    <!-- 3. Memoria & Swap -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Memoria RAM & Swap
            </span>
            <span class="tag tag-ok">24% RAM</span>
        </div>
        <div class="health-check-val">1.0 GB Usado · 3.0 GB Libre</div>
        <div class="health-check-sub">Paginación Swap: 0% en uso (0 B / 1.0 GB). Sin presión de OOM.</div>
    </div>

    <!-- 4. CPU & Térmica -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Rendimiento CPU
            </span>
            <span class="tag tag-ok">Normal</span>
        </div>
        <div class="health-check-val">Load: 0.12 · Temp: 38.4 °C</div>
        <div class="health-check-sub">2 Cores virtuales activos con bajo consumo y respuesta inmediata.</div>
    </div>

    <!-- 5. Certificado SSL -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Certificado SSL Comodín
            </span>
            <span class="tag tag-ok">Válido</span>
        </div>
        <div class="health-check-val">*.recom.net · 364 días</div>
        <div class="health-check-sub">Emisor: CA Raíz srvctl. Firma SHA256 con SANs completas.</div>
    </div>

    <!-- 6. Conectividad & Sockets -->
    <div class="health-check-card">
        <div class="health-check-top">
            <span class="health-check-title">
                <span class="dot ok"></span> Sockets UNIX & Red
            </span>
            <span class="tag tag-ok">12 ms Ping</span>
        </div>
        <div class="health-check-val">php8.4-fpm.sock · mysqld.sock</div>
        <div class="health-check-sub">Sin pérdida de paquetes. Comunicación interprocesos rápida.</div>
    </div>
</div>

<!-- 3. Módulo de Actualizaciones del Sistema Operativo -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-head">
        <div>
            <h2>Actualizaciones del Sistema Operativo (Debian 13 Trixie)</h2>
            <div class="muted" style="margin-top: 4px;">
                Gestión de paquetes APT · Última sincronización: <code><?= e($lastAptCheck) ?></code>
            </div>
        </div>
        <div class="actions">
            <?php if (!empty($pendingUpdates)): ?>
                <form method="POST" action="/" data-loading="Descargando e instalando actualizaciones…" style="display:inline;">
                    <input type="hidden" name="action" value="run_system_update">
                    <input type="hidden" name="_page" value="diagnostics">
                    <?= panel_csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        <span>Actualizar Todo el Sistema (<?= e(count($pendingUpdates)) ?> paquetes)</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Terminal de resultado de actualización si existe -->
    <?php if (!empty($updateResult)): ?>
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <span class="tag tag-ok">Actualización completada (<?= e($updateResult['count']) ?> paquetes) · <?= e($updateResult['timestamp']) ?></span>
                <form method="POST" action="/" style="display:inline;">
                    <input type="hidden" name="action" value="clear_update_result">
                    <input type="hidden" name="_page" value="diagnostics">
                    <?= panel_csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Cerrar terminal</button>
                </form>
            </div>
            <pre class="terminal"><?= e($updateResult['output']) ?></pre>
        </div>
    <?php endif; ?>

    <?php if (!empty($pendingUpdates)): ?>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
            <span class="badge" style="background: var(--primary-soft); color: var(--primary); font-size: 0.85rem; padding: 6px 12px;">
                <?= e(count($pendingUpdates)) ?> actualizaciones disponibles
            </span>
            <span class="muted" style="font-size: 0.82rem;">Se recomienda instalar los parches marcados como seguridad para proteger el servidor.</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Paquete</th>
                        <th>Versión Instalada</th>
                        <th>Nueva Versión</th>
                        <th>Origen / Rama</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th style="text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUpdates ?? [] as $pkg): ?>
                        <tr>
                            <td><strong><code><?= e($pkg['name']) ?></code></strong></td>
                            <td><code><?= e($pkg['current']) ?></code></td>
                            <td><code style="color: var(--primary); font-weight: 700;"><?= e($pkg['available']) ?></code></td>
                            <td><?= e($pkg['repo']) ?></td>
                            <td>
                                <?php if ($pkg['type'] === 'security'): ?>
                                    <span class="badge-security">Seguridad</span>
                                <?php else: ?>
                                    <span class="badge-regular">Actualización</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($pkg['size']) ?></td>
                            <td style="text-align: right;">
                                <form method="POST" action="/" data-loading="Actualizando <?= e($pkg['name']) ?>…" style="display:inline;">
                                    <input type="hidden" name="action" value="run_system_update">
                                    <input type="hidden" name="package_name" value="<?= e($pkg['name']) ?>">
                                    <input type="hidden" name="_page" value="diagnostics">
                                    <?= panel_csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm">Actualizar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="background: var(--success-soft); border: 1px solid rgba(46, 125, 50, 0.2); border-radius: var(--radius); padding: 20px; text-align: center;">
            <div style="color: var(--success); font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;">
                ¡El sistema se encuentra al día!
            </div>
            <p class="muted" style="color: var(--text-secondary); margin: 0;">
                No hay actualizaciones pendientes. Todos los parches de seguridad y paquetes de Debian 13 están aplicados.
            </p>
        </div>
    <?php endif; ?>

    <!-- Historial de actualizaciones -->
    <?php if (!empty($updateHistory)): ?>
        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-light);">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Historial reciente de actualizaciones:</h4>
            <ul style="list-style: none; font-size: 0.82rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 6px;">
                <?php foreach ($updateHistory ?? [] as $h): ?>
                    <li style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Actualización de <?= e($h['packages']) ?> paquete(s) por <code><?= e($h['user']) ?></code></span>
                        <span><span class="tag tag-ok"><?= e($h['status']) ?></span> · <?= e($h['date']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Auditoría y Diagnóstico del Servidor (srvctl verify) -->
<section class="card" style="margin-bottom: 24px;">
    <div class="card-head">
        <div>
            <h2>Auditoría de Diagnóstico srvctl verify</h2>
            <div class="muted" style="margin-top: 4px;">
                Comprobación profunda de puertos, sockets UNIX, configuraciones de VirtualHosts, permisos y certificados.
            </div>
        </div>
        <form method="POST" action="/" data-loading="Ejecutando diagnóstico srvctl verify…">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="_page" value="diagnostics">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-sm">Ejecutar diagnóstico</button>
        </form>
    </div>

    <?php if (!empty($verify)): ?>
        <div class="verify-summary" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="tag tag-ok"><?= e($verify['pass']) ?> OK</span>
                <?php if (!empty($verify) && $verify['fail'] > 0): ?>
                    <span class="tag tag-err"><?= e($verify['fail']) ?> fallos</span>
                <?php endif; ?>
                <span class="badge">Tiempo: <?= e($verify['duration'] ?? '0.38s') ?></span>
                <span class="muted" style="font-size: 0.8rem;"><?= e($verify['timestamp']) ?></span>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_verify">
                <input type="hidden" name="_page" value="diagnostics">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Limpiar resultados</button>
            </form>
        </div>
        <pre class="terminal"><?php
            foreach (explode("\n", $verify['output']) as $line) {
                if (strpos($line, '[ OK ]') !== false) {
                    echo str_replace('[ OK ]', '<span class="ok-text" style="color: var(--success); font-weight: bold;">[ OK ]</span>', e($line)) . "\n";
                } elseif (strpos($line, '[FAIL]') !== false) {
                    echo str_replace('[FAIL]', '<span class="err-text" style="color: var(--danger); font-weight: bold;">[FAIL]</span>', e($line)) . "\n";
                } else {
                    echo e($line) . "\n";
                }
            }
        ?></pre>
    <?php else: ?>
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; text-align: center;">
            <p class="muted" style="margin-bottom: 14px;">No hay resultados de auditoría recientes. Ejecuta el diagnóstico para auditar los 13 componentes clave del servidor Debian 13.</p>
            <form method="POST" action="/" data-loading="Ejecutando diagnóstico srvctl verify…" style="display:inline;">
                <input type="hidden" name="action" value="verify">
                <input type="hidden" name="_page" value="diagnostics">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm">Ejecutar diagnóstico ahora</button>
            </form>
        </div>
    <?php endif; ?>
</section>

<!-- 5. Visor de Eventos en Tiempo Real (Live Event & Log Stream) -->
<section class="live-log-container">
    <div class="live-log-toolbar">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0;">Visor de Eventos del Servidor</h2>
                <div class="live-pulse-badge" id="live-stream-badge">
                    <span class="md-pulse-core" style="width: 6px; height: 6px;"></span>
                    <span>EN VIVO</span>
                </div>
            </div>
            <div class="muted" style="font-size: 0.8rem; margin-top: 2px;">
                Transmisión activa de logs del sistema (Apache, PHP FPM, MariaDB, Redis, UFW, srvctl).
            </div>
        </div>

        <div class="live-log-actions">
            <button type="button" class="btn btn-outline btn-sm" id="btn-toggle-pause">
                <span id="txt-pause">Pausar</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" id="btn-toggle-autoscroll">
                Auto-scroll: <strong id="txt-autoscroll">ON</strong>
            </button>
            <form method="POST" action="/" style="display:inline;" data-loading="Limpiando visor…">
                <input type="hidden" name="action" value="clear_logs">
                <input type="hidden" name="_page" value="diagnostics">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Limpiar</button>
            </form>
        </div>
    </div>

    <!-- Barra de filtros -->
    <div class="live-log-filters" style="margin-bottom: 12px;">
        <select id="filter-log-source" class="input" style="max-width: 170px;">
            <option value="all">Todas las fuentes</option>
            <option value="apache2">Apache 2.4</option>
            <option value="php8.4-fpm">PHP 8.4 FPM</option>
            <option value="mariadb">MariaDB 11.8</option>
            <option value="redis">Redis Server</option>
            <option value="security">Seguridad (UFW/SSH)</option>
            <option value="srvctl">srvctl Core</option>
        </select>

        <select id="filter-log-level" class="input" style="max-width: 140px;">
            <option value="all">Todos los niveles</option>
            <option value="INFO">INFO</option>
            <option value="NOTICE">NOTICE</option>
            <option value="WARN">WARN</option>
            <option value="ERROR">ERROR</option>
        </select>

        <input type="text" id="filter-log-search" class="input" placeholder="Buscar en eventos (ej: TLS, GET, pool, mysql)…" style="flex: 1; min-width: 220px;">
    </div>

    <!-- Consola de logs en tiempo real -->
    <div class="live-log-console" id="live-log-console-box">
        <?php if (!empty($liveLogs)): ?>
            <?php foreach ($liveLogs ?? [] as $l): ?>
                <div class="live-log-line" data-id="<?= e($l['id']) ?>" data-source="<?= e($l['source']) ?>" data-level="<?= e($l['level']) ?>">
                    <span class="log-time">[<?= e($l['timeOnly'] ?? $l['timestamp']) ?>]</span>
                    <span class="log-source">[<?= e($l['source']) ?>]</span>
                    <span class="log-badge <?= e($l['level']) ?>"><?= e($l['level']) ?></span>
                    <span class="log-msg"><?= e($l['message']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="live-log-line">
                <span class="log-msg" style="color: var(--text-muted);">Iniciando escucha de eventos en tiempo real…</span>
            </div>
        <?php endif; ?>
    </div>
</section>

