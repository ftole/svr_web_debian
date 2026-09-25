<?php if (!empty($flashes)): ?>
    <div class="toast-container">
        <?php foreach ($flashes as $flash):
            $type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info'; ?>
            <div class="toast toast-<?= e($type) ?>" data-toast data-ttl="6000">
                <div class="toast-body"><?= nl2br(e($flash['message'])) ?></div>
                <button type="button" class="toast-close" aria-label="Cerrar">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
