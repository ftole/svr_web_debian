#!/bin/bash
# ==============================================================================
# 13_respaldos_cron.sh - Configura respaldos rotativos de 7 dias y cron
# Uso: sudo bash scripts/deploy/13_respaldos_cron.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[13/13] Configurando respaldos diarios rotativos de 7 dias..."

mkdir -p /backup/snapshots /backup/database /opt/scripts

cat > /opt/scripts/backup-daily.sh <<'BACKUP_SCRIPT'
#!/bin/bash
set -eo pipefail
DATE_STR=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="/var/log/backup-daily.log"

echo "=== Respaldo iniciado: ${DATE_STR} ===" >> "${LOG_FILE}"

mariadb-dump --all-databases --single-transaction --quick | gzip -9 > "/backup/database/db_all_${DATE_STR}.sql.gz"
find /backup/database -type f -name "db_all_*.sql.gz" -mtime +7 -delete

if [ -d "/backup/snapshots/daily.6" ]; then rm -rf "/backup/snapshots/daily.6"; fi
for i in 5 4 3 2 1 0; do
    if [ -d "/backup/snapshots/daily.${i}" ]; then mv "/backup/snapshots/daily.${i}" "/backup/snapshots/daily.$((i+1))"; fi
done

LINK_DEST_PARAM=""
if [ -d "/backup/snapshots/daily.1" ]; then LINK_DEST_PARAM="--link-dest=/backup/snapshots/daily.1"; fi
mkdir -p /backup/snapshots/daily.0
rsync -a --delete ${LINK_DEST_PARAM} /var/www/ /backup/snapshots/daily.0/ >> "${LOG_FILE}" 2>&1

echo "=== Respaldo completado: $(date +"%Y-%m-%d_%H-%M-%S") ===" >> "${LOG_FILE}"
BACKUP_SCRIPT

chmod 750 /opt/scripts/backup-daily.sh
/opt/scripts/backup-daily.sh || true

cat > /etc/cron.d/web-daily-backup <<'CRON_CONF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 2 * * * root /opt/scripts/backup-daily.sh > /dev/null 2>&1
CRON_CONF
chmod 644 /etc/cron.d/web-daily-backup

log "      Sistema de respaldos configurado en /opt/scripts/backup-daily.sh y cron."
