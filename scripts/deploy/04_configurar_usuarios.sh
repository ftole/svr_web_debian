#!/bin/bash
# ==============================================================================
# 04_configurar_usuarios.sh - Gestiona usuarios, grupo sudo y auditoria
# Uso: sudo bash scripts/deploy/04_configurar_usuarios.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[4/13] Configurando permisos de usuario y auditoria de sudo..."

# 1. Asegurar usuario instalador en sudo
INSTALL_USER=$(getent passwd | awk -F: '$3>=1000 && $3<65534 && $7 ~ /(bash|zsh|sh)$/ {print $1; exit}')
if [ -n "$INSTALL_USER" ]; then
    if ! id -nG "$INSTALL_USER" | tr ' ' '\n' | grep -qx sudo; then
        usermod -aG sudo "$INSTALL_USER"
        log "      Usuario '${INSTALL_USER}' agregado al grupo sudo."
    else
        log "      Usuario '${INSTALL_USER}' ya pertenece a sudo."
    fi
else
    log "      [WARN] No se detecto usuario de instalacion (UID>=1000)."
fi

# 2. Crear / actualizar usuario administrador
if ! id "${ADMIN_USER}" &>/dev/null; then
    useradd -m -s /bin/bash -G sudo,www-data "${ADMIN_USER}"
fi
usermod -aG sudo,www-data "${ADMIN_USER}"
echo "${ADMIN_USER}:${ADMIN_PASS}" | chpasswd

# 3. Auditoria de sudo
cat > /etc/sudoers.d/99-audit-log <<'SUDO_CONF'
Defaults log_output
Defaults!/usr/bin/sudoreplay !log_output
Defaults logfile="/var/log/sudo.log"
SUDO_CONF
chmod 0440 /etc/sudoers.d/99-audit-log

log "      Usuario '${ADMIN_USER}' configurado y auditoria activada en /var/log/sudo.log."
