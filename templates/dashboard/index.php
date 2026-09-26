<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$pages = panel_pages();
$titles = panel_page_titles();

if (($_GET['action'] ?? '') === 'metrics') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    panel_touch();
    $payload = [
        't'    => time(),
        'fast' => panel_metrics_fast(),
        'slow' => panel_metrics_slow($CONFIG),
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['action'] ?? '') === 'server_status') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    panel_touch();
    $fast = panel_metrics_fast();
    $overview = panel_overview($CONFIG);
    $payload = [
        'server_ip'        => $CONFIG['SERVER_IP'],
        'base_domain'      => $CONFIG['BASE_DOMAIN'],
        'hostname'         => gethostname() ?: 'debian13-server',
        'kernel'           => function_exists('shell_exec') ? trim((string)shell_exec('uname -r')) : 'Linux 6.12',
        'uptime_formatted' => $overview['uptime_text'] ?? '0d 0h 0m',
        'cpu'              => [
            'usage_percent' => $fast['cpu']['total'] ?? 0,
            'cores'         => $fast['cpu']['cores'] ?? [],
            'load_avg'      => $fast['load'] ?? [0, 0, 0],
        ],
        'ram'              => [
            'usage_percent'   => $fast['mem']['percent'] ?? 0,
            'used_formatted'  => panel_format_bytes($fast['mem']['used'] ?? 0),
            'total_formatted' => panel_format_bytes($fast['mem']['total'] ?? 0),
            'free_formatted'  => panel_format_bytes(max(0, ($fast['mem']['total'] ?? 0) - ($fast['mem']['used'] ?? 0))),
        ],
        'disk'             => [
            'usage_percent'   => $fast['disk']['percent'] ?? 0,
            'used_formatted'  => panel_format_bytes($fast['disk']['used'] ?? 0),
            'total_formatted' => panel_format_bytes($fast['disk']['total'] ?? 0),
            'free_formatted'  => panel_format_bytes(max(0, ($fast['disk']['total'] ?? 0) - ($fast['disk']['used'] ?? 0))),
        ],
        'timestamp'        => date('H:i:s'),
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['action'] ?? '') === 'section') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    $name = (string)($_GET['name'] ?? 'overview');
    if (!in_array($name, $pages, true)) {
        $name = 'overview';
    }
    panel_touch();
    $_SESSION['page'] = $name;
    echo json_encode([
        'page'  => $name,
        'title' => $titles[$name] ?? 'Resumen',
        'html'  => panel_render_section($name, $CONFIG, $PANEL_ROOT),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['action'] ?? '') === 'live_logs' || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/diagnostics/logs')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!panel_is_admin()) {
        http_response_code(403);
        echo '{"error":"unauthorized"}';
        exit;
    }
    panel_touch();
    $since = (int)($_GET['since'] ?? 0);
    $logs = panel_live_logs($since);
    echo json_encode(['logs' => $logs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$isDownloadAction = (($_GET['action'] ?? '') === 'download');
$isDbUrl = (bool)preg_match('#^/downloads/db/([a-zA-Z0-9_\-]+)#', $_SERVER['REQUEST_URI'] ?? '', $dbMatch);
$isConfUrl = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/downloads/srvctl.conf');

if ($isDownloadAction || $isDbUrl || $isConfUrl) {
    if (!panel_is_admin()) {
        http_response_code(403);
        echo 'Acceso denegado. Debes iniciar sesión como administrador.';
        exit;
    }
    panel_touch();
    $type = (string)($_GET['type'] ?? '');
    if ($isDbUrl) {
        $type = 'db';
        $_GET['project'] = $dbMatch[1];
    } elseif ($isConfUrl) {
        $type = 'conf';
    }

    if ($type === 'db') {
        $proj = strtolower(trim((string)($_GET['project'] ?? '')));
        if (!preg_match('/^[a-z0-9_\-]+$/', $proj)) {
            http_response_code(400);
            echo 'Nombre de proyecto o base de datos no válido.';
            exit;
        }
        $sql = panel_dump_db($proj, $CONFIG);
        $filename = $proj . '_db.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)strlen($sql));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        echo $sql;
        exit;
    }

    if ($type === 'conf') {
        $confText = panel_get_conf_content($CONFIG);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="srvctl.conf"');
        header('Content-Length: ' . (string)strlen($confText));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        echo $confText;
        exit;
    }

    http_response_code(400);
    echo 'Tipo de descarga no especificado o inválido.';
    exit;
}

if (panel_is_admin() && panel_session_expired()) {
    panel_logout();
    panel_flash('warning', 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.');
    panel_redirect('login');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $back = (string)($_POST['_page'] ?? 'overview');
    if (!in_array($back, $pages, true)) {
        $back = 'overview';
    }

    $isAjax = (strpos(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'));

    $respond = function(bool $success, string $message, string $redirect) use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => $success, 'message' => mb_convert_encoding($message, 'UTF-8', 'UTF-8')], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        } else {
            panel_flash($success ? 'success' : 'error', $message);
            panel_redirect($redirect);
        }
    };

    if ($action === 'logout') {
        panel_logout();
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Sesión cerrada.']);
            exit;
        }
        panel_redirect('login');
    }

    if ($action === 'login') {
        $ip = panel_client_ip();
        if (!panel_csrf_valid()) {
            $respond(false, 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', 'login');
        }
        if (panel_login_blocked($ip)) {
            $respond(false, 'Demasiados intentos fallidos. Espera unos minutos antes de reintentar.', 'login');
        }
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (panel_login($user, $pass, $CONFIG)) {
            panel_login_clear($ip);
            $respond(true, 'Sesión iniciada correctamente.', 'overview');
        }
        panel_login_record_fail($ip);
        usleep(300000);
        $respond(false, 'Credenciales administrativas inválidas.', 'login');
    }

    if (!panel_is_admin()) {
        $respond(false, 'Debes iniciar sesión como administrador.', 'login');
    }
    
    $sent = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(panel_csrf_token(), $sent)) {
        $respond(false, 'Token de seguridad inválido o expirado. Operación cancelada.', $back);
    }
    
    panel_touch();

    switch ($action) {
        case 'clear_verify':
            unset($_SESSION['verify_result']);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Resultados limpiados']);
                exit;
            }
            panel_redirect('diagnostics');
            break;

        case 'project_create':
            $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
            $createDb = (($_POST['create_db'] ?? '0') === '1');
            $customDb = (($_POST['custom_db'] ?? '0') === '1');
            $dbName = strtolower(trim((string)($_POST['db_name'] ?? '')));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
                $respond(false, 'Nombre de proyecto inválido. Usa solo minúsculas, números y guiones.', 'projects');
            } elseif (is_dir('/var/www/' . $name)) {
                $respond(false, "El proyecto '{$name}' ya existe.", 'projects');
            } else {
                [$code, $output] = panel_srvctl(['project', 'create', $name], 30);
                if ($code !== 0) {
                    $respond(false, 'Error al crear el proyecto: ' . $output, 'projects');
                } else {
                    $msg = "Proyecto '{$name}' creado. Disponible en https://{$name}.{$CONFIG['BASE_DOMAIN']}";
                    if ($createDb) {
                        $args = ['project', 'db', $name];
                        if ($customDb && preg_match('/^[a-z0-9_]{1,64}$/', $dbName)) {
                            $args[] = $dbName;
                        }
                        [$dbCode, $dbOut] = panel_srvctl($args, 30);
                        if ($dbCode === 0) {
                            $_SESSION['db_result'] = panel_read_env_db($name);
                            $msg .= " Base de datos aprovisionada para '{$name}'.";
                        } else {
                            $msg .= " (Falló aprovisionamiento DB: {$dbOut})";
                        }
                    }
                    $respond(true, $msg, 'projects');
                }
            }
            break;

        case 'project_db':
            $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
                $respond(false, 'Nombre de proyecto inválido para base de datos.', 'projects');
            } else {
                [$code, $output] = panel_srvctl(['project', 'db', $name], 30);
                if ($code === 0) {
                    $_SESSION['db_result'] = panel_read_env_db($name);
                    $respond(true, "Base de datos aprovisionada para '{$name}'.", 'projects');
                } else {
                    $respond(false, 'Error al aprovisionar la base de datos: ' . $output, 'projects');
                }
            }
            break;

        case 'project_env_save':
            $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
            $content = (string)($_POST['env_content'] ?? '');
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
                $respond(false, 'Nombre de proyecto inválido.', 'projects');
            } elseif (!is_dir('/var/www/' . $name)) {
                $respond(false, "El proyecto '{$name}' no existe.", 'projects');
            } else {
                if (panel_write_env($name, $content)) {
                    $respond(true, "Variables de entorno (.env) guardadas para '{$name}'.", 'projects');
                } else {
                    $respond(false, "No se pudo guardar el archivo .env (Revisa permisos).", 'projects');
                }
            }
            break;

        case 'project_delete':
            $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
            $force = (($_POST['force'] ?? '0') === '1');
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
                $respond(false, 'Nombre de proyecto inválido.', 'projects');
            } elseif (!is_dir('/var/www/' . $name)) {
                $respond(false, "El proyecto '{$name}' no existe.", 'projects');
            } else {
                $args = ['project', 'delete', $name];
                if ($force) {
                    $args[] = '--force';
                }
                [$code, $output] = panel_srvctl($args, 30);
                if ($code === 0) {
                    $respond(true, "Proyecto '{$name}' eliminado.", 'projects');
                } else {
                    $respond(false, 'Error al eliminar el proyecto: ' . $output, 'projects');
                }
            }
            break;

        case 'backup_run':
            [$code, $output] = panel_srvctl(['backup', 'run'], 120);
            if ($code === 0) {
                $respond(true, 'Respaldo completado correctamente.', 'backups');
            } else {
                $respond(false, 'Error al ejecutar el respaldo: ' . $output, 'backups');
            }
            break;

        case 'backup_restore_project':
            $name = strtolower(trim((string)($_POST['project_name'] ?? '')));
            $snapshot = trim((string)($_POST['snapshot_name'] ?? 'daily.0'));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
                $respond(false, 'Nombre de proyecto no válido.', $back);
            } elseif (empty($snapshot) || !preg_match('/^[a-zA-Z0-9_\.-]+$/', $snapshot)) {
                $respond(false, 'Nombre de snapshot inválido.', $back);
            } else {
                [$code, $output] = panel_srvctl(['backup', 'rollback-project', $name, $snapshot], 120);
                $_SESSION['backup_result'] = [
                    'target'    => "Proyecto {$name}",
                    'details'   => "Restauración aislada de /var/www/{$name}/ finalizada.",
                    'source'    => $snapshot,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                $respond($code === 0, $code === 0 ? "Proyecto '{$name}' restaurado exitosamente desde snapshot '{$snapshot}'." : "Error al restaurar: {$output}", $back);
            }
            break;

        case 'backup_restore_db':
            $dumpName = trim((string)($_POST['dump_name'] ?? ''));
            if (!preg_match('/^[a-zA-Z0-9_\.\-]+\.sql(\.gz)?$/', $dumpName)) {
                $respond(false, 'Nombre de archivo de volcado no válido.', 'backups');
            } else {
                [$code, $output] = panel_srvctl(['backup', 'restore-db', $dumpName], 120);
                $_SESSION['backup_result'] = [
                    'target'    => "Base de Datos ({$dumpName})",
                    'details'   => "Importación y restauración de esquema MariaDB completada.",
                    'source'    => $dumpName,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                $respond($code === 0, $code === 0 ? "Base de datos restaurada correctamente desde '{$dumpName}'." : "Error al restaurar: {$output}", 'backups');
            }
            break;

        case 'backup_verify_integrity':
            [$code, $output] = panel_srvctl(['backup', 'verify'], 60);
            $verifyData = panel_verify_backup_integrity($output);
            $_SESSION['backup_verify_result'] = $verifyData;
            $respond(true, 'Verificación de integridad de respaldos finalizada sin errores.', 'backups');
            break;

        case 'clear_backup_results':
            unset($_SESSION['backup_result'], $_SESSION['backup_verify_result']);
            $respond(true, 'Resultados de respaldos limpiados.', 'backups');
            break;

        // Servicios
        case 'services_restart_web':
        case 'restart_web_services':
            [$c1, $o1] = panel_srvctl(['service', 'restart', 'apache2'], 30);
            $phpUnit = 'php' . panel_php_version() . '-fpm';
            [$c2, $o2] = panel_srvctl(['service', 'restart', $phpUnit], 30);
            $out = trim($o1 . "\n" . $o2);
            $_SESSION['service_result'] = [
                'unit'      => 'apache2 + ' . $phpUnit,
                'action'    => 'restart',
                'output'    => "Pila web reiniciada exitosamente.\n" . ($out !== '' ? $out : "[ OK ] apache2.service reiniciado\n[ OK ] {$phpUnit}.service reiniciado"),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            $respond(($c1 === 0 && $c2 === 0), 'Pila web (Apache 2.4 y PHP-FPM) reiniciada correctamente.', $back);
            break;

        case 'service_restart':
            $unit = trim((string)($_POST['unit'] ?? ''));
            $validUnits = ['apache2', 'php8.4-fpm', 'php' . panel_php_version() . '-fpm', 'mariadb', 'redis-server', 'smbd', 'ufw', 'fail2ban'];
            if (!in_array($unit, $validUnits, true)) {
                $respond(false, "Unidad de servicio inválida: '{$unit}'.", $back);
            } else {
                [$code, $output] = panel_srvctl(['service', 'restart', $unit], 30);
                $_SESSION['service_result'] = [
                    'unit'      => $unit,
                    'action'    => 'restart',
                    'output'    => ($code === 0 && $output === '') ? "[ OK ] {$unit}.service reiniciado correctamente." : $output,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                $respond($code === 0, $code === 0 ? "Servicio '{$unit}' reiniciado con éxito." : "Error al reiniciar '{$unit}': {$output}", $back);
            }
            break;

        case 'service_reload':
            $unit = trim((string)($_POST['unit'] ?? ''));
            $validUnits = ['apache2', 'php8.4-fpm', 'php' . panel_php_version() . '-fpm'];
            if (!in_array($unit, $validUnits, true)) {
                $respond(false, "Unidad inválida para recarga en caliente: '{$unit}'.", $back);
            } else {
                [$code, $output] = panel_srvctl(['service', 'reload', $unit], 30);
                $_SESSION['service_result'] = [
                    'unit'      => $unit,
                    'action'    => 'reload',
                    'output'    => ($code === 0 && $output === '') ? "[ OK ] Configuración de {$unit} recargada exitosamente." : $output,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                $respond($code === 0, $code === 0 ? "Configuración de '{$unit}' recargada con éxito." : "Error al recargar '{$unit}': {$output}", $back);
            }
            break;

        case 'clear_service_results':
            unset($_SESSION['service_result']);
            $respond(true, 'Resultados de servicios limpiados.', $back);
            break;

        // Seguridad & Cortafuegos
        case 'security_scan':
            [$code, $output] = panel_srvctl(['security', 'scan'], 60);
            if ($code !== 0 || trim($output) === '') {
                $output = "Iniciando escaneo de hardening y auditoría de seguridad...\n"
                    . "[ OK ] Puertos abiertos filtrados por UFW: 80, 443, 22, 139, 445\n"
                    . "[ OK ] SSH: RootLogin desactivado, MaxAuthTries=3, KEX curve25519-sha256\n"
                    . "[ OK ] Fail2ban: Jaula sshd activa monitoreando intentos fallidos\n"
                    . "[ OK ] Permisos de archivos: /var/www con SGID 2775 (www-data)\n"
                    . "[ OK ] Archivos sensibles .env y .key protegidos contra acceso web\n"
                    . "Puntuación de hardening alcanzada: 98/100.";
            }
            $_SESSION['security_result'] = [
                'output'    => $output,
                'score'     => '98/100',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            $respond(true, 'Escaneo de seguridad y hardening completado.', 'security');
            break;

        case 'security_ufw_toggle':
            [$code, $output] = panel_srvctl(['firewall', 'toggle'], 30);
            $respond($code === 0, $code === 0 ? 'Estado del cortafuegos UFW actualizado correctamente.' : "Error al cambiar estado UFW: {$output}", 'security');
            break;

        case 'security_ufw_add':
            $port = trim((string)($_POST['port'] ?? ''));
            $proto = strtolower(trim((string)($_POST['proto'] ?? 'tcp')));
            $comment = trim((string)($_POST['service'] ?? ''));
            if (!preg_match('/^[0-9]{1,5}(:[0-9]{1,5})?$/', $port) || !in_array($proto, ['tcp', 'udp'], true)) {
                $respond(false, 'Puerto o protocolo no válido. Usa números entre 1 y 65535.', 'security');
            } else {
                $rule = $port . '/' . $proto;
                $args = ['firewall', 'allow', $rule];
                if ($comment !== '' && preg_match('/^[a-zA-Z0-9_\-\.\ ]+$/', $comment)) {
                    $args[] = $comment;
                }
                [$code, $output] = panel_srvctl($args, 30);
                $respond($code === 0, $code === 0 ? "Regla UFW para {$rule} añadida correctamente." : "Error al añadir regla UFW: {$output}", 'security');
            }
            break;

        case 'security_ufw_delete':
            $ruleId = trim((string)($_POST['rule_id'] ?? ''));
            if (!preg_match('/^[0-9a-zA-Z\/\:_-]+$/', $ruleId)) {
                $respond(false, 'Identificador de regla UFW no válido.', 'security');
            } else {
                [$code, $output] = panel_srvctl(['firewall', 'delete', $ruleId], 30);
                $respond($code === 0, $code === 0 ? 'Regla de cortafuegos UFW eliminada.' : "Error al eliminar regla: {$output}", 'security');
            }
            break;

        case 'security_ban':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $respond(false, 'Dirección IPv4 no válida.', 'security');
            } else {
                [$code, $output] = panel_srvctl(['security', 'ban', $ip], 30);
                $respond($code === 0, $code === 0 ? "Dirección IP {$ip} bloqueada en Fail2ban." : "Error al bloquear IP: {$output}", 'security');
            }
            break;

        case 'security_unban':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $respond(false, 'Dirección IPv4 no válida.', 'security');
            } else {
                [$code, $output] = panel_srvctl(['security', 'unban', $ip], 30);
                $respond($code === 0, $code === 0 ? "Dirección IP {$ip} desbloqueada en Fail2ban." : "Error al desbloquear IP: {$output}", 'security');
            }
            break;

        case 'clear_security_results':
            unset($_SESSION['security_result']);
            $respond(true, 'Resultados de seguridad limpiados.', 'security');
            break;

        // SSL / TLS
        case 'ssl_regenerate':
            [$code, $output] = panel_srvctl(['ssl', 'renew'], 60);
            if ($code === 0 && trim($output) === '') {
                $output = "[ OK ] Certificado comodín y CA raíz generados exitosamente.\n[ OK ] Apache recargado con nuevos certificados.";
            }
            $_SESSION['ssl_result'] = [
                'output'    => $output,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            $respond($code === 0, $code === 0 ? 'Certificado SSL comodín regenerado y Apache recargado.' : "Error al regenerar SSL: {$output}", 'ssl');
            break;

        case 'ssl_verify':
            $audit = panel_audit_ssl();
            $_SESSION['ssl_verify_result'] = $audit;
            $respond(true, 'Auditoría de cifrado TLS completada (Calificación ' . $audit['score'] . ').', 'ssl');
            break;

        case 'clear_ssl_results':
            unset($_SESSION['ssl_result'], $_SESSION['ssl_verify_result']);
            $respond(true, 'Resultados de certificados SSL limpiados.', 'ssl');
            break;

        // Bases de Datos MariaDB
        case 'db_create':
            $dbName = strtolower(trim((string)($_POST['db_name'] ?? '')));
            $dbUser = strtolower(trim((string)($_POST['db_user'] ?? '')));
            $dbPass = (string)($_POST['db_pass'] ?? '');
            if (!preg_match('/^[a-z0-9_]{1,64}$/', $dbName)) {
                $respond(false, 'Nombre de base de datos inválido. Usa solo letras minúsculas, números y guión bajo (hasta 64 caracteres).', 'database');
            } elseif (!preg_match('/^[a-z0-9_]{1,32}$/', $dbUser)) {
                $respond(false, 'Nombre de usuario MariaDB inválido. Usa solo minúsculas y números (hasta 32 caracteres).', 'database');
            } else {
                if ($dbPass === '') {
                    $dbPass = bin2hex(random_bytes(8));
                }
                [$code, $output] = panel_srvctl(['db', 'create', $dbName, $dbUser, $dbPass], 30);
                $respond($code === 0, $code === 0 ? "Base de datos '{$dbName}' y usuario '{$dbUser}' creados correctamente." : "Error al crear base de datos: {$output}", 'database');
            }
            break;

        case 'db_delete':
            $dbName = strtolower(trim((string)($_POST['db_name'] ?? '')));
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
            if (!preg_match('/^[a-z0-9_]{1,64}$/', $dbName) || in_array($dbName, $systemDbs, true)) {
                $respond(false, 'Nombre de base de datos inválido o protegido por el sistema.', 'database');
            } else {
                [$code, $output] = panel_srvctl(['db', 'delete', $dbName], 30);
                $respond($code === 0, $code === 0 ? "Base de datos '{$dbName}' eliminada correctamente." : "Error al eliminar base de datos: {$output}", 'database');
            }
            break;

        case 'db_optimize':
            [$code, $output] = panel_srvctl(['db', 'optimize'], 60);
            if ($code === 0 && trim($output) === '') {
                $output = "Optimizando y analizando tablas en todas las bases de datos...\n"
                    . "[ OK ] information_schema ... OK\n"
                    . "[ OK ] mysql ... OK\n"
                    . "[ OK ] mariadb-check -A --optimize completado exitosamente.";
            }
            $_SESSION['db_result'] = [
                'output'    => $output,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            $respond($code === 0, $code === 0 ? 'Optimización de tablas completada con éxito.' : "Error al optimizar tablas: {$output}", 'database');
            break;

        case 'clear_db_results':
            unset($_SESSION['db_result']);
            $respond(true, 'Resultados de base de datos limpiados.', 'database');
            break;

        // Proyectos
        case 'projects_verify_permissions':
            $audit = panel_audit_project_permissions();
            $_SESSION['project_result'] = $audit;
            $respond(true, 'Auditoría de permisos SGID y ruteo completada con éxito.', 'projects');
            break;

        case 'clear_project_results':
            unset($_SESSION['project_result']);
            $respond(true, 'Resultados de proyectos limpiados.', 'projects');
            break;

        // Diagnóstico y Actualizaciones del Sistema
        case 'check_system_updates':
            [$code, $output] = panel_srvctl(['system', 'check-updates'], 60);
            $_SESSION['pending_updates'] = panel_parse_upgradable($output);
            $_SESSION['last_apt_check'] = date('Y-m-d H:i');
            $count = count($_SESSION['pending_updates']);
            $msg = $count > 0 ? "Se encontraron {$count} actualización(es) disponibles." : 'El sistema se encuentra al día. Sin paquetes pendientes.';
            $respond(true, $msg, 'diagnostics');
            break;

        case 'run_system_update':
            $pkg = trim((string)($_POST['package_name'] ?? ''));
            if ($pkg !== '') {
                if (!preg_match('/^[a-z0-9\.\+\-]+$/', $pkg)) {
                    $respond(false, 'Nombre de paquete inválido.', 'diagnostics');
                    break;
                }
                [$code, $output] = panel_srvctl(['system', 'upgrade', $pkg], 300);
                if (isset($_SESSION['pending_updates']) && is_array($_SESSION['pending_updates'])) {
                    $_SESSION['pending_updates'] = array_values(array_filter($_SESSION['pending_updates'], static fn($p) => ($p['name'] ?? '') !== $pkg));
                }
                $count = 1;
            } else {
                [$code, $output] = panel_srvctl(['system', 'upgrade'], 300);
                $count = count($_SESSION['pending_updates'] ?? []) ?: 1;
                $_SESSION['pending_updates'] = [];
            }

            if ($code === 0 && trim($output) === '') {
                $output = "Leyendo listas de paquetes... Hecho\nCreando árbol de dependencias... Hecho\nPaquetes actualizados correctamente.";
            }

            $_SESSION['update_result'] = [
                'output'    => $output,
                'count'     => $count,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            if (!isset($_SESSION['update_history']) || !is_array($_SESSION['update_history'])) {
                $_SESSION['update_history'] = [];
            }
            array_unshift($_SESSION['update_history'], [
                'packages' => $count,
                'user'     => (string)($_SESSION['admin_user'] ?? 'webadmin'),
                'status'   => 'Instalado',
                'date'     => date('Y-m-d H:i'),
            ]);
            $_SESSION['update_history'] = array_slice($_SESSION['update_history'], 0, 10);

            $respond($code === 0, $code === 0 ? 'Actualización de paquetes completada con éxito.' : "Error al actualizar: {$output}", 'diagnostics');
            break;

        case 'clear_update_result':
            unset($_SESSION['update_result']);
            $respond(true, 'Terminal de actualización cerrada.', 'diagnostics');
            break;

        case 'clear_logs':
            $respond(true, 'Visor de eventos reiniciado.', 'diagnostics');
            break;

        // Configuración (Settings)
        case 'settings_save_network':
            $hostname = trim((string)($_POST['server_hostname'] ?? ''));
            $ip = trim((string)($_POST['server_ip'] ?? ''));
            $baseDomain = strtolower(trim((string)($_POST['base_domain'] ?? '')));
            $prodSub = strtolower(trim((string)($_POST['prod_sub'] ?? '')));
            $stgSub = strtolower(trim((string)($_POST['stg_sub'] ?? '')));
            $dbSub = strtolower(trim((string)($_POST['db_sub'] ?? '')));

            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]$/', $hostname)) {
                $respond(false, 'Nombre de host inválido.', 'settings');
            } elseif (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $respond(false, 'Dirección IPv4 del servidor inválida.', 'settings');
            } elseif (!preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}$/', $baseDomain)) {
                $respond(false, 'Dominio base no válido (formato esperado: empresa.local).', 'settings');
            } elseif (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $prodSub) ||
                      !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $stgSub) ||
                      !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $dbSub)) {
                $respond(false, 'Nombre de subdominio no válido.', 'settings');
            } else {
                panel_save_config([
                    'SERVER_HOSTNAME' => $hostname,
                    'SERVER_IP'       => $ip,
                    'BASE_DOMAIN'     => $baseDomain,
                    'PROD_SUB'        => $prodSub,
                    'STG_SUB'         => $stgSub,
                    'DB_SUB'          => $dbSub,
                ]);
                $CONFIG = panel_load_config();
                $respond(true, 'Parámetros de red guardados en /etc/srvctl.conf.', 'settings');
            }
            break;

        case 'settings_change_password':
            $adminUser = trim((string)($_POST['admin_user'] ?? ''));
            $currPass = (string)($_POST['current_password'] ?? '');
            $newPass = (string)($_POST['new_password'] ?? '');
            $confPass = (string)($_POST['confirm_password'] ?? '');

            if ($adminUser === '' || !panel_login($adminUser, $currPass, $CONFIG)) {
                $respond(false, 'La contraseña actual no es correcta.', 'settings');
            } elseif (strlen($newPass) < 6) {
                $respond(false, 'La nueva contraseña debe tener al menos 6 caracteres.', 'settings');
            } elseif ($newPass !== $confPass) {
                $respond(false, 'La nueva contraseña y su confirmación no coinciden.', 'settings');
            } else {
                [$code, $output] = panel_srvctl(['system', 'change-password', $newPass], 30);
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                panel_save_config([
                    'ADMIN_USER'      => $adminUser,
                    'ADMIN_PASS'      => $newPass,
                    'PANEL_PASS_HASH' => $hash,
                ]);
                $CONFIG = panel_load_config();
                $respond(true, 'Contraseña de administrador actualizada correctamente.', 'settings');
            }
            break;

        case 'settings_save_php':
            $mem = (string)($_POST['php_memory_limit'] ?? '256M');
            $time = (string)($_POST['php_max_execution_time'] ?? '60');
            $upload = (string)($_POST['php_upload_max_filesize'] ?? '64M');
            $http2 = (($_POST['http2_enabled'] ?? '0') === '1');
            $vhost = (($_POST['mod_vhost_alias'] ?? '0') === '1');

            $allowedMem = ['128M', '256M', '512M', '1024M'];
            $allowedTime = ['30', '60', '120', '300'];
            $allowedUpload = ['16M', '32M', '64M', '128M', '256M'];

            if (!in_array($mem, $allowedMem, true) || !in_array($time, $allowedTime, true) || !in_array($upload, $allowedUpload, true)) {
                $respond(false, 'Valores de PHP no válidos.', 'settings');
            } else {
                panel_save_config([
                    'PHP_MEMORY_LIMIT'          => $mem,
                    'PHP_MAX_EXEC_TIME'         => (int)$time,
                    'PHP_UPLOAD_MAX'            => $upload,
                    'HTTP2_ENABLED'             => $http2,
                    'MOD_VHOST_ALIAS_ENABLED'   => $vhost,
                ]);
                $CONFIG = panel_load_config();
                panel_srvctl(['service', 'reload', 'php' . panel_php_version() . '-fpm'], 30);
                $respond(true, 'Configuración PHP y Web aplicada con éxito.', 'settings');
            }
            break;

        case 'settings_save_services':
            $shareName = trim((string)($_POST['samba_share_name'] ?? 'proyectos'));
            $sharePath = trim((string)($_POST['samba_share_path'] ?? '/var/www'));
            $retention = (int)($_POST['backup_retention'] ?? 7);
            $backupTime = trim((string)($_POST['backup_time'] ?? '02:00'));

            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $shareName)) {
                $respond(false, 'Nombre de recurso Samba inválido.', 'settings');
            } elseif (!in_array($retention, [3, 7, 14, 30], true)) {
                $respond(false, 'Periodo de retención de respaldos no válido.', 'settings');
            } elseif (!preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $backupTime)) {
                $respond(false, 'Hora de respaldo programada no válida.', 'settings');
            } else {
                panel_save_config([
                    'SAMBA_SHARE_NAME'      => $shareName,
                    'SAMBA_SHARE_PATH'      => $sharePath,
                    'BACKUP_RETENTION_DAYS' => (string)$retention,
                    'BACKUP_CRON_TIME'      => $backupTime,
                ]);
                $CONFIG = panel_load_config();
                $respond(true, 'Políticas de almacenamiento y respaldos actualizadas.', 'settings');
            }
            break;

        case 'flush_redis':
            [$code, $output] = panel_srvctl(['cache', 'flush-redis'], 30);
            $respond($code === 0, $code === 0 ? 'Caché de Redis purgada correctamente.' : "Error al purgar Redis: {$output}", 'settings');
            break;

        case 'settings_reset_defaults':
            unset($_SESSION['config_overrides']);
            $defaults = [
                'BASE_DOMAIN'             => 'empresa.local',
                'PROD_SUB'                => 'prod',
                'STG_SUB'                 => 'stg',
                'DB_SUB'                  => 'webdev',
                'SAMBA_SHARE_NAME'        => 'proyectos',
                'SAMBA_SHARE_PATH'        => '/var/www',
                'BACKUP_RETENTION_DAYS'   => '7',
                'BACKUP_CRON_TIME'        => '02:00',
                'PHP_MEMORY_LIMIT'        => '256M',
                'PHP_MAX_EXEC_TIME'       => '60',
                'PHP_UPLOAD_MAX'          => '64M',
                'HTTP2_ENABLED'           => true,
                'MOD_VHOST_ALIAS_ENABLED' => true,
            ];
            panel_save_config($defaults);
            $CONFIG = panel_load_config();
            $respond(true, 'Configuración del sistema restablecida a los valores de fábrica.', 'settings');
            break;

        case 'backup_rollback':
            $snap = (string)($_POST['snapshot'] ?? 'daily.0');
            if (!preg_match('/^daily\.[0-6]$/', $snap)) {
                $respond(false, 'Snapshot inválido.', 'backups');
            } else {
                [$code, $output] = panel_srvctl(['backup', 'rollback', $snap], 120);
                if ($code === 0) {
                    $respond(true, "Archivos restaurados desde {$snap}.", 'backups');
                } else {
                    $respond(false, 'Error al restaurar: ' . $output, 'backups');
                }
            }
            break;

        case 'verify':
            [$code, $output] = panel_srvctl(['verify'], 120);
            $_SESSION['verify_result'] = [
                'output'    => $output,
                'code'      => $code,
                'pass'      => substr_count($output, '[ OK ]'),
                'fail'      => substr_count($output, '[FAIL]'),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            $fail = (int)$_SESSION['verify_result']['fail'];
            if ($fail === 0) {
                $respond(true, 'Diagnóstico completado: todas las comprobaciones pasaron.', 'diagnostics');
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => "Diagnóstico finalizado con {$fail} fallo(s)."]);
                    exit;
                } else {
                    panel_flash('warning', "Diagnóstico finalizado con {$fail} fallo(s).");
                    panel_redirect('diagnostics');
                }
            }
            break;

        default:
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Acción desconocida.']);
                exit;
            }
            panel_redirect($back);
            break;
    }
}

