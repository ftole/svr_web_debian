#!/bin/bash
# ==============================================================================
# modules/stack/base.sh - Instalacion de herramientas base y gestion de usuarios
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

install_base() {
    validate_root
    load_config

    log "[Stack] Instalando herramientas del sistema y utilidades base..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y sudo curl openssl git rsync auditd samba ufw fail2ban >/dev/null

    # 1. Asegurar usuario instalador en sudo
    local install_user
    install_user=$(getent passwd | awk -F: '$3>=1000 && $3<65534 && $7 ~ /(bash|zsh|sh)$/ {print $1; exit}')
    if [ -n "$install_user" ]; then
        if ! id -nG "$install_user" | tr ' ' '\n' | grep -qx sudo; then
            usermod -aG sudo "$install_user"
            log "        Usuario de instalacion '${install_user}' agregado a sudo."
        fi
    fi

    # 2. Crear / actualizar usuario administrador
    if ! id "${ADMIN_USER}" &>/dev/null; then
        useradd -m -s /bin/bash -G sudo,www-data "${ADMIN_USER}"
    fi
    usermod -aG sudo,www-data "${ADMIN_USER}"
    echo "${ADMIN_USER}:${ADMIN_PASS}" | chpasswd
    log "        Usuario administrador '${ADMIN_USER}' configurado."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    install_base
fi
