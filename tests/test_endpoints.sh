#!/bin/bash
set -euo pipefail

echo "=========================================================="
echo "PRUEBA 4: ENDPOINTS WEB (HTTP / curl)"
echo "=========================================================="

COOKIE_JAR="/tmp/test_cookies.txt"
rm -f "$COOKIE_JAR"

echo "--- 4.1: Prueba de Protección No Autenticada (HTTP 403) ---"

test_unauth() {
    local action="$1"
    echo -n "Probando endpoint unauth '?action=$action'... "
    local http_code body json
    body=$(curl -k -s -w "\n%{http_code}" "https://127.0.0.1/?action=${action}")
    http_code=$(echo "$body" | tail -n 1)
    json=$(echo "$body" | sed '$d')

    if [ "$http_code" = "403" ] && [[ "$json" =~ "unauthorized" ]]; then
        echo "[PASS] (HTTP $http_code, respuesta: $json)"
        return 0
    else
        echo "[FAIL] (HTTP $http_code, respuesta: $json)"
        return 1
    fi
}

test_unauth "server_status"
test_unauth "metrics"
test_unauth "live_logs"

echo ""
echo "--- 4.2: Inicio de Sesión Administrativa ---"
# Obtener token CSRF de la página de login
LOGIN_PAGE=$(curl -k -s -c "$COOKIE_JAR" "https://127.0.0.1/?action=login")
CSRF_TOKEN=$(echo "$LOGIN_PAGE" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | cut -d'"' -f4 || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    echo "  [FAIL] No se pudo extraer csrf_token de login.php"
    exit 1
fi
echo "  [INFO] CSRF Token obtenido: ${CSRF_TOKEN:0:10}..."

# Iniciar sesión vía POST
LOGIN_RESP=$(curl -k -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "https://127.0.0.1/" \
    -H "Accept: application/json" \
    -d "action=login" \
    -d "username=webadmin" \
    -d "password=Pruebas123#" \
    -d "csrf_token=${CSRF_TOKEN}")

echo "  [INFO] Respuesta login: $LOGIN_RESP"

if [[ "$LOGIN_RESP" =~ '"success":true' ]] || [[ "$LOGIN_RESP" =~ 'Sesión iniciada' ]]; then
    echo "  [PASS] Sesion administrativa iniciada con exito."
else
    echo "  [FAIL] Error al autenticar sesion."
    exit 1
fi

echo ""
echo "--- 4.3: Prueba de Endpoints Autenticados (HTTP 200 + JSON Valido) ---"

test_auth_endpoint() {
    local action="$1"
    local required_field="$2"
    echo -n "Probando endpoint auth '?action=$action'... "
    local http_code body json
    body=$(curl -k -s -b "$COOKIE_JAR" -w "\n%{http_code}" "https://127.0.0.1/?action=${action}")
    http_code=$(echo "$body" | tail -n 1)
    json=$(echo "$body" | sed '$d')

    if [ "$http_code" = "200" ] && [[ "$json" =~ $required_field ]]; then
        echo "[PASS] (HTTP $http_code)"
        echo "  Muestra JSON: ${json:0:120}..."
        return 0
    else
        echo "[FAIL] (HTTP $http_code)"
        echo "  Respuesta completa: $json"
        return 1
    fi
}

test_auth_endpoint "server_status" "server_ip"
test_auth_endpoint "metrics" "fast"
test_auth_endpoint "live_logs" "logs"

rm -f "$COOKIE_JAR"

echo ""
echo "=========================================================="
echo "RESULTADO: TODAS LAS PRUEBAS DE ENDPOINTS PASARON [PASS]"
echo "=========================================================="
