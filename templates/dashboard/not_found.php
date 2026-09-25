<?php
declare(strict_types=1);

http_response_code(404);

$subdomain = $_SERVER['HTTP_HOST'] ?? 'subdominio';
$baseDomain = 'empresa.local';
$confFiles = ['/etc/srvctl.conf', '/etc/asistente_servidor.conf'];
foreach ($confFiles as $cf) {
    if (file_exists($cf) && is_readable($cf)) {
        $lines = @file($cf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), 'BASE_DOMAIN=')) {
                    $baseDomain = trim(explode('=', $line, 2)[1], " '\t\n\r\0\x0B\"");
                    break 2;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyecto no encontrado &mdash; <?= htmlspecialchars($subdomain) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a; color: #f8fafc; display: flex; align-items: center;
            justify-content: center; min-height: 100vh; margin: 0; padding: 20px;
        }
        .box {
            background: #1e293b; border: 1px solid #334155; border-radius: 10px;
            padding: 35px; max-width: 520px; width: 100%; text-align: center;
        }
        h1 { font-size: 1.5rem; color: #f59e0b; margin-bottom: 12px; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px; }
        code { background: #0f172a; padding: 3px 8px; border-radius: 4px; color: #38bdf8; }
        .btn {
            display: inline-block; background: #0284c7; color: #fff; text-decoration: none;
            padding: 10px 18px; border-radius: 6px; font-weight: 500; font-size: 0.95rem;
        }
        .btn:hover { background: #0369a1; }
    </style>
</head>
<body>
<div class="box">
    <h1>Proyecto no encontrado</h1>
    <p>El subdominio <code><?= htmlspecialchars($subdomain) ?></code> no tiene una carpeta asociada en la red compartida <code>\proyectos</code>.</p>
    <p>Para activarlo, crea la carpeta en el servidor con su respectivo directorio <code>public_html</code>.</p>
    <a href="https://<?= htmlspecialchars($baseDomain) ?>" class="btn">&larr; Volver al Portal Principal</a>
</div>
</body>
</html>
