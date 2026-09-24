#!/bin/bash
# ==============================================================================
# 07_mariadb.sh - Configura y asegura el motor de base de datos MariaDB
# Uso: sudo bash scripts/deploy/07_mariadb.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[7/13] Configurando y asegurando motor MariaDB..."
systemctl enable --now mariadb

mariadb -e "DELETE FROM mysql.user WHERE User='';"
mariadb -e "DROP DATABASE IF EXISTS test;"
mariadb -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"

mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "ALTER USER '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'localhost' WITH GRANT OPTION;"

mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "ALTER USER '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;"

mariadb -e "FLUSH PRIVILEGES;"

log "      MariaDB asegurado y usuario '${ADMIN_USER}' configurado."
