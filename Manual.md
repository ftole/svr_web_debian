# Manual de Usuario y Operación — Servidor Web Debian 13 con srvctl

Bienvenido al manual oficial de la plataforma **srvctl**. Este documento fue redactado con un enfoque 100% humano, práctico y directo, pensado para que cualquier persona, sin importar su experiencia previa en Linux, pueda poner en marcha sitios web, administrar bases de datos y operar el servidor con total tranquilidad y confianza.

---

## 1. ¿Cómo funciona este servidor? (En palabras sencillas)

Tradicionalmente, para poner una nueva página web en un servidor Linux debías conectarte por consola, crear carpetas, configurar archivos virtuales de Apache, solicitar certificados SSL, reiniciar servicios y pelear con permisos de usuario.

Con **srvctl**, todo ese trabajo pesado desaparece:

1. **Tu servidor como una carpeta más en Windows (`Z:\`):** Mediante la red compartida Samba `[proyectos]`, tu servidor se monta en tu computadora como la unidad `Z:\`. Guardas un archivo en tu editor de código favorito (VS Code, PhpStorm, Notepad++) y se actualiza al instante en el servidor.
2. **Subdominios automáticos sin configuración:** Cualquier carpeta que pongas en `Z:\nombre\public_html` se convierte inmediatamente en el sitio web `https://nombre.empresa.local`, con certificado SSL válido y PHP 8.4 listo para procesar.
3. **Panel Web de Control y Administración:** En el dominio principal (`https://empresa.local`), cuentas con un Centro de Control interactivo donde puedes iniciar sesión para crear proyectos, aprovisionar bases de datos con un clic, ejecutar diagnósticos completos en vivo, generar respaldos y monitorear el consumo de memoria y disco.
4. **Base de datos con 1 solo comando:** Con `srvctl project db <nombre>` o desde el panel web, se crea la base de datos, el usuario, la contraseña aleatoria y el archivo `.env` listo para tu proyecto.

---

## 2. Métodos de resolución de nombres (Dominios y Subdominios)

Para que tu computadora sepa que `tienda.empresa.local`, `blog.empresa.local` o cualquier otro subdominio deben abrirse en la dirección IP de tu servidor Debian (por ejemplo `10.1.0.4`), se requiere un mecanismo de resolución de nombres.

> [!NOTE]
> El archivo `hosts` tradicional de Windows **no admite comodines** (`*.empresa.local`). Por ello, existen varias alternativas para resolver los dominios de forma cómoda:

> [!TIP]
> Si tu red no cuenta con un servidor DNS, los scripts `.bat` de aprovisionamiento (Sección 3) inyectan automáticamente los dominios estándar y los proyectos existentes en el archivo `hosts` de Windows. Basta con ejecutar el `.bat` como administrador.
>
> Si un antivirus (por ejemplo, Kaspersky) o Smart App Control bloquea la escritura, el propio script mostrará las líneas exactas para agregarlas manualmente en `C:\Windows\System32\drivers\etc\hosts`.

### Opción 1 (Recomendada): Servidor DNS Pi-hole
Si cuentas con un servidor Pi-hole en tu red local, es la opción más sencilla ya que resuelve automáticamente cualquier subdominio actual y futuro para todos los dispositivos de la red.
- En la consola de Pi-hole, crea un archivo `/etc/dnsmasq.d/02-wildcard.conf`:
  ```text
  address=/.empresa.local/10.1.0.4
  ```
  *(Sustituye `empresa.local` por tu dominio y `10.1.0.4` por la IP de tu servidor Debian).*
- Reinicia el DNS en Pi-hole con: `pihole restartdns`.
- A partir de ese momento, cualquier subdominio resolverá automáticamente sin tocar nada más.

### Opción 2: Router local o Servidor DNS de red
Si utilizas un router avanzado o servidor DNS interno en tu oficina u hogar:
- **MikroTik (RouterOS):** En *IP -> DNS -> Static*, agrega una regla con `Regexp`: `.*\.empresa\.local` hacia la IP del servidor.
- **pfSense / OPNsense (Unbound DNS):** En *Custom Options*, agrega:
  ```text
  local-zone: "empresa.local" redirect
  local-data: "empresa.local A 10.1.0.4"
  ```
- **OpenWrt / dnsmasq:** Agrega en `/etc/dnsmasq.conf`: `address=/.empresa.local/10.1.0.4`.
- **Windows Server (Active Directory / DNS):** Crea una zona de búsqueda directa para `empresa.local` y agrega un registro comodín `*` de tipo `A` que apunte a la IP de Debian.

### Opción 3: Dominio público o DNS Dinámico
Puedes utilizar un dominio propio de internet (o un subdominio gratuito como DuckDNS) y crear un registro comodín:
- En tu proveedor DNS (Cloudflare, Namecheap, etc.), crea un registro `A` con host `*` apuntando a la IP local de tu servidor (ejemplo: `*.devlocal.miempresa.com` -> `10.1.0.4`).
- Al usar la IP local, la resolución funciona de manera global en cualquier equipo conectado a la misma red sin configurar ningún DNS interno.

### Opción 4: Archivo hosts manual (Sin servidor DNS)
Si estás en una red doméstica simple sin acceso al router ni DNS central, puedes agregar los proyectos puntuales en el archivo `hosts` de Windows (`C:\Windows\System32\drivers\etc\hosts`):
```text
10.1.0.4    empresa.local
10.1.0.4    prod.empresa.local
10.1.0.4    stg.empresa.local
10.1.0.4    webdev.empresa.local
10.1.0.4    tienda.empresa.local
```

---

## 3. Puesta en marcha en 3 minutos

### Paso 1: Instalación inicial en el servidor Debian
En tu servidor Debian 13 recién instalado, abre una terminal como `root` (o con `sudo`) y ejecuta:

```bash
curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
```

El instalador descargará la plataforma, te preguntará la IP del servidor, tu dominio base deseado y tus credenciales preferidas (o tomará los valores automáticos si solo presionas `Enter`). En pocos minutos todo el sistema quedará desplegado y funcionando.

### Paso 2: Configurar la resolución de nombres
Aplica tu método preferido de la Sección 2 (por ejemplo, la regla comodín en tu Pi-hole o router).

### Paso 3: Conectar tu computadora Windows
1. En tu computadora Windows, abre el navegador y entra a: `https://<IP_DEL_SERVIDOR>` (o a `https://empresa.local`).
2. En la sección de recursos del panel de bienvenida, descarga el archivo:
   - `configurar-desarrollador.bat`.
3. Haz clic derecho sobre el archivo descargado y selecciona **Ejecutar como administrador**.
4. ¡Listo! El script:
   - Inyectará la resolución de los subdominios en el archivo `hosts` de Windows (entornos sin DNS comodín).
   - Instalará el certificado de seguridad para que ningún navegador muestre advertencias rojas.
   - Montará automáticamente la unidad de red **`Z:\`** conectada a la carpeta de proyectos del servidor.
   - Configurará el acceso directo para terminal y Git mediante el comando `ssh web`.

---

## 4. Tu día a día: Cómo crear y publicar sitios web

### Opción A (La más fácil — Directo desde Windows):
1. Abre el Explorador de archivos en tu PC y entra a la unidad **`Z:\`**.
2. Crea una carpeta para tu nuevo proyecto, por ejemplo: `Z:\tienda`.
3. Dentro de ella, crea la subcarpeta `public_html`.
4. Coloca ahí tu archivo `index.php` o los archivos de tu sitio web.
5. Abre tu navegador y escribe: `https://tienda.empresa.local`. El sitio ya estará en vivo y con candado verde.

### Opción B (Desde la terminal del servidor):
Si prefieres usar la consola, el asistente `srvctl` hace todo por ti:
```bash
sudo srvctl project create tienda
```
Esto crea la carpeta, genera un archivo `index.php` de bienvenida con diseño moderno, configura los permisos correctos e inicializa un repositorio Git local.

### Opción C (Desde el Panel Web de Control):
1. Ingresa a `https://empresa.local` desde tu navegador.
2. Haz clic en **Iniciar Sesión Administrativa** e ingresa tus credenciales.
3. En la sección **Crear Nuevo Proyecto**, escribe el nombre y haz clic en **Crear Proyecto**.
4. ¡Listo! El proyecto queda publicado inmediatamente con su URL `https://nombre.empresa.local`.

### ¿Cómo crear una Base de Datos para tu proyecto?
Casi todo sitio web requiere base de datos. Para no perder tiempo creando tablas y usuarios a mano, ejecuta:
```bash
sudo srvctl project db tienda
```
El servidor creará automáticamente:
- La base de datos: `tienda_db`.
- El usuario de base de datos: `tienda_usr`.
- Una contraseña aleatoria y segura de 24 caracteres.
- Un archivo `.env` en `Z:\tienda\public_html\.env` listo para que frameworks como Laravel, WordPress o tus scripts PHP se conecten de inmediato.

Para gestionar tus tablas con interfaz gráfica, ingresa a:
`https://webdev.empresa.local` (phpMyAdmin preconfigurado).

---

## 5. Guía de supervivencia: ¿Qué hacer si algo falla?

### 1. ¿Cómo saber si todo el servidor está sano?
Ejecuta la suite de auto-verificación de 40 puntos:
```bash
sudo srvctl verify
```
El sistema comprobará los servicios (Apache, PHP, MariaDB, Redis, Samba, UFW, Fail2ban), la conectividad HTTPS, los permisos de red y el estado de phpMyAdmin, mostrándote una tabla con resultados `[ OK ]` o `[FAIL]`.

Para una vista rápida del uso de memoria y disco:
```bash
sudo srvctl status
```

### 2. Si cometiste un error editando código y el sitio no carga
Como cada proyecto tiene control de versiones Git local, puedes conectarte vía SSH (`ssh web`) y revertir los cambios:
```bash
cd /var/www/tienda/public_html
git restore .            # Descarta modificaciones no guardadas
git clean -fd            # Elimina archivos temporales o accidentales
```

### 3. Si se dañó la base de datos
El servidor realiza respaldos comprimidos de todas las bases de datos a las 02:00 AM. Para restaurar:
```bash
# 1. Ver qué respaldos existen
ls -lh /var/backups/srvctl/database/

# 2. Restaurar el respaldo que elijas
gunzip -c /var/backups/srvctl/database/db_all_YYYY-MM-DD_HH-MM-SS.sql.gz | sudo mariadb
```

### 4. Si quieres recuperar archivos borrados de los últimos 7 días
El servidor mantiene snapshots rotativos diarios mediante hard-links en `/var/backups/srvctl/snapshots/`:
- `daily.0` es el respaldo de ayer.
- `daily.1` es el de hace 2 días (y así hasta `daily.6`, hace 7 días).

Para restaurar todo el contenido de un proyecto al estado de ayer:
```bash
sudo srvctl backup rollback daily.0
```

### 5. ¿Cómo empezar de cero y limpiar todo?
Si necesitas purgar todos los paquetes, bases de datos y configuraciones para dejar el Debian completamente virgen:
```bash
sudo srvctl reset
```

---

## 6. Referencia rápida de comandos de srvctl

Puedes invocar `srvctl` sin argumentos para abrir el menú interactivo con ventanas azules (Whiptail), o usar los siguientes comandos directos:

| Comando | ¿Qué hace? |
| :--- | :--- |
| `sudo srvctl` | Abre el menú visual interactivo con todas las opciones guiadas. |
| `sudo srvctl status` | Muestra un resumen rápido de salud, uso de RAM, disco y servicios activos. |
| `sudo srvctl verify` | Ejecuta la suite de diagnóstico profundo de 40 puntos. |
| `sudo srvctl project create <nombre>` | Crea la estructura de un nuevo proyecto web con Git y permisos listos. |
| `sudo srvctl project db <nombre>` | Crea la base de datos MariaDB, usuario y genera el archivo `.env`. |
| `sudo srvctl project list` | Lista todos los proyectos web activos detectados en el servidor. |
| `sudo srvctl backup run` | Ejecuta un respaldo manual inmediato de base de datos y archivos. |
| `sudo srvctl backup list` | Muestra la lista de respaldos y snapshots existentes con fecha y tamaño. |
| `sudo srvctl backup rollback [snap]` | Restaura los archivos web al snapshot indicado (por defecto `daily.0`). |
| `sudo srvctl reset` | Limpia y desinstala el stack completo para dejar el Debian virgen. |
| `sudo srvctl deploy` | Lanza el asistente de instalación para desplegar todo el stack. |
