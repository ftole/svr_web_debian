#!/bin/bash
# ==============================================================================
# 02_desactivar_ipv6.sh - Desactiva IPv6 en el kernel
# Uso: sudo bash scripts/deploy/02_desactivar_ipv6.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[2/13] Desactivando IPv6 en el kernel..."
cat > /etc/sysctl.d/99-disable-ipv6.conf <<'SYSCTL_CONF'
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv6.conf.lo.disable_ipv6 = 1
SYSCTL_CONF
sysctl -p /etc/sysctl.d/99-disable-ipv6.conf >/dev/null
log "      IPv6 desactivado correctamente."
