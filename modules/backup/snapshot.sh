#!/bin/bash
# ==============================================================================
# modules/backup/snapshot.sh - Respaldos diarios rotativos de 7 dias y reversion
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

setup_backup() {
    validate_root
    load_config

    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    log "[Respaldos] Configurando sistema de snapshots rotativos de 7 dias en ${backup_dir}..."
    mkdir -p "${backup_dir}/snapshots" "${backup_dir}/database" /opt/scripts
    if [ ! -e /backup ]; then
        ln -sf "${backup_dir}" /backup
    fi

    cat > /opt/scripts/backup-daily.sh <<EOF
#!/bin/bash
set -eo pipefail
DATE_STR=\$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="/var/log/backup-daily.log"
BACKUP_DIR="${backup_dir}"

echo "=== Respaldo iniciado: \${DATE_STR} ===" >> "\${LOG_FILE}"

mariadb-dump --all-databases --single-transaction --quick 2>/dev/null | gzip -9 > "\${BACKUP_DIR}/database/db_all_\${DATE_STR}.sql.gz" || true
find "\${BACKUP_DIR}/database" -type f -name "db_all_*.sql.gz" -mtime +7 -delete 2>/dev/null || true

if [ -d "\${BACKUP_DIR}/snapshots/daily.6" ]; then rm -rf "\${BACKUP_DIR}/snapshots/daily.6"; fi
for i in 5 4 3 2 1 0; do
    if [ -d "\${BACKUP_DIR}/snapshots/daily.\${i}" ]; then mv "\${BACKUP_DIR}/snapshots/daily.\${i}" "\${BACKUP_DIR}/snapshots/daily.\$((i+1))"; fi
done

LINK_DEST_PARAM=""
if [ -d "\${BACKUP_DIR}/snapshots/daily.1" ]; then LINK_DEST_PARAM="--link-dest=\${BACKUP_DIR}/snapshots/daily.1"; fi
mkdir -p "\${BACKUP_DIR}/snapshots/daily.0"
rsync -a --delete \${LINK_DEST_PARAM} /var/www/ "\${BACKUP_DIR}/snapshots/daily.0/" >> "\${LOG_FILE}" 2>&1

echo "=== Respaldo completado: \$(date +"%Y-%m-%d_%H-%M-%S") ===" >> "\${LOG_FILE}"
EOF

    chmod 750 /opt/scripts/backup-daily.sh
    /opt/scripts/backup-daily.sh || true

    cat > /etc/cron.d/web-daily-backup <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 2 * * * root /opt/scripts/backup-daily.sh > /dev/null 2>&1
EOF
    chmod 644 /etc/cron.d/web-daily-backup

    log "            Sistema de respaldos activo en cron diario (02:00 AM)."
}

list_backups() {
    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    echo "=== Snapshots de Archivos Web (${backup_dir}/snapshots) ==="
    if [ -d "${backup_dir}/snapshots" ]; then
        ls -ld "${backup_dir}"/snapshots/daily.* 2>/dev/null || echo "No hay snapshots creados aun."
    fi
    echo ""
    echo "=== Volcados de Base de Datos (${backup_dir}/database) ==="
    if [ -d "${backup_dir}/database" ]; then
        ls -lh "${backup_dir}"/database/db_all_*.sql.gz 2>/dev/null || echo "No hay volcados creados aun."
    fi
}

rollback_web() {
    validate_root
    local snap="${1:-daily.0}"
    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    local snap_path="${backup_dir}/snapshots/${snap}"
    if [ ! -d "$snap_path" ]; then
        die "El snapshot ${snap_path} no existe."
    fi
    log "[Rollback] Restaurando /var/www/ desde ${snap_path}..."
    rsync -a --delete "${snap_path}/" /var/www/
    log "           Archivos web restaurados con exito desde ${snap}."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-setup}"
    case "$action" in
        setup)    setup_backup ;;
        list)     list_backups ;;
        rollback) rollback_web "${2:-daily.0}" ;;
        *) die "Uso: $0 [setup|list|rollback <snap>]" ;;
    esac
fi
