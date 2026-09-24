#!/bin/bash
# ==============================================================================
# 02_purgar_paquetes.sh - Purga paquetes instalados del stack web y servicios
# Uso: sudo bash scripts/cleanup/02_purgar_paquetes.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "=== [Limpieza 2/5] Purgando paquetes instalados ==="
export DEBIAN_FRONTEND=noninteractive
apt-get purge -y \
  'apache2*' 'libapache2-mod-fcgid' \
  'php*' 'phpmyadmin*' \
  'mariadb*' 'galera*' \
  'samba*' 'winbind' \
  'fail2ban' 'ufw' 'auditd' \
  'libpam-pwquality' 2>/dev/null || true

apt-get autoremove --purge -y 2>/dev/null || true
apt-get clean 2>/dev/null || true

log "[OK] Paquetes purgados y cache APT limpia."
