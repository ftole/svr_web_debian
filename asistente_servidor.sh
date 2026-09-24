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
# ------------------------------------------------------------------------------
# Uso:  sudo bash asistente_servidor.sh
#       (modo no interactivo: ASISTENTE_NONINTERACTIVE=1 y variables de entorno)
# ==============================================================================
set -eo pipefail

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

# Guarda parametros para el verificador
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

# ------------------------------------------------------------------------------
# [0/11] Usuario de instalacion del SO -> grupo sudo (por defecto)
# ------------------------------------------------------------------------------
log "[0/11] Asegurando que el usuario de instalacion pertenezca a sudo..."
INSTALL_USER=$(getent passwd | awk -F: '$3>=1000 && $3<65534 && $7 ~ /(bash|zsh|sh)$/ {print $1; exit}')
if [ -n "$INSTALL_USER" ]; then
    if ! id -nG "$INSTALL_USER" | tr ' ' '\n' | grep -qx sudo; then
        usermod -aG sudo "$INSTALL_USER"
        log "      Usuario '${INSTALL_USER}' agregado al grupo sudo."
    else
        log "      Usuario '${INSTALL_USER}' ya pertenece a sudo."
    fi
else
    log "      [WARN] No se detecto usuario de instalacion (UID>=1000)."
fi

log "[1/11] Configurando directivas anti-suspension y ahorro de energia..."
systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
mkdir -p /etc/systemd/logind.conf.d
cat > /etc/systemd/logind.conf.d/99-nas.conf <<'LOGIND_CONF'
[Login]
HandleSuspendKey=ignore
HandleHibernateKey=ignore
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
LOGIND_CONF
systemctl restart systemd-logind 2>/dev/null || true

log "[2/11] Desactivando IPv6 en el kernel..."
cat > /etc/sysctl.d/99-disable-ipv6.conf <<'SYSCTL_CONF'
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv6.conf.lo.disable_ipv6 = 1
SYSCTL_CONF
sysctl -p /etc/sysctl.d/99-disable-ipv6.conf >/dev/null

log "[3/11] Instalando paquetes base, Apache, PHP y MariaDB..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y sudo curl openssl ufw git rsync auditd fail2ban samba \
  mariadb-server apache2 libapache2-mod-fcgid \
  php-fpm php-mysql php-curl php-gd php-mbstring \
  php-xml php-zip php-intl php-bcmath php-soap php-opcache

log "[4/11] Configurando y asegurando motor MariaDB..."
systemctl enable --now mariadb
mariadb -e "DELETE FROM mysql.user WHERE User='';"
mariadb -e "DROP DATABASE IF EXISTS test;"
mariadb -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "ALTER USER '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'localhost' WITH GRANT OPTION;"
mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "ALTER USER '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;"
mariadb -e "FLUSH PRIVILEGES;"

log "[5/11] Instalando phpMyAdmin sin interrupciones interactivas..."
# Pre-siembra tolerante: tras una purga total las plantillas debconf de phpMyAdmin
# pueden no estar registradas todavia y debconf-set-selections devuelve error.
# El almacenamiento se configura despues de forma determinista (paso 5b),
# por lo que la pre-siembra es solo una optimizacion opcional.
echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections 2>/dev/null || true
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections 2>/dev/null || true
DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin

log "[5b/11] Configurando almacenamiento de phpMyAdmin (pmadb + controluser)..."
PMA_DB="phpmyadmin"
PMA_USER="pma"
PMA_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
if [ -f /usr/share/phpmyadmin/sql/create_tables.sql ]; then
    mariadb < /usr/share/phpmyadmin/sql/create_tables.sql
    mariadb <<SQL
