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

    validate_disk_space 1536000

    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    log "[Respaldos] Configurando sistema de snapshots rotativos de 7 dias en ${backup_dir}..."
    mkdir -p "${backup_dir}/snapshots" "${backup_dir}/database" /opt/scripts /run/lock
    if [ ! -e /backup ]; then
        ln -sf "${backup_dir}" /backup
    fi

    cat > /opt/scripts/backup-daily.sh <<EOF
#!/bin/bash
set -eo pipefail
DATE_STR=\$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="/var/log/backup-daily.log"
BACKUP_DIR="${backup_dir}"

mkdir -p "\${BACKUP_DIR}/database" "\${BACKUP_DIR}/snapshots" /run/lock

exec 200>/run/lock/srvctl-backup.lock
if ! flock -n 200; then
    echo "[\${DATE_STR}] AVISO: Operacion de respaldo omitida; ya existe otro proceso en ejecucion." >> "\${LOG_FILE}"
    exit 0
fi

avail_kb=\$(df -Pk "\${BACKUP_DIR}" 2>/dev/null | awk 'NR==2 {print \$4}')
if [ -n "\$avail_kb" ] && [ "\$avail_kb" -lt 1536000 ]; then
    echo "[\${DATE_STR}] ERROR: Espacio critico insuficiente en \${BACKUP_DIR} (\${avail_kb} KB disponibles, minimo 1.5 GB). Respaldo abortado para proteger el sistema." >> "\${LOG_FILE}"
    exit 1
fi

echo "=== Respaldo iniciado: \${DATE_STR} ===" >> "\${LOG_FILE}"

DUMP_TMP="\${BACKUP_DIR}/database/db_all_\${DATE_STR}.sql.gz"
if mariadb-dump --all-databases --single-transaction --quick | gzip -9 > "\${DUMP_TMP}"; then
    if gzip -t "\${DUMP_TMP}" 2>/dev/null; then
        echo "[\${DATE_STR}] Volcado MariaDB generado e integro." >> "\${LOG_FILE}"
    else
        echo "[\${DATE_STR}] ERROR: Volcado MariaDB corrupto o truncado. Se elimina." >> "\${LOG_FILE}"
        rm -f "\${DUMP_TMP}"
    fi
else
    echo "[\${DATE_STR}] ERROR: Fallo el volcado de bases de datos MariaDB" >> "\${LOG_FILE}"
    rm -f "\${DUMP_TMP}"
fi

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

acquire_backup_lock() {
    mkdir -p /run/lock
    exec 200>/run/lock/srvctl-backup.lock
    if ! flock -n 200; then
        die "Existe otra operacion de respaldo o restauracion en curso. Reintenta en unos instantes."
    fi
}

