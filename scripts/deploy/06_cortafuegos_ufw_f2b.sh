#!/bin/bash
# ==============================================================================
# 06_cortafuegos_ufw_f2b.sh - Configura cortafuegos UFW y jaulas de Fail2ban
# Uso: sudo bash scripts/deploy/06_cortafuegos_ufw_f2b.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[6/13] Configurando cortafuegos UFW y proteccion Fail2ban..."

if [ -f /etc/default/ufw ]; then
    sed -i 's/IPV6=yes/IPV6=no/' /etc/default/ufw
fi

ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp comment 'SSH' >/dev/null
ufw allow 80/tcp comment 'HTTP' >/dev/null
ufw allow 443/tcp comment 'HTTPS' >/dev/null
ufw allow 445/tcp comment 'Samba SMB' >/dev/null
ufw allow 3389/tcp comment 'GNOME Remote Desktop RDP' >/dev/null
ufw --force enable >/dev/null

cat > /etc/fail2ban/jail.local <<'FAIL2BAN_CONF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port = 22
FAIL2BAN_CONF

systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban

log "      Cortafuegos UFW y Fail2ban configurados y activos."
