@echo off
setlocal EnableDelayedExpansion
:: ==============================================================================
:: configurar-cliente.bat - Resolucion de nombres (hosts) y confianza SSL
:: Ejecutar como ADMINISTRADOR en Windows
:: ==============================================================================
title Configuracion de Cliente Web

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script debe ejecutarse como Administrador.
    echo Clic derecho -^> Ejecutar como administrador.
    pause
    exit /b 1
)

set "SERVER_IP=__SERVER_IP__"
set "HOSTS=%SystemRoot%\System32\drivers\etc\hosts"
set "CERT_UNC=\\%SERVER_IP%\proyectos\_dashboard\downloads\rootCA.crt"
set "CERT_TMP=%TEMP%\rootCA.crt"

echo ==============================================================================
echo    CONFIGURACION DE CLIENTE - %SERVER_IP%
echo ==============================================================================
echo.
echo [1/3] Inyectando resolucion de nombres en el archivo hosts...
:: __HOSTS_CALLS__
echo.
echo [2/3] Obteniendo Certificado SSL Raiz...
if exist "%CERT_TMP%" del "%CERT_TMP%" >nul 2>&1

where curl.exe >nul 2>&1
if %errorLevel% equ 0 (
    curl.exe -k -s -f "https://%SERVER_IP%/downloads/rootCA.crt" -o "%CERT_TMP%" >nul 2>&1
)

if not exist "%CERT_TMP%" (
    echo       Descargando certificado via HTTPS [PowerShell]...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; [Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}; Invoke-WebRequest -Uri 'https://%SERVER_IP%/downloads/rootCA.crt' -OutFile '%CERT_TMP%' -UseBasicParsing" <nul >nul 2>&1
)

if not exist "%CERT_TMP%" (
    if exist "%CERT_UNC%" (
        echo       Copiando certificado desde recurso de red...
        copy /Y "%CERT_UNC%" "%CERT_TMP%" >nul 2>&1
    )
)

if not exist "%CERT_TMP%" (
    echo [ERROR] No se pudo obtener el certificado rootCA.crt.
    echo Verifica la conexion con %SERVER_IP% en el puerto 443 [HTTPS].
    pause
    exit /b 1
)

echo [3/3] Instalando Certificado Raiz en Windows [Entidades de Confianza]...
certutil -addstore -f "Root" "%CERT_TMP%" >nul
set "CERT_STATUS=!errorlevel!"
del "%CERT_TMP%" >nul 2>&1

echo.
echo ==============================================================================
if "!CERT_STATUS!"=="0" (
    echo [OK] Certificado Raiz instalado con exito.
    echo Navegacion HTTPS 100%% segura y sin alertas activada.
) else (
    echo [WARN] certutil finalizo con codigo !CERT_STATUS!. Verifica permisos de administrador.
)
echo ==============================================================================
echo.
pause
exit /b

:add_host
attrib -r "%HOSTS%" >nul 2>&1
findstr /i /c:"%SERVER_IP% %~1" "%HOSTS%" >nul 2>&1
if errorlevel 1 (
    >>"%HOSTS%" echo %SERVER_IP% %~1
    echo       [OK] hosts: %~1 -^> %SERVER_IP%
) else (
    echo       [INFO] hosts ya contiene %~1
)
exit /b
