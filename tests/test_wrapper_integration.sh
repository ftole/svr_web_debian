#!/bin/bash
set -u

echo "=========================================================="
echo "PRUEBAS DE INTEGRACION: SRVCTL-WEB-WRAPPER Y SUBCOMANDOS"
echo "=========================================================="

test_wrapper_cmd() {
    local cmd="$1"
    local name="$2"
    echo -n "Probando [$name] '$cmd'... "
    
    # Ejecutar como usuario www-data con sudo hacia el wrapper
    local out code
    out=$(sudo -u www-data env SRVCTL_CMD="$cmd" sudo /usr/local/bin/srvctl-web-wrapper 2>&1)
    code=$?
    
    if [ $code -eq 0 ]; then
        echo "[PASS]"
        echo "--- Salida (primeras 5 lineas) ---"
        echo "$out" | head -n 5
        echo "----------------------------------"
        return 0
    else
        echo "[FAIL] (Exit code: $code)"
        echo "--- Salida de error ---"
        echo "$out"
        echo "-----------------------"
        return 1
    fi
}

FAILURES=0

# 1. service restart php8.4-fpm
test_wrapper_cmd "service restart php8.4-fpm" "Reinicio de PHP-FPM" || FAILURES=$((FAILURES + 1))

# 2. firewall toggle (apagamos y encendemos para dejarlo en su estado original)
test_wrapper_cmd "firewall toggle" "Toggle cortafuegos UFW" || FAILURES=$((FAILURES + 1))
# Restauramos si quedó inactivo
if ! ufw status 2>/dev/null | grep -qi 'Status: active'; then
    test_wrapper_cmd "firewall toggle on" "Restaurar cortafuegos UFW activo" || FAILURES=$((FAILURES + 1))
fi

# 3. security scan
test_wrapper_cmd "security scan" "Escaneo y auditoria de seguridad" || FAILURES=$((FAILURES + 1))

# 4. cache flush-redis
test_wrapper_cmd "cache flush-redis" "Vaciado de cache Redis" || FAILURES=$((FAILURES + 1))

# 5. backup verify
test_wrapper_cmd "backup verify" "Verificacion de respaldos y snapshots" || FAILURES=$((FAILURES + 1))

# 6. system check-updates
test_wrapper_cmd "system check-updates" "Verificacion de actualizaciones APT" || FAILURES=$((FAILURES + 1))

# 7. db optimize
test_wrapper_cmd "db optimize" "Optimizacion de bases de datos MariaDB" || FAILURES=$((FAILURES + 1))

echo "=========================================================="
if [ $FAILURES -eq 0 ]; then
    echo "RESULTADO: TODAS LAS PRUEBAS DEL WRAPPER PASARON (7/7)"
    exit 0
else
    echo "RESULTADO: $FAILURES PRUEBAS FALLARON EN EL WRAPPER"
    exit 1
fi
