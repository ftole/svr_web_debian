#!/bin/bash
# ==============================================================================
# modules/system/network.sh - Gestion de parametros de red y kernel (IPv6)
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"

SYSCTL_CONF="/etc/sysctl.d/99-disable-ipv6.conf"

disable_ipv6() {
    validate_root
    log "[Red] Desactivando IPv6 en el kernel..."
    cat > "$SYSCTL_CONF" <<'EOF'
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv6.conf.lo.disable_ipv6 = 1
EOF
    sysctl -p "$SYSCTL_CONF" >/dev/null 2>&1 || true
    log "      IPv6 desactivado."
}

enable_ipv6() {
    validate_root
    log "[Red] Restaurando IPv6 en el kernel..."
    rm -f "$SYSCTL_CONF"
    sysctl --system >/dev/null 2>&1 || true
    log "      IPv6 restaurado."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-disable-ipv6}"
    case "$action" in
        disable-ipv6) disable_ipv6 ;;
        enable-ipv6)  enable_ipv6 ;;
        *) die "Uso: $0 [disable-ipv6|enable-ipv6]" ;;
    esac
fi
