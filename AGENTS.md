# AGENTS.md — Reglas y contexto del proyecto

Este archivo permite continuar el desarrollo en cualquier equipo. Léelo antes de tocar el repositorio.

## 1. Qué es

Plataforma de automatización y administración para servidores web nativos sobre **Debian 13 (Trixie)**:
Apache 2.4 (MPM Event) + PHP 8.4 FPM + Composer 2.x + Redis Server + MariaDB 11.8 + phpMyAdmin por subdominio,
**SSL comodín interno**, Samba SMBv3 (recurso maestro `[proyectos]`), UFW + Fail2ban y respaldos rotativos de 7 días.

- **CLI principal:** `srvctl` instalado en `/usr/local/bin/srvctl` (orquestación modular con TUI interactiva y subcomandos).
- **Ruteo web:** Dominio base para Dashboard de bienvenida y salud (`_dashboard`). Acceso dual simultáneo: subdominios dinámicos sin configuración vía `mod_vhost_alias` (`https://<proyecto>.empresa.local`) y acceso por ruta directa en el dominio base o por IP (`https://empresa.local/<proyecto>/` y `https://<IP>/<proyecto>/`). phpMyAdmin disponible en `webdev.empresa.local` y por alias en `/phpmyadmin` o `/webdev`.
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
| `tests/` | Suites de pruebas automatizadas: `test_validator.sh`, `test_wrapper.sh` y `test_endpoints.sh`. |
| `.agents/rules/` | Reglas operativas y directrices de orquestacion multi-agente. |
| `.agents/skills/` | Catalogo de habilidades modulares especializadas para agentes de trabajo. |
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
6. Antes de commitear, validar sintaxis con `bash -n`, `shellcheck -S warning` y ejecutar `tests/test_validator.sh` y `tests/test_wrapper.sh`.
7. **Paradigma Multi-Agente Obligatorio:** Todo desarrollo, refactorización, endurecimiento o auditoría compleja debe abordarse coordinando agentes y subagentes concurrentes y especializados (ej. Coordinador, Desarrollador de Scripts, Desarrollador UI, Auditor de Seguridad/Caos y Verificador QA). Ningún cambio se da por concluido sin validación cruzada entre agentes.
8. **Invariantes Anti-Autoaniquilación (Self-Defending System):** Todo script, endpoint o acción interactiva debe incorporar protecciones activas contra acciones destructivas o accidentales del usuario (guardrails en firewall contra auto-bloqueo SSH, prohibición estricta de borrado de recursos de sistema, pruebas pre-vuelo antes de reiniciar servicios y bloqueos de concurrencia con `flock`).

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
bash tests/test_wrapper.sh
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
3. Ejecucion de las suites de pruebas unitarias y de integracion (tests/test_validator.sh y tests/test_wrapper.sh).
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
11. **Compatibilidad de Acceso Dual (Subdominio y Ruta):** En `00-dashboard.conf`, Apache mapea mediante mod_rewrite cualquier ruta que coincida con una carpeta de proyecto existente en `/var/www/<nombre>/public_html` con forzado de barra final (301) para preservar hipervínculos relativos. Los recursos internos del Dashboard (`app`, `assets`, `downloads`, `partials`, `views`), `not_found.php` y phpMyAdmin (`/phpmyadmin`, `/${DB_SUB}`) quedan reservados y protegidos.
12. **Principio Anti-Autoaniquilación y Guardrails:** La plataforma está diseñada para protegerse a sí misma de la intervención del usuario:
    - *Firewall / SSH:* Nunca permitir la eliminación de la regla de acceso SSH ni la desactivación de UFW sin verificar que el puerto SSH esté garantizado. Fail2ban mantiene IPs de administración en lista blanca (`ignoreip`).
    - *Pre-vuelos obligatorios (Config-Test First):* Prohibido reiniciar Apache o PHP-FPM sin validar previamente la sintaxis (`apache2ctl configtest`, `php-fpm8.4 -t`). Si la prueba falla, el servicio en ejecución no se interrumpe y se reporta el error.
    - *Inmutabilidad de esquemas y directorios:* Prohibición absoluta de eliminar o renombrar esquemas de base de datos (`mysql`, `sys`, `information_schema`, `performance_schema`, `pmadb`, `phpmyadmin`) o directorios del sistema (`_dashboard`, `lost+found`, proyectos base).
    - *Bloqueo de concurrencia (`flock`):* Operaciones críticas o de larga duración (actualizaciones del sistema, respaldos masivos, optimizaciones) deben usar descriptores de bloqueo atómicos (`flock`) para evitar condiciones de carrera o ejecuciones simultáneas.
    - *Escrituras atómicas:* Todo archivo de configuración modificado por `srvctl` se escribe primero en un temporal y se sustituye atómicamente (`install -m` o `mv`).
13. **Desacoplamiento y Degradación Elegante en Dashboard:** Si un servicio dependiente (MariaDB, Redis, Samba) colapsa, la interfaz web del Dashboard debe mantenerse 100% operativa, reportando el estado visualmente y aislando el fallo sin generar pantallas en blanco (WSOD) ni errores fatales no capturados.

## 9. Seguridad

- Default `ADMIN_PASS=Temp123#` es solo para demo: advertir cambiarla.
- Credenciales operativas guardadas en `/etc/srvctl.conf` y `/etc/asistente_servidor.conf` con permisos `600` (solo root).
- Auditoria de comandos con privilegios en `/var/log/sudo.log`.
- `debian_pruebas.txt` esta en `.gitignore`; nunca subirlo.

## 10. Catálogo de Skills Especializadas (.agents/skills/)

| Skill | Enfoque y Responsabilidad |
| :--- | :--- |
| `multi-agent-orchestration` | Protocolo de división de trabajo, sincronización y validación cruzada concurrente entre múltiples agentes. |
| `script-hardener-shell` | Estándares de desarrollo de scripts Bash robustos (`set -eo pipefail` en orquestadores modulares, `set -euo pipefail` en wrappers/instalador, trampas `trap`, validación estricta, `flock`, reemplazo atómico). |
| `self-defense-guardian` | Guardrails de auto-protección del servidor (protección SSH/UFW, pre-vuelos antes de recargas, esquemas y carpetas inmutables). |
| `chaos-anti-breakage` | Metodología de auditoría contra dobles envíos, tolerancia a servicios caídos y resiliencia en frontend. |
| `fullstack-integration` | Especificación técnica de la cadena segura Navegador -> PHP -> Wrapper -> CLI -> JSON. |
| `security-resilience` | Auditoría de seguridad ofensiva y defensiva (inyección de comandos, path traversal, CSRF, sudoers). |
| `dashboard-bulletproof-ui` | UI/UX resiliente, sin scripts inline (estricto CSP), deshabilitación reactiva de formularios y degradación elegante. |
| `qa-automated-verifier` | Arnés de ejecución automatizada de pruebas (`tests/`), linters (`shellcheck`, `bash -n`), `actionlint` y validación de regresión. |
