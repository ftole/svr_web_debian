# MANUAL MAESTRO DE DESPLIEGUE, OPERACIÓN Y REGRESIÓN
## ARQUITECTURA WEB NATIVA DEBIAN 13 (PERFIL HOSTINGER CON SUBDOMINIOS Y ASISTENTE)

---

## 1. Ficha Técnica y Especificaciones del Sistema

| Parámetro | Detalle Técnico |
| :--- | :--- |
| **Sistema Operativo** | Debian GNU/Linux 13 (Trixie) x86_64 |
| **Pila Web** | Apache 2.4 (MPM Event) + PHP 8.4 FPM (FastCGI) |
| **Motor de Base de Datos** | MariaDB 11.8 con acceso administrativo dual (`localhost` y `127.0.0.1`) |
| **Gestor Visual DB** | phpMyAdmin 5.x / 6.x en VirtualHost dedicado por subdominio |
| **Compartición de Red** | Samba 4.22 (SMBv3 forzado, NetBIOS deshabilitado, puertos 445/tcp) |
| **Seguridad Perimetral** | UFW (IPv4 exclusivo) + Fail2ban (Jail SSH con bloqueo progresivo) |
| **Puertos Abiertos UFW** | 22 (SSH), 80 (HTTP), 443 (HTTPS), 445 (Samba), 3389 (GNOME RDP) |
| **Gestión de Energía** | Bloqueo estricto de suspensión, hibernación y cierre de tapa en systemd |
| **Pila de Red** | IPv4 activa / IPv6 deshabilitado permanentemente a nivel kernel |
| **Arquitectura de Dominios** | Dominio base + Subdominios para Producción, Pruebas y phpMyAdmin |
| **Certificado SSL** | CA Raíz privada interna + Certificado SAN Comodín (*Wildcard* `*.dominio.local` e IP) |
| **Control de Versiones** | Repositorio Git local en cada raíz web (`/var/www/prod` y `/var/www/stg`) |
| **Auditoría del Sistema** | Registro de comandos administrativos en `/var/log/sudo.log` y `auditd` |
| **Respaldos Automatizados** | Snapshots diarios mediante hard-links (7 días) + Volcados SQL comprimidos |

---

## 2. Procedimiento de Limpieza Total (Retorno a Estado Base Recién Instalado)

Si tu servidor ya contiene paquetes a medio configurar o carpetas residuales, ejecuta este bloque en Debian como `root` para garantizar un entorno limpio antes de lanzar el asistente:

```bash
#!/bin/bash
set -x

echo "=== 1. Deteniendo servicios ==="
systemctl stop apache2 php8.4-fpm mariadb smbd nmbd fail2ban ufw 2>/dev/null || true
ufw --force disable 2>/dev/null || true

echo "=== 2. Purgando paquetes instalados ==="
apt-get purge -y \
  apache2* libapache2-mod-fcgid \
  php* php8.4* phpmyadmin* \
  mariadb* galera* \
  samba* winbind \
  fail2ban ufw auditd \
  libpam-pwquality 2>/dev/null || true

apt-get autoremove --purge -y
apt-get clean

echo "=== 3. Eliminando archivos y carpetas residuales ==="
rm -rf /var/www/prod /var/www/stg
rm -rf /etc/apache2 /etc/php /etc/mysql /etc/samba /var/lib/mysql /var/log/samba /etc/phpmyadmin
rm -rf /etc/ssl/localcerts /backup /opt/scripts
rm -f /var/log/backup-daily.log /var/log/sudo.log /etc/cron.d/web-daily-backup
rm -f /etc/sysctl.d/99-disable-ipv6.conf /etc/sudoers.d/99-audit-log /etc/ssh/sshd_config.d/01-hardening.conf
rm -f /etc/systemd/logind.conf.d/99-nas.conf /root/asistente_servidor.sh

mkdir -p /var/www/html
chown -R root:root /var/www

echo "=== 4. Restaurando IPv6 y políticas del sistema ==="
sysctl --system 2>/dev/null || true
systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
systemctl restart systemd-logind 2>/dev/null || true

echo "=== 5. Eliminando usuarios secundarios preservando la sesión actual ==="
CURRENT_USER=$(logname 2>/dev/null || echo "$USER")
for u in devops2 devops_sr webadmin adminops; do
    if [ "$u" != "$CURRENT_USER" ] && id "$u" &>/dev/null; then
        pkill -u "$u" 2>/dev/null || true
        deluser --remove-home "$u" 2>/dev/null || true
    fi
done

echo "=== SISTEMA RESTAURADO AL ESTADO BASE LIMPIO ==="
```

