#!/bin/bash
# ==============================================================================
# 09_estructura_web_git.sh - Estructura de directorios web, permisos y Git
# Uso: sudo bash scripts/deploy/09_estructura_web_git.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[9/13] Configurando estructura web, permisos y repositorios Git..."

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
    (
        cd "/var/www/${dir}/public_html"
        if [ ! -d .git ]; then
            git init -b main >/dev/null
            git config user.name "Administrador Web"
            git config user.email "admin@${BASE_DOMAIN}"
            git add . && git commit -m "Commit inicial: ${dir} (${fqdn})" >/dev/null
        fi
    )
done

log "      Estructura de directorios y repositorios Git inicializados."
