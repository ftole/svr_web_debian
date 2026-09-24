# Servidor Web Nativo Debian 13 — Despliegue automatizado

[![Debian 13](https://img.shields.io/badge/Debian-13-A81D33?logo=debian&logoColor=white)](https://www.debian.org/)
[![Apache 2.4](https://img.shields.io/badge/Apache-2.4-D22128?logo=apache&logoColor=white)](https://httpd.apache.org/)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MariaDB 11.8](https://img.shields.io/badge/MariaDB-11.8-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![License: Proprietary](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)
[![CI](https://github.com/ftole/svr_web_debian/actions/workflows/ci.yml/badge.svg)](https://github.com/ftole/svr_web_debian/actions/workflows/ci.yml)

Asistente (**wizard interactivo**) que despliega una arquitectura web tipo *Hostinger* sobre **Debian 13 (Trixie)**: Apache 2.4 (MPM Event) + PHP 8.4 FPM + MariaDB 11.8 + phpMyAdmin sobre subdominio dedicado, con **SSL comodín interno**, Samba SMBv3, UFW + Fail2ban, auditoría `sudo` y respaldos automáticos de 7 días.

> [!NOTE]
> Este `README.md` es el **manual principal**. La referencia técnica detallada está en
> [`manual_maestro_de_despliegue_y_operaci_n_servidor_web_debian_13.md`](manual_maestro_de_despliegue_y_operaci_n_servidor_web_debian_13.md).

> [!CAUTION]
> Repositorio **propietario**. Todos los derechos reservados.
> Autor: **José Francisco Toledo** — ver [`LICENSE`](LICENSE).

---

## Tabla de contenidos

1. [Características](#características)
2. [Arquitectura](#arquitectura)
3. [Requisitos](#requisitos)
4. [Despliegue rápido con `curl`](#despliegue-rápido-con-curl)
5. [Variables de configuración](#variables-de-configuración)
6. [Scripts del repositorio](#scripts-del-repositorio)
7. [Después del despliegue](#después-del-despliegue)
8. [Verificación](#verificación)
9. [Limpieza y reinstalación](#limpieza-y-reinstalación)
10. [Incidentes conocidos y correcciones](#incidentes-conocidos-y-correcciones)
11. [Seguridad](#seguridad)
12. [Cliente Windows](#cliente-windows)

---

## Características

| Parámetro | Detalle |
| :--- | :--- |
| **Sistema Operativo** | Debian GNU/Linux 13 (Trixie) x86_64 |
| **Pila Web** | Apache 2.4 (MPM Event) + PHP 8.4 FPM (FastCGI) |
| **Base de Datos** | MariaDB 11.8 con usuario admin dual (`localhost` y `127.0.0.1`) |
| **Gestor Visual DB** | phpMyAdmin 5.x sobre VirtualHost dedicado |
| **Compartición de Red** | Samba (SMBv3, NetBIOS deshabilitado, puerto 445/tcp) |
| **Seguridad** | UFW (IPv4) + Fail2ban (jail SSH) |
| **Puertos UFW** | 22 (SSH), 80 (HTTP), 443 (HTTPS), 445 (Samba), 3389 (RDP) |
| **Energía** | Bloqueo de suspensión/hibernación/cierre de tapa vía systemd |
| **Red** | IPv4 activo / IPv6 deshabilitado a nivel kernel |
| **SSL** | CA raíz privada + certificado SAN comodín (`*.dominio.local` + IP) |
| **Control de versiones** | Repositorio Git local en cada raíz web (`/var/www/prod`, `/var/www/stg`) |
| **Auditoría** | Registro de comandos administrativos en `/var/log/sudo.log` |
| **Respaldos** | Snapshots diarios por hard-links (7 días) + volcados SQL comprimidos |

---

## Arquitectura

Se parte de un **dominio base** y se generan tres subdominios:

| Entorno | Host | DocumentRoot |
| :--- | :--- | :--- |
| Producción | `prod.<dominio>` | `/var/www/prod/public_html` |
| Pruebas / Staging | `stg.<dominio>` | `/var/www/stg/public_html` |
| phpMyAdmin | `webdev.<dominio>` | `/usr/share/phpmyadmin` |

Todos con **redirección 301** de HTTP a HTTPS y **certificado comodín**.

> [!TIP]
> El certificado incluye `*.dominio.local` (comodín) + el dominio base + la IP, así que
> **cualquier subdominio queda cubierto por TLS automáticamente**, incluso los que agregues
> en el futuro. No hay que regenerar el certificado por añadir subdominios.

---

## Requisitos

- Debian 13 (Trixie) recién instalado o con posibilidad de purgar paquetes.
- Acceso **`root`** o usuario con `sudo`.
- Conexión a Internet (repositorios APT y descarga de scripts).
- Puertos **22** (SSH), **80**/**443** (web), **445** (Samba) y **3389** (RDP) libres y alcanzables.

---

## Despliegue rápido con `curl`

### Opción 1 — One-liner (interactivo, recomendada)

```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
```

Aunque use `curl | bash`, el instalador **sí pide los datos por pantalla**: lee los prompts
directamente de `/dev/tty`. Si no hay terminal (automatización), cae automáticamente a modo
no interactivo con los valores por defecto.

### Opción 2 — Descargar y revisar antes (segura)

Recomendada en producción: descarga, inspecciona y luego ejecuta.

```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh -o /tmp/install.sh
less /tmp/install.sh && sudo bash /tmp/install.sh
```

### Opción 3 — No interactiva con parámetros propios

```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh -o /tmp/install.sh
sudo ASISTENTE_NONINTERACTIVE=1 \
     SERVER_IP=10.0.0.10 BASE_DOMAIN=miempresa.local \
     PROD_SUB=prod STG_SUB=stg DB_SUB=webdev \
     ADMIN_USER=adminweb ADMIN_PASS='MiClaveSegura#' \
     bash /tmp/install.sh
```

> [!WARNING]
> La Opción 1 (`curl | bash`) ejecuta código remoto directamente. En entornos productivos
> prefiere la **Opción 2** (descargar → revisar → ejecutar).

---

## Variables de configuración

Se pasan como variables de entorno (solo en **modo no interactivo**; en interactivo se piden
por pantalla y `Enter` acepta el valor por defecto).

| Variable | Descripción | Valor por defecto |
| :--- | :--- | :--- |
| `ASISTENTE_NONINTERACTIVE` | `1` desactiva los prompts | auto (`1` si no hay TTY) |
| `SERVER_IP` | IP del servidor | autodetectada |
| `BASE_DOMAIN` | Dominio base | `empresa.local` |
| `PROD_SUB` | Subdominio de Producción | `prod` |
| `STG_SUB` | Subdominio de Pruebas | `stg` |
| `DB_SUB` | Subdominio de phpMyAdmin | `webdev` |
| `ADMIN_USER` | Usuario administrador (SO, MariaDB y Samba) | `webadmin` |
| `ADMIN_PASS` | Contraseña maestra del administrador | `Temp123#` |

> [!CAUTION]
> Cambia `ADMIN_PASS`. El valor por defecto `Temp123#` es **solo para demo**. Usa la variable
> de entorno o responde el prompt con una contraseña robusta.

---

## Scripts del repositorio

| Archivo | Rol |
| :--- | :--- |
| `install.sh` | **Bootstrap**: descarga los scripts y lanza el asistente (los prompts se leen de `/dev/tty`). Es el que consume el `curl`. |
| `asistente_servidor.sh` | **Wizard de despliegue** (idempotente, con pre-chequeos, logging y auto-verificación). |
| `verificar_servidor.sh` | **Auto-test** del stack (40 comprobaciones PASS/FAIL, incluye login real a phpMyAdmin). |
| `limpiar_servidor.sh` | Retorno al **estado base limpio** (purga total, preserva el acceso SSH/sudo). |
| `pma_login_test.sh` | Prueba puntual de login a phpMyAdmin (diagnóstico). |
| `manual_maestro_..._13.md` | Manual técnico detallado. |

---

## Después del despliegue

1. **Registrar nombres en Windows** (ver [Cliente Windows](#cliente-windows)), o en tu DNS.
2. **Credenciales**: al finalizar, el asistente imprime una pantalla de resguardo con la IP,
   dominios, credenciales y los comandos de Windows. Se guarda además en
   `/etc/asistente_servidor.conf` (modo `600`, solo `root`).
3. **Unidades de red Samba** `\\IP\prod` y `\\IP\stg` con el usuario administrador.
4. **Certificado raíz** disponible en `\\IP\prod\public_html\rootCA.crt` para importarlo como
   de confianza.
5. **SSH / Git**: el bloque de Windows crea el alias **`ssh web`**; úsalo para administrar y
   versionar los repositorios Git locales (`/var/www/prod`, `/var/www/stg`).

---

## Verificación

En cualquier momento, en el servidor:

```bash
sudo bash /root/verificar_servidor.sh
```

Comprueba: servicios activos, reglas UFW, HTTP→HTTPS, PHP-FPM, **login real a phpMyAdmin sin
el aviso de almacenamiento ni advertencias de Twig**, SSL/SAN, Samba, respaldos y permisos.
Devuelve `0` si todo pasa.

Para una prueba puntual de login a phpMyAdmin (diagnóstico):

```bash
sudo PMA_HOST=webdev.tudominio.local bash /root/pma_login_test.sh
```

---

## Limpieza y reinstalación

Para volver a un estado base y volver a desplegar:

```bash
sudo bash /root/limpiar_servidor.sh     # purga todo y deja el sistema limpio
sudo bash /root/asistente_servidor.sh   # despliegue limpio
```

> [!NOTE]
> El limpiador **no** borra el usuario con el que se ejecuta ni su acceso SSH/sudo.

---

## Incidentes conocidos y correcciones

Ambos defectos fueron detectados en pruebas reales y **corregidos por el asistente**.

### 1. «El almacenamiento de configuración phpMyAdmin no está completamente configurado»

**Causa:** usar `dbconfig-install false` deja `/etc/phpmyadmin/config-db.php` con
`$dbuser=''` y `$dbpass=''`; `config.inc.php` configura `pmadb` pero sin usuario de control
válido, así que se desactivan las funciones extendidas (relaciones, historial, favoritos,
marcadores, seguimiento…).

**Corrección aplicada:** se importa `create_tables.sql` (19 tablas `pma__*`), se crea el
usuario de control `pma@localhost` con privilegios mínimos y se escribe `config-db.php` con
las credenciales correctas. Alternativa equivalente: `dpkg-reconfigure -plow phpmyadmin`.

### 2. Advertencias deprecadas de `twig/twig` 3.21+

**Causa:** Debian 13 sirve **phpMyAdmin 5.2.2** con **php-twig 3.27**; `TransTokenParser.php`
usa la API `getExpressionParser()->parseExpression()`, deprecada desde Twig 3.21. Son avisos
`E_USER_DEPRECATED` **cosméticos** (no rompen nada).

**Corrección aplicada:** parche oficial no invasivo (`$this->parser->parseExpression()` +
limpieza de la caché de plantillas). Se reaplica de forma idempotente en cada ejecución.

---

## Seguridad

> [!CAUTION]
> Cambia `ADMIN_PASS`: el valor por defecto `Temp123#` es solo para demo.

> [!WARNING]
> `curl | bash` ejecuta código remoto directamente. En producción usa la **Opción 2**
> (descargar → revisar → ejecutar).

> [!IMPORTANT]
> No subas credenciales: `debian_pruebas.txt` está en `.gitignore`. No lo comitees.

- El asistente endurece SSH (`PermitRootLogin no`), activa UFW con política `deny incoming`
  y Fail2ban.
- Las credenciales quedan en `/etc/asistente_servidor.conf` (modo `600`, solo `root`).

---

## Cliente Windows

El asistente imprime un bloque listo para pegar en **PowerShell (como Administrador)** que:

1. Registra los dominios en el archivo `hosts`.
2. Habilita `EnableLinkedConnections` (para que las unidades mapeadas como administrador se vean en el Explorador).
3. Monta las unidades de red Samba en **letras libres automáticas** (no fija `Z:`/`Y:`; salta las ocupadas).
4. Importa el certificado raíz **por UNC** (`\\IP\prod\public_html\rootCA.crt`), sin depender de una letra de unidad.
5. Crea una **llave SSH** (`id_ed25519_web`) y el alias **`web`** en `~/.ssh/config` para gestionar Git en el servidor.

Con el alias `web` te conectas con `ssh web` y trabajas con los repositorios Git locales de `/var/www/prod` y `/var/www/stg` (control de versiones).

> [!NOTE]
> Las unidades se mapean desde PowerShell **como Administrador**. Windows aísla esos mapeos de la
> sesión normal, así que si no aparecen en el Explorador, **reinicia Windows una vez**:
> `EnableLinkedConnections` ya queda aplicado y los mapeos son persistentes (`/persistent:yes`).

> [!TIP]
> **¿Por qué se registran los subdominios y no solo el dominio principal?**
> El certificado es comodín (`*.dominio.local`), así que TLS ya valida todos los subdominios.
> Pero el archivo `hosts` de Windows es una **lista plana sin comodines**: si solo agregas
> `dominio.local`, `prod.dominio.local` no resolverá a la IP y el navegador no podrá conectar
> (error de resolución, no de certificado). Por eso se registran cada uno de los subdominios.
> Alternativa: usar un DNS con comodín (p. ej. `dnsmasq`) o un dominio público real.

---

## Estructura del repositorio

```
svr_web_debian/
├── README.md
├── LICENSE                      # propietaria (All rights reserved)
├── install.sh                   # bootstrap (curl)
├── asistente_servidor.sh        # wizard de despliegue
├── verificar_servidor.sh        # auto-test
├── limpiar_servidor.sh          # reset a estado base
├── pma_login_test.sh            # prueba de login phpMyAdmin
├── manual_maestro_..._13.md     # manual técnico completo
├── .gitignore                   # excluye credenciales y temporales
├── .gitattributes               # fuerza LF en scripts
└── .github/workflows/ci.yml     # CI: bash -n + shellcheck + actionlint
```

---

## Licencia

Este proyecto es **propietario**. Todos los derechos reservados.
Autor y titular del copyright: **José Francisco Toledo** (2026).
Consulta el archivo [`LICENSE`](LICENSE) para los términos completos.
