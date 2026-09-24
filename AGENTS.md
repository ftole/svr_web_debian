# AGENTS.md — Reglas y contexto del proyecto

Este archivo permite continuar el desarrollo en cualquier equipo. Léelo antes de tocar el repositorio.

## 1. Qué es

Asistente (wizard) que despliega una arquitectura web nativa sobre **Debian 13 (Trixie)**:
Apache 2.4 (MPM Event) + PHP 8.4 FPM + MariaDB 11.8 + phpMyAdmin por subdominio, **SSL comodín interno**,
Samba SMBv3, UFW + Fail2ban y respaldos rotativos de 7 días.

- **Repositorio:** `https://github.com/ftole/svr_web_debian` (rama `main`, **público**).
- **Titular/licencia:** propietaria — `José Francisco Toledo` <cisco_red@outlook.com> (ver `LICENSE`).
- **Idioma:** documentación, mensajes de commit y comentarios en **español**. No usar emojis salvo que se pida.

## 2. Mapa de archivos

| Archivo | Rol |
| :--- | :--- |
| `install.sh` | Bootstrap del `curl`: descarga los 3 scripts a `/root` y lanza el asistente. Prompts por `/dev/tty`. |
| `asistente_servidor.sh` | Wizard de despliegue (idempotente, pre-chequeos, logging, auto-verificación). |
| `verificar_servidor.sh` | Auto-test del stack (40 comprobaciones PASS/FAIL; incluye login real a phpMyAdmin). |
| `limpiar_servidor.sh` | Reset a estado base limpio (preserva el acceso SSH/sudo del usuario actual). |
| `pma_login_test.sh` | Prueba puntual de login a phpMyAdmin (diagnóstico). |
| `manual_maestro_..._13.md` | Manual técnico. **Referencia histórica** parcialmente supersedida (ver nota en su §3). |
| `README.md` | Manual principal y ficha del repo. |
| `AGENTS.md` | Este archivo. |
| `LICENSE` | Licencia propietaria. |
| `.github/workflows/ci.yml` | CI: `bash -n` + `shellcheck -S error` + `actionlint`. |
| `.gitattributes` / `.gitignore` | LF obligatorio / exclusión de secretos. |

## 3. Reglas de trabajo (obligatorias)

1. **Commits atómicos: un archivo por commit.** Mensajes en español con prefijo tipo Conventional Commits
   (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `ci:`, `chore:`). No mezclar varios archivos en un commit.
2. **Finales de línea LF** siempre (`.gitattributes`: `* text=auto eol=lf`). Nunca CRLF en los `.sh`.
3. **Nunca** commitear `debian_pruebas.txt` ni credenciales (está en `.gitignore`). Si añades secretos, ignóralos.
4. **No añadir comentarios** al código salvo que aporten valor real; el código de shell se documenta con bloques claros.
5. **CI en verde** es requisito antes de dar por terminado un cambio (ver §5).
6. Antes de commitear, validar sintaxis y shellcheck (ver §5).
7. Al editar el **bloque PowerShell** dentro del heredoc `RESGUARDO_EOF` de `asistente_servidor.sh`:
   - Escapa las variables de PowerShell como `\$var` (bash las convierte en `$var`).
   - Usa `${VAR}` (sin escapar) para que bash expanda las variables del asistente.
   - Valida generando el bloque real (ver §5) además de `bash -n`/`shellcheck`.

## 4. Valores por defecto del despliegue

| Variable | Default |
| :--- | :--- |
| `SERVER_IP` | autodetectada |
| `BASE_DOMAIN` | `empresa.local` |
| `PROD_SUB` | `prod` |
| `STG_SUB` | `stg` |
| `DB_SUB` | `webdev` |
| `ADMIN_USER` | `webadmin` |
| `ADMIN_PASS` | `Temp123#` (solo demo; **documentar que se cambie**) |

Subdominios: `prod.<dominio>`, `stg.<dominio>`, `webdev.<dominio>`. Directorios: `/var/www/prod`, `/var/www/stg`.

## 5. Comandos de trabajo

