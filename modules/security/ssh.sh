#!/bin/bash
# ==============================================================================
# modules/security/ssh.sh - Hardening de SSH y auditoria de comandos sudo
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"

apply_ssh_hardening() {
    validate_root
    log "[Seguridad] Aplicando hardening a OpenSSH y auditoria sudo..."

    mkdir -p /etc/ssh/sshd_config.d
    cat > /etc/ssh/sshd_config.d/01-hardening.conf <<'EOF'
PermitRootLogin no
MaxAuthTries 4
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
EOF

    cat > /etc/sudoers.d/99-audit-log <<'EOF'
Defaults log_output
Defaults!/usr/bin/sudoreplay !log_output
Defaults logfile="/var/log/sudo.log"
EOF
    chmod 0440 /etc/sudoers.d/99-audit-log

    systemctl restart ssh 2>/dev/null || systemctl restart sshd 2>/dev/null || true
    log "            SSH asegurado y auditoria activa en /var/log/sudo.log."
}

revert_ssh_hardening() {
    validate_root
    log "[Seguridad] Revirtiendo hardening de SSH y auditoria sudo..."
    rm -f /etc/ssh/sshd_config.d/01-hardening.conf /etc/sudoers.d/99-audit-log
    systemctl restart ssh 2>/dev/null || systemctl restart sshd 2>/dev/null || true
    log "            Configuracion SSH por defecto restaurada."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-apply}"
    case "$action" in
        apply)  apply_ssh_hardening ;;
        revert) revert_ssh_hardening ;;
        *) die "Uso: $0 [apply|revert]" ;;
    esac
fi
