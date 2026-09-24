#!/bin/bash
# ==============================================================================
# 11_apache_php.sh - Configura Apache 2.4 (MPM Event), PHP-FPM y VirtualHosts
# Uso: sudo bash scripts/deploy/11_apache_php.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[11/13] Configurando Apache 2.4 con perfiles VirtualHosts..."

a2enmod actions fcgid alias proxy_fcgi rewrite headers ssl >/dev/null
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

log "      Apache 2.4 configurado y activo con HTTP/HTTPS y PHP-FPM."
