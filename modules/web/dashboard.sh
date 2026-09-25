#!/bin/bash
# ==============================================================================
# modules/web/dashboard.sh - Despliegue de la aplicacion de salud y bienvenida
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/logger.sh" ] && . "${SCRIPT_DIR}/../../core/logger.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/validator.sh" ] && . "${SCRIPT_DIR}/../../core/validator.sh"
# shellcheck source=/dev/null
[ -f "${SCRIPT_DIR}/../../core/config.sh" ] && . "${SCRIPT_DIR}/../../core/config.sh"

deploy_dashboard() {
    validate_root
    load_config

    log "[Web] Desplegando aplicacion de Dashboard y Centro de Descargas..."
    local dash_dir="/var/www/_dashboard"
    local dl_dir="${dash_dir}/downloads"
    local tpl_dir="${SCRIPT_DIR}/../../templates"

    mkdir -p "${dash_dir}" "${dl_dir}"

    if [ -f "${tpl_dir}/dashboard/index.php" ]; then
        cp -f "${tpl_dir}/dashboard/index.php" "${dash_dir}/index.php"
    fi
    if [ -f "${tpl_dir}/dashboard/not_found.php" ]; then
        cp -f "${tpl_dir}/dashboard/not_found.php" "${dash_dir}/not_found.php"
    fi

    # Generar scripts para Windows en downloads con resolucion de hosts inyectada
    local hosts_names="" n d b
    local -A hosts_seen=()
    local -a hosts_list=("${BASE_DOMAIN}" "${PROD_FQDN}" "${STG_FQDN}" "${DB_FQDN}")
    for d in /var/www/*; do
        [ -d "$d" ] || continue
        b="$(basename "$d")"
        case "$b" in
            html|_*) continue ;;
        esac
        hosts_list+=("${b}.${BASE_DOMAIN}")
    done
    for n in "${hosts_list[@]}"; do
        [ -n "${hosts_seen[$n]:-}" ] && continue
        hosts_seen[$n]=1
        hosts_names="${hosts_names:+${hosts_names},}'${n}'"
    done

    if [ -f "${tpl_dir}/windows/configurar-cliente.bat" ]; then
        sed -e "s/__SERVER_IP__/${SERVER_IP}/g" \
            -e "s/__HOSTS_NAMES__/${hosts_names}/g" \
            "${tpl_dir}/windows/configurar-cliente.bat" > "${dl_dir}/configurar-cliente.bat"
    fi

    if [ -f "${tpl_dir}/windows/configurar-desarrollador.bat" ]; then
        sed -e "s/__SERVER_IP__/${SERVER_IP}/g" \
            -e "s/__ADMIN_USER__/${ADMIN_USER}/g" \
            -e "s/__HOSTS_NAMES__/${hosts_names}/g" \
            "${tpl_dir}/windows/configurar-desarrollador.bat" > "${dl_dir}/configurar-desarrollador.bat"
    fi

    rm -f "${dl_dir}/configurar-desarrollador.ps1"

    # Los .bat deben usar finales de linea CRLF para cmd.exe
    if [ -f "${dl_dir}/configurar-cliente.bat" ]; then
        sed -i 's/$/\r/' "${dl_dir}/configurar-cliente.bat"
    fi
    if [ -f "${dl_dir}/configurar-desarrollador.bat" ]; then
        sed -i 's/$/\r/' "${dl_dir}/configurar-desarrollador.bat"
    fi

    # Copiar rootCA si existe
    if [ -f "/etc/ssl/localcerts/rootCA.crt" ]; then
        cp -f "/etc/ssl/localcerts/rootCA.crt" "${dl_dir}/rootCA.crt"
    fi

    chown -R "${ADMIN_USER}":www-data "${dash_dir}"
    find "${dash_dir}" -type d -exec chmod 2775 {} \;
    find "${dash_dir}" -type f -exec chmod 0664 {} \;

    # Regla sudoers limpia para que la interfaz web (www-data) ejecute srvctl sin contrasena
    mkdir -p /etc/sudoers.d
    cat > /etc/sudoers.d/srvctl-web <<'EOF'
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/srvctl
EOF
    chmod 0440 /etc/sudoers.d/srvctl-web

    # Asegurar permisos de lectura para el grupo www-data en la configuracion
    for cfg in /etc/srvctl.conf /etc/asistente_servidor.conf; do
        if [ -f "$cfg" ]; then
            chgrp www-data "$cfg" 2>/dev/null || true
            chmod 640 "$cfg" 2>/dev/null || true
        fi
    done

    log "      Dashboard desplegado en /var/www/_dashboard/."
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    deploy_dashboard
fi