---

## 3. Asistente Interactivo de Despliegue (Script Wizard)

> **IMPORTANTE (revisión 2026-09):** El script incrustado más abajo corresponde a la versión original y **queda supersedido** por el archivo independiente **`asistente_servidor.sh`**, que corrige dos defectos detectados en pruebas reales:
> 1. **phpMyAdmin:** el script original usa `dbconfig-install boolean false`, lo que deja `$dbuser`/`$dbpass` vacíos en `/etc/phpmyadmin/config-db.php` y provoca el aviso *«El almacenamiento de configuración phpMyAdmin no está completamente configurado…»*. La versión corregida configura el `pmadb`, el usuario de control y las tablas `pma__*` de forma determinista.
> 2. **Twig:** phpMyAdmin 5.2.2 con `php-twig` ≥ 3.21 emite advertencias deprecadas (`getExpressionParser()`). La versión corregida aplica el parche oficial no invasivo.
>
> Además la versión corregida es **idempotente**, **agrega por defecto al usuario de instalación del SO al grupo `sudo`**, escribe log en `/var/log/asistente_servidor.log` y ejecuta una **auto-verificación** con `verificar_servidor.sh`. Ver **ANEXO A** al final del documento.

Este script interactivo solicita los parámetros del servidor (o toma valores por defecto presionando `Enter`), despliega de forma ordenada toda la arquitectura sin fallos de dependencias, genera los certificados comodín y finaliza imprimiendo una pantalla de resguardo con las credenciales y el bloque de PowerShell listo para Windows.

### Ejecución en Debian 13 (como `root`):

