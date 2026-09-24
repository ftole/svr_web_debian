#!/bin/bash
# ==============================================================================
# modules/share/samba.sh - Configuracion del recurso maestro [proyectos] en Samba
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

configure_samba() {
    validate_root
    load_config

    log "[Samba] Configurando recurso maestro [proyectos] en SMBv3..."
    cat > /etc/samba/smb.conf <<EOF
[global]
   workgroup = WORKGROUP
   server string = Servidor Web Debian 13
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

[proyectos]
   comment = Directorio Maestro de Proyectos Web
   path = /var/www
   browseable = yes
   read only = no
   guest ok = no
   valid users = ${ADMIN_USER}
   force group = www-data
   create mask = 0664
   directory mask = 2775
   force create mode = 0664
   force directory mode = 2775
   veto files = /.git/.env/.htaccess/_dashboard/*.key/
   delete veto files = no
EOF

    (echo "${ADMIN_PASS}"; echo "${ADMIN_PASS}") | smbpasswd -s -a "${ADMIN_USER}" >/dev/null 2>&1
    systemctl enable --now smbd >/dev/null 2>&1 || true
    systemctl restart smbd >/dev/null 2>&1 || true

    log "        Samba SMBv3 listo con recurso \\\\${SERVER_IP}\\proyectos."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    configure_samba
fi
