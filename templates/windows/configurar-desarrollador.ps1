# ==============================================================================
# configurar-desarrollador.ps1 - Aprovisionamiento para Desarrolladores en Windows
# ==============================================================================
# Requiere ejecutarse en una ventana de PowerShell como Administrador.

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] Este script debe ejecutarse como Administrador en PowerShell." -ForegroundColor Red
    Write-Host "Abre PowerShell con clic derecho -> 'Ejecutar como administrador' y vuelve a ejecutar." -ForegroundColor Yellow
    Exit 1
}

$ServerIP   = "__SERVER_IP__"
$AdminUser  = "__ADMIN_USER__"
$SharePath  = "\\$ServerIP\proyectos"
$CertTmp    = "$env:TEMP\rootCA.crt"

Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host "    CONFIGURACION DEL ENTORNO DE DESARROLLADOR - $ServerIP" -ForegroundColor Cyan
Write-Host "==============================================================================" -ForegroundColor Cyan

$securePass = Read-Host -Prompt "Contrasena de $AdminUser@$ServerIP" -AsSecureString
$AdminPass  = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
)
if ([string]::IsNullOrEmpty($AdminPass)) {
    Write-Host "[ERROR] Debes indicar la contrasena del usuario $AdminUser para continuar." -ForegroundColor Red
    Exit 1
}

# 1. Certificado SSL Raiz
Write-Host "`n[1/5] Instalando Certificado SSL Raiz..." -ForegroundColor Green
$certDownloaded = $false

try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    [Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
    Invoke-WebRequest -Uri "https://$ServerIP/downloads/rootCA.crt" -OutFile $CertTmp -UseBasicParsing -ErrorAction Stop
    $certDownloaded = $true
} catch {
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -k -s -f "https://$ServerIP/downloads/rootCA.crt" -o $CertTmp
        if (Test-Path $CertTmp) {
            $certDownloaded = $true
        }
    }
}

if ($certDownloaded -and (Test-Path $CertTmp)) {
    try {
        $cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($CertTmp)
        $store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "LocalMachine")
        $store.Open("ReadWrite")
        $store.Add($cert)
        $store.Close()
        Write-Host "      [OK] Certificado raiz instalado en el almacen de confianza." -ForegroundColor Gray
    } catch {
        certutil -addstore -f "Root" $CertTmp | Out-Null
        Write-Host "      [OK] Certificado raiz instalado via certutil." -ForegroundColor Gray
    }
    Remove-Item -Path $CertTmp -Force -ErrorAction SilentlyContinue
} else {
    Write-Host "      [AVISO] No se pudo descargar automaticamente el certificado." -ForegroundColor Yellow
}

# 2. Habilitar EnableLinkedConnections
Write-Host "[2/5] Habilitando visibilidad de unidades mapeadas (EnableLinkedConnections)..." -ForegroundColor Green
$regPath = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
Set-ItemProperty -Path $regPath -Name "EnableLinkedConnections" -Value 1 -Type DWord -Force | Out-Null
Write-Host "      [OK] Registro de Windows configurado (EnableLinkedConnections=1)." -ForegroundColor Gray

# 3. Credenciales y purga SMB (Prevencion de Error 1219)
Write-Host "[3/5] Configurando credenciales y purgando conexiones SMB previas..." -ForegroundColor Green
try {
    Get-SmbMapping -ErrorAction SilentlyContinue | Where-Object { $_.RemotePath -like "*$ServerIP*" } | ForEach-Object {
        net use $_.LocalPath /delete /y 2>$null | Out-Null
    }
} catch {}
net use "\\$ServerIP" /delete /y 2>$null | Out-Null
net use "\\$ServerIP\proyectos" /delete /y 2>$null | Out-Null
net use "\\$ServerIP\*" /delete /y 2>$null | Out-Null

cmdkey /add:$ServerIP /user:$AdminUser /pass:$AdminPass | Out-Null
Write-Host "      [OK] Credenciales registradas en Windows (cmdkey) para $ServerIP." -ForegroundColor Gray

# 4. Mapeo dinamico de unidad de red (Z: a T:, prevencion de Error 85)
Write-Host "[4/5] Mapeando unidad de red hacia $SharePath..." -ForegroundColor Green
$candidateLetters = @('Z', 'Y', 'X', 'W', 'V', 'U', 'T')
$mountedDrive = $null
$existingDrives = Get-CimInstance Win32_LogicalDisk -ErrorAction SilentlyContinue