```bash
cat << 'EOF' > /root/asistente_servidor.sh
#!/bin/bash
set -eo pipefail

# 1. Verificación de superusuario
if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Este script debe ejecutarse exclusivamente como root." >&2
    exit 1
fi

clear
echo "======================================================================"
echo "    ASISTENTE DE INSTALACIÓN - SERVIDOR WEB NATIVO DEBIAN 13         "
echo "        Arquitectura Hostinger con Subdominios y SSL Comodín         "
echo "======================================================================"
echo ""

# Detección de IP primaria de red
DETECTED_IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')
if [ -z "$DETECTED_IP" ]; then
    DETECTED_IP=$(hostname -I | awk '{print $1}')
fi

read -rp "[?] Dirección IP del servidor [${DETECTED_IP}]: " INPUT_IP
SERVER_IP=${INPUT_IP:-$DETECTED_IP}

read -rp "[?] Dominio Base [empresa.local]: " INPUT_BASE_DOMAIN
BASE_DOMAIN=${INPUT_BASE_DOMAIN:-empresa.local}

read -rp "[?] Subdominio para Producción [prod]: " INPUT_PROD_SUB
PROD_SUB=${INPUT_PROD_SUB:-prod}
PROD_FQDN="${PROD_SUB}.${BASE_DOMAIN}"

read -rp "[?] Subdominio para Pruebas / Staging [stg]: " INPUT_STG_SUB
STG_SUB=${INPUT_STG_SUB:-stg}
STG_FQDN="${STG_SUB}.${BASE_DOMAIN}"

read -rp "[?] Subdominio para phpMyAdmin [webdev]: " INPUT_DB_SUB
DB_SUB=${INPUT_DB_SUB:-webdev}
DB_FQDN="${DB_SUB}.${BASE_DOMAIN}"

read -rp "[?] Usuario Administrador [webadmin]: " INPUT_ADMIN_USER
ADMIN_USER=${INPUT_ADMIN_USER:-webadmin}

read -rp "[?] Contraseña Maestra [Temp123#]: " INPUT_ADMIN_PASS
ADMIN_PASS=${INPUT_ADMIN_PASS:-Temp123#}

echo ""
echo "======================================================================"
echo " PARÁMETROS CONFIRMADOS:"
echo " - Servidor IP:           ${SERVER_IP}"
echo " - Dominio Base:          ${BASE_DOMAIN}"
echo " - Producción:            https://${PROD_FQDN} (y https://${SERVER_IP})"
echo " - Staging / Pruebas:     https://${STG_FQDN}"
echo " - phpMyAdmin:            https://${DB_FQDN} (y https://${SERVER_IP}/phpmyadmin)"
echo " - Administrador:         ${ADMIN_USER}"
echo "======================================================================"
read -rp "¿Comenzar la instalación? (s/N): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[sS]$ ]]; then
    echo "Operación cancelada."
    exit 0
fi

echo ""
echo "[1/11] Configurando directivas anti-suspensión y ahorro de energía..."
systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
mkdir -p /etc/systemd/logind.conf.d
cat << 'LOGIND_CONF' > /etc/systemd/logind.conf.d/99-nas.conf
[Login]
HandleSuspendKey=ignore
HandleHibernateKey=ignore
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
LOGIND_CONF
systemctl restart systemd-logind 2>/dev/null || true

echo "[2/11] Desactivando IPv6 en el kernel..."
cat << 'SYSCTL_CONF' > /etc/sysctl.d/99-disable-ipv6.conf
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv6.conf.lo.disable_ipv6 = 1
SYSCTL_CONF
sysctl -p /etc/sysctl.d/99-disable-ipv6.conf >/dev/null

echo "[3/11] Instalando paquetes base, Apache, PHP y MariaDB..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y sudo curl ufw git rsync auditd fail2ban samba \
  mariadb-server apache2 libapache2-mod-fcgid \
  php-fpm php-mysql php-curl php-gd php-mbstring \
  php-xml php-zip php-intl php-bcmath php-soap php-opcache

echo "[4/11] Configurando y asegurando motor MariaDB..."
systemctl enable --now mariadb
mariadb -e "DELETE FROM mysql.user WHERE User='';"
mariadb -e "DROP DATABASE IF EXISTS test;"
mariadb -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
# Creación dual de usuario administrativo para Socket local y TCP 127.0.0.1
mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'localhost' WITH GRANT OPTION;"
mariadb -e "CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${ADMIN_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO '${ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;"
mariadb -e "FLUSH PRIVILEGES;"

echo "[5/11] Instalando phpMyAdmin sin interrupciones interactivas..."
echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections
apt-get install -y phpmyadmin

echo "[6/11] Creando usuario administrador del sistema (${ADMIN_USER}) y auditoría..."
if ! id "${ADMIN_USER}" &>/dev/null; then
    useradd -m -s /bin/bash -G sudo,www-data "${ADMIN_USER}"
    echo "${ADMIN_USER}:${ADMIN_PASS}" | chpasswd
else
    usermod -aG sudo,www-data "${ADMIN_USER}"
    echo "${ADMIN_USER}:${ADMIN_PASS}" | chpasswd
fi

cat << 'SUDO_CONF' > /etc/sudoers.d/99-audit-log
Defaults log_output
Defaults!/usr/bin/sudoreplay !log_output
Defaults logfile="/var/log/sudo.log"
SUDO_CONF
chmod 0440 /etc/sudoers.d/99-audit-log

echo "[7/11] Hardening SSH y cortafuegos UFW..."
cat << 'SSH_CONF' > /etc/ssh/sshd_config.d/01-hardening.conf
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

cat << 'FAIL2BAN_CONF' > /etc/fail2ban/jail.local
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

echo "[8/11] Estructura web, Git y permisos colaborativos..."
mkdir -p /var/www/prod/public_html
mkdir -p /var/www/stg/public_html

cat << INDEX_PROD_EOF > /var/www/prod/public_html/index.php
<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Producción - ${PROD_FQDN}</title>
<style>body{font-family:sans-serif;margin:40px;background:#f5f2e9;color:#233446}.card{background:#fff;padding:25px;border-radius:8px;border-left:6px solid #aa8a45;box-shadow:0 2px 5px rgba(0,0,0,0.1)}h1{margin-top:0}.badge{background:#aa8a45;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold}</style></head>
<body><div class="card"><h1>Servidor Web Nativo - <span class="badge">PRODUCCIÓN (PROD)</span></h1><p><strong>Dominio:</strong> ${PROD_FQDN}</p><p><strong>PHP:</strong> <?= phpversion(); ?></p><p><strong>DocumentRoot:</strong> <?= __DIR__; ?></p></div></body></html>
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

cd /var/www/prod/public_html
if [ ! -d .git ]; then
    git init -b main
    git config user.name "Administrador Web" && git config user.email "admin@${BASE_DOMAIN}"
    git add . && git commit -m "Commit inicial: Producción (${PROD_FQDN})"
fi

cd /var/www/stg/public_html
if [ ! -d .git ]; then
    git init -b main
    git config user.name "Administrador Web" && git config user.email "admin@${BASE_DOMAIN}"
    git add . && git commit -m "Commit inicial: Staging (${STG_FQDN})"
fi

echo "[9/11] Generando Autoridad Certificadora (CA) y Certificado SAN Comodín (*.${BASE_DOMAIN})..."
mkdir -p /etc/ssl/localcerts
cd /etc/ssl/localcerts

openssl genrsa -out rootCA.key 4096 2>/dev/null
openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 3650 \
  -subj "/C=MX/ST=Hidalgo/L=Pachuca/O=Infraestructura/CN=Local-RootCA" \
  -out rootCA.crt 2>/dev/null

openssl genrsa -out webserver.key 2048 2>/dev/null

cat << SAN_CONF > openssl_san.cnf
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

echo "[10/11] Configurando Apache 2.4 con perfiles VirtualHosts..."
a2enmod actions fcgid alias proxy_fcgi rewrite headers ssl >/dev/null
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
a2enconf php${PHP_VER}-fpm >/dev/null
a2dismod mpm_prefork >/dev/null || true
a2enmod mpm_event >/dev/null

echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null

cat << 'SEC_CONF' > /etc/apache2/conf-available/security-hardening.conf
ServerTokens Prod
ServerSignature Off
TraceEnable Off
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
SEC_CONF
a2enconf security-hardening >/dev/null

# VirtualHost Producción
cat << VH_PROD > /etc/apache2/sites-available/01-prod.conf
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

# VirtualHost Staging
cat << VH_STG > /etc/apache2/sites-available/02-stg.conf
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

# VirtualHost phpMyAdmin
cat << VH_PMA > /etc/apache2/sites-available/03-webdev.conf
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

a2dissite 000-default.conf >/dev/null || true
a2ensite 01-prod.conf 02-stg.conf 03-webdev.conf >/dev/null
systemctl restart apache2

echo "[11/11] Configurando Samba (SMBv3) y respaldos rotativos de 7 días..."
cat << SAMBA_CONF > /etc/samba/smb.conf
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
   comment = Entorno Producción PROD
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
cat << 'BACKUP_SCRIPT' > /opt/scripts/backup-daily.sh
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
rsync -a --delete ${LINK_DEST_PARAM} /var/www/ /backup/snapshots/daily.0/ >> "${LOG_FILE}" 2>&1

echo "=== Respaldo completado: $(date +"%Y-%m-%d_%H-%M-%S") ===" >> "${LOG_FILE}"
BACKUP_SCRIPT

chmod 750 /opt/scripts/backup-daily.sh
/opt/scripts/backup-daily.sh

cat << 'CRON_CONF' > /etc/cron.d/web-daily-backup
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 2 * * * root /opt/scripts/backup-daily.sh > /dev/null 2>&1
CRON_CONF
chmod 644 /etc/cron.d/web-daily-backup

clear
cat << RESGUARDO_EOF
==============================================================================
                RESGUARDO DE DATOS CRÍTICOS DEL SERVIDOR
==============================================================================
 [!] IMPORTANTE: Copia y resguarda esta información en tu gestor de contraseñas.
     Por motivos de seguridad, NO se volverá a mostrar en pantalla.
==============================================================================
 IP Servidor:              ${SERVER_IP}
 Dominio Base:             ${BASE_DOMAIN}
 Certificado SSL:          ${BASE_DOMAIN}, *.${BASE_DOMAIN} e IP ${SERVER_IP}

 Direcciones Web (HTTPS):
  - Producción (prod):     https://${PROD_FQDN} (o https://${SERVER_IP})
  - Pruebas (stg):         https://${STG_FQDN}
  - phpMyAdmin:            https://${DB_FQDN} (o https://${SERVER_IP}/phpmyadmin)

 Recursos Compartidos de Red (Samba):
  - Producción:            \\\\${SERVER_IP}\\prod
  - Pruebas:               \\\\${SERVER_IP}\\stg

 Credenciales de Administrador:
  - Usuario:               ${ADMIN_USER}
  - Contraseña:            ${ADMIN_PASS}
  - Privilegios:           SSH/Sudo (Linux), Red (Samba) y Base de Datos (MariaDB)
==============================================================================

==============================================================================
           INSTRUCCIONES AUTOMÁTICAS PARA EL CLIENTE WINDOWS
==============================================================================
# Abre PowerShell como ADMINISTRADOR en Windows y copia/pega el siguiente bloque:

\$ServerIP = "${SERVER_IP}"
\$BaseDomain = "${BASE_DOMAIN}"
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

# 2. Montaje de Unidades de Red Samba
net use Z: \\\\\$ServerIP\\prod /user:${ADMIN_USER} ${ADMIN_PASS} /persistent:yes
net use Y: \\\\\$ServerIP\\stg  /user:${ADMIN_USER} ${ADMIN_PASS} /persistent:yes
Write-Host "[OK] Unidades Z: (prod) e Y: (stg) montadas correctamente." -ForegroundColor Green

# 3. Importación del Certificado SSL Raiz (Soporte Universal PowerShell 5.1 y 7+)
# Se copia directamente desde el recurso compartido montado Z: evitando bloqueos SSL previos
\$certSource = "Z:\public_html\rootCA.crt"
if (Test-Path \$certSource) {
    Import-Certificate -FilePath \$certSource -CertStoreLocation Cert:\LocalMachine\Root | Out-Null
    Write-Host "[OK] Certificado Raiz importado. Candado verde habilitado para todos los subdominios." -ForegroundColor Green
} else {
    Write-Host "[WARN] No se pudo leer Z:\public_html\rootCA.crt directamente. Instalalo manualmente." -ForegroundColor Yellow
}

# 4. Configurar Llave SSH y Conexión mediante Alias 'webdev'
if (!(Test-Path "\$env:USERPROFILE\.ssh\id_ed25519_webdev")) {
    ssh-keygen -t ed25519 -f "\$env:USERPROFILE\.ssh\id_ed25519_webdev" -N '""'
}
Get-Content "\$env:USERPROFILE\.ssh\id_ed25519_webdev.pub" | ssh ${ADMIN_USER}@\$ServerIP "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"

\$sshConfig = "\$env:USERPROFILE\.ssh\config"
\$configEntry = @"

Host webdev
    HostName \$ServerIP
    User ${ADMIN_USER}
    IdentityFile ~/.ssh/id_ed25519_webdev
    ServerAliveInterval 60
"@
Add-Content -Path \$sshConfig -Value \$configEntry
Write-Host "[OK] Alias SSH listo. Puedes conectarte escribiendo: ssh webdev" -ForegroundColor Green
==============================================================================
RESGUARDO_EOF
EOF

chmod +x /root/asistente_servidor.sh
/root/asistente_servidor.sh
```

