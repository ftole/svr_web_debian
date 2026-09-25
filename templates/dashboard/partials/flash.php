<?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= nl2br(e($flash['message'])) ?>
    </div>
<?php endforeach; ?>