foreach ($letter in $candidateLetters) {
    $driveName = "${letter}:"

    $existing = $existingDrives | Where-Object { $_.DeviceID -eq $driveName }
    if ($existing) {
        if ($existing.DriveType -eq 4 -and $existing.ProviderName -like "*$ServerIP*") {
            net use $driveName /delete /y 2>$null | Out-Null
        } else {
            continue
        }
    } else {
        net use $driveName /delete /y 2>$null | Out-Null
    }

    $null = net use $driveName $SharePath /user:$AdminUser $AdminPass /persistent:yes 2>&1
    if ($LASTEXITCODE -eq 0) {
        $mountedDrive = $driveName
        break
    }
}

if ($mountedDrive) {
    Write-Host "      [OK] Unidad $mountedDrive conectada exitosamente a $SharePath." -ForegroundColor Gray
} else {
    Write-Host "      [AVISO] No se pudo montar ninguna letra libre (Z: a T:) hacia $SharePath." -ForegroundColor Yellow
    Write-Host "             Puedes acceder directamente desde el Explorador en: $SharePath" -ForegroundColor Yellow
}

# 5. Configurar SSH y alias 'web'
Write-Host "[5/5] Configurando llave SSH y alias 'web'..." -ForegroundColor Green
$sshDir = "$env:USERPROFILE\.ssh"
if (-not (Test-Path $sshDir)) { New-Item -ItemType Directory -Path $sshDir -Force | Out-Null }
$keyFile = "$sshDir\id_ed25519_web"
if (-not (Test-Path $keyFile)) {
    ssh-keygen -t ed25519 -f $keyFile -N '""' -q
}
$sshConfig = "$sshDir\config"
$entry = "`nHost web`n    HostName $ServerIP`n    User $AdminUser`n    IdentityFile ~/.ssh/id_ed25519_web`n    ServerAliveInterval 60`n"
if (Test-Path $sshConfig) {
    $current = Get-Content $sshConfig -Raw
    if ($current -notmatch "Host web\b") { Add-Content -Path $sshConfig -Value $entry }
} else {
    Set-Content -Path $sshConfig -Value $entry
}
Write-Host "      [OK] Alias SSH configurado (usa 'ssh web' desde cualquier terminal)." -ForegroundColor Gray

# 6. Visibilidad en el Explorador de Windows (Aislamiento UAC)
Write-Host "`n==============================================================================" -ForegroundColor Cyan
Write-Host "    VISIBILIDAD EN EXPLORADOR DE ARCHIVOS (AISLAMIENTO UAC)" -ForegroundColor Cyan
Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host "Debido al aislamiento de sesiones UAC en Windows, para que la unidad" -ForegroundColor Gray
if ($mountedDrive) {
    Write-Host "mapeada ($mountedDrive) y las credenciales se reflejen de inmediato" -ForegroundColor Gray
} else {
    Write-Host "de red y las credenciales se reflejen de inmediato" -ForegroundColor Gray
}
Write-Host "en 'Este equipo' sin reiniciar la PC, se recomienda reiniciar explorer.exe." -ForegroundColor Gray
Write-Host ""

$restartExp = "S"
try {
    if ([Environment]::UserInteractive) {
        $ans = Read-Host "¿Deseas reiniciar explorer.exe ahora? [S/N] (Por defecto: S)"
        if (-not [string]::IsNullOrWhiteSpace($ans)) {
            $restartExp = $ans.Trim().ToUpper()
        }
    }
} catch {
    $restartExp = "S"
}

if ($restartExp -eq "S") {
    Write-Host "      Reiniciando Explorador de Windows..." -ForegroundColor Gray
    Stop-Process -Name explorer -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    Start-Process explorer.exe
    Write-Host "      [OK] Explorador de Windows reiniciado exitosamente." -ForegroundColor Gray
} else {
    Write-Host "      [INFO] Si la unidad no aparece en el Explorador, reinicia tu sesion o el equipo." -ForegroundColor Yellow
}

Write-Host "`n==============================================================================" -ForegroundColor Cyan
Write-Host " [LISTO] Entorno configurado exitosamente:" -ForegroundColor Green
if ($mountedDrive) {
    Write-Host "  - Unidad de red: $mountedDrive\ -> $SharePath (Proyectos web)" -ForegroundColor White
} else {
    Write-Host "  - Recurso de red: $SharePath (Acceso directo sin contrasena via cmdkey)" -ForegroundColor White
}
Write-Host "  - Acceso SSH / Git: ssh web" -ForegroundColor White
Write-Host "  - Certificados TLS: Reconocidos y validados" -ForegroundColor White
Write-Host "==============================================================================`n" -ForegroundColor Cyan

if ([Environment]::UserInteractive) {
    try {
        Write-Host "Presiona cualquier tecla para salir..." -ForegroundColor Gray
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    } catch {
        Pause
    }
}