---

## 4. Manual de Reversión y Rollback Operativo

Para realizar cualquier recuperación ante fallas de despliegue, errores de desarrollo o incidentes de base de datos, conéctate vía SSH (`ssh webdev` o con el usuario configurado) y ejecuta el procedimiento respectivo:

### A. Reversión de Cambios en Código Web (Git)
Aplica cuando un archivo editado desde Windows corrompe el sitio:
```bash
cd /var/www/prod/public_html

# Descartar modificaciones locales no commiteadas
git restore .
git clean -fd

# Revertir un commit previo generando un contra-commit seguro
git revert HEAD --no-edit

# Regresar a un commit específico descartando lo posterior
git reset --hard <hash_commit>
sudo chown -R webadmin:www-data /var/www/prod
sudo find /var/www/prod -type d -exec chmod 2775 {} \;
sudo find /var/www/prod -type f -exec chmod 0664 {} \;
```

### B. Restauración de Base de Datos MariaDB
Aplica ante pérdida o corrupción de tablas:
```bash
# 1. Listar respaldos disponibles
ls -lh /backup/database/

# 2. Restaurar volcado específico
gunzip -c /backup/database/db_all_YYYY-MM-DD_HH-MM-SS.sql.gz | sudo mariadb

# 3. Comprobar bases de datos activas
sudo mariadb -e "SHOW DATABASES;"
```

