#!/bin/bash
# ==============================================================================
# modules/stack/phpmyadmin.sh - Instalacion, pmadb y parche Twig de phpMyAdmin
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

install_phpmyadmin() {
    validate_root
    load_config

    log "[Stack] Instalando phpMyAdmin de forma no interactiva..."
    echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections 2>/dev/null || true
    echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections 2>/dev/null || true
    export DEBIAN_FRONTEND=noninteractive
    apt-get install -y phpmyadmin >/dev/null

    log "        Configurando almacenamiento pmadb y usuario de control pma..."
    local pma_db="phpmyadmin"
    local pma_user="pma"
    local pma_pass
    pma_pass="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"

    if [ -f /usr/share/phpmyadmin/sql/create_tables.sql ]; then
        mariadb < /usr/share/phpmyadmin/sql/create_tables.sql
        mariadb <<SQL
CREATE USER IF NOT EXISTS '${pma_user}'@'localhost' IDENTIFIED BY '${pma_pass}';
ALTER USER '${pma_user}'@'localhost' IDENTIFIED BY '${pma_pass}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${pma_db}\`.* TO '${pma_user}'@'localhost';
FLUSH PRIVILEGES;
SQL
        cat > /etc/phpmyadmin/config-db.php <<PHP
<?php
\$dbuser='${pma_user}';
\$dbpass='${pma_pass}';
\$basepath='';
\$dbname='${pma_db}';
\$dbserver='localhost';
\$dbport='3306';
\$dbtype='mysql';
PHP
        chown root:www-data /etc/phpmyadmin/config-db.php
        chmod 640 /etc/phpmyadmin/config-db.php
        log "        Almacenamiento de configuracion phpMyAdmin establecido."
    fi

    # Parche no invasivo para Twig >= 3.21 en phpMyAdmin 5.2.2
    local twig_parser="/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php"
    if [ -f "$twig_parser" ] && grep -q 'getExpressionParser()->parseExpression()' "$twig_parser"; then
        cp -n "$twig_parser" "${twig_parser}.orig" 2>/dev/null || true
        sed -i 's/\$this->parser->getExpressionParser()->parseExpression()/\$this->parser->parseExpression()/g' "$twig_parser"
        log "        Parche Twig aplicado con exito."
    fi

    rm -rf /var/lib/phpmyadmin/tmp/twig/* 2>/dev/null || true
    log "        phpMyAdmin configurado y optimizado."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    install_phpmyadmin
fi
