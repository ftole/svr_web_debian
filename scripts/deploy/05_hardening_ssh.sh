#!/bin/bash
# ==============================================================================
# 05_hardening_ssh.sh - Aplica configuracion restrictiva (hardening) a SSH
# Uso: sudo bash scripts/deploy/05_hardening_ssh.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[5/13] Aplicando hardening al servicio SSH..."
mkdir -p /etc/ssh/sshd_config.d
cat > /etc/ssh/sshd_config.d/01-hardening.conf <<'SSH_CONF'
PermitRootLogin no
MaxAuthTries 4
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
SSH_CONF

systemctl restart ssh 2>/dev/null || systemctl restart sshd 2>/dev/null || true
log "      SSH endurecido y servicio reiniciado."
