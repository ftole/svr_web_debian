#!/bin/bash
# ==============================================================================
# modules/stack/mariadb.sh - Motor de base de datos MariaDB 11.8
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

install_mariadb() {
    validate_root
    load_config

    log "[Stack] Instalando y asegurando motor MariaDB..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y mariadb-server >/dev/null

    systemctl enable --now mariadb >/dev/null 2>&1 || true

    mariadb -e "DELETE FROM mysql.user WHERE User='';" >/dev/null 2>&1 || true
    mariadb -e "DROP DATABASE IF EXISTS test;" >/dev/null 2>&1 || true
    mariadb -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" >/dev/null 2>&1 || true

    mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
    mariadb -e "ALTER USER '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
    mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'localhost' WITH GRANT OPTION;"

    mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
    mariadb -e "ALTER USER '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
    mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;"

    mariadb -e "FLUSH PRIVILEGES;"

    log "        MariaDB configurado y usuario '${ADMIN_USER}' activo."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    install_mariadb
fi
