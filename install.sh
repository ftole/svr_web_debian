#!/usr/bin/env bash
# ==============================================================================
# install.sh - Bootstrap de instalacion para la plataforma srvctl en Debian 13
# ------------------------------------------------------------------------------
# Se ejecuta con "curl | sudo bash" (o descargando y revisando antes).
# Descarga e instala el gestor en /opt/srvctl y enlaza /usr/local/bin/srvctl.
#
# Uso:
#   curl -fsSL https://raw.githubusercontent.com/ftole/svr_web_debian/main/install.sh | sudo bash
# ==============================================================================
set -euo pipefail

REPO_USER="ftole"
REPO_NAME="svr_web_debian"
BRANCH="main"
TAR_URL="https://github.com/${REPO_USER}/${REPO_NAME}/archive/refs/heads/${BRANCH}.tar.gz"

DEST_DIR="/opt/srvctl"
BIN_LINK="/usr/local/bin/srvctl"

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Debes ejecutar este bootstrap como root (sudo)." >&2
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "[ERROR] Se requiere 'curl' para descargar los paquetes." >&2
    exit 1
fi

if ! command -v tar >/dev/null 2>&1; then
    echo "[ERROR] Se requiere 'tar' para desempaquetar los componentes." >&2
    exit 1
fi

echo "[deploy] Descargando plataforma srvctl (${BRANCH})..."
mkdir -p "$DEST_DIR"
curl -fsSL "$TAR_URL" | tar -xz --strip-components=1 -C "$DEST_DIR"

chmod +x "${DEST_DIR}/bin/srvctl"
find "${DEST_DIR}/modules" -type f -name "*.sh" -exec chmod +x {} + 2>/dev/null || true
find "${DEST_DIR}/core" -type f -name "*.sh" -exec chmod +x {} + 2>/dev/null || true

ln -sf "${DEST_DIR}/bin/srvctl" "$BIN_LINK"

# Enlaces en /root para retrocompatibilidad
for s in asistente_servidor.sh verificar_servidor.sh limpiar_servidor.sh; do
    if [ -f "${DEST_DIR}/${s}" ]; then
        ln -sf "${DEST_DIR}/${s}" "/root/${s}"
    fi
done

echo "[deploy] Plataforma instalada en ${DEST_DIR}."
echo "[deploy] Comando global disponible: srvctl"
echo ""

if [ -t 0 ]; then
    exec "$BIN_LINK" deploy
elif exec 3< /dev/tty 2>/dev/null; then
    exec 3<&-
    exec "$BIN_LINK" deploy < /dev/tty
else
    exec "$BIN_LINK" deploy
fi
