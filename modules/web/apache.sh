#!/bin/bash
# ==============================================================================
# modules/web/apache.sh - Configuracion de Apache 2.4 y VirtualHosts dinamicos
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

configure_apache() {
    validate_root
    load_config

    log "[Web] Configurando Apache 2.4 MPM Event y VirtualHosts..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y apache2 libapache2-mod-fcgid >/dev/null

    a2enmod actions fcgid alias proxy_fcgi rewrite headers ssl vhost_alias >/dev/null 2>&1 || true
    a2enconf "php${PHP_VER}-fpm" >/dev/null 2>&1 || true
    a2dismod mpm_prefork >/dev/null 2>&1 || true
    a2enmod mpm_event >/dev/null 2>&1 || true

    echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
    a2enconf servername >/dev/null 2>&1 || true

    cat > /etc/apache2/conf-available/security-hardening.conf <<'EOF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"

<FilesMatch "^\.(git|env|user\.ini|htaccess)|(\.key|\.sql|\.bak)$">
    Require all denied
</FilesMatch>
EOF
    a2enconf security-hardening >/dev/null 2>&1 || true

    # 1. 00-dashboard.conf (Dominio Base y salud del sistema)
    cat > /etc/apache2/sites-available/00-dashboard.conf <<EOF
<VirtualHost *:80>
    ServerName ${BASE_DOMAIN}
    ServerAlias localhost ${SERVER_IP}
    DocumentRoot /var/www/_dashboard
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${BASE_DOMAIN}
    ServerAlias localhost ${SERVER_IP}
    DocumentRoot /var/www/_dashboard
    SSLEngine on
    SSLCertificateFile /etc/ssl/localcerts/webserver.crt
    SSLCertificateKeyFile /etc/ssl/localcerts/webserver.key
    <Directory /var/www/_dashboard>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/apache2/dashboard_error.log
    CustomLog /var/log/apache2/dashboard_access.log combined
</VirtualHost>
EOF

    # 2. 01-prod.conf (Produccion strictly on prod sub)
    cat > /etc/apache2/sites-available/01-prod.conf <<EOF
<VirtualHost *:80>
    ServerName ${PROD_FQDN}
    DocumentRoot /var/www/prod/public_html
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${PROD_FQDN}
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
EOF

    # 3. 02-stg.conf (Staging)
    cat > /etc/apache2/sites-available/02-stg.conf <<EOF
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
EOF

    # 4. 03-webdev.conf (phpMyAdmin)
    cat > /etc/apache2/sites-available/03-webdev.conf <<EOF
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
EOF

    # 5. 99-dynamic.conf (Enrutador comodin automatico)
    cat > /etc/apache2/sites-available/99-dynamic.conf <<EOF
<VirtualHost *:80>
    ServerName dynamic.${BASE_DOMAIN}
    ServerAlias *.${BASE_DOMAIN}
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName dynamic.${BASE_DOMAIN}
    ServerAlias *.${BASE_DOMAIN}
    SSLEngine on
    SSLCertificateFile /etc/ssl/localcerts/webserver.crt
    SSLCertificateKeyFile /etc/ssl/localcerts/webserver.key

    VirtualDocumentRoot /var/www/%1/public_html

    <Directory /var/www/*/public_html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>

    RewriteEngine On
    RewriteCond %{HTTP_HOST} ^([a-zA-Z0-9-]+)\.${BASE_DOMAIN}$ [NC]
    RewriteCond /var/www/%1/public_html !-d
    RewriteRule ^ /not_found.php [L]

    Alias /not_found.php /var/www/_dashboard/not_found.php
    <Directory /var/www/_dashboard>
        Require all granted
    </Directory>

    ErrorLog /var/log/apache2/dynamic_error.log
    CustomLog /var/log/apache2/dynamic_access.log combined
</VirtualHost>
EOF

    a2dissite 000-default.conf >/dev/null 2>&1 || true
    a2ensite 00-dashboard.conf 01-prod.conf 02-stg.conf 03-webdev.conf 99-dynamic.conf >/dev/null 2>&1 || true

    systemctl restart apache2 >/dev/null 2>&1 || true
    log "        Apache 2.4 configurado con enrutamiento dinamico y VirtualHosts listos."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    configure_apache
fi
