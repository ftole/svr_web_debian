#!/usr/bin/env bash
# ==============================================================================
# install.sh - Bootstrap de despliegue del asistente de servidor web Debian 13
# ------------------------------------------------------------------------------
# Se ejecuta con "curl | sudo bash" (o descargando y revisando antes).
# Descarga los scripts del repositorio y lanza el asistente.
#
# Uso principal (interactivo incluso con pipe: los prompts se leen de /dev/tty):
#   curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
#
# Uso descargando y revisando antes (recomendado en produccion):
#   curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh -o /tmp/install.sh
#   less /tmp/install.sh && sudo bash /tmp/install.sh
#
# Uso no interactivo (valores por defecto o variables de entorno):
#   curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo ASISTENTE_NONINTERACTIVE=1 bash
#   sudo ASISTENTE_NONINTERACTIVE=1 SERVER_IP=10.0.0.10 BASE_DOMAIN=miempresa.local \
#        ADMIN_USER=adminweb ADMIN_PASS='MiClaveSegura#' bash /tmp/install.sh
# ==============================================================================
set -euo pipefail

REPO_USER="ftole"
REPO_NAME="svr_web_debian"
BRANCH="main"
BASE_URL="https://raw.githubusercontent.com/${REPO_USER}/${REPO_NAME}/${BRANCH}"

DEST="/root"
SCRIPTS=(asistente_servidor.sh verificar_servidor.sh limpiar_servidor.sh)

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Debes ejecutar este bootstrap como root (sudo)." >&2
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "[ERROR] Se requiere 'curl' para descargar los scripts." >&2
    exit 1
fi

mkdir -p "$DEST"

for f in "${SCRIPTS[@]}"; do
    echo "[deploy] Descargando ${f} ..."
    curl -fsSL "${BASE_URL}/${f}" -o "${DEST}/${f}"
    chmod +x "${DEST}/${f}"
done

echo "[deploy] Scripts descargados en ${DEST}."
echo "[deploy] Iniciando asistente de despliegue..."
echo ""

# Forzar prompts interactivos aunque se ejecute con "curl | sudo bash".
# El pipe deja stdin sin TTY; si existe una terminal real (/dev/tty) se usa
# para leer los prompts. Si no la hay, el asistente cae a modo no interactivo.
if [ -t 0 ]; then
    exec bash "${DEST}/asistente_servidor.sh"
elif exec 3< /dev/tty 2>/dev/null; then
    exec 3<&-
    exec bash "${DEST}/asistente_servidor.sh" < /dev/tty
else
    exec bash "${DEST}/asistente_servidor.sh"
fi
