#!/bin/bash
# ==============================================================================
# ASISTENTE DE INSTALACION - SERVIDOR WEB NATIVO DEBIAN 13
# Arquitectura "Hostinger" con subdominios, SSL comodin y phpMyAdmin corregido
# ------------------------------------------------------------------------------
# Correcciones incluidas frente al manual original:
#   - Agrega automaticamente a sudo el usuario creado en la instalacion del SO.
#   - phpMyAdmin: configura de forma determinista el almacenamiento de
#     configuracion (pmadb + usuario de control + tablas pma__*), evitando el
#     aviso "El almacenamiento de configuracion phpMyAdmin no esta ... configurado".
#   - Twig: aplica el parche oficial (no invasivo) que elimina las advertencias
#     deprecadas de twig >= 3.21 en phpMyAdmin 5.2.2.
#   - Idempotente (se puede re-ejecutar), con pre-chequeos, logging y
#     auto-verificacion final mediante verificar_servidor.sh.
#   - Arquitectura modular: despliegue orquestado mediante scripts independientes
#     en scripts/deploy/.
# ------------------------------------------------------------------------------
# Uso:  sudo bash asistente_servidor.sh
#       (modo no interactivo: ASISTENTE_NONINTERACTIVE=1 y variables de entorno)
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${SCRIPT_DIR}/scripts/deploy"
if [ ! -d "$DEPLOY_DIR" ] && [ -d "/root/svr_web_debian/scripts/deploy" ]; then
    DEPLOY_DIR="/root/svr_web_debian/scripts/deploy"
fi

LOG="/var/log/asistente_servidor.log"
CONF="/etc/asistente_servidor.conf"

# ------------------------------------------------------------------------------
# Utilidades
# ------------------------------------------------------------------------------
log() { echo "$*"; }
die() { echo "[ERROR] $*" >&2; exit 1; }

if [ "$(id -u)" -ne 0 ]; then
    die "Este script debe ejecutarse exclusivamente como root."
fi

mkdir -p /var/log
touch "$LOG"
exec > >(tee -a "$LOG") 2>&1

trap 'echo "[ERROR] Fallo inesperado en la linea $LINENO. Ver $LOG" >&2' ERR

if [ ! -t 0 ]; then
    NONINTERACTIVE="${ASISTENTE_NONINTERACTIVE:-1}"
else
    NONINTERACTIVE="${ASISTENTE_NONINTERACTIVE:-0}"
fi

# ask VAR "Prompt" "default"  -> toma valor de la variable de entorno VAR si existe
ask() {
    local var="$1" prompt="$2" def="$3"
    local envval="${!var}"
    local cur="${envval:-$def}"
    if [ "${NONINTERACTIVE:-0}" = "1" ]; then
        printf -v "$var" '%s' "$cur"
        return 0
    fi
    local in
    read -rp "$prompt [$cur]: " in || true
    printf -v "$var" '%s' "${in:-$cur}"
}

# ------------------------------------------------------------------------------
# Pre-chequeos
# ------------------------------------------------------------------------------
log "======================================================================"
log "    ASISTENTE DE INSTALACION - SERVIDOR WEB NATIVO DEBIAN 13         "
log "        Arquitectura Hostinger con Subdominios y SSL Comodin         "
log "======================================================================"

[ -d /run/systemd/system ] || die "systemd no esta activo."
df -Pk / | awk 'NR==2 {exit !($4>512000)}' || die "Espacio insuficiente en / (se requieren >500 MB)."

# ------------------------------------------------------------------------------
# Deteccion de parametros
# ------------------------------------------------------------------------------
DETECTED_IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')
[ -z "$DETECTED_IP" ] && DETECTED_IP=$(hostname -I | awk '{print $1}')

ask SERVER_IP       "[?] Direccion IP del servidor"             "$DETECTED_IP"
ask BASE_DOMAIN     "[?] Dominio Base"                          "empresa.local"
ask PROD_SUB        "[?] Subdominio para Produccion"            "prod"
PROD_FQDN="${PROD_SUB}.${BASE_DOMAIN}"
ask STG_SUB         "[?] Subdominio para Pruebas / Staging"     "stg"
STG_FQDN="${STG_SUB}.${BASE_DOMAIN}"
ask DB_SUB          "[?] Subdominio para phpMyAdmin"            "webdev"
DB_FQDN="${DB_SUB}.${BASE_DOMAIN}"
ask ADMIN_USER      "[?] Usuario Administrador"                 "webadmin"
ask ADMIN_PASS      "[?] Contrasena Maestra"                    "Temp123#"

