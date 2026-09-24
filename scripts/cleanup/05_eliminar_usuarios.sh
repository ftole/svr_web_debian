#!/bin/bash
# ==============================================================================
# 05_eliminar_usuarios.sh - Elimina usuarios secundarios preservando sesion actual
# Uso: sudo bash scripts/cleanup/05_eliminar_usuarios.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "=== [Limpieza 5/5] Eliminando usuarios secundarios preservando la sesion actual ==="
CURRENT_USER="${SUDO_USER:-$(logname 2>/dev/null || echo "$USER")}"

for u in devops2 devops_sr webadmin adminops; do
    if [ "$u" != "$CURRENT_USER" ] && id "$u" &>/dev/null; then
        pkill -u "$u" 2>/dev/null || true
        deluser --remove-home "$u" 2>/dev/null || true
        log "      Usuario '${u}' eliminado."
    fi
done

log "[OK] Limpieza de usuarios completada (sesion de '${CURRENT_USER}' protegida)."
