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

toggle_firewall() {
    validate_root
    local target_state="${1:-}"
    if [ -z "$target_state" ]; then
        if ufw status 2>/dev/null | grep -qi 'Status: active'; then
            target_state="off"
        else
            target_state="on"
        fi
    fi

    case "$target_state" in
        on|enable)
            log "[Seguridad] Activando cortafuegos UFW..."
            ufw --force enable
            systemctl enable --now ufw >/dev/null 2>&1 || true
            log "            Cortafuegos UFW activado exitosamente."
            ;;
        off|disable)
            log "[Seguridad] Desactivando cortafuegos UFW..."
            ufw --force disable
            log "            Cortafuegos UFW desactivado exitosamente."
            ;;
        *)
            die "Estado desconocido para toggle: ${target_state}. Usa 'on' u 'off'."
            ;;
    esac
}

allow_rule() {
    validate_root
    local rule_spec="$1"
    shift || true
    local comment="$*"

    [ -n "$rule_spec" ] || die "Debes especificar el puerto o regla. Ej: srvctl firewall allow 8080/tcp 'API Node'"

    local port proto
    if [[ "$rule_spec" =~ ^([0-9]+)/([a-z]+)$ ]]; then
        port="${BASH_REMATCH[1]}"
        proto="${BASH_REMATCH[2]}"
    elif [[ "$rule_spec" =~ ^[0-9]+$ ]]; then
        port="$rule_spec"
        proto=""
    else
        die "Formato de puerto invalido: ${rule_spec}. Ejemplos: 8080 o 8080/tcp."
    fi

    if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
        die "Numero de puerto fuera de rango (1-65535): ${port}"
    fi

    if [ -n "$proto" ] && [[ ! "$proto" =~ ^(tcp|udp)$ ]]; then
        die "Protocolo invalido: ${proto}. Usa 'tcp' o 'udp'."
    fi

    local target="${port}${proto:+/$proto}"
    log "[Seguridad] Permitiendo puerto ${target} en UFW..."
    if [ -n "$comment" ]; then
        local clean_comment="${comment//\'/}"
        ufw allow "${target}" comment "${clean_comment}"
    else
        ufw allow "${target}"
    fi
    log "            Regla permitida en UFW: ${target}"
}

delete_rule() {
    validate_root
    local target="$1"
    [ -n "$target" ] || die "Debes especificar el numero de regla o puerto a eliminar. Ej: srvctl firewall delete 3 o srvctl firewall delete 8080/tcp"

    if [[ "$target" =~ ^[0-9]+$ ]] && [ "$target" -lt 1000 ]; then
        log "[Seguridad] Eliminando regla numero ${target} de UFW..."
        echo "y" | ufw delete "$target" || ufw --force delete "$target"
    elif [[ "$target" =~ ^[0-9]+(/[a-z]+)?$ ]]; then
        log "[Seguridad] Eliminando permiso para ${target} en UFW..."
        ufw --force delete allow "$target"
    else
        die "Formato de regla invalido para eliminar: ${target}"
    fi
    log "            Regla eliminada exitosamente de UFW: ${target}"
}

ban_ip() {
    validate_root
    local ip="$1"
    [ -n "$ip" ] || die "Debes especificar la direccion IP a banear. Ej: srvctl security ban 192.168.1.100"
    if ! validate_ipv4 "$ip"; then
        die "Direccion IPv4 invalida: ${ip}"
    fi
    log "[Seguridad] Baneando IP ${ip} en Fail2ban (jaula sshd)..."
    fail2ban-client set sshd banip "$ip"
    log "            IP ${ip} baneada exitosamente."
}

unban_ip() {
    validate_root
    local ip="$1"
    [ -n "$ip" ] || die "Debes especificar la direccion IP a desbanear. Ej: srvctl security unban 192.168.1.100"
    if ! validate_ipv4 "$ip"; then
        die "Direccion IPv4 invalida: ${ip}"
    fi
    log "[Seguridad] Desbaneando IP ${ip} en Fail2ban (jaula sshd)..."
    fail2ban-client set sshd unbanip "$ip"
    log "            IP ${ip} desbaneada exitosamente."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-apply}"
    case "$action" in
        apply)   apply_firewall ;;
        disable) disable_firewall ;;
        toggle)  toggle_firewall "${2:-}" ;;
        allow)   shift; allow_rule "$@" ;;
        delete)  delete_rule "${2:-}" ;;
        ban)     ban_ip "${2:-}" ;;
        unban)   unban_ip "${2:-}" ;;
        *) die "Uso: $0 [apply|disable|toggle [on|off]|allow <puerto>[/<proto>] [<comentario>]|delete <num|puerto>|ban <ip>|unban <ip>]" ;;
    esac
fi
