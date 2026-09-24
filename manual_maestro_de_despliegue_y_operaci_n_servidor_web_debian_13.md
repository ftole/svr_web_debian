# MANUAL MAESTRO DE DESPLIEGUE, OPERACIÓN Y REGRESIÓN
## ARQUITECTURA WEB NATIVA DEBIAN 13 (PERFIL HOSTINGER CON SUBDOMINIOS Y ASISTENTE)

---

## 1. Ficha Técnica y Especificaciones del Sistema

| Parámetro | Detalle Técnico |
| :--- | :--- |
| **Sistema Operativo** | Debian GNU/Linux 13 (Trixie) x86_64 |
| **Pila Web** | Apache 2.4 (MPM Event) + PHP 8.4 FPM (FastCGI) |
| **Motor de Base de Datos** | MariaDB 11.8 con acceso administrativo dual (`localhost` y `127.0.0.1`) |
| **Gestor Visual DB** | phpMyAdmin 5.2.2 en VirtualHost dedicado por subdominio |
| **Compartición de Red** | Samba 4.22 (SMBv3 forzado, NetBIOS deshabilitado, puertos 445/tcp) |
| **Seguridad Perimetral** | UFW (IPv4 exclusivo) + Fail2ban (Jail SSH con bloqueo progresivo) |
| **Puertos Abiertos UFW** | 22 (SSH), 80 (HTTP), 443 (HTTPS), 445 (Samba), 3389 (GNOME RDP) |
| **Gestión de Energía** | Bloqueo estricto de suspensión, hibernación y cierre de tapa en systemd |
| **Pila de Red** | IPv4 activa / IPv6 deshabilitado permanentemente a nivel kernel |
| **Arquitectura de Dominios** | Dominio base + Subdominios para Producción, Pruebas y phpMyAdmin |
| **Certificado SSL** | CA Raíz privada interna + Certificado SAN Comodín (*Wildcard* `*.dominio.local` e IP) |
| **Control de Versiones** | Repositorio Git local en cada raíz web (`/var/www/prod` y `/var/www/stg`) |
| **Auditoría del Sistema** | Registro de comandos administrativos en `/var/log/sudo.log` y `auditd` |
| **Respaldos Automatizados** | Snapshots diarios mediante hard-links (7 días) + Volcados SQL comprimidos |

---

## 2. Procedimiento de Limpieza Total (Retorno a Estado Base Recién Instalado)

Para revertir el servidor al estado base limpio eliminando paquetes residuales y configuraciones:

```bash
sudo bash limpiar_servidor.sh
```

El script orquesta de forma modular los componentes ubicados en `scripts/cleanup/`:
* `01_detener_servicios.sh`: Detiene apache2, php-fpm, mariadb, smbd, nmbd, fail2ban y ufw.
* `02_purgar_paquetes.sh`: Purga paquetes con APT (`apt-get purge`, `autoremove`, `clean`).
* `03_eliminar_residuales.sh`: Elimina `/var/www/prod`, `/var/www/stg`, certificados, respaldos y configs.
* `04_restaurar_sistema.sh`: Restaura IPv6 en kernel y reactiva targets de suspensión.
* `05_eliminar_usuarios.sh`: Limpia usuarios secundarios protegiendo la sesión activa.

---

## 3. Asistente Modular de Despliegue (Script Wizard)

El asistente interactivo solicita los parámetros del servidor (o toma valores por defecto presionando `Enter`), despliega de forma ordenada toda la arquitectura sin fallos de dependencias, genera los certificados comodín y finaliza imprimiendo una pantalla de resguardo con las credenciales y el bloque de PowerShell listo para Windows.

### Ejecución en Debian 13 (como `root`):

```bash
# Método rápido con curl:
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash

# O ejecución directa local:
sudo bash asistente_servidor.sh
```

### Arquitectura de submódulos (`scripts/deploy/`):
El orquestador ejecuta en orden estricto los siguientes módulos reutilizables:
1. `01_energia_anti_suspension.sh`: Directivas systemd anti-suspensión, hibernación y bloqueo de tapa (`99-nas.conf`).
2. `02_desactivar_ipv6.sh`: Desactiva IPv6 en el kernel vía `sysctl.d`.
3. `03_instalar_paquetes_base.sh`: Actualiza e instala el stack completo vía APT.
4. `04_configurar_usuarios.sh`: Usuario instalador a `sudo`, crea usuario admin y auditoría en `/var/log/sudo.log`.
5. `05_hardening_ssh.sh`: Hardening de SSH (`PermitRootLogin no`, timeouts).
6. `06_cortafuegos_ufw_f2b.sh`: Reglas de firewall UFW y jaula SSH en Fail2ban.
7. `07_mariadb.sh`: Securización de MariaDB y privilegios de usuario administrativo.
8. `08_phpmyadmin.sh`: phpMyAdmin no interactivo, almacenamiento `pmadb` (tablas `pma__*`) y parche Twig.
9. `09_estructura_web_git.sh`: Directorios web, permisos colaborativos `2775` y repositorios Git.
10. `10_ssl_comodin.sh`: Root CA interna y certificado SAN comodín (`*.dominio` + IP + localhost).
11. `11_apache_php.sh`: VirtualHosts HTTP/HTTPS con redirección 301, MPM Event y PHP-FPM.
12. `12_samba_shares.sh`: Recursos compartidos Samba SMBv3 (`prod` y `stg`).
13. `13_respaldos_cron.sh`: Respaldos diarios rotativos de 7 días (rsync `--link-dest` + volcado SQL) y cron.

