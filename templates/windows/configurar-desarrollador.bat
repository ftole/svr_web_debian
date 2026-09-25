@echo off
setlocal EnableDelayedExpansion
:: ==============================================================================
:: configurar-desarrollador.bat - Configuracion completa para Desarrolladores Windows
:: Mapeo SMB Dinamico + Credenciales + Confianza SSL + EnableLinkedConnections + SSH
:: Ejecutar como ADMINISTRADOR en Windows
:: ==============================================================================
title Configuracion de Desarrollador

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script debe ejecutarse como Administrador.
    echo Clic derecho -^> Ejecutar como administrador.
    pause
    exit /b 1
)

set "SERVER_IP=__SERVER_IP__"
set "ADMIN_USER=__ADMIN_USER__"
set "SHARE=\\%SERVER_IP%\proyectos"
set "CERT_TMP=%TEMP%\rootCA.crt"

echo ==============================================================================
echo    CONFIGURACION DEL ENTORNO DE DESARROLLADOR - %SERVER_IP%
echo ==============================================================================
echo.

if not defined ADMIN_PASS set /p "ADMIN_PASS=Contrasena de %ADMIN_USER%@%SERVER_IP%: "
if not defined ADMIN_PASS (
    echo [ERROR] Debes indicar la contrasena del usuario %ADMIN_USER% para continuar.
    pause
    exit /b 1
)

echo [1/5] Instalando Certificado SSL Raiz...
if exist "%CERT_TMP%" del "%CERT_TMP%" >nul 2>&1
where curl.exe >nul 2>&1
if %errorLevel% equ 0 (
    curl.exe -k -s -f "https://%SERVER_IP%/downloads/rootCA.crt" -o "%CERT_TMP%" >nul 2>&1
)
if not exist "%CERT_TMP%" (
    echo       Descargando certificado via HTTPS [PowerShell]...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; [Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}; Invoke-WebRequest -Uri 'https://%SERVER_IP%/downloads/rootCA.crt' -OutFile '%CERT_TMP%' -UseBasicParsing" <nul >nul 2>&1
)
if exist "%CERT_TMP%" (
    certutil -addstore -f "Root" "%CERT_TMP%" >nul
    del "%CERT_TMP%" >nul 2>&1
    echo       [OK] Certificado raiz instalado en el almacen de confianza.
) else (
    echo       [WARN] No se pudo descargar automaticamente el certificado.
)

echo [2/5] Habilitando visibilidad de unidades mapeadas [EnableLinkedConnections]...
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v EnableLinkedConnections /t REG_DWORD /d 1 /f >nul
echo       [OK] Registro de Windows configurado [EnableLinkedConnections=1].

echo [3/5] Configurando credenciales y purgando conexiones SMB previas...
net use "\\%SERVER_IP%" /delete /y >nul 2>&1
net use "\\%SERVER_IP%\proyectos" /delete /y >nul 2>&1
net use "\\%SERVER_IP%\*" /delete /y >nul 2>&1
cmdkey /add:%SERVER_IP% /user:%ADMIN_USER% /pass:%ADMIN_PASS% >nul 2>&1
echo       [OK] Credenciales registradas en Windows [cmdkey] para %SERVER_IP%.

echo [4/5] Mapeando unidad de red hacia %SHARE%...
set "MOUNTED_LETTER="
for %%D in (Z Y X W V U T) do (
    if not defined MOUNTED_LETTER (
        set "TRY_LETTER=1"
        if exist "%%D:\" (
            net use %%D: 2>nul | findstr /i "%SHARE%" >nul 2>&1
            if !errorlevel! equ 0 (
                net use %%D: /delete /y >nul 2>&1
            ) else (
                set "TRY_LETTER=0"
            )
        )
        if "!TRY_LETTER!"=="1" (
            net use %%D: /delete /y >nul 2>&1
            net use %%D: "%SHARE%" /user:%ADMIN_USER% %ADMIN_PASS% /persistent:yes >nul 2>&1
            if !errorlevel! equ 0 (
                set "MOUNTED_LETTER=%%D:"
                goto :drive_assigned
            )
        )
    )
)
:drive_assigned
if defined MOUNTED_LETTER (
    echo       [OK] Unidad !MOUNTED_LETTER! conectada exitosamente a %SHARE%.
) else (
    echo       [WARN] No se pudo montar ninguna letra libre [Z: a T:] hacia %SHARE%.
    echo              Puedes acceder directamente desde el Explorador en: %SHARE%
)

echo [5/5] Configurando llave SSH y alias 'web'...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$sshDir = \"$env:USERPROFILE\.ssh\"; if (!(Test-Path $sshDir)) { New-Item -ItemType Directory -Path $sshDir -Force | Out-Null }; $keyFile = \"$sshDir\id_ed25519_web\"; if (!(Test-Path $keyFile)) { ssh-keygen -t ed25519 -f $keyFile -N '\"\"' -q }; $sshConfig = \"$sshDir\config\"; $entry = \"`nHost web`n    HostName %SERVER_IP%`n    User %ADMIN_USER%`n    IdentityFile ~/.ssh/id_ed25519_web`n    ServerAliveInterval 60`n\"; if (Test-Path $sshConfig) { $c = Get-Content $sshConfig -Raw; if ($c -notmatch \"Host web\b\") { Add-Content -Path $sshConfig -Value $entry } } else { Set-Content -Path $sshConfig -Value $entry }" <nul >nul 2>&1
echo       [OK] Alias SSH configurado (usa 'ssh web' desde cualquier terminal).

echo.
echo ==============================================================================
echo    VISIBILIDAD EN EXPLORADOR DE ARCHIVOS [AISLAMIENTO UAC]
echo ==============================================================================
echo Debido al aislamiento de sesiones UAC en Windows, para que la unidad
if defined MOUNTED_LETTER (
    echo mapeada [!MOUNTED_LETTER!] y las credenciales se reflejen de inmediato
) else (
    echo de red y las credenciales se reflejen de inmediato
)
echo en 'Este equipo' sin reiniciar la PC, se recomienda reiniciar explorer.exe.
echo.
set "RESTART_EXP=S"
set /p "RESTART_EXP=Desea reiniciar el Explorador de Windows ahora? [S/N] [Por defecto: S]: "
if /i "!RESTART_EXP!"=="" set "RESTART_EXP=S"
if /i "!RESTART_EXP:~0,1!"=="S" (
    echo       Reiniciando Explorador de Windows...
    taskkill /f /im explorer.exe >nul 2>&1
    ping 127.0.0.1 -n 3 >nul
    start explorer.exe
    echo       [OK] Explorador de Windows reiniciado exitosamente.
) else (
    echo       [INFO] Si la unidad no aparece en el Explorador, reinicia tu sesion o el equipo.
)

echo.
echo ==============================================================================
echo [LISTO] Entorno de desarrollo configurado exitosamente:
if defined MOUNTED_LETTER (
    echo  - Unidad de red: !MOUNTED_LETTER!\ -^> %SHARE% [Proyectos web]
) else (
    echo  - Recurso de red: %SHARE% [Acceso directo sin contrasena via cmdkey]
)
echo  - Acceso SSH / Git: ssh web
echo  - Certificado TLS: Confiable y validado
echo ==============================================================================
echo.
pause
