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

    tmp_sudoers="$(mktemp)"
    cat > "$tmp_sudoers" <<'EOF'
Defaults log_output
Defaults!/usr/bin/sudoreplay !log_output
Defaults logfile="/var/log/sudo.log"
EOF
    if visudo -c -f "$tmp_sudoers" >/dev/null 2>&1; then
        install -m 0440 "$tmp_sudoers" /etc/sudoers.d/99-audit-log
    else
        log_warn "Error de sintaxis en sudoers de auditoria. Omitiendo instalacion."
    fi
    rm -f "$tmp_sudoers"

    if sshd -t >/dev/null 2>&1; then
        systemctl restart ssh 2>/dev/null || systemctl restart sshd 2>/dev/null || true
        log "            SSH asegurado y auditoria activa en /var/log/sudo.log."
    else
        log_warn "Error de sintaxis en configuracion de SSH. No se reinicia el servicio para evitar auto-bloqueo."
    fi
}

revert_ssh_hardening() {
    validate_root
    log "[Seguridad] Revirtiendo hardening de SSH y auditoria sudo..."
    rm -f /etc/ssh/sshd_config.d/01-hardening.conf /etc/sudoers.d/99-audit-log
    if sshd -t >/dev/null 2>&1; then
        systemctl restart ssh 2>/dev/null || systemctl restart sshd 2>/dev/null || true
    fi
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
