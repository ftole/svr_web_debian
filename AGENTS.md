# AGENTS.md — Reglas y contexto del proyecto

Este archivo permite continuar el desarrollo en cualquier equipo. Léelo antes de tocar el repositorio.

## 1. Qué es

Plataforma de automatización y administración para servidores web nativos sobre **Debian 13 (Trixie)**:
Apache 2.4 (MPM Event) + PHP 8.4 FPM + Composer 2.x + Redis Server + MariaDB 11.8 + phpMyAdmin por subdominio,
**SSL comodín interno**, Samba SMBv3 (recurso maestro `[proyectos]`), UFW + Fail2ban y respaldos rotativos de 7 días.

- **CLI principal:** `srvctl` instalado en `/usr/local/bin/srvctl` (orquestación modular con TUI interactiva y subcomandos).
- **Ruteo web:** Dominio base para Dashboard de bienvenida y salud (`_dashboard`). Subdominios dinámicos sin configuración para nuevos proyectos vía `mod_vhost_alias`.
- **Resolución DNS:** Delegada preferentemente a **Pi-hole** (recomendado mediante `address=/.empresa.local/<IP>`), o bien mediante router local (dnsmasq/MikroTik/pfSense), dominio público con comodín o archivo `hosts`. Debian **no** ejecuta servicios DNS locales.
- **Repositorio:** `https://github.com/ftole/svr_web_debian` (rama `main`, **público**).
- **Titular/licencia:** propietaria — `José Francisco Toledo` <cisco_red@outlook.com> (ver `LICENSE`).
- **Idioma:** documentación, mensajes de commit y comentarios en **español**. No usar emojis salvo que se pida.

## 2. Mapa de archivos

| Archivo / Directorio | Rol |
| :--- | :--- |
| `bin/srvctl` | Binario ejecutable principal. CLI interactivo con Whiptail y subcomandos de operacion. |
| `core/logger.sh` | Sistema central de logging a consola y `/var/log/srvctl.log`. |
| `core/validator.sh` | Validadores estrictos de IPv4 (rango 0-255), dominios FQDN, subdominios, disco y entorno. |
| `core/config.sh` | Carga y persistencia centralizada de configuracion en `/etc/srvctl.conf`. |
| `modules/system/` | Optimizaciones de sistema operativo (politicas anti-suspension, desactivacion de IPv6 en kernel). |
| `modules/security/` | Cortafuegos UFW, jaula SSH en Fail2ban, hardening de OpenSSH y auditoria en `/var/log/sudo.log`. |
| `modules/stack/` | Pila de software (paquetes base, PHP 8.4 FPM, Composer 2.x, Redis, MariaDB 11.8, phpMyAdmin 5.x). |
| `modules/web/` | Autoridad CA raiz y SSL comodin SAN, VirtualHosts de Apache (`mod_vhost_alias`) y Dashboard de salud. |
| `modules/share/` | Servidor Samba SMBv3 con recurso unificado `[proyectos]` sobre `/var/www`. |
| `modules/backup/` | Sistema de respaldos diarios por snapshots rotativos de 7 dias y volcados SQL. |
| `templates/dashboard/` | Codigo fuente del Panel de Control multiarchivo (`index.php`, `app/`, `views/`, `partials/`, `assets/`, `not_found.php`). |
| `templates/windows/` | Scripts de aprovisionamiento en 1 clic para clientes (`configurar-cliente.bat`, `configurar-desarrollador.bat`) con inyeccion de resolucion en el archivo `hosts`. |
| `tests/test_validator.sh` | Suite de pruebas unitarias automatizadas y chaos testing de validacion de entradas. |
| `install.sh` | Bootstrap de instalacion via `curl`: descarga a `/opt/srvctl` y enlaza el CLI global. |
| `README.md` | Manual principal y documentacion de arquitectura. |
| `Manual.md` | Manual de operacion y guia paso a paso en lenguaje 100% humano. |
| `AGENTS.md` | Este archivo de contexto y directrices tecnicas. |
| `LICENSE` | Licencia propietaria. |
| `.github/workflows/ci.yml` | Integracion continua: `bash -n` + `shellcheck -S warning` + pruebas unitarias + `actionlint`. |
| `.gitattributes` / `.gitignore` | Forzado de finales de linea LF / exclusion de secretos y credenciales. |

## 3. Reglas de trabajo (obligatorias)

1. **Commits atómicos: un archivo por commit.** Mensajes en español con prefijo tipo Conventional Commits
   (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `ci:`, `chore:`). No mezclar varios archivos en un commit.
2. **Finales de línea LF** siempre (`.gitattributes`: `* text=auto eol=lf`). Nunca CRLF en los `.sh` o ejecutables.
3. **Nunca** commitear `debian_pruebas.txt` ni credenciales (esta en `.gitignore`). Si anades secretos, ignoralos.
4. **No añadir comentarios** al código salvo que aporten valor real; el código de shell se documenta con bloques claros.
5. **CI en verde** es requisito antes de dar por terminado un cambio (ver §5).
6. Antes de commitear, validar sintaxis con `bash -n`, `shellcheck -S warning` y ejecutar `tests/test_validator.sh`.

## 4. Valores por defecto del despliegue

