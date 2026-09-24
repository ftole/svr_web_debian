#!/bin/bash
# ==============================================================================
# tests/test_validator.sh - Pruebas unitarias y de robustez para validator.sh
# ==============================================================================
set -eo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
. "${TEST_DIR}/../core/validator.sh"

FAILED=0
TOTAL=0

assert_true() {
    local desc="$1"; shift
    TOTAL=$((TOTAL + 1))
    if "$@"; then
        echo "  [PASS] $desc"
    else
        echo "  [FAIL] Esperaba verdadero: $desc"
        FAILED=$((FAILED + 1))
    fi
}

assert_false() {
    local desc="$1"; shift
    TOTAL=$((TOTAL + 1))
    if ! "$@"; then
        echo "  [PASS] $desc"
    else
        echo "  [FAIL] Esperaba falso: $desc"
        FAILED=$((FAILED + 1))
    fi
}

echo "=== Ejecutando pruebas unitarias de validacion ==="

# IPv4 Validations
assert_true  "IPv4 valida: 10.1.0.4" validate_ipv4 "10.1.0.4"
assert_true  "IPv4 valida: 192.168.1.1" validate_ipv4 "192.168.1.1"
assert_true  "IPv4 valida: 127.0.0.1" validate_ipv4 "127.0.0.1"
assert_false "IPv4 octeto > 255: 999.0.0.1" validate_ipv4 "999.0.0.1"
assert_false "IPv4 no numerica: 10.0.abc.1" validate_ipv4 "10.0.abc.1"
assert_false "IPv4 incompleta: 10.0.1" validate_ipv4 "10.0.1"
assert_false "IPv4 extra octeto: 1.2.3.4.5" validate_ipv4 "1.2.3.4.5"
assert_false "IPv4 cadena vacia" validate_ipv4 ""

# Domain Validations
assert_true  "Dominio valido: empresa.local" validate_domain "empresa.local"
assert_true  "Dominio valido: dev.midominio.com" validate_domain "dev.midominio.com"
assert_false "Dominio con inyeccion: empresa.local;ls" validate_domain "empresa.local;ls"
assert_false "Dominio con espacios: empresa .local" validate_domain "empresa .local"
assert_false "Dominio sin TLD: empresa" validate_domain "empresa"

# Subdomain Validations
assert_true  "Subdominio valido: tienda" validate_subdomain "tienda"
assert_true  "Subdominio valido: prod-2" validate_subdomain "prod-2"
assert_false "Subdominio con mayusculas: Tienda" validate_subdomain "Tienda"
assert_false "Subdominio con inyeccion: tienda;rm" validate_subdomain "tienda;rm"
assert_false "Subdominio con guion inicial: -tienda" validate_subdomain "-tienda"
assert_false "Subdominio con simbolos: sub*demo!" validate_subdomain "sub*demo!"

echo "=================================================="
echo "Resultados: $((TOTAL - FAILED))/$TOTAL pruebas pasadas."

if [ "$FAILED" -gt 0 ]; then
    echo "ERROR: Fallaron $FAILED pruebas."
    exit 1
fi
exit 0
