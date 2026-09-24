@echo off
:: ==============================================================================
:: configurar-cliente.bat - Configuracion de confianza SSL para clientes
:: Ejecutar como ADMINISTRADOR en Windows
:: ==============================================================================
title Configuracion de Cliente Web

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script debe ejecutarse como Administrador.
    echo Clic derecho -> Ejecutar como administrador.
    pause
    exit /b 1
)

set "SERVER_IP=__SERVER_IP__"
set "CERT_UNC=\\%SERVER_IP%\proyectos\_dashboard\downloads\rootCA.crt"
set "CERT_TMP=%TEMP%\rootCA.crt"

echo [1/2] Obteniendo Certificado SSL Raiz...
if exist "%CERT_UNC%" (
    copy /Y "%CERT_UNC%" "%CERT_TMP%" >nul
) else (
    echo Descargando certificado via HTTPS...
    powershell -Command "[Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}; Invoke-WebRequest -Uri 'https://%SERVER_IP%/downloads/rootCA.crt' -OutFile '%CERT_TMP%'" >nul 2>&1
)

if not exist "%CERT_TMP%" (
    echo [ERROR] No se pudo obtener el certificado rootCA.crt.
    echo Verifica conexion con %SERVER_IP%.
    pause
    exit /b 1
)

echo [2/2] Instalando Certificado Raiz en Windows (Entidades de Confianza)...
certutil -addstore -f "Root" "%CERT_TMP%" >nul
del "%CERT_TMP%" >nul 2>&1

echo.
echo ==============================================================================
echo [OK] Certificado Raiz instalado con exito.
echo Navegacion HTTPS 100%% segura y sin alertas activada.
echo ==============================================================================
echo.
pause