---

## 4. Manual de Reversión y Rollback Operativo

Para realizar cualquier recuperación ante fallas de despliegue, errores de desarrollo o incidentes de base de datos, conéctate vía SSH (`ssh web`, alias creado por el bloque de Windows, o con el usuario configurado) y ejecuta el procedimiento respectivo:

> [!NOTE]
> En los ejemplos se usa `webadmin` como usuario administrador; sustitúyelo por tu `ADMIN_USER` si configuraste otro.

### A. Reversión de Cambios en Código Web (Git)
Aplica cuando un archivo editado desde Windows corrompe el sitio:
```bash
cd /var/www/prod/public_html

# Descartar modificaciones locales no commiteadas
git restore .
git clean -fd

# Revertir un commit previo generando un contra-commit seguro
git revert HEAD --no-edit

# Regresar a un commit específico descartando lo posterior
git reset --hard <hash_commit>
sudo chown -R webadmin:www-data /var/www/prod
sudo find /var/www/prod -type d -exec chmod 2775 {} \;
sudo find /var/www/prod -type f -exec chmod 0664 {} \;
```

### B. Restauración de Base de Datos MariaDB
Aplica ante pérdida o corrupción de tablas:
```bash
# 1. Listar respaldos disponibles
ls -lh /backup/database/

# 2. Restaurar volcado específico
gunzip -c /backup/database/db_all_YYYY-MM-DD_HH-MM-SS.sql.gz | sudo mariadb

# 3. Comprobar bases de datos activas
sudo mariadb -e "SHOW DATABASES;"
```

### C. Restauración Completa desde Snapshots (Retención de 7 Días)
Los snapshots diarios están ubicados en `/backup/snapshots/daily.0` (más reciente) hasta `daily.6` (hace 7 días):
```bash
# Restaurar entorno Producción (prod) al snapshot daily.0
sudo rsync -a --delete /backup/snapshots/daily.0/prod/ /var/www/prod/
sudo chown -R webadmin:www-data /var/www/prod

# Restaurar entorno Pruebas (stg) al snapshot daily.0
sudo rsync -a --delete /backup/snapshots/daily.0/stg/ /var/www/stg/
sudo chown -R webadmin:www-data /var/www/stg
```

---

## 5. Auditoría del Sistema y Alta de Nuevos Administradores

### A. Inspección de Comandos Privilegiados (`sudo.log`)
Permite rastrear qué usuario ejecutó qué comando y en qué ruta:
```bash
sudo tail -n 50 /var/log/sudo.log
```

### B. Procedimiento para Dar de Alta Nuevos Administradores
Para otorgar acceso a un nuevo miembro del equipo técnico:
```bash
NUEVO_USER="devops_sr"

# 1. Crear usuario y asignar grupos administrativos
sudo useradd -m -s /bin/bash -G sudo,www-data ${NUEVO_USER}
sudo passwd ${NUEVO_USER}

# 2. Habilitar credencial en red Samba
sudo smbpasswd -a ${NUEVO_USER}

# 3. Autorizar en los recursos compartidos
sudo sed -i "s/valid users = /valid users = ${NUEVO_USER} /g" /etc/samba/smb.conf
sudo systemctl reload smbd
```

---

## ANEXO A. Correcciones aplicadas (revisión 2026-09) y Guía del Asistente

Este anexo documenta los defectos detectados en pruebas reales sobre Debian 13 (Trixie), sus causas raíz y los archivos corregidos.

### A.1 Archivos que componen el asistente

| Archivo | Rol |
| :--- | :--- |
| `install.sh` | Bootstrap que consume el `curl` (descarga los scripts y lanza el asistente; prompts por `/dev/tty`). |
| `asistente_servidor.sh` | Wizard interactivo de despliegue (idempotente, con auto-verificación). |
| `verificar_servidor.sh` | Auto-test del stack completo (40 comprobaciones PASS/FAIL). |
| `limpiar_servidor.sh` | Retorno al estado base limpio (purga total, preserva acceso SSH). |

### A.2 Uso rápido (como `root`)

```bash
# 1) (Opcional) Regresar a estado base limpio
sudo bash limpiar_servidor.sh

# 2) Desplegar todo el stack (interactivo)
sudo bash asistente_servidor.sh

# 3) Verificar de forma independiente en cualquier momento
sudo bash verificar_servidor.sh
```

Modo no interactivo (para automatización / CI):

