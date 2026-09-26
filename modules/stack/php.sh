#!/bin/bash
# ==============================================================================
# modules/stack/php.sh - Instalacion de PHP 8.4 FPM, extensiones y Composer 2.x
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

install_php() {
    validate_root
    load_config

    log "[Stack] Instalando PHP FPM y extensiones esenciales..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y \
      php-fpm php-mysql php-curl php-gd php-mbstring \
      php-xml php-zip php-intl php-bcmath php-soap php-opcache php-cli unzip >/dev/null

    # Deteccion de version real instalada
    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.4")"
    systemctl enable --now "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

    # Instalacion global de Composer 2.x
    if ! command -v composer >/dev/null 2>&1; then
        log "[Stack] Instalando Composer 2.x de forma global..."
        local comp_tmp
        comp_tmp="$(mktemp -d)"
        if curl -fsSL https://getcomposer.org/installer -o "${comp_tmp}/composer-setup.php"; then
            php "${comp_tmp}/composer-setup.php" --install-dir=/usr/local/bin --filename=composer --quiet
            chmod +x /usr/local/bin/composer
            log "        Composer $(composer --version 2>/dev/null | awk '{print $3}') instalado en /usr/local/bin/composer."
        else
            log_warn "No se pudo descargar Composer automáticamente."
        fi
        rm -rf "${comp_tmp}"
    else
        log "        Composer ya se encuentra instalado."
    fi

    # Pool dedicado de PHP-FPM para el Dashboard (Aislamiento de administracion)
    mkdir -p "/etc/php/${PHP_VER}/fpm/pool.d"
    cat > "/etc/php/${PHP_VER}/fpm/pool.d/dashboard.conf" <<EOF
[dashboard]
user = www-data
group = www-data
listen = /run/php/php${PHP_VER}-fpm-dashboard.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 120
EOF
    systemctl restart "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

    log "        PHP ${PHP_VER} FPM y Composer configurados correctamente."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    install_php
fi
