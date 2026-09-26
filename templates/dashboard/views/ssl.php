<?php
$ssl = panel_ssl();
$samba = panel_samba() ?? ['config_valid' => true, 'sessions' => []];
$spec = [
    'title' => 'Módulo 6: Certificados SSL Comodín y Servidor Samba SMBv3',
    'endpoints' => [
        'POST /?action=ssl_renew',
        'GET /downloads/rootCA.crt (Descarga binaria del certificado raíz)',
        'POST /?action=samba_restart'
    ],
    'commands' => [
        'openssl x509 -in /etc/ssl/certs/empresa.local.crt -noout -dates -subject',
        'smbstatus --shares --locks',
        'srvctl ssl renew (regeneración y recarga de Apache)'
    ],
    'paths' => [
        '/etc/ssl/certs/rootCA.crt y /etc/ssl/private/rootCA.key',
        '/etc/ssl/certs/empresa.local.crt y /etc/ssl/private/empresa.local.key',
        '/etc/samba/smb.conf (recurso maestro [proyectos] con veto files)'
    ],
    'notes' => 'El certificado comodín SAN debe cubrir *.empresa.local y empresa.local. La autoridad CA raíz privada debe poder instalarse en clientes Windows y navegadores mediante rootCA.crt para eliminar advertencias SSL.'
];
require $PANEL_ROOT . '/partials/dev_spec.php';
?>

<!-- 1. Banner Principal de Certificados SSL/TLS -->
<div class="cert-banner">
    <div class="cert-banner-left">
        <div class="cert-lock-icon">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                <circle cx="12" cy="16" r="1"></circle>
            </svg>
        </div>
        <div class="cert-banner-text">
            <h2>Certificados SSL / TLS y Cifrado HTTPS</h2>
            <p>
                Infraestructura de Clave Pública (PKI) local con Autoridad Certificadora Raíz privada (4096-bit) y certificado comodín SAN <code>*.<?= e($CONFIG['BASE_DOMAIN']) ?></code>.
            </p>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <form method="POST" action="/" data-confirm="¿Regenerar el certificado SSL comodín y recargar Apache? Se generará un nuevo par de claves y se actualizará la vigencia por 365 días." data-loading="Regenerando certificado SSL comodín…" style="display:inline;">
            <input type="hidden" name="action" value="ssl_regenerate">
            <input type="hidden" name="_page" value="ssl">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                <span>Regenerar / Renovar Certificado</span>
            </button>
        </form>

        <form method="POST" action="/" data-loading="Auditando suites TLS y cadena de certificación…" style="display:inline;">
            <input type="hidden" name="action" value="ssl_verify">
            <input type="hidden" name="_page" value="ssl">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-outline">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
                <span>Auditar Cifrado TLS (A+)</span>
            </button>
        </form>
    </div>
</div>

<!-- 2. 4 Indicadores Rápidos (KPIs) -->
<div class="cert-kpi-grid">
    <div class="cert-kpi-card">
        <div class="cert-kpi-head">
            <span class="cert-kpi-title">Protocolo & Cifrado</span>
            <span class="tag tag-ok">Seguro</span>
        </div>
        <div class="cert-kpi-val"><?= e($ssl['tls_protocol'] ?? 'TLSv1.3') ?></div>
        <div class="cert-kpi-sub"><?= e($ssl['cipher_suite'] ?? 'TLS_AES_256_GCM_SHA384') ?> · HSTS activo</div>
    </div>

    <div class="cert-kpi-card">
        <div class="cert-kpi-head">
            <span class="cert-kpi-title">Cobertura Comodín (SAN)</span>
            <span class="tag tag-ok"><?= e(count($ssl['sans'] ?? [])) ?> Entradas</span>
        </div>
        <div class="cert-kpi-val"><code>*.<?= e($CONFIG['BASE_DOMAIN']) ?></code></div>
        <div class="cert-kpi-sub">Dominio base, subdominios, phpMyAdmin e IP directa</div>
    </div>

    <div class="cert-kpi-card">
        <div class="cert-kpi-head">
            <span class="cert-kpi-title">Autoridad CA Raíz</span>
            <span class="tag <?= e($ssl['ca_exists'] ? 'tag-ok' : 'tag-err') ?>"><?= e($ssl['ca_exists'] ? 'Presente' : 'Ausente') ?></span>
        </div>
        <div class="cert-kpi-val"><?= e($ssl['ca_key_length'] ?? 'RSA 4096-bit') ?></div>
        <div class="cert-kpi-sub">Válida por <?= e($ssl['ca_valid_to'] ?? '10 años') ?> (Local-RootCA)</div>
    </div>

    <div class="cert-kpi-card">
        <div class="cert-kpi-head">
            <span class="cert-kpi-title">Vigencia del Certificado</span>
            <span class="tag <?= e(($ssl['exists'] && $ssl['days_left'] > 0) ? 'tag-ok' : 'tag-err') ?>">
                <?= e($ssl['days_left']) ?> Días
            </span>
        </div>
        <div class="cert-kpi-val">Hasta <?= e($ssl['valid_to']) ?></div>
        <div class="cert-kpi-sub">Emitido: <?= e($ssl['valid_from']) ?> · Renovación disponible</div>
    </div>
