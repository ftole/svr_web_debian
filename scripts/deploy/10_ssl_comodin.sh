#!/bin/bash
# ==============================================================================
# 10_ssl_comodin.sh - Genera CA local y Certificado SSL SAN comodin (*.dominio)
# Uso: sudo bash scripts/deploy/10_ssl_comodin.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../common.sh" ] && . "${SCRIPT_DIR}/../common.sh"

require_root
load_config

log "[10/13] Generando CA y Certificado SAN Comodin (*.${BASE_DOMAIN})..."

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

if [ -d /var/www/prod/public_html ]; then
    cp /etc/ssl/localcerts/rootCA.crt /var/www/prod/public_html/rootCA.crt
    chown "${ADMIN_USER}":www-data /var/www/prod/public_html/rootCA.crt
fi

log "      Certificados generados y rootCA.crt publicado para descarga."
