#!/bin/bash
# ==============================================================================
# verificar_servidor.sh - Auto-verificacion del servidor web Debian 13
# Uso: sudo bash verificar_servidor.sh
# Retorna 0 si todas las comprobaciones pasan; 1 si alguna falla.
# ==============================================================================

CONF="/etc/asistente_servidor.conf"
if [ -f "$CONF" ]; then
    # shellcheck disable=SC1090
    . "$CONF"
fi

SERVER_IP="${SERVER_IP:-127.0.0.1}"
BASE_DOMAIN="${BASE_DOMAIN:-empresa.local}"
PROD_FQDN="${PROD_FQDN:-prod.${BASE_DOMAIN}}"
STG_FQDN="${STG_FQDN:-stg.${BASE_DOMAIN}}"
DB_FQDN="${DB_FQDN:-webdev.${BASE_DOMAIN}}"

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".";echo PHP_MINOR_VERSION;' 2>/dev/null)"
[ -z "$PHP_VER" ] && PHP_VER="8.4"

PASS=0
FAIL=0
RESULTS=()

check() { # check "descripcion" comando...
    local desc="$1"; shift
    if "$@" >/dev/null 2>&1; then
        RESULTS+=("OK|${desc}")
        PASS=$((PASS+1))
    else
        RESULTS+=("FAIL|${desc}")
        FAIL=$((FAIL+1))
    fi
}

svc_active() { systemctl is-active --quiet "$1"; }

http_code() { # http_code URL [resolve] [extra...]
    local url="$1"; shift
    curl -sk -o /dev/null -w '%{http_code}' "$@" "$url"
}

echo "=============================================================================="
echo "           VERIFICACION DEL SERVIDOR WEB - $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================================="

# --- Servicios -----------------------------------------------------------------
check "Servicio apache2 activo"            svc_active apache2
check "Servicio php${PHP_VER}-fpm activo"  svc_active "php${PHP_VER}-fpm"
check "Servicio mariadb activo"            svc_active mariadb
check "Servicio smbd activo"               svc_active smbd
check "Servicio fail2ban activo"           svc_active fail2ban
check "Firewall UFW activo"                bash -c "ufw status | grep -q 'Status: active'"
check "Socket PHP-FPM existe"              test -S "/run/php/php${PHP_VER}-fpm.sock"

# --- Usuario administrador / sudo ---------------------------------------------
ADMIN_USER_V="$(awk -F: '$3>=1000 && $3<65534 && $7 ~ /(bash|zsh|sh)$/ {print $1; exit}' /etc/passwd)"
check "Usuario de instalacion en sudo"      bash -c "id -nG '${ADMIN_USER_V}' 2>/dev/null | tr ' ' '\n' | grep -qx sudo"
check "Usuario admin existe"                id "${ADMIN_USER:-webadmin}"

# --- Puertos UFW ---------------------------------------------------------------
for p in 22 80 443 445 3389; do
    check "UFW permite puerto ${p}/tcp" bash -c "ufw status | grep -q '${p}/tcp'"
done

# --- Web (HTTPS con SNI via --resolve) -----------------------------------------
for pair in "Produccion:${PROD_FQDN}" "Staging:${STG_FQDN}" "phpMyAdmin:${DB_FQDN}"; do
    name="${pair%%:*}"; host="${pair#*:}"
    code="$(http_code "https://${host}/" --resolve "${host}:443:127.0.0.1")"
    check "${name} HTTPS responde 200 ($host) [${code}]" test "$code" = "200"
done

# --- Redireccion HTTP -> HTTPS -------------------------------------------------
for pair in "Produccion:${PROD_FQDN}" "Staging:${STG_FQDN}" "phpMyAdmin:${DB_FQDN}"; do
    name="${pair%%:*}"; host="${pair#*:}"
    code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${host}" "http://127.0.0.1/")"
    check "${name} redirige 301 a HTTPS [${code}]" test "$code" = "301"
done

# --- PHP ejecuta ---------------------------------------------------------------
check "PHP-FPM procesa PHP (Produccion)" bash -c \
  "[ \"$(http_code "https://${PROD_FQDN}/" --resolve "${PROD_FQDN}:443:127.0.0.1")\" = 200 ] && \
   curl -sk --resolve "${PROD_FQDN}:443:127.0.0.1" "https://${PROD_FQDN}/" | grep -qi 'php'"

# --- phpMyAdmin: almacenamiento de configuracion -------------------------------
PMA_INFO="$(php -r "\$d=''; @include '/etc/phpmyadmin/config-db.php'; echo (\$dbuser??'').'|'.(\$dbpass??'').'|'.(\$dbname??'');" 2>/dev/null)"
PMA_USER_V="${PMA_INFO%%|*}"; PMA_REST="${PMA_INFO#*|}"
PMA_PASS_V="${PMA_REST%%|*}"; PMA_DB_V="${PMA_REST#*|}"

