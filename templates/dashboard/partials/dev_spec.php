<?php
/** @var array $spec */
if (!isset($spec)) return;
?>
<div class="dev-spec-box">
    <div class="dev-spec-header">
        <div class="dev-spec-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
            <span><?= e($spec['title'] ?? '') ?></span>
        </div>
        <span class="dev-spec-badge">Contrato Backend</span>
    </div>
    <div class="dev-spec-grid">
        <div class="dev-spec-item">
            <strong>Endpoints / Acciones</strong>
            <?php if (!empty($spec['endpoints'])): ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                    <?php foreach ($spec['endpoints'] as $ep): ?>
                        <li><code><?= e($ep) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span>No requiere endpoints dedicados.</span>
            <?php endif; ?>
        </div>
        <div class="dev-spec-item">
            <strong>Comandos del Sistema Debian 13</strong>
            <?php if (!empty($spec['commands'])): ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                    <?php foreach ($spec['commands'] as $cmd): ?>
                        <li><code><?= e($cmd) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span>Lectura directa de archivos o telemetría.</span>
            <?php endif; ?>
        </div>
        <div class="dev-spec-item">
            <strong>Rutas / Archivos Involucrados</strong>
            <?php if (!empty($spec['paths'])): ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                    <?php foreach ($spec['paths'] as $p): ?>
                        <li><code><?= e($p) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span>Variables de entorno o configuración en memoria.</span>
            <?php endif; ?>
        </div>
        <div class="dev-spec-item">
            <strong>Reglas de Negocio & Seguridad</strong>
            <p style="margin: 0; line-height: 1.45;"><?= e($spec['notes'] ?? '') ?></p>
        </div>
    </div>
</div>
