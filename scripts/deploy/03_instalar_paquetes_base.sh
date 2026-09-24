#!/bin/bash
# ==============================================================================
# 03_instalar_paquetes_base.sh - Instala paquetes base, Apache, PHP y MariaDB
# Uso: sudo bash scripts/deploy/03_instalar_paquetes_base.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[3/13] Instalando paquetes base, Apache, PHP y MariaDB..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y sudo curl openssl ufw git rsync auditd fail2ban samba \
  mariadb-server apache2 libapache2-mod-fcgid \
  php-fpm php-mysql php-curl php-gd php-mbstring \
  php-xml php-zip php-intl php-bcmath php-soap php-opcache

log "      Paquetes base instalados con exito."