| Variable | Default |
| :--- | :--- |
| `SERVER_IP` | autodetectada |
| `BASE_DOMAIN` | `empresa.local` |
| `PROD_SUB` | `prod` |
| `STG_SUB` | `stg` |
| `DB_SUB` | `webdev` |
| `ADMIN_USER` | `webadmin` |
| `ADMIN_PASS` | `Temp123#` (solo demo; documentar que se cambie) |

- Dominio base: `https://<dominio>` -> Dashboard de salud y descargas (`/var/www/_dashboard`).
- Subdominios estandar: `prod.<dominio>`, `stg.<dominio>`, `webdev.<dominio>`.
- Subdominios dinamicos: `*.<dominio>` -> `/var/www/<nombre>/public_html/`.

## 5. Comandos de trabajo

**Validación local (requiere Linux o WSL con bash y shellcheck):**
```bash
bash -n *.sh bin/* core/*.sh modules/*/*.sh tests/*.sh
shellcheck -S warning *.sh bin/* core/*.sh modules/*/*.sh tests/*.sh
bash tests/test_validator.sh
```

**Despliegue rápido:**
```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
```

**Diagnóstico y verificación (en el servidor):**
```bash
sudo srvctl status
sudo srvctl verify
```

## 6. Entorno de pruebas

- Equipo real de pruebas: **Debian 13 en `10.1.0.4`**.
- Acceso SSH: usuario **`web`** (tiene llave; credenciales reservadas en local).
- `web` pertenece al grupo `sudo`. Para comandos no interactivos: `echo '<pass>' | sudo -S -p '' <cmd>`.
- El sistema es completamente **idempotente** y reversible via `srvctl reset`.

## 7. CI (Integración Continua)

`.github/workflows/ci.yml` corre en cada push/PR a `main`:
1. `bash -n` recursivo sobre todos los scripts y binarios.
2. `shellcheck -S warning` sobre todos los componentes.
3. Ejecucion de la suite de pruebas unitarias (`tests/test_validator.sh`).
4. `actionlint` para validar la sintaxis de GitHub Actions.
Comprobar: `gh run list --limit 3`.

## 8. Decisiones técnicas clave (NO romper)

1. **Resolución DNS Delegada (Pi-hole u homólogos):** Debian no aloja servicio DNS para mantener la máxima ligereza. La resolución de cualquier subdominio nuevo se delega preferentemente a un servidor Pi-hole mediante `address=/.empresa.local/<IP>`, o bien mediante router local (dnsmasq/MikroTik/pfSense), dominio público con comodín o archivo hosts.
2. **Subdominios dinámicos sin reinicios:** Apache utiliza `VirtualDocumentRoot /var/www/%1/public_html`. Si el directorio no existe, un `RewriteCond` deriva limpiamente a `/not_found.php` entregando HTTP 404 amigable.
3. **Dominio Base Desacoplado y Panel de Control:** El dominio base (`https://empresa.local`) sirve exclusivamente el centro de administración y salud (`/var/www/_dashboard`), permitiendo gestionar proyectos, bases de datos y diagnósticos de forma segura y aislando la producción (`prod.empresa.local`).
4. **phpMyAdmin / almacenamiento:** `dbconfig-install false` seguido de configuracion determinista de `pmadb`: importa `/usr/share/phpmyadmin/sql/create_tables.sql` (19 tablas `pma__*`), crea usuario `pma@localhost` y genera `/etc/phpmyadmin/config-db.php`.
5. **Twig ≥ 3.21:** Parche idempotente en `/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php` (`getExpressionParser()->parseExpression()` -> `parseExpression()`) y limpieza de cache Twig.
6. **Paridad Hostinger:** Inclusion de Redis Server (`redis-server`), extension `php-redis` y binario global de Composer 2.x en `/usr/local/bin/composer`.
7. **Recurso Samba Unificado `[proyectos]`:** Mapeo unico a `/var/www` con permisos SGID `2775`, `force group = www-data`, y directiva `veto files` para ocultar `.git`, `.env`, `.htaccess`, `_dashboard` y llaves `.key`.
8. **Scripts Windows 1-Clic (.bat):** Ambos `.bat` inyectan la resolucion de los subdominios en el archivo `hosts` del cliente (entornos sin DNS comodin). `configurar-cliente.bat` ademas instala la CA raiz para evitar alertas SSL; `configurar-desarrollador.bat` anade `EnableLinkedConnections`, mapea la unidad `Z:\` a `\\IP\proyectos` y configura el alias SSH `web`.
9. **Usuario de instalación del SO:** El primer UID ≥ 1000 con shell se anade a `sudo` por defecto.
10. **Aprovisionamiento Automático DB:** `srvctl project db <nombre>` genera la base de datos MariaDB, usuario dedicado y archivo `.env` en `public_html`.

## 9. Seguridad

- Default `ADMIN_PASS=Temp123#` es solo para demo: advertir cambiarla.
- Credenciales operativas guardadas en `/etc/srvctl.conf` y `/etc/asistente_servidor.conf` con permisos `600` (solo root).
- Auditoria de comandos con privilegios en `/var/log/sudo.log`.
- `debian_pruebas.txt` esta en `.gitignore`; nunca subirlo.
