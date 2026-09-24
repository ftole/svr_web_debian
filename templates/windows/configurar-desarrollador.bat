@echo off
:: ==============================================================================
:: configurar-desarrollador.bat - Configuracion completa para Desarrolladores Windows
:: Mapeo de unidad Z: + Confianza SSL + EnableLinkedConnections + SSH
:: Ejecutar como ADMINISTRADOR en Windows
:: ==============================================================================
title Configuracion de Desarrollador

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script debe ejecutarse como Administrador.
    echo Clic derecho -> Ejecutar como administrador.
    pause
    exit /b 1
)

set "SERVER_IP=__SERVER_IP__"
set "ADMIN_USER=__ADMIN_USER__"
set "ADMIN_PASS=__ADMIN_PASS__"
set "SHARE=\\%SERVER_IP%\proyectos"
set "CERT_TMP=%TEMP%\rootCA.crt"

echo [1/4] Instalando Certificado SSL Raiz...
powershell -Command "[Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}; Invoke-WebRequest -Uri 'https://%SERVER_IP%/downloads/rootCA.crt' -OutFile '%CERT_TMP%'" >nul 2>&1
if exist "%CERT_TMP%" (
    certutil -addstore -f "Root" "%CERT_TMP%" >nul
    del "%CERT_TMP%" >nul 2>&1
    echo       [OK] Certificado instalado.
) else (
    echo       [WARN] No se pudo descargar el certificado automaticamente.
)

echo [2/4] Habilitando visibilidad de unidades mapeadas (EnableLinkedConnections)...
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v EnableLinkedConnections /t REG_DWORD /d 1 /f >nul
echo       [OK] Registro actualizado.

echo [3/4] Mapeando unidad Z: hacia %SHARE%...
net use Z: /delete /y >nul 2>&1
net use Z: "%SHARE%" /user:%ADMIN_USER% %ADMIN_PASS% /persistent:yes >nul
if %errorLevel% equ 0 (
    echo       [OK] Unidad Z: montada exitosamente.
) else (
    echo       [WARN] Fallo al montar unidad Z:. Verifica conexion con el servidor.
)

echo [4/4] Configurando llave SSH y alias 'web'...
powershell -Command "if (!(Test-Path \"$env:USERPROFILE\.ssh\id_ed25519_web\")) { ssh-keygen -t ed25519 -f \"$env:USERPROFILE\.ssh\id_ed25519_web\" -N '\"\"' }; $entry = \"`nHost web`n    HostName %SERVER_IP%`n    User %ADMIN_USER%`n    IdentityFile ~/.ssh/id_ed25519_web`n    ServerAliveInterval 60`n\"; Add-Content -Path \"$env:USERPROFILE\.ssh\config\" -Value $entry -Force"
echo       [OK] Alias SSH configurado (usa 'ssh web' en terminal).

echo.
echo ==============================================================================
echo [LISTO] Entorno de desarrollo configurado:
echo  - Unidad Z: -> %SHARE% (Tus proyectos web viven aqui)
echo  - Acceso SSH / Git -> ssh web
echo  - Certificado SSL -> Confiable y activo
echo ==============================================================================
echo.
pause
