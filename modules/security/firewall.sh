#!/bin/bash
# ==============================================================================
# modules/security/firewall.sh - Cortafuegos UFW y jaulas de Fail2ban
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"

apply_firewall() {
    validate_root
    log "[Seguridad] Configurando cortafuegos UFW y jaula Fail2ban..."

    if [ -f /etc/default/ufw ]; then
        sed -i 's/IPV6=yes/IPV6=no/' /etc/default/ufw
    fi

    ufw default deny incoming >/dev/null 2>&1 || true
    ufw default allow outgoing >/dev/null 2>&1 || true
    ufw allow 22/tcp comment 'SSH' >/dev/null 2>&1 || true
    ufw allow 80/tcp comment 'HTTP' >/dev/null 2>&1 || true
    ufw allow 443/tcp comment 'HTTPS' >/dev/null 2>&1 || true
    ufw allow 445/tcp comment 'Samba SMB' >/dev/null 2>&1 || true
    ufw allow 3389/tcp comment 'GNOME RDP' >/dev/null 2>&1 || true
    ufw --force enable >/dev/null 2>&1 || true
    systemctl enable --now ufw >/dev/null 2>&1 || true

    mkdir -p /etc/fail2ban
    cat > /etc/fail2ban/jail.local <<'EOF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port = 22
EOF

    systemctl enable --now fail2ban >/dev/null 2>&1 || true
    systemctl restart fail2ban >/dev/null 2>&1 || true
    log "            UFW y Fail2ban activos y asegurados."
}

disable_firewall() {
    validate_root
    log "[Seguridad] Deshabilitando UFW y deteniendo Fail2ban..."
    ufw --force disable >/dev/null 2>&1 || true
    systemctl stop ufw >/dev/null 2>&1 || true
    systemctl stop fail2ban >/dev/null 2>&1 || true
    log "            Cortafuegos y Fail2ban deshabilitados."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-apply}"
    case "$action" in
        apply)   apply_firewall ;;
        disable) disable_firewall ;;
        *) die "Uso: $0 [apply|disable]" ;;
    esac
fi
