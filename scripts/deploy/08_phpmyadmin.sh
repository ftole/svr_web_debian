#!/bin/bash
# ==============================================================================
# 08_phpmyadmin.sh - Instala phpMyAdmin, pmadb y aplica parche Twig >= 3.21
# Uso: sudo bash scripts/deploy/08_phpmyadmin.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root

log "[8/13] Instalando phpMyAdmin de forma no interactiva..."

# Pre-siembra tolerante de debconf
echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections 2>/dev/null || true
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections 2>/dev/null || true
DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin

log "      Configurando almacenamiento de phpMyAdmin (pmadb + controluser)..."
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
## Generado por scripts/deploy/08_phpmyadmin.sh - almacenamiento phpMyAdmin
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

# Parche Twig para Twig >= 3.21 en phpMyAdmin 5.2.2
TWIG_PARSER="/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php"
if [ -f "$TWIG_PARSER" ] && grep -q 'getExpressionParser()->parseExpression()' "$TWIG_PARSER"; then
    cp -n "$TWIG_PARSER" "${TWIG_PARSER}.orig" 2>/dev/null || true
    sed -i 's/\$this->parser->getExpressionParser()->parseExpression()/\$this->parser->parseExpression()/g' "$TWIG_PARSER"
    log "      Parche Twig aplicado a TransTokenParser.php."
else
    log "      Twig: no requiere parche (ya aplicado o version corregida)."
fi

# Limpieza de cache Twig
rm -rf /var/lib/phpmyadmin/tmp/twig/* 2>/dev/null || true

log "      phpMyAdmin listo."