rollback_web() {
    validate_root
    acquire_backup_lock
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

rollback_project() {
    validate_root
    load_config
    acquire_backup_lock
    local project="$1"
    local snap="${2:-daily.0}"
    [ -n "$project" ] || die "Debes especificar el nombre del proyecto. Ej: srvctl backup rollback-project tienda daily.0"

    if ! validate_subdomain "$project"; then
        die "Nombre de proyecto invalido: ${project}. Usa solo letras minusculas, numeros y guiones."
    fi

    if [[ ! "$snap" =~ ^daily\.[0-6]$ ]]; then
        die "Nombre de snapshot invalido: ${snap}. Debe ser daily.0 a daily.6."
    fi

    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    local snap_proj="${backup_dir}/snapshots/${snap}/${project}"
    if [ ! -d "$snap_proj" ]; then
        die "El proyecto '${project}' no existe en el snapshot ${snap} (${snap_proj})."
    fi

    local target_dir="/var/www/${project}"
    log "[Rollback-Project] Restaurando ${target_dir} de forma aislada desde ${snap_proj}..."
    mkdir -p "${target_dir}"
    rsync -a --delete "${snap_proj}/" "${target_dir}/"

    chown -R "${ADMIN_USER}":www-data "${target_dir}" 2>/dev/null || true
    find "${target_dir}" -type d -exec chmod 2775 {} \; 2>/dev/null || true
    find "${target_dir}" -type f -exec chmod 0664 {} \; 2>/dev/null || true

    log "                  Proyecto '${project}' restaurado exitosamente desde ${snap}."
}

restore_db() {
    validate_root
    load_config
    acquire_backup_lock
    local dump_input="$1"
    [ -n "$dump_input" ] || die "Debes especificar el archivo de volcado. Ej: srvctl backup restore-db db_all_2026-09-26.sql.gz"

    local dump_name
    dump_name="$(basename "$dump_input")"
    if [[ ! "$dump_name" =~ ^[a-zA-Z0-9_\.-]+\.sql(\.gz)?$ ]]; then
        die "Nombre de archivo de volcado invalido: ${dump_name}."
    fi

    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    local dump_file="${backup_dir}/database/${dump_name}"

    if [ ! -f "$dump_file" ]; then
        if [ -f "/backup/database/${dump_name}" ]; then
            dump_file="/backup/database/${dump_name}"
        else
            die "El archivo de volcado no existe en ${dump_file}."
        fi
    fi

    if ! mariadb -e "SELECT 1;" >/dev/null 2>&1; then
        die "MariaDB no responde o no esta activo para restaurar la base de datos."
    fi

    log "[Restore-DB] Importando volcado ${dump_name} en MariaDB..."
    if [[ "$dump_name" =~ \.gz$ ]]; then
        gunzip -c "$dump_file" | mariadb
    else
        mariadb < "$dump_file"
    fi
    log "             Base de datos restaurada exitosamente desde ${dump_name}."
}

verify_backup() {
    load_config
    local backup_dir="${BACKUP_DIR:-/var/backups/srvctl}"
    echo "=============================================================================="
    echo "           VERIFICACION DE RESPALDOS Y ALMACENAMIENTO                         "
    echo "=============================================================================="

    local errors=0
    local snap_count=0
    local dump_count=0

    # 1. Comprobar directorio base
    if [ -d "$backup_dir" ]; then
        echo " [ OK ]   Directorio principal de respaldos accesible: ${backup_dir}"
    else
        echo " [FAIL]  Directorio principal de respaldos no existe: ${backup_dir}"
        errors=$((errors + 1))
    fi

    # 2. Espacio libre en disco
    local free_space
    free_space=$(df -h "${backup_dir}" 2>/dev/null | awk 'NR==2 {print $4}')
    echo " [ INFO ] Espacio disponible en volumen de respaldos: ${free_space:-N/D}"

    # 3. Comprobar snapshots
    echo ""
    echo "--- Comprobacion de Snapshots ---"
    if [ -d "${backup_dir}/snapshots" ]; then
        for i in 0 1 2 3 4 5 6; do
            local sdir="${backup_dir}/snapshots/daily.${i}"
            if [ -d "$sdir" ]; then
                snap_count=$((snap_count + 1))
                local fcount
                fcount=$(find "$sdir" -type f 2>/dev/null | wc -l)
                echo " [ OK ]   Snapshot daily.${i} presente (${fcount} archivos)"
            fi
        done
        if [ "$snap_count" -eq 0 ]; then
            echo " [AVISO]  No se encontraron snapshots en ${backup_dir}/snapshots"
        fi
    else
        echo " [FAIL]  Directorio de snapshots no existe: ${backup_dir}/snapshots"
        errors=$((errors + 1))
    fi

    # 4. Comprobar volcados de base de datos e integridad gzip
    echo ""
    echo "--- Comprobacion de Volcados MariaDB ---"
    if [ -d "${backup_dir}/database" ]; then
        local dump_file
        for dump_file in "${backup_dir}/database"/*.sql.gz; do
            [ -e "$dump_file" ] || continue
            dump_count=$((dump_count + 1))
            local bname
            bname=$(basename "$dump_file")
            if gzip -t "$dump_file" 2>/dev/null; then
                local sha
                sha=$(sha256sum "$dump_file" 2>/dev/null | awk '{print $1}')
                echo " [ OK ]   Volcado integro: ${bname} (SHA256: ${sha:0:16}...)"
            else
                echo " [FAIL]  Volcado corrupto o no valido: ${bname}"
                errors=$((errors + 1))
            fi
        done
        if [ "$dump_count" -eq 0 ]; then
            echo " [AVISO]  No se encontraron archivos de volcado .sql.gz en ${backup_dir}/database"
        fi
    else
        echo " [FAIL]  Directorio de base de datos no existe: ${backup_dir}/database"
        errors=$((errors + 1))
    fi

    echo ""
    echo "=============================================================================="
    echo "Resumen: ${snap_count} snapshots, ${dump_count} volcados MariaDB, ${errors} fallos."
    echo "=============================================================================="

    [ "$errors" -eq 0 ]
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-setup}"
    case "$action" in
        setup)            setup_backup ;;
        list)             list_backups ;;
        rollback)         rollback_web "${2:-daily.0}" ;;
        rollback-project) rollback_project "${2:-}" "${3:-daily.0}" ;;
        restore-db)       restore_db "${2:-}" ;;
        verify)           verify_backup ;;
        *) die "Uso: $0 [setup|list|rollback <snap>|rollback-project <proyecto> [snap]|restore-db <dump>|verify]" ;;
    esac
fi
