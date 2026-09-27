---
name: script-hardener-shell
description: >-
  Estándares y patrones de programación defensiva para scripts Bash en srvctl (Debian 13).
  Garantiza scripts a prueba de caídas, condiciones de carrera, errores silenciosos,
  archivos truncados y estados inconsistentes en el sistema operativo.
---

# Guía de Bastionado y Programación Defensiva en Shell (srvctl)

Este documento define las reglas de codificación obligatorias para todo script en `bin/`, `core/`, `modules/` y utilidades de automatización en Debian 13.

## 1. Modo Estricto y Control de Ejecución
- **Scripts Standalone, Instalador y Wrappers de Seguridad (`install.sh`, `srvctl-web-wrapper`):**
  ```bash
  #!/bin/bash
  set -euo pipefail
  ```
- **Orquestadores Modulares y Módulos de Pila (`bin/srvctl`, `modules/*/*.sh`):**
  ```bash
  #!/bin/bash
  set -eo pipefail
  ```
  *(Permite la carga dinámica de configuración, herencia de variables opcionales y evaluación controlada sin abortos imprevistos de shell).*
- `-e`: Aborta de inmediato si cualquier comando retorna un código de salida distinto de 0 (salvo en condicionales).
- `-u`: Trata variables no definidas como un error fatal en scripts independientes.
- `-o pipefail`: Propaga el código de error en tuberías (`cmd1 | cmd2`); si `cmd1` falla, la tubería completa falla.

## 2. Trampas y Limpieza Garantizada (Cleanup Traps)
Cualquier script que genere archivos temporales o adquiera bloqueos debe registrar una trampa:
```bash
TMP_DIR="$(mktemp -d -t srvctl.XXXXXX)"
cleanup() {
    rm -rf "${TMP_DIR}"
}
trap cleanup EXIT INT TERM
```

## 3. Pre-vuelos Obligatorios de Configuración (Config-Test First)
Queda terminantemente prohibido reiniciar o recargar servicios de red sin verificar la sintaxis de configuración primero:
- **Apache 2.4:**
  ```bash
  if ! apache2ctl configtest >/dev/null 2>&1; then
      die "Error de sintaxis en configuracion de Apache. Servicio NO reiniciado."
  fi
  systemctl reload apache2
  ```
- **PHP-FPM:**
  ```bash
  if ! "php-fpm${PHP_VER}" -t >/dev/null 2>&1; then
      die "Error de sintaxis en configuracion de PHP-FPM. Servicio NO reiniciado."
  fi
  systemctl reload "php${PHP_VER}-fpm"
  ```

## 4. Escrituras Atómicas e Inmutabilidad
Nunca redirigir directamente sobre un archivo de configuración crítico en producción (`> /etc/...`). Si el proceso muere a mitad de la operación, el archivo queda en 0 bytes.
- **Patrón atómico:**
  ```bash
  local tmp_file
  tmp_file="$(mktemp)"
  generate_config > "${tmp_file}"
  install -m 644 "${tmp_file}" "/etc/destino.conf"
  rm -f "${tmp_file}"
  ```

## 5. Prevención de Concurrencia con `flock`
Las operaciones de larga duración (actualizaciones `apt`, volcados de bases de datos, copias de seguridad `rsync` y optimizaciones de tablas) deben usar bloqueo exclusivo de kernel:
```bash
LOCK_FILE="/run/lock/srvctl-${OP_NAME}.lock"
exec 200>"${LOCK_FILE}"
if ! flock -n 200; then
    die "La operacion '${OP_NAME}' ya se encuentra en ejecucion por otro proceso."
fi
```

## 6. Validación de Argumentos y Rutas
- Toda variable recibida del exterior debe verificarse contra listas blancas antes de usarse.
- Jamás concatenar argumentos de usuario en subshells arbitrarias (`eval` queda estrictamente prohibido).
- Para rutas de archivo, rechazar `..` o componentes que intenten escapar del DocumentRoot o directorio de backups.
