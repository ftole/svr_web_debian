<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$capturedErrors = [];
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) use (&$capturedErrors): bool {
    $errType = match ($errno) {
        E_ERROR, E_USER_ERROR => 'ERROR',
        E_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => "ERR_$errno",
    };
    $capturedErrors[] = "[$errType] $errstr in $errfile:$errline";
    return true;
});

require_once '/var/www/_dashboard/app/bootstrap.php';

$pages = [
    'overview',
    'projects',
    'database',
    'services',
    'security',
    'ssl',
    'backups',
    'diagnostics',
    'downloads',
    'settings'
];

echo "========================================================\n";
echo "PRUEBA DE RENDERIZADO DE LAS 10 SECCIONES DEL DASHBOARD\n";
echo "========================================================\n";

$allPassed = true;

foreach ($pages as $page) {
    $errorsBefore = count($capturedErrors);
    $output = '';
    $exception = null;
    $initLevel = ob_get_level();

    try {
        $output = panel_render_section($page, $CONFIG, '/var/www/_dashboard');
    } catch (\Throwable $t) {
        $exception = $t;
    } finally {
        while (ob_get_level() > $initLevel) {
            ob_end_clean();
        }
    }

    $pageErrors = array_slice($capturedErrors, $errorsBefore);
    $len = strlen($output);

    if ($exception !== null) {
        $allPassed = false;
        echo sprintf("  [FAIL] %-12s EXCEPTION: %s (%s:%d)\n", $page, $exception->getMessage(), $exception->getFile(), $exception->getLine());
    } elseif (!empty($pageErrors)) {
        $allPassed = false;
        echo sprintf("  [FAIL] %-12s Renderizado con %d advertencias/errores (Longitud: %d bytes):\n", $page, count($pageErrors), $len);
        foreach ($pageErrors as $err) {
            echo "         -> $err\n";
        }
    } elseif ($len === 0) {
        $allPassed = false;
        echo sprintf("  [FAIL] %-12s Salida vacia (0 bytes)\n", $page);
    } else {
        echo sprintf("  [PASS] %-12s OK (%d bytes, 0 advertencias, 0 errores)\n", $page, $len);
    }
}

echo "========================================================\n";
if ($allPassed) {
    echo "RESULTADO: 10/10 SECCIONES RENDERIZADAS CORRECTAMENTE\n";
    exit(0);
} else {
    echo "RESULTADO: FALLARON ALGUNAS SECCIONES\n";
    exit(1);
}