**Validación local (requiere Linux con bash y shellcheck):**
```bash
bash -n ./*.sh
shellcheck -S error ./*.sh
```

**Generar y revisar el bloque de Windows del asistente:**
```bash
sed -n '/cat <<RESGUARDO_EOF/,/^RESGUARDO_EOF/p' asistente_servidor.sh
# (o construir un pequeño wrapper que defina las variables y ejecute el bloque)
```

**Despliegue (equipo de pruebas):**
```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
```

**Verificación / limpieza (en el servidor, como root):**
```bash
sudo bash /root/verificar_servidor.sh
sudo bash /root/limpiar_servidor.sh
```

## 6. Entorno de pruebas

- Equipo real de pruebas: **Debian 13 en `10.1.0.4`**.
- Acceso SSH: usuario **`web`** (tiene llave; también password en `debian_pruebas.txt`, que **no se sube**).
- `web` pertenece al grupo `sudo`. Para comandos no interactivos: `echo '<pass>' | sudo -S -p '' <cmd>`.
- Tras un `push`, `raw.githubusercontent.com` puede tardar ~5 min en servir la versión nueva (caché CDN).
- El asistente es **idempotente**: se puede re-ejecutar; para partir de cero, `limpiar_servidor.sh` y desplegar.

## 7. CI

`.github/workflows/ci.yml` corre en cada push/PR a `main`:
`bash -n ./*.sh` + `shellcheck -S error ./*.sh` + `actionlint` (con `-shellcheck=`, su shellcheck interno está desactivado).
Comprobar: `gh run list --limit 3`.

## 8. Decisiones técnicas clave (NO romper)

1. **phpMyAdmin / almacenamiento:** el asistente hace `dbconfig-install false` (tolerante) **y luego** configura
   manualmente el `pmadb`: importa `/usr/share/phpmyadmin/sql/create_tables.sql` (19 tablas `pma__*`), crea el
   usuario de control `pma@localhost` con `SELECT/INSERT/UPDATE/DELETE` y escribe `/etc/phpmyadmin/config-db.php`
   con `$dbuser/$dbpass`. Sin esto aparece *«El almacenamiento de configuración phpMyAdmin no está completamente configurado»*.
2. **Twig ≥ 3.21:** parche no invasivo en `/usr/share/php/PhpMyAdmin/Twig/Extensions/TokenParser/TransTokenParser.php`
   (`getExpressionParser()->parseExpression()` → `parseExpression()`), luego limpiar `/var/lib/phpmyadmin/tmp/twig/*`.
3. **Prompts con `curl | bash`:** `install.sh` redirige stdin a `/dev/tty` si existe; si no, modo no interactivo.
4. **Bloque Windows** (generado por el asistente):
   - `EnableLinkedConnections=1` (para ver unidades mapeadas como admin).
   - Samba en **letras libres** (`Get-FreeDriveLetter`).
   - Certificado raíz **por UNC** (`\\$ServerIP\prod\public_html\rootCA.crt`), no por letra de unidad.
   - Alias SSH **`web`** (`~/.ssh/config`: `Host web` → `$AdminUser@$ServerIP`, llave `id_ed25519_web`).
5. **Nombres genéricos:** producción es `prod` (no `izzi`).
6. **Usuario de instalación del SO** (primer UID ≥ 1000 con shell) se agrega a `sudo` por defecto.

## 9. Seguridad

- Default `ADMIN_PASS=Temp123#` es solo demo: los docs deben advertir cambiarla.
- `curl | bash` ejecuta código remoto: documentar la opción «descargar → revisar → ejecutar».
- `debian_pruebas.txt` está en `.gitignore`; **nunca** subirlo.
- Credenciales runtime en `/etc/asistente_servidor.conf` (modo `600`, solo root).

## 10. Pendientes / ideas

- Valorar reemplazar el script incrustado del manual (§3, ~600 líneas obsoletas) por un enlace a los archivos reales.
- Posible endurecimiento: `NoNewPrivileges`/`ProtectSystem` para phpMyAdmin, rotación de logs, tests en contenedor Debian.
