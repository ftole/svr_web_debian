#!/usr/bin/env bash
# ==============================================================================
# install.sh - Bootstrap de despliegue del asistente de servidor web Debian 13
# ------------------------------------------------------------------------------
# Se ejecuta con "curl | sudo bash" (o descargando y revisando antes).
# Descarga el repositorio completo a /root/svr_web_debian y lanza el asistente.
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
TAR_URL="https://github.com/${REPO_USER}/${REPO_NAME}/archive/refs/heads/${BRANCH}.tar.gz"

DEST_REPO="/root/svr_web_debian"
DEST_ROOT="/root"

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Debes ejecutar este bootstrap como root (sudo)." >&2
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "[ERROR] Se requiere 'curl' para descargar los paquetes." >&2
    exit 1
fi

if ! command -v tar >/dev/null 2>&1; then
    echo "[ERROR] Se requiere 'tar' para desempaquetar los scripts." >&2
    exit 1
fi

echo "[deploy] Descargando repositorio ${REPO_USER}/${REPO_NAME} (${BRANCH})..."
mkdir -p "$DEST_REPO"
curl -fsSL "$TAR_URL" | tar -xz --strip-components=1 -C "$DEST_REPO"

chmod +x "${DEST_REPO}"/*.sh 2>/dev/null || true
find "${DEST_REPO}/scripts" -type f -name "*.sh" -exec chmod +x {} + 2>/dev/null || true

# Enlaces en /root para retrocompatibilidad
for s in asistente_servidor.sh verificar_servidor.sh limpiar_servidor.sh; do
    ln -sf "${DEST_REPO}/${s}" "${DEST_ROOT}/${s}"
done

echo "[deploy] Repositorio preparado en ${DEST_REPO}."
echo "[deploy] Iniciando asistente de despliegue..."
echo ""

# Forzar prompts interactivos aunque se ejecute con "curl | sudo bash".
# El pipe deja stdin sin TTY; si existe una terminal real (/dev/tty) se usa
# para leer los prompts. Si no la hay, el asistente cae a modo no interactivo.
if [ -t 0 ]; then
    exec bash "${DEST_ROOT}/asistente_servidor.sh"
elif exec 3< /dev/tty 2>/dev/null; then
    exec 3<&-
    exec bash "${DEST_ROOT}/asistente_servidor.sh" < /dev/tty
else
    exec bash "${DEST_ROOT}/asistente_servidor.sh"
fi