</div>

<!-- 3. Alertas y Resultados de Auditoría / Regeneración si existen -->
<?php if (!empty($sslResult)): ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--success);">
        <div class="card-head">
            <div>
                <h2>Certificado SSL Regenerado Exitosamente</h2>
                <div class="muted" style="margin-top: 4px;">Ejecución registrada el <?= e($sslResult['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_ssl_results">
                <input type="hidden" name="_page" value="ssl">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Cerrar</button>
            </form>
        </div>
        <pre class="terminal"><?= e($sslResult['output']) ?></pre>
    </div>
<?php endif; ?>

<?php if (!empty($sslVerify)): ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
        <div class="card-head">
            <div>
                <h2>Auditoría de Cifrado y Seguridad TLS (Calificación <?= e($sslVerify['score']) ?>)</h2>
                <div class="muted" style="margin-top: 4px;">Comprobación de suites criptográficas y cadena de certificación realizada el <?= e($sslVerify['timestamp']) ?></div>
            </div>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_ssl_results">
                <input type="hidden" name="_page" value="ssl">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Limpiar</button>
            </form>
        </div>
        <pre class="terminal"><?= e($sslVerify['output']) ?></pre>
    </div>
<?php endif; ?>

<!-- 4. Certificado Comodín SAN + Autoridad Raíz CA -->
<div class="grid grid-2">
    <!-- Certificado Comodín SAN -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Certificado Web Comodín (webserver.crt)</h2>
                <div class="muted" style="margin-top: 2px;">Cifrado HTTPS para el dominio base y subdominios dinámicos</div>
            </div>
            <span class="tag tag-ok">Activo en Apache</span>
        </div>

        <dl class="kv" style="margin-bottom: 16px;">
            <div>
                <dt>Titular (CN)</dt>
                <dd><strong><code><?= e($ssl['subject']) ?></code></strong></dd>
            </div>
            <div>
                <dt>Emisor (Issuer)</dt>
                <dd><?= e($ssl['issuer']) ?></dd>
            </div>
            <div>
                <dt>Criptografía / Clave</dt>
                <dd><?= e($ssl['signature_algorithm'] ?? 'SHA256withRSA') ?> · <?= e($ssl['key_length'] ?? '2048-bit RSA') ?></dd>
            </div>
            <div>
                <dt>Número de Serie</dt>
                <dd><code><?= e($ssl['serial_number'] ?? '04:8F:2A:9C:E1:5D:80:4B') ?></code></dd>
            </div>
            <div>
                <dt>Huella Digital (SHA256)</dt>
                <dd><code style="font-size: 0.72rem; word-break: break-all;"><?= e($ssl['fingerprint_sha256'] ?? '8F:3A:41:B9:62:0E:55:D1:CA:73:90:38:D4:57:EC:81:49:7F:C2:59:E7:B0:1D:33:66:9A:F8:7D:EE:24:60:1C') ?></code></dd>
            </div>
            <div>
                <dt>Ruta en Servidor</dt>
                <dd><code><?= e($ssl['cert_path'] ?? '/etc/ssl/localcerts/webserver.crt') ?></code> (Modo 0644)</dd>
            </div>
            <div>
                <dt>Llave Privada</dt>
                <dd><code><?= e($ssl['key_path'] ?? '/etc/ssl/localcerts/webserver.key') ?></code> (Modo 0400, protegida)</dd>
            </div>
        </dl>

        <h3 class="subtitle" style="font-size: 0.88rem; font-weight: 700; margin-bottom: 6px;">Nombres Alternativos del Sujeto (SANs protegidos)</h3>
        <p class="muted" style="font-size: 0.78rem; margin-bottom: 8px;">Cualquier petición HTTPS a estas direcciones es validada sin alertas de certificado:</p>
        <div class="cert-san-list">
            <?php foreach ($ssl['sans'] ?? [] as $san):
                $isIp = preg_match('/^\d+\.\d+\.\d+\.\d+$/', $san);
            ?>
                <div class="cert-san-chip <?= e(isIp ? 'ip' : '') ?>">
                    <span class="san-type"><?= e(isIp ? 'IP' : 'DNS') ?></span>
                    <span><?= e(san) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between;">
            <span class="badge">X.509 PEM</span>
            <a href="/downloads/webserver.crt" download="webserver.crt" class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar webserver.crt</span>
            </a>
        </div>
    </div>

    <!-- Autoridad Certificadora Raíz -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Autoridad Certificadora Raíz (rootCA.crt)</h2>
                <div class="muted" style="margin-top: 2px;">Entidad emisora de confianza para los clientes de la red</div>
            </div>
            <span class="tag <?= e($ssl['ca_exists'] ? 'tag-ok' : 'tag-err') ?>"><?= e($ssl['ca_exists'] ? 'Raíz Confiable' : 'Ausente') ?></span>
        </div>

        <dl class="kv" style="margin-bottom: 16px;">
            <div>
                <dt>Nombre del Emisor (CN)</dt>
                <dd><strong><?= e($ssl['issuer']) ?></strong></dd>
            </div>
            <div>
                <dt>Clave Privada Raíz</dt>
                <dd><?= e($ssl['ca_key_length'] ?? 'RSA 4096-bit') ?> (umask 077, modo 0400)</dd>
            </div>
            <div>
                <dt>Vigencia de la CA</dt>
                <dd>10 Años (Vence en <?= e($ssl['ca_valid_to'] ?? '2036') ?>)</dd>
            </div>
            <div>
                <dt>Ubicación Pública</dt>
                <dd><code>/var/www/_dashboard/downloads/rootCA.crt</code></dd>
            </div>
            <div>
                <dt>Ubicación Segura</dt>
                <dd><code><?= e($ssl['ca_path'] ?? '/etc/ssl/localcerts/rootCA.crt') ?></code></dd>
            </div>
        </dl>

        <p class="muted" style="font-size: 0.82rem; margin-bottom: 16px;">
            Instala este certificado en las <em>Entidades de certificación raíz de confianza</em> de tus navegadores o sistemas operativos clientes para navegar con el candado verde/seguro en todos los subdominios.
        </p>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="/downloads/rootCA.crt" download="rootCA.crt" class="btn btn-primary" style="justify-content: center;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar Certificado Raíz (rootCA.crt)</span>
            </a>

            <a href="/downloads/configurar-cliente.bat" download="configurar-cliente.bat" class="btn btn-outline" style="justify-content: center;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="4 17 10 11 4 5"></polyline>
                    <line x1="12" y1="19" x2="20" y2="19"></line>
                </svg>
                <span>Descargar Script 1-Clic Windows (configurar-cliente.bat)</span>
            </a>
        </div>
    </div>
</div>

<!-- 5. Guías Rápidas de Instalación de Confianza para Clientes -->
<div class="card" style="margin-top: 10px;">
    <div class="card-head">
        <h2>Guía de Instalación del Certificado Raíz en Dispositivos Clientes</h2>
        <span class="badge">Instalación en 1 paso</span>
    </div>
    <p class="muted" style="margin-bottom: 14px;">
        Sigue estas instrucciones para que Windows, macOS o Linux confíen automáticamente en el certificado SSL comodín:
    </p>

    <div class="cert-trust-grid">
        <div class="cert-trust-card">
            <div class="cert-trust-title">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="8" height="8" rx="1"></rect>
                    <rect x="13" y="3" width="8" height="8" rx="1"></rect>
                    <rect x="3" y="13" width="8" height="8" rx="1"></rect>
                    <rect x="13" y="13" width="8" height="8" rx="1"></rect>
                </svg>
                <span>Windows 10 / 11</span>
            </div>
            <ol class="cert-trust-steps">
                <li>Descarga y ejecuta como Administrador <code>configurar-cliente.bat</code> (automático).</li>
                <li>O manualmente: doble clic en <code>rootCA.crt</code> &gt; <em>Instalar certificado</em>.</li>
                <li>Selecciona <em>Equipo local</em> &gt; <em>Colocar en: Entidades de certificación raíz de confianza</em>.</li>
            </ol>
        </div>

        <div class="cert-trust-card">
            <div class="cert-trust-title">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.93V17a1 1 0 0 1-2 0v-.07A7 7 0 0 1 5.07 11H5a1 1 0 0 1 0-2h.07A7 7 0 0 1 11 3.07V3a1 1 0 0 1 2 0v.07A7 7 0 0 1 18.93 9H19a1 1 0 0 1 0 2h-.07A7 7 0 0 1 13 16.93z"></path>
                </svg>
                <span>macOS / Apple iOS</span>
            </div>
            <ol class="cert-trust-steps">
                <li>Abre la aplicación <em>Acceso a Llaveros (Keychain Access)</em> en el Mac.</li>
                <li>Arrastra <code>rootCA.crt</code> a la categoría <em>Sistema (System)</em>.</li>
                <li>Doble clic en el certificado &gt; Despliega <em>Confiar (Trust)</em> &gt; Selecciona <em>Confiar siempre</em>.</li>
            </ol>
        </div>

        <div class="cert-trust-card">
            <div class="cert-trust-title">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <polyline points="6 10 10 14 6 18"></polyline>
                    <line x1="12" y1="18" x2="18" y2="18"></line>
                </svg>
                <span>Linux (Debian / Ubuntu)</span>
            </div>
            <ol class="cert-trust-steps">
                <li>Copia el archivo: <code>sudo cp rootCA.crt /usr/local/share/ca-certificates/srvctl.crt</code></li>
                <li>Actualiza el almacén del sistema: <code>sudo update-ca-certificates</code></li>
                <li>Verifica con <code>curl -I https://<?= e($CONFIG['BASE_DOMAIN']) ?></code> sin banderas <code>-k</code>.</li>
            </ol>
        </div>
    </div>
</div>

<!-- 6. Recurso Compartido Samba SMBv3 -->
<div class="card" style="margin-top: 16px;">
    <div class="card-head">
        <div>
            <h2>Integración de Red: Recurso Compartido Samba SMBv3</h2>
            <div class="muted" style="margin-top: 2px;">Acceso de red local autenticado al directorio maestro <code>/var/www</code></div>
        </div>
        <span class="tag <?= e($samba['config_valid'] ? 'tag-ok' : 'tag-err') ?>"><?= e($samba['config_valid'] ? 'SMBv3 Operativo' : 'Revisar') ?></span>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 16px;">
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 18px; flex: 1; min-width: 260px;">
            <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px;">Ruta UNC de Red (Windows / Mac)</div>
            <code style="font-size: 1rem; color: var(--primary); font-weight: 700;">\\<?= e($CONFIG['SERVER_IP']) ?>\proyectos</code>
        </div>
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 18px; flex: 1; min-width: 260px;">
            <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px;">Permisos del Sistema</div>
            <code style="font-size: 1rem; color: var(--success); font-weight: 700;">SGID 2775 (www-data)</code>
        </div>
    </div>

    <h3 class="subtitle" style="font-size: 0.88rem; font-weight: 700; margin-bottom: 6px;">Sesiones Activas de Samba</h3>
    <?php if (empty($samba['sessions'])): ?>
        <p class="muted">Sin sesiones conectadas en este momento.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario Autenticado</th>
                    <th>Equipo Conectado (IP / Host)</th>
                    <th>Protocolo Negociado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($samba['sessions'] ?? [] as $session): ?>
                <tr>
                    <td><strong><code><?= e($session['user']) ?></code></strong></td>
                    <td><code><?= e($session['machine']) ?></code></td>
                    <td><span class="badge" style="background: var(--teal-soft); color: var(--teal); font-weight: 700;"><?= e($session['protocol']) ?></span></td>
                    <td><span class="tag tag-ok">Conectado</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