### C. Restauración Completa desde Snapshots (Retención de 7 Días)
Los snapshots diarios están ubicados en `/backup/snapshots/daily.0` (más reciente) hasta `daily.6` (hace 7 días):
```bash
# Restaurar entorno Producción (prod) al snapshot daily.0
sudo rsync -a --delete /backup/snapshots/daily.0/prod/ /var/www/prod/
sudo chown -R webadmin:www-data /var/www/prod

# Restaurar entorno Pruebas (stg) al snapshot daily.0
sudo rsync -a --delete /backup/snapshots/daily.0/stg/ /var/www/stg/
sudo chown -R webadmin:www-data /var/www/stg
```

---

## 5. Auditoría del Sistema y Alta de Nuevos Administradores

### A. Inspección de Comandos Privilegiados (`sudo.log`)
Permite rastrear qué usuario ejecutó qué comando y en qué ruta:
```bash
sudo tail -n 50 /var/log/sudo.log
```

### B. Procedimiento para Dar de Alta Nuevos Administradores
Para otorgar acceso a un nuevo miembro del equipo técnico:
```bash
NUEVO_USER="devops_sr"

# 1. Crear usuario y asignar grupos administrativos
sudo useradd -m -s /bin/bash -G sudo,www-data ${NUEVO_USER}
sudo passwd ${NUEVO_USER}

# 2. Habilitar credencial en red Samba
sudo smbpasswd -a ${NUEVO_USER}

# 3. Autorizar en los recursos compartidos
sudo sed -i "s/valid users = /valid users = ${NUEVO_USER} /g" /etc/samba/smb.conf
sudo systemctl reload smbd
```

