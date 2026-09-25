<?php
declare(strict_types=1);

http_response_code(404);

$subdomain = $_SERVER['HTTP_HOST'] ?? 'subdominio';
$baseDomain = 'empresa.local';
foreach (['/etc/srvctl-panel.conf', '/etc/srvctl.conf', '/etc/asistente_servidor.conf'] as $file) {
    if (is_file($file) && is_readable($file)) {
        foreach (@file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), 'BASE_DOMAIN=')) {
                $baseDomain = trim(explode('=', $line, 2)[1], " '\"\t\n\r\0\x0B");
                break 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyecto no encontrado</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f7f9; color: #111827; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 34px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 10px 30px rgba(16,24,40,0.08); }
        h1 { font-size: 1.4rem; margin-bottom: 10px; }
        p { color: #6b7280; font-size: 0.95rem; line-height: 1.55; margin-bottom: 18px; }
        code { background: #f3f4f6; padding: 2px 7px; border-radius: 5px; color: #2563eb; }
        .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.92rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>Proyecto no encontrado</h1>
    <p>El subdominio <code><?= htmlspecialchars($subdomain, ENT_QUOTES, 'UTF-8') ?></code> no tiene una carpeta asociada en <code>/var/www</code>.</p>
    <p>Crea la carpeta con su subdirectorio <code>public_html</code> para activarlo.</p>
    <a class="btn" href="https://<?= htmlspecialchars($baseDomain, ENT_QUOTES, 'UTF-8') ?>">Volver al panel</a>
</div>
</body>
</html>