log ""
log " PARAMETROS CONFIRMADOS:"
log " - Servidor IP:        ${SERVER_IP}"
log " - Dominio Base:       ${BASE_DOMAIN}"
log " - Produccion:         https://${PROD_FQDN} (y https://${SERVER_IP})"
log " - Staging / Pruebas:  https://${STG_FQDN}"
log " - phpMyAdmin:         https://${DB_FQDN} (y https://${SERVER_IP}/phpmyadmin)"
log " - Administrador:      ${ADMIN_USER}"
log "======================================================================"

if [ "${NONINTERACTIVE:-0}" != "1" ]; then
    read -rp "Comenzar la instalacion? (s/N): " CONFIRM
    [[ "$CONFIRM" =~ ^[sS]$ ]] || { log "Operacion cancelada."; exit 0; }
fi

# Guarda parametros para modulos y verificador
cat > "$CONF" <<CONF_EOF
SERVER_IP='${SERVER_IP}'
BASE_DOMAIN='${BASE_DOMAIN}'
PROD_SUB='${PROD_SUB}'
STG_SUB='${STG_SUB}'
DB_SUB='${DB_SUB}'
PROD_FQDN='${PROD_FQDN}'
STG_FQDN='${STG_FQDN}'
DB_FQDN='${DB_FQDN}'
ADMIN_USER='${ADMIN_USER}'
ADMIN_PASS='${ADMIN_PASS}'
CONF_EOF
chmod 600 "$CONF"

# Exportar variables de entorno para los submódulos
export CONF SERVER_IP BASE_DOMAIN PROD_SUB STG_SUB DB_SUB PROD_FQDN STG_FQDN DB_FQDN ADMIN_USER ADMIN_PASS

# ------------------------------------------------------------------------------
# Ejecución modular del despliegue
# ------------------------------------------------------------------------------
bash "${DEPLOY_DIR}/01_energia_anti_suspension.sh"
bash "${DEPLOY_DIR}/02_desactivar_ipv6.sh"
bash "${DEPLOY_DIR}/03_instalar_paquetes_base.sh"
bash "${DEPLOY_DIR}/04_configurar_usuarios.sh"
bash "${DEPLOY_DIR}/05_hardening_ssh.sh"
bash "${DEPLOY_DIR}/06_cortafuegos_ufw_f2b.sh"
bash "${DEPLOY_DIR}/07_mariadb.sh"
bash "${DEPLOY_DIR}/08_phpmyadmin.sh"
bash "${DEPLOY_DIR}/09_estructura_web_git.sh"
bash "${DEPLOY_DIR}/10_ssl_comodin.sh"
bash "${DEPLOY_DIR}/11_apache_php.sh"
bash "${DEPLOY_DIR}/12_samba_shares.sh"
bash "${DEPLOY_DIR}/13_respaldos_cron.sh"

clear 2>/dev/null || true
cat <<RESGUARDO_EOF
==============================================================================
                RESGUARDO DE DATOS CRITICOS DEL SERVIDOR
==============================================================================
 [!] IMPORTANTE: Copia y resguarda esta informacion en tu gestor de contrasenas.
     Por motivos de seguridad, NO se volvera a mostrar en pantalla.
==============================================================================
 IP Servidor:              ${SERVER_IP}
 Dominio Base:             ${BASE_DOMAIN}
 Certificado SSL:          ${BASE_DOMAIN}, *.${BASE_DOMAIN} e IP ${SERVER_IP}

 Direcciones Web (HTTPS):
  - Produccion (prod):     https://${PROD_FQDN} (o https://${SERVER_IP})
  - Pruebas (stg):         https://${STG_FQDN}
  - phpMyAdmin:            https://${DB_FQDN} (o https://${SERVER_IP}/phpmyadmin)

 Recursos Compartidos de Red (Samba):
  - Produccion:            \\\\${SERVER_IP}\\prod
  - Pruebas:               \\\\${SERVER_IP}\\stg

 Credenciales de Administrador:
  - Usuario:               ${ADMIN_USER}
  - Contrasena:            ${ADMIN_PASS}
  - Privilegios:           SSH/Sudo (Linux), Red (Samba) y Base de Datos (MariaDB)
==============================================================================

==============================================================================
           INSTRUCCIONES AUTOMATICAS PARA EL CLIENTE WINDOWS
==============================================================================
# Abre PowerShell como ADMINISTRADOR en Windows y copia/pega el siguiente bloque:

\$ServerIP = "${SERVER_IP}"
\$BaseDomain = "${BASE_DOMAIN}"
\$AdminUser = "${ADMIN_USER}"
\$AdminPass = '${ADMIN_PASS}'
\$hostsPath = "\$env:windir\System32\drivers\etc\hosts"

# 1. Registro de nombres DNS en archivo hosts
\$entries = @"

# Servidor Web Debian 13 (\$BaseDomain)
\$ServerIP    \$BaseDomain
\$ServerIP    ${PROD_FQDN}
\$ServerIP    ${STG_FQDN}
\$ServerIP    ${DB_FQDN}
"@
Add-Content -Path \$hostsPath -Value \$entries -Force
Clear-DnsClientCache
Write-Host "[OK] Dominios registrados en Windows y cache DNS purgada." -ForegroundColor Green

# 2. Hacer visibles en el Explorador las unidades mapeadas como administrador
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v EnableLinkedConnections /t REG_DWORD /d 1 /f | Out-Null
Write-Host "[OK] EnableLinkedConnections habilitado." -ForegroundColor Green

# 3. Montaje de Unidades de Red Samba en letras libres (auto)
function Get-FreeDriveLetter {
    \$used = @((Get-CimInstance Win32_LogicalDisk -ErrorAction SilentlyContinue).DeviceID -replace ':')
    foreach (\$l in 'Z','Y','X','W','V','U','T','S','R','Q','P','O','N','M','L','K','J','I','H','G','F','E') {
        if (\$used -notcontains \$l) { return "\${l}:" }
    }
    return \$null
}
\$driveProd = Get-FreeDriveLetter
if (\$driveProd) { net use \$driveProd \\\\\$ServerIP\\prod /user:\$AdminUser \$AdminPass /persistent:yes | Out-Null }
\$driveStg = Get-FreeDriveLetter
if (\$driveStg) { net use \$driveStg \\\\\$ServerIP\\stg /user:\$AdminUser \$AdminPass /persistent:yes | Out-Null }
Write-Host "[OK] Recursos Samba montados (prod=\$driveProd, stg=\$driveStg)." -ForegroundColor Green

# 4. Importacion del Certificado SSL Raiz (por UNC, sin depender de la letra)
\$certSrc = "\\\\\$ServerIP\\prod\\public_html\\rootCA.crt"
\$certTmp = "\$env:TEMP\\rootCA.crt"
if (Test-Path \$certSrc) {
    Copy-Item \$certSrc \$certTmp -Force
    Import-Certificate -FilePath \$certTmp -CertStoreLocation Cert:\LocalMachine\Root | Out-Null
    Write-Host "[OK] Certificado Raiz importado." -ForegroundColor Green
} else {
    Write-Host "[WARN] No se pudo leer \$certSrc. Instalalo manualmente." -ForegroundColor Yellow
}

# 5. Llave SSH y alias 'web' para gestionar Git en el servidor
if (!(Test-Path "\$env:USERPROFILE\.ssh\id_ed25519_web")) {
    ssh-keygen -t ed25519 -f "\$env:USERPROFILE\.ssh\id_ed25519_web" -N '""'
}
# (pedira la contrasena del administrador una vez)
Get-Content "\$env:USERPROFILE\.ssh\id_ed25519_web.pub" | ssh \$AdminUser@\$ServerIP "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"

\$sshConfig = "\$env:USERPROFILE\.ssh\config"
\$configEntry = @"

Host web
    HostName \$ServerIP
    User \$AdminUser
    IdentityFile ~/.ssh/id_ed25519_web
    ServerAliveInterval 60
"@
Add-Content -Path \$sshConfig -Value \$configEntry
Write-Host "[OK] Alias SSH listo. Gestiona Git con: ssh web" -ForegroundColor Green
# NOTA: si las unidades no aparecen en el Explorador, reinicia Windows una vez
#       (EnableLinkedConnections ya quedo aplicado y los mapeos son persistentes).
==============================================================================
RESGUARDO_EOF

log ""
log "======================================================================"
log "  INSTALACION FINALIZADA. Ejecutando verificacion automatica..."
log "======================================================================"
if [ -x "${SCRIPT_DIR}/verificar_servidor.sh" ]; then
    bash "${SCRIPT_DIR}/verificar_servidor.sh" || true
elif [ -x /root/verificar_servidor.sh ]; then
    bash /root/verificar_servidor.sh || true
else
    log "[WARN] No se encontro verificar_servidor.sh para autoverificacion."
fi