---

## ANEXO A. Correcciones aplicadas (revisión 2026-09) y Guía del Asistente

Este anexo documenta los defectos detectados en pruebas reales sobre Debian 13 (Trixie), sus causas raíz y los archivos corregidos.

### A.1 Archivos que componen el asistente

| Archivo | Rol |
| :--- | :--- |
| `asistente_servidor.sh` | Wizard interactivo de despliegue (idempotente, con auto-verificación). |
| `verificar_servidor.sh` | Auto-test del stack completo (40 comprobaciones PASS/FAIL). |
| `limpiar_servidor.sh` | Retorno al estado base limpio (purga total, preserva acceso SSH). |

### A.2 Uso rápido (como `root`)

```bash
# 1) (Opcional) Regresar a estado base limpio
sudo bash limpiar_servidor.sh

# 2) Desplegar todo el stack (interactivo)
sudo bash asistente_servidor.sh

# 3) Verificar de forma independiente en cualquier momento
sudo bash verificar_servidor.sh
```

Modo no interactivo (para automatización / CI):

```bash
sudo ASISTENTE_NONINTERACTIVE=1 \
     SERVER_IP=10.0.0.10 BASE_DOMAIN=empresa.local \
     PROD_SUB=prod STG_SUB=stg DB_SUB=webdev \
     ADMIN_USER=webadmin ADMIN_PASS='Temp123#' \
     bash asistente_servidor.sh
```

### A.3 Incidente conocido 1 — «El almacenamiento de configuración phpMyAdmin no está completamente configurado»

**Síntoma:** al entrar a phpMyAdmin aparece el aviso y se desactivan funciones extendidas (relaciones, historial, favoritos, marcadores, seguimiento, etc.).

