#!/bin/bash
# ==============================================================================
# core/validator.sh - Validaciones de seguridad, red y sistema para srvctl
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/logger.sh" ] && . "${SCRIPT_DIR}/logger.sh"

validate_root() {
    if [ "$(id -u)" -ne 0 ]; then
        die "Este comando debe ejecutarse exclusivamente como root (sudo)."
    fi
}

validate_systemd() {
    [ -d /run/systemd/system ] || die "systemd no esta activo en este sistema."
}

validate_disk_space() {
    local req_kb="${1:-512000}"
    local avail_kb
    avail_kb=$(df -Pk / 2>/dev/null | awk 'NR==2 {print $4}')
    if [ -z "$avail_kb" ] || [ "$avail_kb" -lt "$req_kb" ]; then
        die "Espacio insuficiente en / (se requieren al menos $((req_kb / 1024)) MB)."
    fi
}

validate_ipv4() {
    local ip="$1"
    local rx='^([0-9]{1,3}\.){3}[0-9]{1,3}$'
    if [[ ! "$ip" =~ $rx ]]; then
        return 1
    fi
    local IFS='.'
    # shellcheck disable=SC2086
    set -- $ip
    for octet in "$@"; do
        if [ "$octet" -lt 0 ] || [ "$octet" -gt 255 ]; then
            return 1
        fi
    done
    return 0
}

validate_domain() {
    local domain="$1"
    local rx='^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$'
    if [[ ! "$domain" =~ $rx ]]; then
        return 1
    fi
    return 0
}

validate_subdomain() {
    local sub="$1"
    local rx='^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$'
    if [[ ! "$sub" =~ $rx ]]; then
        return 1
    fi
    return 0
}
