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
            if (empty($snapshot) || !preg_match('/^[a-zA-Z0-9_\.-]+$/', $snapshot)) {
                $respond(false, 'Nombre de snapshot inválido.', 'projects');
            } else {
                [$code, $output] = panel_srvctl(['backup', 'rollback', $snapshot], 120);
                if ($code === 0) {
                    $respond(true, "Respaldo '{$snapshot}' restaurado correctamente.", 'projects');
                } else {
                    $respond(false, "Error al restaurar: " . $output, 'projects');
                }
            }
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
