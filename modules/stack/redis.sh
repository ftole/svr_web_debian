#!/bin/bash
# ==============================================================================
# modules/stack/redis.sh - Instalacion y aseguramiento de Redis y php-redis
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

install_redis() {
    validate_root
    load_config

    log "[Stack] Instalando Redis Server y modulo php-redis..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y redis-server php-redis >/dev/null

    # Asegurar que escuche exclusivamente en loopback local IPv4
    if [ -f /etc/redis/redis.conf ]; then
        sed -i 's/^bind .*/bind 127.0.0.1/' /etc/redis/redis.conf
    fi

    systemctl enable --now redis-server >/dev/null 2>&1 || true
    systemctl restart redis-server >/dev/null 2>&1 || true

    # Reiniciar PHP-FPM para activar php-redis
    systemctl restart "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

    log "        Redis Server activo y enlazado a PHP ${PHP_VER}."
}

flush_redis() {
    validate_root
    log "[Cache] Purgando toda la memoria de Redis (FLUSHALL)..."
    if command -v redis-cli >/dev/null 2>&1; then
        redis-cli flushall
        log "        Memoria de Redis purgada correctamente."
    else
        die "redis-cli no esta instalado o disponible en el PATH."
    fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-install}"
    case "$action" in
        install) install_redis ;;
        flush)   flush_redis ;;
        *) die "Uso: $0 [install|flush]" ;;
    esac
fi
