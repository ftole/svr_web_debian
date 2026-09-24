#!/bin/bash
# ==============================================================================
# core/logger.sh - Sistema unificado de logging para srvctl
# ==============================================================================
set -eo pipefail

LOG_FILE="${LOG_FILE:-/var/log/srvctl.log}"

log() {
    local ts msg
    ts="$(date '+%Y-%m-%d %H:%M:%S')"
    msg="[${ts}] $*"
    echo "$*"
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "$msg" >> "$LOG_FILE" 2>/dev/null || true
}

log_warn() {
    log "[WARN] $*"
}

log_err() {
    local ts msg
    ts="$(date '+%Y-%m-%d %H:%M:%S')"
    msg="[${ts}] [ERROR] $*"
    echo "[ERROR] $*" >&2
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "$msg" >> "$LOG_FILE" 2>/dev/null || true
}

die() {
    log_err "$*"
    exit 1
}
