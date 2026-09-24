#!/bin/bash
# ==============================================================================
# modules/system/power.sh - Directivas anti-suspension y ahorro de energia
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"

apply_power_policy() {
    validate_root
    log "[Sistema] Configurando directivas anti-suspension y energia..."
    systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
    mkdir -p /etc/systemd/logind.conf.d
    cat > /etc/systemd/logind.conf.d/99-nas.conf <<'EOF'
[Login]
HandleSuspendKey=ignore
HandleHibernateKey=ignore
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
EOF
    systemctl restart systemd-logind 2>/dev/null || true
    log "          Directivas de energia aplicadas."
}

revert_power_policy() {
    validate_root
    log "[Sistema] Restaurando politicas de suspension del kernel..."
    rm -f /etc/systemd/logind.conf.d/99-nas.conf
    systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
    systemctl restart systemd-logind 2>/dev/null || true
    log "          Politicas de suspension restauradas."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-apply}"
    case "$action" in
        apply) apply_power_policy ;;
        revert) revert_power_policy ;;
        *) die "Uso: $0 [apply|revert]" ;;
    esac
fi
