#!/bin/bash
# ==============================================================================
# core/config.sh - Gestion de variables y persistencia de configuracion srvctl
# ==============================================================================
set -eo pipefail

CORE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${CORE_DIR}/logger.sh" ] && . "${CORE_DIR}/logger.sh"
# shellcheck source=/dev/null
[ -f "${CORE_DIR}/validator.sh" ] && . "${CORE_DIR}/validator.sh"

CONF_FILE="${CONF_FILE:-/etc/srvctl.conf}"
LEGACY_CONF="/etc/asistente_servidor.conf"

load_config() {
    if [ -f "$CONF_FILE" ]; then
        # shellcheck source=/dev/null
        . "$CONF_FILE"
    elif [ -f "$LEGACY_CONF" ]; then
        # shellcheck source=/dev/null
        . "$LEGACY_CONF"
    fi

    if [ -z "${SERVER_IP:-}" ]; then
        local detected_ip
        detected_ip=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')
        [ -z "$detected_ip" ] && detected_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
        SERVER_IP="${detected_ip:-127.0.0.1}"
    fi

    BASE_DOMAIN="${BASE_DOMAIN:-empresa.local}"
    PROD_SUB="${PROD_SUB:-prod}"
    STG_SUB="${STG_SUB:-stg}"
    DB_SUB="${DB_SUB:-webdev}"

    PROD_FQDN="${PROD_SUB}.${BASE_DOMAIN}"
    STG_FQDN="${STG_SUB}.${BASE_DOMAIN}"
    DB_FQDN="${DB_SUB}.${BASE_DOMAIN}"

    ADMIN_USER="${ADMIN_USER:-webadmin}"
    ADMIN_PASS="${ADMIN_PASS:-Temp123#}"

    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
    if [ -z "$PHP_VER" ]; then
        PHP_VER="8.4"
    fi
    return 0
}

save_config() {
    validate_root
    mkdir -p "$(dirname "$CONF_FILE")"
    (
        umask 077
        cat > "$CONF_FILE" <<EOF
# Configuracion central de srvctl
SERVER_IP='${SERVER_IP}'
BASE_DOMAIN='${BASE_DOMAIN}'
PROD_SUB='${PROD_SUB}'
STG_SUB='${STG_SUB}'
DB_SUB='${DB_SUB}'
PROD_FQDN='${PROD_FQDN}'
STG_FQDN='${STG_FQDN}'
DB_FQDN='${DB_FQDN}'
ADMIN_USER='${ADMIN_USER}'
ADMIN_PASS='${ADMIN_PASS}'
PHP_VER='${PHP_VER}'
EOF
    )
    return 0
}
