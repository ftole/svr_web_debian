#!/bin/bash
# ==============================================================================
# modules/web/ssl.sh - Generacion y gestion de CA privada y certificado comodin
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

generate_ssl() {
    validate_root
    load_config

    log "[Web] Generando CA raiz privada y certificado comodin (*.${BASE_DOMAIN})..."
    local ssl_dir="/etc/ssl/localcerts"
    mkdir -p "$ssl_dir"
    cd "$ssl_dir"

    (
        umask 077
        if [ ! -f rootCA.key ]; then
            openssl genrsa -out rootCA.key 4096 2>/dev/null
            openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 3650 \
              -subj "/C=MX/ST=Hidalgo/L=Pachuca/O=Infraestructura/CN=Local-RootCA" \
              -out rootCA.crt 2>/dev/null
        fi
        openssl genrsa -out webserver.key 2048 2>/dev/null
    )

    cat > openssl_san.cnf <<EOF
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
EOF

    openssl req -new -key webserver.key -out webserver.csr -config openssl_san.cnf 2>/dev/null
    openssl x509 -req -in webserver.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial \
      -out webserver.crt -days 1095 -sha256 -extfile openssl_san.cnf -extensions req_ext 2>/dev/null

    chmod 400 "${ssl_dir}"/*.key
    chmod 644 "${ssl_dir}"/*.crt

    # Publicar copia en downloads del dashboard
    local dl_dir="/var/www/_dashboard/downloads"
    mkdir -p "$dl_dir"
    cp -f "${ssl_dir}/rootCA.crt" "${dl_dir}/rootCA.crt"
    chmod 644 "${dl_dir}/rootCA.crt"
    chown -R www-data:www-data "$dl_dir" 2>/dev/null || true

    log "      Certificados SSL listos (*.${BASE_DOMAIN} e IP ${SERVER_IP})."
}

renew_ssl() {
    validate_root
    load_config
    log "[SSL] Regenerando par de claves y certificado comodin (*.${BASE_DOMAIN})..."
    generate_ssl
    if command -v apache2ctl >/dev/null 2>&1; then
        if apache2ctl configtest >/dev/null 2>&1; then
            systemctl reload apache2 2>/dev/null || systemctl restart apache2 2>/dev/null || true
            log "      Apache recargado con el nuevo certificado SSL."
        else
            log_warn "Sintaxis de Apache no valida. No se recargo el servicio."
        fi
    fi
    log "[SSL] Renovacion de certificados completada exitosamente."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    action="${1:-generate}"
    case "$action" in
        generate) generate_ssl ;;
        renew)    renew_ssl ;;
        *) die "Uso: $0 [generate|renew]" ;;
    esac
fi
