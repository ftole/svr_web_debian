#!/bin/bash
set -euo pipefail

echo "=========================================================="
echo "PRUEBA 3: CREACION Y ROLLBACK AISLADO DE PROYECTO"
echo "=========================================================="

PROJ="testproj"
PROJ_DIR="/var/www/${PROJ}"
SNAP_DIR="/var/backups/srvctl/snapshots/daily.0/${PROJ}"

# 0. Limpieza previa si existiera
if [ -d "$PROJ_DIR" ]; then
    rm -rf "$PROJ_DIR"
fi
if [ -d "$SNAP_DIR" ]; then
    rm -rf "$SNAP_DIR"
fi

# 1. Crear proyecto de prueba con el wrapper como www-data
echo "[PASO 1] Creando proyecto '${PROJ}' mediante el wrapper..."
out=$(sudo -u www-data env SRVCTL_CMD="project create ${PROJ}" sudo /usr/local/bin/srvctl-web-wrapper 2>&1)
echo "$out"

if [ -d "${PROJ_DIR}/public_html" ] && [ -f "${PROJ_DIR}/public_html/index.php" ]; then
    echo "  [PASS] Proyecto '${PROJ}' creado exitosamente en ${PROJ_DIR}."
else
    echo "  [FAIL] Directorio o index.php de ${PROJ} no encontrado."
    exit 1
fi

# Guardar contenido original generado
ORIGINAL_CONTENT=$(cat "${PROJ_DIR}/public_html/index.php")

# 2. Simular snapshot daily.0 para testproj
echo "[PASO 2] Preparando snapshot daily.0 para '${PROJ}'..."
mkdir -p "$SNAP_DIR/public_html"
cp "${PROJ_DIR}/public_html/index.php" "${SNAP_DIR}/public_html/index.php"
echo "SNAPSHOT_MARKER_ORIGINAL" > "${SNAP_DIR}/snapshot_marker.txt"

# Modificar el proyecto en /var/www/testproj
echo "MODIFICADO_DURANTE_PRUEBA" > "${PROJ_DIR}/public_html/index.php"
echo "ARCHIVO_NUEVO_A_ELIMINAR" > "${PROJ_DIR}/temp_dirty.txt"

# Guardar estado de otro proyecto para probar aislamiento (ej. /var/www/prod)
PROD_MOD_TIME=$(stat -c %Y /var/www/prod 2>/dev/null || echo "0")

# 3. Ejecutar rollback aislado del proyecto mediante el wrapper
echo "[PASO 3] Ejecutando 'backup rollback-project ${PROJ} daily.0' mediante wrapper..."
out=$(sudo -u www-data env SRVCTL_CMD="backup rollback-project ${PROJ} daily.0" sudo /usr/local/bin/srvctl-web-wrapper 2>&1)
echo "$out"

# Verificaciones de aislamiento y restauración:
echo "[PASO 4] Verificando restauración y aislamiento..."

# 4a. Verificar que index.php fue restaurado
CURRENT_CONTENT=$(cat "${PROJ_DIR}/public_html/index.php")
if [ "$CURRENT_CONTENT" = "$ORIGINAL_CONTENT" ]; then
    echo "  [PASS] index.php restaurado correctamente al estado del snapshot."
else
    echo "  [FAIL] index.php no coincide con el snapshot original."
    exit 1
fi

# 4b. Verificar que temp_dirty.txt fue eliminado por rsync --delete
if [ ! -f "${PROJ_DIR}/temp_dirty.txt" ]; then
    echo "  [PASS] Archivos no presentes en snapshot fueron purgados (--delete aislado)."
else
    echo "  [FAIL] temp_dirty.txt todavia existe tras rollback."
    exit 1
fi

# 4c. Verificar que el archivo del snapshot existe en el proyecto restaurado
if [ -f "${PROJ_DIR}/snapshot_marker.txt" ]; then
    echo "  [PASS] snapshot_marker.txt restaurado en ${PROJ_DIR}."
else
    echo "  [FAIL] snapshot_marker.txt no fue restaurado."
    exit 1
fi

# 4d. Verificar que otros directorios (/var/www/prod) no sufrieron alteración
CURRENT_PROD_MOD=$(stat -c %Y /var/www/prod 2>/dev/null || echo "0")
if [ "$CURRENT_PROD_MOD" = "$PROD_MOD_TIME" ]; then
    echo "  [PASS] Aislamiento garantizado: /var/www/prod no fue alterado."
else
    echo "  [WARN] /var/www/prod cambio su timestamp."
fi

# 5. Eliminar proyecto de prueba mediante el wrapper
echo "[PASO 5] Eliminando proyecto '${PROJ}' mediante el wrapper..."
out=$(sudo -u www-data env SRVCTL_CMD="project delete ${PROJ}" sudo /usr/local/bin/srvctl-web-wrapper 2>&1)
echo "$out"

if [ ! -d "$PROJ_DIR" ]; then
    echo "  [PASS] Directorio ${PROJ_DIR} eliminado correctamente."
else
    echo "  [FAIL] ${PROJ_DIR} aun existe tras delete."
    exit 1
fi

# Limpieza del snapshot temporal creado para la prueba
rm -rf "$SNAP_DIR"

echo "=========================================================="
echo "RESULTADO: PRUEBA DE CREACION Y ROLLBACK AISLADO COMPLETADA CON EXITO [PASS]"
echo "=========================================================="
