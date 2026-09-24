#!/bin/bash
# ==============================================================================
# limpiar_servidor.sh - Retorna Debian 13 al estado base limpio
# Uso: sudo bash limpiar_servidor.sh
# Conserva la sesion/usuario actual y su acceso SSH. NO borra el usuario 'web'.
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLEANUP_DIR="${SCRIPT_DIR}/scripts/cleanup"
if [ ! -d "$CLEANUP_DIR" ] && [ -d "/root/svr_web_debian/scripts/cleanup" ]; then
    CLEANUP_DIR="/root/svr_web_debian/scripts/cleanup"
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Este script debe ejecutarse como root." >&2
    exit 1
fi

echo "=============================================================================="
echo "          INICIANDO LIMPIEZA COMPLETA DEL SERVIDOR WEB DEBIAN 13             "
echo "=============================================================================="

bash "${CLEANUP_DIR}/01_detener_servicios.sh"
bash "${CLEANUP_DIR}/02_purgar_paquetes.sh"
bash "${CLEANUP_DIR}/03_eliminar_residuales.sh"
bash "${CLEANUP_DIR}/04_restaurar_sistema.sh"
bash "${CLEANUP_DIR}/05_eliminar_usuarios.sh"

echo "=============================================================================="
echo "          === SISTEMA RESTAURADO AL ESTADO BASE LIMPIO ==="
echo "=============================================================================="
