---
name: self-defense-guardian
description: >-
  Invariantes de seguridad, autodefensa y proteccion contra auto-aniquilacion para srvctl en Debian 13.
  Previene el aislamiento del administrador (bloqueo SSH/Firewall), borrado de esquemas o usuarios criticos
  en MariaDB, caida de Apache por errores de sintaxis, corrupcion por concurrencia sin locks y agotamiento de disco.
---

# Invariantes de Seguridad y Autodefensa (Self-Defense Guardian)

Este documento estipula las **invariantes inviolables de autoprotección** de la plataforma `srvctl`. Ningún cambio en el código, comando CLI ni acción disparada desde la interfaz web puede violar estas reglas, ya que provocarían la desconexión del administrador, la destrucción de datos o la caída del servidor.

---

## 1. Invariante Anti-Lockout de Red y Cortafuegos (Firewall Safety)
- **Puertos Vitales Inmutables:** Los puertos de infraestructura crítica **NUNCA** pueden ser eliminados ni bloqueados:
  - `22/tcp` (Acceso SSH administrativo remoto).
  - `80/tcp` y `443/tcp` (Tráfico HTTP/HTTPS y panel de administración central `_dashboard`).
  - `445/tcp` (Recurso Samba `[proyectos]`).
- **Validación Estricta en `delete_rule`:**
  - El borrado de reglas por número (`ufw delete <num>`) debe inspeccionar la regla antes de ejecutar para verificar que no apunte a un puerto vital.
  - El borrado directo de puertos (`ufw delete 22`, `22/tcp`, etc.) debe ser rechazado inmediatamente con error fatal.
- **Inmunidad contra Auto-Baneo en Fail2ban:**
  - `ban_ip` debe rechazar categóricamente direcciones de auto-aislamiento:
    - Loopback (`127.0.0.1`, `::1`).
    - Dirección IP del servidor (`$SERVER_IP`).
    - Dirección IP actual de la sesión del administrador (`$SSH_CLIENT`, `$SSH_CONNECTION`, `REMOTE_ADDR`).
    - Puerta de enlace predeterminada (default gateway).

---

## 2. Invariante de Protección de Base de Datos y Esquemas del Sistema
- **Bases de Datos de Sistema Intocables:**
  - `information_schema`, `performance_schema`, `mysql`, `sys`, `phpmyadmin`, `pmadb`.
- **Protección de Cuentas Administrativas en `delete_database`:**
  - Al eliminar una base de datos, el script **NUNCA** debe ejecutar `DROP USER` sobre usuarios reservados o del sistema:
    - `root`, `pma`, `debian-sys-maint`, `mariadb.sys`, `mysql`, `mysql.*`, `${ADMIN_USER}` (usuario maestro del servidor).
- **Protección contra Inyección Indirecta vía `.env`:**
  - Al ejecutar `project delete <nombre>`, si se lee `DB_DATABASE` del archivo `.env`, dicho valor DEBE pasar por la lista negra de bases de datos protegidas antes de ejecutar `DROP DATABASE`.
- **Salvaguarda Previa e Integridad (Pre-drop Safety Snapshot):**
  - Toda eliminación de base de datos (`srvctl db delete` o `srvctl project delete`) debe generar un volcado transaccional de seguridad en `/var/backups/srvctl/trash/` y validar su integridad con `gzip -t` antes de cualquier borrado irreversible.

---

## 3. Invariante de Validación Previa de Sintaxis en Apache y PHP-FPM (Zero-Downtime Reload)
- **Pre-flight Check Obligatorio en Apache:**
  - **NUNCA** ejecutar `systemctl restart apache2` ni `systemctl reload apache2` sin haber ejecutado previamente `apache2ctl configtest` (o `apachectl -t`).
  - Si `configtest` retorna código de salida distinto de `0`: abortar la recarga y preservar el servicio activo.
- **Pre-flight Check Obligatorio en PHP-FPM:**
  - **NUNCA** reiniciar ni recargar `php8.4-fpm` sin validar previamente con `php-fpm8.4 -t` (o `php-fpm -t`).
- **Verificación de Certificados SSL:**
  - Comprobar la existencia física y no vacía de `/etc/ssl/localcerts/webserver.crt` y `webserver.key` antes de habilitar o recargar VirtualHosts SSL.
- **Nombres de Proyecto Reservados en Ruteo:**
  - No permitir la creación de proyectos con nombres de rutas internas del Dashboard:
    - `app`, `assets`, `downloads`, `partials`, `views`, `not_found`, `phpmyadmin`, `${DB_SUB}`.

---

## 4. Invariante de Exclusión Mutua y Bloqueo de Concurrencia (Mutex/Flock)
- **Control de Concurrencia Obligatorio:**
  - Toda operación de larga duración o potencialmente destructiva debe usar un bloqueo de archivo exclusivo (`flock`):
    - `/run/lock/srvctl-backup.lock` para creación y restauración de snapshots y volcados.
    - `/run/lock/srvctl-apt.lock` para actualizaciones (`system upgrade`, `install`).
    - `/run/lock/srvctl-project.lock` para creación, aprovisionamiento DB y eliminación de proyectos.
    - `/run/lock/srvctl-mariadb.lock` para optimizaciones globales de tablas (`mariadb-check -A --optimize`).
- **Comportamiento ante Colisión:**
  - Si un proceso ya tiene el lock (ej. cron diario de respaldo corriendo), cualquier intento simultáneo (desde CLI o web) debe salir limpiamente con código de error explicativo ("Operación en curso por otro proceso; reintente más tarde"), sin corromper el estado.

---

## 5. Invariante de Guardia de Almacenamiento (Storage Safeguard)
- **Umbral Mínimo de Espacio Libre para Respaldos:**
  - Antes de iniciar cualquier snapshot o volcado (`backup-daily.sh`, `srvctl backup run`), verificar que el sistema de archivos de destino tenga al menos 1.5 GB libres (1536000 KB validados con `validate_disk_space`).
  - Si el espacio libre está por debajo del umbral:
    - Abortar el respaldo antes de agotar los bloques de disco.
    - Notificar y registrar alarma en `/var/log/srvctl.log` y `/var/log/backup-daily.log`.
- **Integridad de Volcados:**
  - Tras generar un volcado `mariadb-dump | gzip`, validar siempre su integridad con `gzip -t`.

---

## 6. Invariante contra Comandos de Autodestrucción Rápida
- **Confirmación Obligatoria en `srvctl reset`:**
  - La invocación directa por CLI de `srvctl reset` debe requerir confirmación explícita mediante un flag especial (ej. `--confirm-destroy-all`) o solicitud interactiva en consola, impidiendo ejecuciones accidentales.
- **Aislamiento en Restauración de Respaldos:**
  - Fomentar la restauración por proyecto (`rollback-project`) sobre el rollback global indiscriminado (`rollback_web`), preservando siempre las carpetas de sistema y el panel `_dashboard`.
