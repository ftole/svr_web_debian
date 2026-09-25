<?php
$verify = $_SESSION['verify_result'] ?? null;
?>
<section class="card">
    <div class="card-head">
        <h2>Diagnóstico del servidor</h2>
        <form method="POST" action="/" data-loading="Ejecutando diagnóstico…">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="_page" value="diagnostics">
            <?= panel_csrf_field() ?>
            <button type="submit" class="btn btn-primary">Ejecutar diagnóstico</button>
        </form>
    </div>
    <p class="muted">Verificación integral de servicios, puertos, sockets, SSL, redirecciones, Samba y phpMyAdmin.</p>
    <?php if ($verify): ?>
        <div class="verify-summary">
            <span class="tag tag-ok"><?= (int)$verify['pass'] ?> OK</span>
            <?php if ((int)$verify['fail'] > 0): ?>
                <span class="tag tag-err"><?= (int)$verify['fail'] ?> fallos</span>
            <?php endif; ?>
            <span class="muted"><?= e($verify['timestamp']) ?></span>
            <form method="POST" action="/" style="display:inline;">
                <input type="hidden" name="action" value="clear_verify">
                <input type="hidden" name="_page" value="diagnostics">
                <?= panel_csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">Limpiar</button>
            </form>
        </div>
        <pre class="terminal"><?php
            foreach (explode("\n", (string)$verify['output']) as $line) {
                $escaped = e($line);
                if (str_contains($line, '[ OK ]')) {
                    echo str_replace('[ OK ]', '<span class="ok-text">[ OK ]</span>', $escaped) . "\n";
                } elseif (str_contains($line, '[FAIL]')) {
                    echo str_replace('[FAIL]', '<span class="err-text">[FAIL]</span>', $escaped) . "\n";
                } else {
                    echo $escaped . "\n";
                }
            }
        ?></pre>
    <?php else: ?>
        <div class="empty"><p class="muted">Ejecuta el diagnóstico para ver los resultados.</p></div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2>Visor de Logs (error.log)</h2>
    </div>
    <pre class="terminal"><?= e(panel_get_error_logs()) ?></pre>
</section>
