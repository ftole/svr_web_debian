#!/bin/bash
# ==============================================================================
# 04_restaurar_sistema.sh - Restaura IPv6 y politicas del sistema (energia/logind)
# Uso: sudo bash scripts/cleanup/04_restaurar_sistema.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "=== [Limpieza 4/5] Restaurando IPv6 y politicas del sistema ==="
sysctl --system >/dev/null 2>&1 || true
systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
systemctl restart systemd-logind 2>/dev/null || true

log "[OK] Parametros del kernel y politicas de suspension restaurados."
