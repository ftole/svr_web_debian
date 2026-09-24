#!/bin/bash
# ==============================================================================
# limpiar_servidor.sh - Retorna Debian 13 al estado base limpio
# Uso: sudo bash limpiar_servidor.sh
# Conserva la sesion/usuario actual y su acceso SSH. NO borra el usuario 'web'.
# ==============================================================================
set -x

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Este script debe ejecutarse como root." >&2
    exit 1
fi

echo "=== 1. Deteniendo servicios ==="
systemctl stop apache2 php8.4-fpm php-fpm mariadb smbd nmbd fail2ban ufw 2>/dev/null || true
ufw --force disable 2>/dev/null || true

echo "=== 2. Purgando paquetes instalados ==="
export DEBIAN_FRONTEND=noninteractive
apt-get purge -y \
  'apache2*' 'libapache2-mod-fcgid' \
  'php*' 'phpmyadmin*' \
  'mariadb*' 'galera*' \
  'samba*' 'winbind' \
  'fail2ban' 'ufw' 'auditd' \
  'libpam-pwquality' 2>/dev/null || true

apt-get autoremove --purge -y 2>/dev/null || true
apt-get clean 2>/dev/null || true

echo "=== 3. Eliminando archivos y carpetas residuales ==="
rm -rf /var/www/izzi /var/www/stg
rm -rf /etc/apache2 /etc/php /etc/mysql /etc/samba /var/lib/mysql /var/log/samba /etc/phpmyadmin
rm -rf /etc/ssl/localcerts /backup /opt/scripts
rm -rf /var/lib/phpmyadmin /var/lib/php
rm -f /var/log/backup-daily.log /var/log/sudo.log /etc/cron.d/web-daily-backup
rm -f /etc/sysctl.d/99-disable-ipv6.conf /etc/sudoers.d/99-audit-log /etc/ssh/sshd_config.d/01-hardening.conf
rm -f /etc/systemd/logind.conf.d/99-nas.conf
rm -f /root/asistente_servidor.sh /root/verificar_servidor.sh /etc/asistente_servidor.conf

mkdir -p /var/www/html
chown -R root:root /var/www

echo "=== 4. Restaurando IPv6 y politicas del sistema ==="
sysctl --system 2>/dev/null || true
systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target 2>/dev/null || true
systemctl restart systemd-logind 2>/dev/null || true

echo "=== 5. Eliminando usuarios secundarios preservando la sesion actual ==="
CURRENT_USER="${SUDO_USER:-$(logname 2>/dev/null || echo "$USER")}"
for u in devops2 devops_sr webadmin adminops; do
    if [ "$u" != "$CURRENT_USER" ] && id "$u" &>/dev/null; then
        pkill -u "$u" 2>/dev/null || true
        deluser --remove-home "$u" 2>/dev/null || true
    fi
done

echo "=== SISTEMA RESTAURADO AL ESTADO BASE LIMPIO ==="
