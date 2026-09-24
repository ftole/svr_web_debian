#!/bin/bash
# ==============================================================================
# 03_eliminar_residuales.sh - Elimina archivos y directorios residuales del stack
# Uso: sudo bash scripts/cleanup/03_eliminar_residuales.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "=== [Limpieza 3/5] Eliminando archivos y carpetas residuales ==="
rm -rf /var/www/prod /var/www/stg
rm -rf /etc/apache2 /etc/php /etc/mysql /etc/samba /var/lib/mysql /var/log/samba /etc/phpmyadmin
rm -rf /etc/ssl/localcerts /backup /opt/scripts
rm -rf /var/lib/phpmyadmin /var/lib/php
rm -f /var/log/backup-daily.log /var/log/sudo.log /etc/cron.d/web-daily-backup
rm -f /etc/sysctl.d/99-disable-ipv6.conf /etc/sudoers.d/99-audit-log /etc/ssh/sshd_config.d/01-hardening.conf
rm -f /etc/systemd/logind.conf.d/99-nas.conf
rm -f /etc/asistente_servidor.conf

mkdir -p /var/www/html
chown -R root:root /var/www

log "[OK] Archivos y carpetas residuales eliminados."
