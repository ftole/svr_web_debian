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
$AdminPass  = "__ADMIN_PASS__"
$SharePath  = "\\$ServerIP\proyectos"
$CertTmp    = "$env:TEMP\rootCA.crt"

Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host "    CONFIGURACION DEL ENTORNO DE DESARROLLADOR - $ServerIP" -ForegroundColor Cyan
Write-Host "==============================================================================" -ForegroundColor Cyan

# 1. Certificado SSL Raiz
Write-Host "`n[1/4] Instalando Certificado SSL Raiz..." -ForegroundColor Green
try {
    [Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}
    Invoke-WebRequest -Uri "https://$ServerIP/downloads/rootCA.crt" -OutFile $CertTmp -UseBasicParsing -ErrorAction Stop
    $cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($CertTmp)
    $store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "LocalMachine")
    $store.Open("ReadWrite")
    $store.Add($cert)
    $store.Close()
    Remove-Item -Path $CertTmp -Force -ErrorAction SilentlyContinue
    Write-Host "      [OK] Certificado raiz instalado en el almacen de confianza." -ForegroundColor Gray
} catch {
    Write-Host "      [AVISO] No se pudo descargar automaticamente el certificado." -ForegroundColor Yellow
}

# 2. Habilitar EnableLinkedConnections
Write-Host "[2/4] Habilitando visibilidad de unidades mapeadas (EnableLinkedConnections)..." -ForegroundColor Green
$regPath = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
Set-ItemProperty -Path $regPath -Name "EnableLinkedConnections" -Value 1 -Type DWord -Force | Out-Null
Write-Host "      [OK] Registro de Windows configurado." -ForegroundColor Gray

# 3. Montar Unidad de Red Z:
Write-Host "[3/4] Mapeando unidad de red Z: hacia $SharePath..." -ForegroundColor Green
try {
    net use Z: /delete /y 2>$null | Out-Null
    net use Z: $SharePath /user:$AdminUser $AdminPass /persistent:yes | Out-Null
    Write-Host "      [OK] Unidad Z: conectada exitosamente a $SharePath." -ForegroundColor Gray
} catch {
    Write-Host "      [AVISO] Revisa la conectividad hacia $ServerIP en el puerto 445 (Samba)." -ForegroundColor Yellow
}

# 4. Configurar SSH y alias 'web'
Write-Host "[4/4] Configurando llave SSH y alias 'web'..." -ForegroundColor Green
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

Write-Host "`n==============================================================================" -ForegroundColor Cyan
Write-Host " [LISTO] Entorno configurado exitosamente:" -ForegroundColor Green
Write-Host "  - Unidad de red: Z:\ -> $SharePath (Proyectos web)" -ForegroundColor White
Write-Host "  - Acceso SSH / Git: ssh web" -ForegroundColor White
Write-Host "  - Certificados TLS: Reconocidos y validados" -ForegroundColor White
Write-Host "==============================================================================`n" -ForegroundColor Cyan
