#!/bin/bash
# ==============================================================================
# 12_samba_shares.sh - Configura Samba SMBv3 para entornos prod y stg
# Uso: sudo bash scripts/deploy/12_samba_shares.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[12/13] Configurando Samba (SMBv3) para recursos colaborativos..."

cat > /etc/samba/smb.conf <<SAMBA_CONF
[global]
   workgroup = WORKGROUP
   server string = Servidor Web Desarrollo
   security = user
   server role = standalone server
   passdb backend = tdbsam
   server min protocol = SMB2_10
   client min protocol = SMB2_10
   server smb encrypt = desired
   disable netbios = yes
   smb ports = 445
   dos filemode = yes
   vfs objects = catia fruit streams_xattr
   log file = /var/log/samba/log.%m
   max log size = 1000

[prod]
   comment = Entorno Produccion PROD
   path = /var/www/prod
   browseable = yes
   read only = no
   guest ok = no
   valid users = ${ADMIN_USER}
   force group = www-data
   create mask = 0664
   directory mask = 2775
   force create mode = 0664
   force directory mode = 2775

[stg]
   comment = Entorno Pruebas STG
   path = /var/www/stg
   browseable = yes
   read only = no
   guest ok = no
   valid users = ${ADMIN_USER}
   force group = www-data
   create mask = 0664
   directory mask = 2775
   force create mode = 0664
   force directory mode = 2775
SAMBA_CONF

(echo "${ADMIN_PASS}"; echo "${ADMIN_PASS}") | smbpasswd -s -a "${ADMIN_USER}"
systemctl enable --now smbd >/dev/null
systemctl restart smbd

log "      Samba SMBv3 activo con recursos [prod] y [stg]."