check "phpMyAdmin: config-db.php con usuario"  test -n "$PMA_USER_V"
check "phpMyAdmin: config-db.php con clave"    test -n "$PMA_PASS_V"
check "phpMyAdmin: pmadb = phpmyadmin"         test "$PMA_DB_V" = "phpmyadmin"

PMA_TBL_COUNT="$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='phpmyadmin' AND table_name LIKE 'pma\\_\\_%';" 2>/dev/null)"
check "phpMyAdmin: tablas pma__ presentes (${PMA_TBL_COUNT:-0})" bash -c "[ '${PMA_TBL_COUNT:-0}' -ge 19 ]"

if [ -n "$PMA_USER_V" ]; then
    check "phpMyAdmin: controluser conecta a pmadb" \
      mariadb -u "$PMA_USER_V" -p"$PMA_PASS_V" -e "SELECT 1;" phpmyadmin
fi

# Login real end-to-end a phpMyAdmin
PMA_CJ="$(mktemp)"; PMA_BODY="$(mktemp)"
curl -sk -c "$PMA_CJ" -o "$PMA_BODY" --resolve "${DB_FQDN}:443:127.0.0.1" "https://${DB_FQDN}/index.php" 2>/dev/null
PMA_TOKEN="$(sed -n 's/.*name="token" value="\([^"]*\)".*/\1/p' "$PMA_BODY" | head -1)"
curl -sk -L -b "$PMA_CJ" -c "$PMA_CJ" -o "$PMA_BODY" --resolve "${DB_FQDN}:443:127.0.0.1" \
  --data-urlencode "pma_username=${ADMIN_USER}" \
  --data-urlencode "pma_password=${ADMIN_PASS}" \
  --data-urlencode "server=1" \
  --data-urlencode "token=${PMA_TOKEN}" \
  "https://${DB_FQDN}/index.php" 2>/dev/null

check "phpMyAdmin: login real correcto" bash -c "! grep -qiE 'pma_username|Cannot log in|Access denied for user' '${PMA_BODY}'"
check "phpMyAdmin: sin aviso de almacenamiento (tras login)" bash -c "! grep -qi 'configuration storage is not completely configured' '${PMA_BODY}'"
check "Twig: sin advertencias deprecadas (tras login)" bash -c "! grep -qiE 'getExpressionParser|ExpressionParser::parseExpression|Since twig/twig 3\\.21' '${PMA_BODY}'"
rm -f "$PMA_CJ" "$PMA_BODY"

# --- Twig (advertencias deprecadas corregidas) ---------------------------------
TWIG_PARSER="/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php"
if [ -f "$TWIG_PARSER" ]; then
    check "Twig: parche anti-deprecados aplicado" bash -c "! grep -q 'getExpressionParser()->parseExpression()' '${TWIG_PARSER}'"
else
    RESULTS+=("OK|Twig: no requiere parche (archivo ausente)")
    PASS=$((PASS+1))
fi

# --- SSL -----------------------------------------------------------------------
check "Certificado SSL existe"                 test -f /etc/ssl/localcerts/webserver.crt
check "SSL es valido (no expirado)"            bash -c "openssl x509 -checkend 0 -noout -in /etc/ssl/localcerts/webserver.crt"
check "SSL incluye SAN wildcard *.${BASE_DOMAIN}" bash -c "openssl x509 -in /etc/ssl/localcerts/webserver.crt -noout -text | grep -q 'DNS:\\*\\.${BASE_DOMAIN//./\\.}'"
check "SSL incluye IP ${SERVER_IP}"            bash -c "openssl x509 -in /etc/ssl/localcerts/webserver.crt -noout -text | grep -q 'IP Address:${SERVER_IP}'"

# --- Samba ---------------------------------------------------------------------
check "Samba: configuracion valida"            testparm -s
check "Samba: recurso [prod] definido"         bash -c "testparm -s 2>/dev/null | grep -q '\\[prod\\]'"
check "Samba: recurso [stg] definido"          bash -c "testparm -s 2>/dev/null | grep -q '\\[stg\\]'"

# --- Respaldos -----------------------------------------------------------------
check "Backup: script ejecutable"              test -x /opt/scripts/backup-daily.sh
check "Backup: cron instalado"                 test -f /etc/cron.d/web-daily-backup
check "Backup: snapshot diario generado"       test -d /backup/snapshots/daily.0

# --- Resultados ----------------------------------------------------------------
echo ""
printf "%-6s %s\n" "ESTADO" "COMPROBACION"
echo "------------------------------------------------------------------------------"
for r in "${RESULTS[@]}"; do
    st="${r%%|*}"; ds="${r#*|}"
    if [ "$st" = "OK" ]; then
        printf "[ OK ] %s\n" "$ds"
    else
        printf "[FAIL] %s\n" "$ds"
    fi
done
echo "------------------------------------------------------------------------------"
echo "TOTAL: ${PASS} OK / ${FAIL} FALLOS"
echo "=============================================================================="

[ "$FAIL" -eq 0 ]