```bash
sudo ASISTENTE_NONINTERACTIVE=1 \
     SERVER_IP=10.0.0.10 BASE_DOMAIN=empresa.local \
     PROD_SUB=prod STG_SUB=stg DB_SUB=webdev \
     ADMIN_USER=webadmin ADMIN_PASS='Temp123#' \
     bash asistente_servidor.sh
```

### A.3 Incidente conocido 1 — «El almacenamiento de configuración phpMyAdmin no está completamente configurado»

**Síntoma:** al entrar a phpMyAdmin aparece el aviso y se desactivan funciones extendidas (relaciones, historial, favoritos, marcadores, seguimiento, etc.).

**Causa raíz:** el script original preseeda `phpmyadmin/dbconfig-install boolean false`. Con ello `dbconfig-common` genera `/etc/phpmyadmin/config-db.php` con `$dbname='phpmyadmin'` pero **`$dbuser=''` y `$dbpass=''`**. Como `config.inc.php` evalúa `if (!empty($dbname))`, deja configurado `pmadb` y los nombres de tablas, pero sin usuario de control válido, por lo que no puede usar el almacenamiento.

**Solución aplicada por el asistente (determinista):**

```bash
# 1) Crear la base de control y sus 19 tablas pma__*
mariadb < /usr/share/phpmyadmin/sql/create_tables.sql

# 2) Crear/actualizar el usuario de control con privilegios mínimos
PMA_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
mariadb <<SQL
CREATE USER IF NOT EXISTS 'pma'@'localhost' IDENTIFIED BY '${PMA_PASS}';
ALTER USER 'pma'@'localhost' IDENTIFIED BY '${PMA_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`phpmyadmin\`.* TO 'pma'@'localhost';
FLUSH PRIVILEGES;
SQL

# 3) Escribir config-db.php con las credenciales correctas
cat > /etc/phpmyadmin/config-db.php <<PHP
<?php
\$dbuser='pma';
\$dbpass='${PMA_PASS}';
\$basepath='';
\$dbname='phpmyadmin';
\$dbserver='localhost';
\$dbport='3306';
\$dbtype='mysql';
PHP
chown root:www-data /etc/phpmyadmin/config-db.php
chmod 640 /etc/phpmyadmin/config-db.php
```

> Alternativa Debian equivalente: `dpkg-reconfigure -plow phpmyadmin` (respondiendo *Sí* a configurar la base de datos). El asistente usa el método manual por ser determinista y verificable.

### A.4 Incidente conocido 2 — Advertencias deprecadas de `twig/twig` 3.21+

**Síntoma:** múltiples líneas `Since twig/twig 3.21: Method "Twig\Parser::getExpressionParser()" is deprecated…` en la interfaz.

**Causa raíz:** Debian 13 sirve **phpMyAdmin 5.2.2** con **php-twig 3.27** y **php-twig-i18n-extension 5.0.0-1.1**. El archivo `/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php` (líneas 63, 67 y 74) usa la API `getExpressionParser()->parseExpression()`, deprecada desde Twig 3.21. Son avisos `E_USER_DEPRECATED` **cosméticos** (no rompen nada), pero contaminan la UI.

**Solución aplicada (parche oficial, no invasivo y reversible):**

```bash
F=/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php
cp -n "$F" "$F.orig"
sed -i 's/\$this->parser->getExpressionParser()->parseExpression()/\$this->parser->parseExpression()/g' "$F"
rm -rf /var/lib/phpmyadmin/tmp/twig/*   # forzar recompilación de plantillas
```

> Es exactamente el arreglo publicado por el proyecto upstream (`phpmyadmin/twig-i18n-extension`, PR #23). El arreglo definitivo llegará con phpMyAdmin 5.2.3+, aún no disponible en los repositorios estables de Debian 13. El asistente reaplica el parche de forma idempotente en cada ejecución (y conviene reaplicarlo tras actualizar paquetes).

### A.5 Usuario de instalación y `sudo`

El asistente detecta automáticamente el usuario creado durante la instalación del sistema operativo (primer UID ≥ 1000 con shell interactivo) y lo agrega al grupo `sudo` si no pertenece a él. Esto garantiza una vía de administración aunque no se cree ningún usuario adicional.

### A.6 Notas de operación

- **Idempotencia:** el asistente puede re-ejecutarse; usa guardas (`id`, `[ ! -d .git ]`, `CREATE ... IF NOT EXISTS`, etc.) y regenera credenciales de forma consistente.
- **Log:** toda la ejecución se registra en `/var/log/asistente_servidor.log` y los parámetros quedan en `/etc/asistente_servidor.conf` (modo `600`, solo `root`).
- **Verificación:** `verificar_servidor.sh` realiza un *login real* a phpMyAdmin (cookie + token CSRF) y comprueba que **no** aparezcan ni el aviso de almacenamiento ni las advertencias de Twig, además de servicios, UFW, SSL, Samba, respaldos y HTTP/HTTPS.