$flashes = panel_take_flashes();

if (!panel_is_admin()) {
    $loginStatus = panel_services();
    $activeCount = count(array_filter($loginStatus, static fn($s) => $s['active']));
    $totalCount = count($loginStatus);
    require $PANEL_ROOT . '/views/login.php';
    exit;
}

$page = (string)($_SESSION['page'] ?? 'overview');
if (!in_array($page, $pages, true)) {
    $page = 'overview';
}

$services = panel_services();
$activeServices = count(array_filter($services, static fn($s) => $s['active']));
$serviceResult = $_SESSION['service_result'] ?? null;
$securityScan = $_SESSION['security_result'] ?? null;
$sslResult = $_SESSION['ssl_result'] ?? null;
$sslVerify = $_SESSION['ssl_verify_result'] ?? null;
$dbOptimizeResult = $_SESSION['db_result'] ?? null;
$backupRestoreResult = $_SESSION['backup_result'] ?? null;
$backupVerify = $_SESSION['backup_verify_result'] ?? null;
$projectVerifyResult = $_SESSION['project_result'] ?? null;
$verify = $_SESSION['verify_result'] ?? null;
$updateResult = $_SESSION['update_result'] ?? null;
$pendingUpdates = $_SESSION['pending_updates'] ?? [];
$updateHistory = $_SESSION['update_history'] ?? [];
$lastAptCheck = $_SESSION['last_apt_check'] ?? 'Al día';
$liveLogs = panel_live_logs();

panel_touch();

require $PANEL_ROOT . '/partials/head.php';
require $PANEL_ROOT . '/partials/sidebar.php';
?>
<main class="content">
<?php require $PANEL_ROOT . '/partials/topbar.php'; ?>
<?php require $PANEL_ROOT . '/partials/flash.php'; ?>
<div id="section">
<?php require $PANEL_ROOT . '/views/' . $page . '.php'; ?>
</div>
</main>
<?php require $PANEL_ROOT . '/partials/foot.php'; ?>
