<?php
$ssl = panel_ssl();
$samba = panel_samba();
?>
<section class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Certificado comodín</h2>
            <span class="tag <?= ($ssl['exists'] && $ssl['days_left'] > 0) ? 'tag-ok' : 'tag-err' ?>">
                <?= $ssl['exists'] ? (int)$ssl['days_left'] . ' días' : 'N/D' ?>
            </span>
        </div>
        <?php if ($ssl['exists']): ?>
            <dl class="kv">
                <div><dt>Titular (CN)</dt><dd><?= e($ssl['subject']) ?></dd></div>
                <div><dt>Emisor</dt><dd><?= e($ssl['issuer']) ?></dd></div>
                <div><dt>Válido desde</dt><dd><?= e($ssl['valid_from']) ?></dd></div>
                <div><dt>Válido hasta</dt><dd><?= e($ssl['valid_to']) ?></dd></div>
            </dl>
            <h3 class="subtitle">Nombres alternativos (SAN)</h3>
            <ul class="chips">
                <?php foreach ($ssl['sans'] as $san): ?><li class="chip"><?= e($san) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No se encontró el certificado.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Autoridad certificadora raíz</h2>
            <span class="tag <?= $ssl['ca_exists'] ? 'tag-ok' : 'tag-err' ?>"><?= $ssl['ca_exists'] ? 'Presente' : 'Ausente' ?></span>
        </div>
        <p class="muted">Instala la CA raíz en los clientes para evitar advertencias del navegador.</p>
        <div class="actions">
            <a class="btn btn-outline" href="/downloads/rootCA.crt" download>Descargar rootCA.crt</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>Recurso compartido Samba</h2>
        <?php if ($samba !== null): ?>
            <span class="tag <?= !empty($samba['config_valid']) ? 'tag-ok' : 'tag-err' ?>"><?= !empty($samba['config_valid']) ? 'Configuración válida' : 'Revisar' ?></span>
        <?php endif; ?>
    </div>
    <?php if ($samba === null): ?>
        <p class="muted">No se pudo obtener información de Samba.</p>
    <?php else: ?>
        <ul class="chips">
            <?php foreach (($samba['shares'] ?? []) as $share): ?><li class="chip">[<?= e((string)$share) ?>]</li><?php endforeach; ?>
        </ul>
        <h3 class="subtitle">Sesiones activas</h3>
        <?php if (($samba['sessions'] ?? []) === []): ?>
            <p class="muted">Sin sesiones conectadas.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Usuario</th><th>Equipo</th><th>Protocolo</th></tr></thead>
                <tbody>
                <?php foreach ($samba['sessions'] as $session): ?>
                    <tr>
                        <td><?= e((string)($session['user'] ?? '')) ?></td>
                        <td><?= e((string)($session['machine'] ?? '')) ?></td>
                        <td><?= e((string)($session['protocol'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</section>
