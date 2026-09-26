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

create_database() {
    validate_root
    load_config
    local name="$1"
    local user="$2"
    local pass="${3:-}"

    [ -n "$name" ] || die "Debes especificar el nombre de la base de datos. Ej: srvctl db create tienda_db usr_tienda [pass]"
    [ -n "$user" ] || die "Debes especificar el usuario de la base de datos."

    if [[ ! "$name" =~ ^[a-z0-9_]{1,64}$ ]]; then
        die "Nombre de base de datos invalido: ${name}. Usa solo minusculas, numeros y guion bajo (max 64)."
    fi

    if [[ ! "$user" =~ ^[a-z0-9_]{1,32}$ ]]; then
        die "Nombre de usuario invalido: ${user}. Usa solo minusculas, numeros y guion bajo (max 32)."
    fi

    if [ -z "$pass" ]; then
        pass="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
    fi

    log "[MariaDB] Creando base de datos '${name}' y usuario '${user}'..."
    mariadb -e "CREATE DATABASE IF NOT EXISTS \`${name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mariadb -e "CREATE USER IF NOT EXISTS '${user}'@'localhost' IDENTIFIED BY '${pass}';"
    mariadb -e "ALTER USER '${user}'@'localhost' IDENTIFIED BY '${pass}';"
    mariadb -e "GRANT ALL PRIVILEGES ON \`${name}\`.* TO '${user}'@'localhost';"
    mariadb -e "CREATE USER IF NOT EXISTS '${user}'@'127.0.0.1' IDENTIFIED BY '${pass}';"
    mariadb -e "ALTER USER '${user}'@'127.0.0.1' IDENTIFIED BY '${pass}';"
    mariadb -e "GRANT ALL PRIVILEGES ON \`${name}\`.* TO '${user}'@'127.0.0.1';"
    mariadb -e "FLUSH PRIVILEGES;"

    echo "======================================================================"
    echo "         BASE DE DATOS CREADA CON EXITO                              "
    echo "======================================================================"
    echo " - Host:        127.0.0.1 (o localhost)"
    echo " - Puerto:      3306"
    echo " - Base Datos:  ${name}"
    echo " - Usuario:     ${user}"
    echo " - Contrasena:  ${pass}"
    echo " - Charset:     utf8mb4 / utf8mb4_unicode_ci"
    echo "======================================================================"
}

delete_database() {
    validate_root
    load_config
    local name="$1"
    [ -n "$name" ] || die "Debes especificar el nombre de la base de datos a eliminar. Ej: srvctl db delete tienda_db"

    if [[ ! "$name" =~ ^[a-z0-9_]{1,64}$ ]]; then
        die "Nombre de base de datos invalido: ${name}."
    fi

    case "$name" in
        information_schema|performance_schema|mysql|sys|phpmyadmin)
            die "No se permite eliminar bases de datos reservadas del sistema: ${name}"
            ;;
    esac

    log "[MariaDB] Eliminando base de datos '${name}'..."
    mariadb -e "DROP DATABASE IF EXISTS \`${name}\`;"

    local user="${name%_db}_usr"
    if [ "$user" != "$name" ]; then
        mariadb -e "DROP USER IF EXISTS '${user}'@'localhost', '${user}'@'127.0.0.1';" 2>/dev/null || true
    fi
    mariadb -e "DROP USER IF EXISTS '${name}'@'localhost', '${name}'@'127.0.0.1';" 2>/dev/null || true
    mariadb -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    log "[MariaDB] Base de datos '${name}' y accesos eliminados correctamente."
}

optimize_database() {
    validate_root
    log "[MariaDB] Optimizando y analizando todas las tablas (mariadb-check)..."
    mariadb-check -A --optimize
    log "[MariaDB] Optimizacion de tablas completada exitosamente."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-install}"
    case "$action" in
        install)  install_mariadb ;;
        create)   create_database "${2:-}" "${3:-}" "${4:-}" ;;
        delete)   delete_database "${2:-}" ;;
        optimize) optimize_database ;;
        *) die "Uso: $0 [install|create <db> <user> [pass]|delete <db>|optimize]" ;;
    esac
fi