**Causa raíz:** el script original preseeda `phpmyadmin/dbconfig-install boolean false`. Con ello `dbconfig-common` genera `/etc/phpmyadmin/config-db.php` con `$dbname='phpmyadmin'` pero **`$dbuser=''` y `$dbpass=''`**. Como `config.inc.php` evalúa `if (!empty($dbname))`, deja configurado `pmadb` y los nombres de tablas, pero sin usuario de control válido, por lo que no puede usar el almacenamiento.

**Solución aplicada por el asistente (determinista):**

```bash
# 1) Crear la base de control y sus 19 tablas pma__*
mariadb < /usr/share/phpmyadmin/sql/create_tables.sql

# 2) Crear/actualizar el usuario de control con privilegios mínimos
PMA_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
mariadb <<SQL
CREATE USER IF NOT EXISTS 'pma'@'localhost' IDENTIFIED BY '${PMA_PASS}';
ALTER USER 'pma'@'localhost' IDENTIFIED BY '${PMA_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`phpmyadmin\`.* TO 'pma'@'localhost';
FLUSH PRIVILEGES;
SQL

# 3) Escribir config-db.php con las credenciales correctas
cat > /etc/phpmyadmin/config-db.php <<PHP
<?php
\$dbuser='pma';
\$dbpass='${PMA_PASS}';
\$basepath='';
\$dbname='phpmyadmin';
\$dbserver='localhost';
\$dbport='3306';
\$dbtype='mysql';
PHP
chown root:www-data /etc/phpmyadmin/config-db.php
chmod 640 /etc/phpmyadmin/config-db.php
```

> Alternativa Debian equivalente: `dpkg-reconfigure -plow phpmyadmin` (respondiendo *Sí* a configurar la base de datos). El asistente usa el método manual por ser determinista y verificable.

### A.4 Incidente conocido 2 — Advertencias deprecadas de `twig/twig` 3.21+

**Síntoma:** múltiples líneas `Since twig/twig 3.21: Method "Twig\Parser::getExpressionParser()" is deprecated…` en la interfaz.

**Causa raíz:** Debian 13 sirve **phpMyAdmin 5.2.2** con **php-twig 3.27** y **php-twig-i18n-extension 5.0.0-1.1**. El archivo `/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php` (líneas 63, 67 y 74) usa la API `getExpressionParser()->parseExpression()`, deprecada desde Twig 3.21. Son avisos `E_USER_DEPRECATED` **cosméticos** (no rompen nada), pero contaminan la UI.

**Solución aplicada (parche oficial, no invasivo y reversible):**

```bash
F=/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php
cp -n "$F" "$F.orig"
sed -i 's/\$this->parser->getExpressionParser()->parseExpression()/\$this->parser->parseExpression()/g' "$F"
rm -rf /var/lib/phpmyadmin/tmp/twig/*   # forzar recompilación de plantillas
```

> Es exactamente el arreglo publicado por el proyecto upstream (`phpmyadmin/twig-i18n-extension`, PR #23). El arreglo definitivo llegará con phpMyAdmin 5.2.3+, aún no disponible en los repositorios estables de Debian 13. El asistente reaplica el parche de forma idempotente en cada ejecución (y conviene reaplicarlo tras actualizar paquetes).

### A.5 Usuario de instalación y `sudo`

El asistente detecta automáticamente el usuario creado durante la instalación del sistema operativo (primer UID ≥ 1000 con shell interactivo) y lo agrega al grupo `sudo` si no pertenece a él. Esto garantiza una vía de administración aunque no se cree ningún usuario adicional.

### A.6 Notas de operación

- **Idempotencia:** el asistente puede re-ejecutarse; usa guardas (`id`, `[ ! -d .git ]`, `CREATE ... IF NOT EXISTS`, etc.) y regenera credenciales de forma consistente.
- **Log:** toda la ejecución se registra en `/var/log/asistente_servidor.log` y los parámetros quedan en `/etc/asistente_servidor.conf` (modo `600`, solo `root`).
- **Verificación:** `verificar_servidor.sh` realiza un *login real* a phpMyAdmin (cookie + token CSRF) y comprueba que **no** aparezcan ni el aviso de almacenamiento ni las advertencias de Twig, además de servicios, UFW, SSL, Samba, respaldos y HTTP/HTTPS.