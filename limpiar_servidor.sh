#!/bin/bash
# ==============================================================================
# limpiar_servidor.sh - Wrapper de retrocompatibilidad hacia srvctl reset
# Uso: sudo bash limpiar_servidor.sh
# ==============================================================================
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Este script debe ejecutarse exclusivamente como root." >&2
    exit 1
fi

if [ -x "${SCRIPT_DIR}/bin/srvctl" ]; then
    exec "${SCRIPT_DIR}/bin/srvctl" reset "$@"
elif command -v srvctl >/dev/null 2>&1; then
    exec srvctl reset "$@"
elif [ -x /opt/srvctl/bin/srvctl ]; then
    exec /opt/srvctl/bin/srvctl reset "$@"
else
    echo "[ERROR] No se encontro el binario srvctl." >&2
    exit 1
fi
