#!/bin/bash
# ==============================================================================
# common.sh - Utilidades y funciones comunes para despliegue y administracion
# ==============================================================================

set -eo pipefail

log() { echo "$*"; }
die() { echo "[ERROR] $*" >&2; exit 1; }

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        die "Este script debe ejecutarse exclusivamente como root (sudo)."
    fi
}

load_config() {
    local conf_file="${CONF:-/etc/asistente_servidor.conf}"
    if [ -f "$conf_file" ]; then
        # shellcheck source=/dev/null
        . "$conf_file"
    fi

    if [ -z "${SERVER_IP:-}" ]; then
        SERVER_IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')
        [ -z "$SERVER_IP" ] && SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
        SERVER_IP="${SERVER_IP:-127.0.0.1}"
    fi

    BASE_DOMAIN="${BASE_DOMAIN:-empresa.local}"
    PROD_SUB="${PROD_SUB:-prod}"
    STG_SUB="${STG_SUB:-stg}"
    DB_SUB="${DB_SUB:-webdev}"

    PROD_FQDN="${PROD_FQDN:-${PROD_SUB}.${BASE_DOMAIN}}"
    STG_FQDN="${STG_FQDN:-${STG_SUB}.${BASE_DOMAIN}}"
    DB_FQDN="${DB_FQDN:-${DB_SUB}.${BASE_DOMAIN}}"

    ADMIN_USER="${ADMIN_USER:-webadmin}"
    ADMIN_PASS="${ADMIN_PASS:-Temp123#}"

    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
    [ -z "$PHP_VER" ] && PHP_VER="8.4"
}
