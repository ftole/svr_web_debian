#!/bin/bash
set -euo pipefail

# Extraer el wrapper de dashboard.sh para validarlo
sed -n '/cat > \/usr\/local\/bin\/srvctl-web-wrapper <<'\''EOF'\''/,/^EOF$/p' modules/web/dashboard.sh | sed '1d;$d' > /tmp/test-srvctl-wrapper.sh
chmod +x /tmp/test-srvctl-wrapper.sh

echo "Verificando sintaxis del wrapper..."
bash -n /tmp/test-srvctl-wrapper.sh
shellcheck -S warning /tmp/test-srvctl-wrapper.sh
echo "[OK] Sintaxis del wrapper valida."

# Crear un mock de /usr/local/bin/srvctl para probar ejecuciones
mkdir -p /tmp/mock-bin
cat > /tmp/mock-bin/srvctl <<'EOF'
#!/bin/bash
echo "MOCK_SRVCTL_EXEC: $*"
exit 0
EOF
chmod +x /tmp/mock-bin/srvctl

# Reemplazar la ruta en el script de prueba temporal
sed -i 's|/usr/local/bin/srvctl|/tmp/mock-bin/srvctl|g' /tmp/test-srvctl-wrapper.sh

test_cmd() {
    local cmd="$1"
    local expected="$2"
    local out
    if out=$(SRVCTL_CMD="$cmd" /tmp/test-srvctl-wrapper.sh 2>&1); then
        if [ "$expected" = "ALLOW" ]; then
            echo "  [PASS-ALLOW] '$cmd' -> $out"
        else
            echo "  [FAIL-UNEXPECTED-ALLOW] '$cmd' deberia haber sido rechazado pero paso: $out"
            exit 1
        fi
    else
        if [ "$expected" = "DENY" ]; then
            echo "  [PASS-DENY] '$cmd' rechazado correctamente."
        else
            echo "  [FAIL-UNEXPECTED-DENY] '$cmd' deberia haber sido permitido pero fallo: $out"
            exit 1
        fi
    fi
}

echo "=== Probando comandos permitidos (ALLOW) ==="
test_cmd "service restart apache2" ALLOW
test_cmd "service restart php8.4-fpm" ALLOW
test_cmd "service restart mariadb" ALLOW
test_cmd "service restart redis-server" ALLOW
test_cmd "service restart smbd" ALLOW
test_cmd "service restart ufw" ALLOW
test_cmd "service restart fail2ban" ALLOW
test_cmd "service reload apache2" ALLOW
test_cmd "service reload php8.4-fpm" ALLOW
test_cmd "firewall toggle" ALLOW
test_cmd "firewall toggle on" ALLOW
test_cmd "firewall toggle off" ALLOW
test_cmd "firewall allow 8080/tcp API Service" ALLOW
test_cmd "firewall allow 8080" ALLOW
test_cmd "firewall delete 3" ALLOW
test_cmd "firewall delete 8080/tcp" ALLOW
test_cmd "security ban 192.168.1.50" ALLOW
test_cmd "security unban 192.168.1.50" ALLOW
test_cmd "security scan" ALLOW
test_cmd "ssl renew" ALLOW
test_cmd "backup run" ALLOW
test_cmd "backup list" ALLOW
test_cmd "backup verify" ALLOW
test_cmd "backup rollback daily.0" ALLOW
test_cmd "backup rollback-project tienda daily.0" ALLOW
test_cmd "backup restore-db db_all_2026.sql.gz" ALLOW
test_cmd "db create mi_tienda_db usr_tienda pass123" ALLOW
test_cmd "db create mi_tienda_db usr_tienda" ALLOW
test_cmd "db delete mi_tienda_db" ALLOW
test_cmd "db optimize" ALLOW
test_cmd "cache flush-redis" ALLOW
test_cmd "system check-updates" ALLOW
test_cmd "system upgrade" ALLOW
test_cmd "system upgrade apache2" ALLOW
test_cmd "system change-password ClaveSegura2026!" ALLOW
test_cmd "project create tienda" ALLOW
test_cmd "project delete tienda" ALLOW
test_cmd "project db tienda" ALLOW
test_cmd "project list" ALLOW
test_cmd "verify" ALLOW
test_cmd "api security" ALLOW
test_cmd "api samba" ALLOW
test_cmd "api database" ALLOW

echo "=== Probando comandos maliciosos / no permitidos (DENY) ==="
test_cmd "service restart apache2; rm -rf /" DENY
test_cmd "service restart evil-unit" DENY
test_cmd "service reload mariadb" DENY
test_cmd "security ban 192.168.1.50; cat /etc/shadow" DENY
test_cmd "security ban not-an-ip" DENY
test_cmd "firewall allow 80; reboot" DENY
test_cmd 'system upgrade `whoami`' DENY
test_cmd "system change-password 123" DENY
test_cmd "backup restore-db ../../../etc/passwd" DENY
test_cmd "backup rollback-project tienda; rm -rf /" DENY
test_cmd "unknown_cmd" DENY
test_cmd "" DENY

echo "=============================================="
echo "TODAS LAS PRUEBAS DEL WRAPPER PASARON (39 ALLOW / 12 DENY)"
echo "=============================================="
