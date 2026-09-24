#!/bin/bash
# ==============================================================================
# 01_energia_anti_suspension.sh - Configura directivas anti-suspension y ahorro
# Uso: sudo bash scripts/deploy/01_energia_anti_suspension.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[1/13] Configurando directivas anti-suspension y ahorro de energia..."
systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
mkdir -p /etc/systemd/logind.conf.d
cat > /etc/systemd/logind.conf.d/99-nas.conf <<'LOGIND_CONF'
[Login]
HandleSuspendKey=ignore
HandleHibernateKey=ignore
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
LOGIND_CONF
systemctl restart systemd-logind 2>/dev/null || true
log "      Directivas anti-suspension aplicadas."