CREATE USER IF NOT EXISTS '${PMA_USER}'@'localhost' IDENTIFIED BY '${PMA_PASS}';
ALTER USER '${PMA_USER}'@'localhost' IDENTIFIED BY '${PMA_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${PMA_DB}\`.* TO '${PMA_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    cat > /etc/phpmyadmin/config-db.php <<PHP
<?php
##
## Generado por asistente_servidor.sh - almacenamiento de configuracion phpMyAdmin
##
\$dbuser='${PMA_USER}';
\$dbpass='${PMA_PASS}';
\$basepath='';
\$dbname='${PMA_DB}';
\$dbserver='localhost';
\$dbport='3306';
\$dbtype='mysql';
PHP
    chown root:www-data /etc/phpmyadmin/config-db.php
    chmod 640 /etc/phpmyadmin/config-db.php
    log "      Almacenamiento de phpMyAdmin configurado (${PMA_DB}/${PMA_USER})."
else
    log "      [WARN] No se encontro create_tables.sql; phpMyAdmin usara configuracion por defecto."
fi

log "[6/11] Creando usuario administrador del sistema (${ADMIN_USER}) y auditoria..."
if ! id "${ADMIN_USER}" &>/dev/null; then
    useradd -m -s /bin/bash -G sudo,www-data "${ADMIN_USER}"
fi
usermod -aG sudo,www-data "${ADMIN_USER}"
echo "${ADMIN_USER}:${ADMIN_PASS}" | chpasswd

cat > /etc/sudoers.d/99-audit-log <<'SUDO_CONF'
Defaults log_output
Defaults!/usr/bin/sudoreplay !log_output
Defaults logfile="/var/log/sudo.log"
SUDO_CONF
chmod 0440 /etc/sudoers.d/99-audit-log

log "[7/11] Hardening SSH y cortafuegos UFW..."
mkdir -p /etc/ssh/sshd_config.d
cat > /etc/ssh/sshd_config.d/01-hardening.conf <<'SSH_CONF'
PermitRootLogin no
MaxAuthTries 4
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
SSH_CONF
systemctl restart ssh

sed -i 's/IPV6=yes/IPV6=no/' /etc/default/ufw
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp comment 'SSH' >/dev/null
ufw allow 80/tcp comment 'HTTP' >/dev/null
ufw allow 443/tcp comment 'HTTPS' >/dev/null
ufw allow 445/tcp comment 'Samba SMB' >/dev/null
ufw allow 3389/tcp comment 'GNOME Remote Desktop RDP' >/dev/null
ufw --force enable >/dev/null

cat > /etc/fail2ban/jail.local <<'FAIL2BAN_CONF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port = 22
FAIL2BAN_CONF
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban

log "[8/11] Estructura web, Git y permisos colaborativos..."
mkdir -p /var/www/prod/public_html
mkdir -p /var/www/stg/public_html

cat << INDEX_PROD_EOF > /var/www/prod/public_html/index.php
<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Produccion - ${PROD_FQDN}</title>
<style>body{font-family:sans-serif;margin:40px;background:#f5f2e9;color:#233446}.card{background:#fff;padding:25px;border-radius:8px;border-left:6px solid #aa8a45;box-shadow:0 2px 5px rgba(0,0,0,0.1)}h1{margin-top:0}.badge{background:#aa8a45;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold}</style></head>
<body><div class="card"><h1>Servidor Web Nativo - <span class="badge">PRODUCCION (PROD)</span></h1><p><strong>Dominio:</strong> ${PROD_FQDN}</p><p><strong>PHP:</strong> <?= phpversion(); ?></p><p><strong>DocumentRoot:</strong> <?= __DIR__; ?></p></div></body></html>
INDEX_PROD_EOF

cat << INDEX_STG_EOF > /var/www/stg/public_html/index.php
<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pruebas - ${STG_FQDN}</title>
<style>body{font-family:sans-serif;margin:40px;background:#f5f2e9;color:#233446}.card{background:#fff;padding:25px;border-radius:8px;border-left:6px solid #6aa3c2;box-shadow:0 2px 5px rgba(0,0,0,0.1)}h1{margin-top:0}.badge{background:#6aa3c2;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold}</style></head>
<body><div class="card"><h1>Servidor Web Nativo - <span class="badge">STAGING (PRUEBAS)</span></h1><p><strong>Dominio:</strong> ${STG_FQDN}</p><p><strong>PHP:</strong> <?= phpversion(); ?></p><p><strong>DocumentRoot:</strong> <?= __DIR__; ?></p></div></body></html>
INDEX_STG_EOF

chown -R "${ADMIN_USER}":www-data /var/www/prod /var/www/stg
find /var/www/prod /var/www/stg -type d -exec chmod 2775 {} \;
find /var/www/prod /var/www/stg -type f -exec chmod 0664 {} \;

git config --system --add safe.directory /var/www/prod/public_html
git config --system --add safe.directory /var/www/stg/public_html

for pair in "prod:${PROD_FQDN}" "stg:${STG_FQDN}"; do
    dir="${pair%%:*}"; fqdn="${pair#*:}"
    cd "/var/www/${dir}/public_html"
    if [ ! -d .git ]; then
        git init -b main >/dev/null
        git config user.name "Administrador Web"
        git config user.email "admin@${BASE_DOMAIN}"
        git add . && git commit -m "Commit inicial: ${dir} (${fqdn})" >/dev/null
    fi
done

log "[9/11] Generando CA y Certificado SAN Comodin (*.${BASE_DOMAIN})..."
mkdir -p /etc/ssl/localcerts
cd /etc/ssl/localcerts

if [ ! -f rootCA.key ]; then
    openssl genrsa -out rootCA.key 4096 2>/dev/null
    openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 3650 \
      -subj "/C=MX/ST=Hidalgo/L=Pachuca/O=Infraestructura/CN=Local-RootCA" \
      -out rootCA.crt 2>/dev/null
fi

openssl genrsa -out webserver.key 2048 2>/dev/null

cat > openssl_san.cnf <<SAN_CONF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = req_ext

[dn]
CN = ${BASE_DOMAIN}

[req_ext]
subjectAltName = @alt_names

[alt_names]
DNS.1 = ${BASE_DOMAIN}
DNS.2 = *.${BASE_DOMAIN}
DNS.3 = localhost
IP.1 = ${SERVER_IP}
IP.2 = 127.0.0.1
SAN_CONF

openssl req -new -key webserver.key -out webserver.csr -config openssl_san.cnf 2>/dev/null
openssl x509 -req -in webserver.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial \
  -out webserver.crt -days 1095 -sha256 -extfile openssl_san.cnf -extensions req_ext 2>/dev/null

chmod 600 /etc/ssl/localcerts/*.key
chmod 644 /etc/ssl/localcerts/*.crt

cp /etc/ssl/localcerts/rootCA.crt /var/www/prod/public_html/rootCA.crt
chown "${ADMIN_USER}":www-data /var/www/prod/public_html/rootCA.crt

log "[10/11] Configurando Apache 2.4 con perfiles VirtualHosts..."
a2enmod actions fcgid alias proxy_fcgi rewrite headers ssl >/dev/null
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
a2enconf "php${PHP_VER}-fpm" >/dev/null
a2dismod mpm_prefork >/dev/null 2>&1 || true
a2enmod mpm_event >/dev/null

echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null

cat > /etc/apache2/conf-available/security-hardening.conf <<'SEC_CONF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
SEC_CONF
a2enconf security-hardening >/dev/null

cat > /etc/apache2/sites-available/01-prod.conf <<VH_PROD
<VirtualHost *:80>
    ServerName ${PROD_FQDN}
    ServerAlias ${BASE_DOMAIN} localhost
    DocumentRoot /var/www/prod/public_html
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${PROD_FQDN}
    ServerAlias ${BASE_DOMAIN} localhost
    DocumentRoot /var/www/prod/public_html
    SSLEngine on
    SSLCertificateFile /etc/ssl/localcerts/webserver.crt
    SSLCertificateKeyFile /etc/ssl/localcerts/webserver.key
    <Directory /var/www/prod/public_html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/apache2/prod_error.log
    CustomLog /var/log/apache2/prod_access.log combined
</VirtualHost>
VH_PROD

cat > /etc/apache2/sites-available/02-stg.conf <<VH_STG
<VirtualHost *:80>
    ServerName ${STG_FQDN}
    DocumentRoot /var/www/stg/public_html
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${STG_FQDN}
    DocumentRoot /var/www/stg/public_html
    SSLEngine on
    SSLCertificateFile /etc/ssl/localcerts/webserver.crt
    SSLCertificateKeyFile /etc/ssl/localcerts/webserver.key
    <Directory /var/www/stg/public_html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/apache2/stg_error.log
    CustomLog /var/log/apache2/stg_access.log combined
</VirtualHost>
VH_STG

cat > /etc/apache2/sites-available/03-webdev.conf <<VH_PMA
<VirtualHost *:80>
    ServerName ${DB_FQDN}
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${DB_FQDN}
    DocumentRoot /usr/share/phpmyadmin
    SSLEngine on
    SSLCertificateFile /etc/ssl/localcerts/webserver.crt
    SSLCertificateKeyFile /etc/ssl/localcerts/webserver.key
    <Directory /usr/share/phpmyadmin>
        Options FollowSymLinks
        DirectoryIndex index.php
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/apache2/webdev_error.log
    CustomLog /var/log/apache2/webdev_access.log combined
</VirtualHost>
VH_PMA

a2dissite 000-default.conf >/dev/null 2>&1 || true
a2ensite 01-prod.conf 02-stg.conf 03-webdev.conf >/dev/null
apache2ctl configtest
systemctl restart apache2

log "[10b/11] Aplicando parche Twig (elimina advertencias deprecadas)..."
TWIG_PARSER="/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php"
if [ -f "$TWIG_PARSER" ] && grep -q 'getExpressionParser()->parseExpression()' "$TWIG_PARSER"; then
    cp -n "$TWIG_PARSER" "${TWIG_PARSER}.orig" 2>/dev/null || true
    sed -i 's/\$this->parser->getExpressionParser()->parseExpression()/\$this->parser->parseExpression()/g' "$TWIG_PARSER"
    log "      Parche Twig aplicado a TransTokenParser.php."
else
    log "      Twig: no requiere parche (ya aplicado o version corregida)."
fi

log "[11/11] Configurando Samba (SMBv3) y respaldos rotativos de 7 dias..."
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

mkdir -p /backup/snapshots /backup/database /opt/scripts
cat > /opt/scripts/backup-daily.sh <<'BACKUP_SCRIPT'
#!/bin/bash
set -eo pipefail
DATE_STR=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="/var/log/backup-daily.log"

echo "=== Respaldo iniciado: ${DATE_STR} ===" >> "${LOG_FILE}"

mariadb-dump --all-databases --single-transaction --quick | gzip -9 > "/backup/database/db_all_${DATE_STR}.sql.gz"
find /backup/database -type f -name "db_all_*.sql.gz" -mtime +7 -delete

if [ -d "/backup/snapshots/daily.6" ]; then rm -rf "/backup/snapshots/daily.6"; fi
for i in 5 4 3 2 1 0; do
    if [ -d "/backup/snapshots/daily.${i}" ]; then mv "/backup/snapshots/daily.${i}" "/backup/snapshots/daily.$((i+1))"; fi
done

LINK_DEST_PARAM=""
if [ -d "/backup/snapshots/daily.1" ]; then LINK_DEST_PARAM="--link-dest=/backup/snapshots/daily.1"; fi
mkdir -p /backup/snapshots/daily.0
rsync -a --delete ${LINK_DEST_PARAM} /var/www/ /backup/snapshots/daily.0/ >> "${LOG_FILE}" 2>&1

echo "=== Respaldo completado: $(date +"%Y-%m-%d_%H-%M-%S") ===" >> "${LOG_FILE}"
BACKUP_SCRIPT

chmod 750 /opt/scripts/backup-daily.sh
/opt/scripts/backup-daily.sh || true

cat > /etc/cron.d/web-daily-backup <<'CRON_CONF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 2 * * * root /opt/scripts/backup-daily.sh > /dev/null 2>&1
CRON_CONF
chmod 644 /etc/cron.d/web-daily-backup

# Limpiar cache twig para forzar recompilacion con el parche
rm -rf /var/lib/phpmyadmin/tmp/twig/* 2>/dev/null || true

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
# NOTA: si las unidades no aparecen en el Explorador, reinicia Windows una vez
#       (EnableLinkedConnections ya quedo aplicado y los mapeos son persistentes).
==============================================================================
RESGUARDO_EOF

log ""
log "======================================================================"
log "  INSTALACION FINALIZADA. Ejecutando verificacion automatica..."
log "======================================================================"
if [ -x /root/verificar_servidor.sh ]; then
    bash /root/verificar_servidor.sh || true
else
    log "[WARN] No se encontro /root/verificar_servidor.sh para autoverificacion."
fi
