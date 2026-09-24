#!/bin/bash
# ==============================================================================
# 01_detener_servicios.sh - Detiene servicios del stack web y cortafuegos
# Uso: sudo bash scripts/cleanup/01_detener_servicios.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "=== [Limpieza 1/5] Deteniendo servicios ==="
systemctl stop apache2 php8.4-fpm php-fpm mariadb smbd nmbd fail2ban ufw 2>/dev/null || true
ufw --force disable 2>/dev/null || true

log "[OK] Servicios detenidos y cortafuegos deshabilitado."
