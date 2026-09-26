---
name: fullstack-integration
description: >-
  Guía y especificación técnica para la integración end-to-end entre la interfaz web (Dashboard PHP),
  el despachador central index.php, el wrapper de seguridad /usr/local/bin/srvctl-web-wrapper y el CLI bin/srvctl en Debian 13.
---

# Procedimiento de Integración Fullstack para srvctl

Este documento define la arquitectura y el protocolo de comunicación bidireccional entre la interfaz web y el sistema operativo Debian 13.

## 1. Cadena de Ejecución Segura
Toda petición ejecutada por la web sigue este flujo estricto:
```
Navegador (Fetch / AJAX)
   │ POST / { action: '...', csrf_token: '...', ... }
   ▼
Apache 2.4 (VirtualHost _dashboard -> Socket dedicado FPM)
   │
   ▼
PHP-FPM Dashboard (templates/dashboard/index.php como usuario www-data)
   │ Valida CSRF (hash_equals), sanitiza inputs con regex
   ▼
templates/dashboard/app/srvctl.php::panel_srvctl(['subcomando', ...])
   │ env SRVCTL_CMD='subcomando ...' sudo /usr/local/bin/srvctl-web-wrapper
   ▼
/usr/local/bin/srvctl-web-wrapper (Ejecutado como root vía sudoers NOPASSWD)
   │ Valida regex de lista blanca estricta
   ▼
/usr/local/bin/srvctl (CLI modular)
   │ Ejecuta la lógica del módulo correspondiente
   ▼
Retorno a PHP: [$code, $output]
   │
   ▼
Respuesta JSON al Navegador: { "success": true/false, "message": "...", "data": ... }
```

## 2. Requisitos de Respuestas JSON para el Frontend
El script `assets/js/app.js` intercepta todo formulario con `data-loading="..."` y espera estrictamente:
- Si éxito: `{ "success": true, "message": "Mensaje descriptivo en español" }`
- Si error: `{ "success": false, "message": "Detalle del error en español" }`
- Si la acción genera un informe (ej. auditoría o terminal): Guardar resultado en `$_SESSION['<modulo>_result']` y refrescar la sección con `?action=section&name=<modulo>`.

## 3. Subcomandos CLI Requeridos en `bin/srvctl`
1. `service restart <apache2|php8.4-fpm|mariadb|redis-server|smbd|ufw|fail2ban>`
2. `service reload <apache2|php8.4-fpm>`
3. `firewall toggle` (conmuta estado UFW)
4. `firewall allow <puerto>/<proto> [<comentario>]`
5. `firewall delete <regla_num|puerto/proto>`
6. `security ban <ip>` (fail2ban-client set sshd banip <ip>)
7. `security unban <ip>` (fail2ban-client set sshd unbanip <ip>)
8. `security scan` (auditoría de puertos y sudo.log)
9. `ssl renew` (recreación de CA/comodín y recarga limpia de Apache)
10. `backup rollback-project <proyecto> <snapshot>` (rsync aislado sobre `/var/www/<proyecto>/`)
11. `backup restore-db <dump_filename>` (descompresión e importación MariaDB)
12. `backup verify` (verificación de snapshots y sumas sha256)
13. `db create <db_name> <db_user> [<db_pass>]` (creación directa de esquema y usuario)
14. `db delete <db_name>` (eliminación directa de BD y usuario)
15. `db optimize` (mariadb-check -A --optimize)
16. `cache flush-redis` (redis-cli flushall)
17. `system check-updates` (apt update && apt list --upgradable)
18. `system upgrade [<package>]` (apt-get install -y --only-upgrade o apt-get dist-upgrade)
19. `system change-password <new_password>` (actualiza ADMIN_PASS y hash en /etc/srvctl.conf)

## 4. Reglas Obligatorias de Código
- Commits atómicos: un archivo por commit con prefijo Conventional Commits en español.
- Finales de línea LF en todos los archivos.
- `bash -n`, `shellcheck -S warning` y `test_validator.sh` en verde antes de cada despliegue.
- La página de login (`views/login.php`, `robot.js`, `datacenter-bg.svg`) no se modifica.
