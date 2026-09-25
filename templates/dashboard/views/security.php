<?php
$security = panel_security();
?>
<?php if ($security === null): ?>
    <div class="alert alert-warning">No se pudo obtener la información de seguridad (¿`srvctl api security` disponible?).</div>
<?php else: ?>
    <section class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Cortafuegos UFW</h2>
                <span class="tag <?= !empty($security['ufw']['enabled']) ? 'tag-ok' : 'tag-err' ?>"><?= !empty($security['ufw']['enabled']) ? 'Activo' : 'Inactivo' ?></span>
            </div>
            <dl class="kv">
                <div><dt>Entrante</dt><dd><?= e((string)($security['ufw']['default_incoming'] ?? 'N/D')) ?></dd></div>
                <div><dt>Saliente</dt><dd><?= e((string)($security['ufw']['default_outgoing'] ?? 'N/D')) ?></dd></div>
            </dl>
            <table class="table">
                <thead><tr><th>Regla</th></tr></thead>
                <tbody>
                <?php foreach (($security['ufw']['rules'] ?? []) as $rule): ?>
                    <tr><td><code><?= e((string)$rule) ?></code></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-head"><h2>Fail2ban</h2>
                <span class="tag <?= !empty($security['fail2ban']['active']) ? 'tag-ok' : 'tag-err' ?>"><?= !empty($security['fail2ban']['active']) ? 'Activo' : 'Inactivo' ?></span>
            </div>
            <?php $jails = $security['fail2ban']['jails'] ?? []; ?>
            <?php if ($jails === []): ?>
                <p class="muted">Sin jaulas configuradas.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($jails as $jail): ?>
                        <li class="list-row">
                            <span><code><?= e((string)$jail) ?></code></span>
                            <span class="tag"><?= count($security['fail2ban']['banned'][$jail] ?? []) ?> baneadas</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>OpenSSH</h2></div>
            <dl class="kv">
                <div><dt>PermitRootLogin</dt><dd><code><?= e((string)($security['ssh']['permit_root_login'] ?? 'N/D')) ?></code></dd></div>
                <div><dt>MaxAuthTries</dt><dd><code><?= e((string)($security['ssh']['max_auth_tries'] ?? 'N/D')) ?></code></dd></div>
            </dl>
        </div>
        <div class="card">
            <div class="card-head"><h2>Auditoría sudo (últimas líneas)</h2></div>
            <?php $sudoLog = $security['sudo_log'] ?? []; ?>
            <?php if ($sudoLog === []): ?>
                <p class="muted">Sin registros disponibles.</p>
            <?php else: ?>
                <pre class="terminal"><?php foreach ($sudoLog as $line) { echo e((string)$line) . "\n"; } ?></pre>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
