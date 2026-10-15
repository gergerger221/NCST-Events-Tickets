# ==============================================================================
# NCST Events & Tickets System - LAN HTTPS Setup Engine for XAMPP
# Generates local CA-backed certificates with Subject Alternative Names (SAN)
# for the host LAN IP and configures Apache SSL safely without overwriting defaults.
# ==============================================================================

[CmdletBinding()]
param (
    [string]$LanIp,
    [string[]]$AdditionalSan = @(),
    [switch]$RestartApache,
    [switch]$ForceReissue
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  NCST Events & Tickets - Safe Local HTTPS Generator" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Resolve host LAN IP
if (-not $LanIp) {
    $detected = Get-NetIPAddress -AddressFamily IPv4 | 
        Where-Object { 
            $_.InterfaceAlias -notmatch 'Loopback' -and 
            $_.IPAddress -notmatch '^169\.254\.' -and 
            $_.IPAddress -notmatch '^127\.' 
        } | Select-Object -First 1

    if ($detected) {
        $LanIp = $detected.IPAddress
        Write-Host "[OK] Auto-detected Host LAN IP: $LanIp" -ForegroundColor Green
    } else {
        $LanIp = "127.0.0.1"
        Write-Host "[WARN] Could not auto-detect LAN IP, falling back to 127.0.0.1" -ForegroundColor Yellow
    }
} else {
    Write-Host "[OK] Using specified LAN IP: $LanIp" -ForegroundColor Green
}

# 2. Locate mkcert executable
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
$mkcertCmd = Get-Command mkcert.exe -ErrorAction SilentlyContinue

if (-not $mkcertCmd) {
    $wingetMkcert = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter "mkcert.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($wingetMkcert) {
        $mkcertPath = $wingetMkcert.FullName
    } else {
        throw "mkcert not found. Please run 'winget install FiloSottile.mkcert' to install."
    }
} else {
    $mkcertPath = $mkcertCmd.Source
}

Write-Host "[OK] mkcert found at: $mkcertPath" -ForegroundColor Green

# 3. Locate CA directory & ensure CA files exist
$caRoot = (& $mkcertPath -CAROOT).Trim()
$caKey = Join-Path $caRoot "rootCA-key.pem"
$caCert = Join-Path $caRoot "rootCA.pem"

if (-not (Test-Path $caCert)) {
    Write-Host "Generating new Root CA..." -ForegroundColor Yellow
    & $mkcertPath -install
}

if (-not (Test-Path $caCert)) {
    throw "Root CA certificate not found at $caCert"
}
Write-Host "[OK] Root CA located at: $caCert" -ForegroundColor Green

# 4. Copy Root CA to web-accessible locations for mobile installation
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir

$exportTargets = @(
    (Join-Path $projectRoot "ncst-ca.crt"),
    (Join-Path $projectRoot "dist\ncst-ca.crt"),
    (Join-Path $projectRoot "public\ncst-ca.crt")
)

foreach ($target in $exportTargets) {
    $targetDir = Split-Path -Parent $target
    if (Test-Path $targetDir) {
        Copy-Item -Path $caCert -Destination $target -Force
        Write-Host "[OK] Exported Root CA to: $target" -ForegroundColor Gray
    }
}

# 5. Define target certificate paths in Apache
$apacheConfDir = "C:\xampp\apache\conf"
$sslCrtDir = Join-Path $apacheConfDir "ssl.crt"
$sslKeyDir = Join-Path $apacheConfDir "ssl.key"

if (-not (Test-Path $sslCrtDir)) { New-Item -ItemType Directory -Path $sslCrtDir -Force | Out-Null }
if (-not (Test-Path $sslKeyDir)) { New-Item -ItemType Directory -Path $sslKeyDir -Force | Out-Null }

$serverCrt = Join-Path $sslCrtDir "ncst-server.crt"
$serverKey = Join-Path $sslKeyDir "ncst-server.key"

# 6. Build SAN list
$sanList = @("localhost", "127.0.0.1", "::1", $LanIp)
if ($env:COMPUTERNAME -and -not ($sanList -contains $env:COMPUTERNAME)) {
    $sanList += $env:COMPUTERNAME
}
foreach ($item in $AdditionalSan) {
    if ($item -and -not ($sanList -contains $item)) {
        $sanList += $item
    }
}

Write-Host "Generating server certificate with SANs:" -ForegroundColor Cyan
foreach ($san in $sanList) {
    Write-Host "  - $san" -ForegroundColor Gray
}

# Execute mkcert to issue certificate
& $mkcertPath -cert-file $serverCrt -key-file $serverKey @sanList
if ($LASTEXITCODE -ne 0) {
    throw "mkcert certificate issuance failed with exit code $LASTEXITCODE"
}
Write-Host "[OK] Generated server certificate at: $serverCrt" -ForegroundColor Green
Write-Host "[OK] Generated server private key at: $serverKey" -ForegroundColor Green

# 7. Safely configure Apache SSL
$httpdSslConf = "C:\xampp\apache\conf\extra\httpd-ssl.conf"
$httpdSslBackup = "C:\xampp\apache\conf\extra\httpd-ssl.conf.bak"

if (Test-Path $httpdSslConf) {
    if (-not (Test-Path $httpdSslBackup)) {
        Copy-Item -Path $httpdSslConf -Destination $httpdSslBackup -Force
        Write-Host "[OK] Created backup of httpd-ssl.conf at: $httpdSslBackup" -ForegroundColor Green
    } else {
        Write-Host "[INFO] Existing backup found at: $httpdSslBackup" -ForegroundColor Gray
    }

    $sslConfContent = Get-Content -Path $httpdSslConf -Raw

    # Update SSLCertificateFile and SSLCertificateKeyFile to point to ncst-server
    $updatedContent = $sslConfContent -replace 'SSLCertificateFile\s+"conf/ssl\.crt/server\.crt"', 'SSLCertificateFile "conf/ssl.crt/ncst-server.crt"'
    $updatedContent = $updatedContent -replace 'SSLCertificateKeyFile\s+"conf/ssl\.key/server\.key"', 'SSLCertificateKeyFile "conf/ssl.key/ncst-server.key"'

    if ($updatedContent -ne $sslConfContent) {
        Set-Content -Path $httpdSslConf -Value $updatedContent -Encoding Ascii
        Write-Host "[OK] Updated httpd-ssl.conf directives to ncst-server.crt / ncst-server.key" -ForegroundColor Green
    } else {
        Write-Host "[INFO] httpd-ssl.conf directives already configured." -ForegroundColor Gray
    }
} else {
    Write-Host "[WARN] httpd-ssl.conf not found at $httpdSslConf, skipping Apache edit." -ForegroundColor Yellow
}

# 8. Test Apache configuration syntax
$httpdExe = "C:\xampp\apache\bin\httpd.exe"
if (Test-Path $httpdExe) {
    $prevEAP = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $syntaxCheck = (& $httpdExe -t 2>&1) | Out-String
    $ErrorActionPreference = $prevEAP

    if ($syntaxCheck -match 'Syntax OK') {
        Write-Host "[OK] Apache configuration syntax test passed: Syntax OK" -ForegroundColor Green
    } else {
        Write-Host "[WARN] Apache syntax check output: $syntaxCheck" -ForegroundColor Yellow
    }
}

# 9. Restart Apache if requested
if ($RestartApache) {
    Write-Host "Restarting Apache..." -ForegroundColor Cyan
    Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force
    Start-Sleep -Seconds 1
    Start-Process -FilePath $httpdExe -WindowStyle Hidden
    Start-Sleep -Seconds 2
    $running = Get-Process -Name httpd -ErrorAction SilentlyContinue
    if ($running) {
        $pidsText = ($running.Id) -join ', '
        Write-Host "[OK] Apache successfully restarted (PIDs: $pidsText)" -ForegroundColor Green
    } else {
        Write-Host "[WARN] Apache did not start automatically. Please launch it from XAMPP Control Panel." -ForegroundColor Yellow
    }
}

# 10. Summary & Mobile Device Setup Instructions
Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "  LAN HTTPS SETUP COMPLETED SUCCESSFULLY" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "[URL] Mobile Scanner Access URL:" -ForegroundColor Yellow
Write-Host "      https://$LanIp/NCST-Events-Tickets/#scanner" -ForegroundColor White
Write-Host ""
Write-Host "[DOWNLOAD] Mobile Root CA Certificate Link (Open on phone):" -ForegroundColor Yellow
Write-Host "      http://$LanIp/NCST-Events-Tickets/ncst-ca.crt" -ForegroundColor White
Write-Host ""
Write-Host "[STEPS] Step-by-Step Mobile Trust Instructions:" -ForegroundColor Cyan
Write-Host "   * Android:" -ForegroundColor White
Write-Host "     1. Open http://$LanIp/NCST-Events-Tickets/ncst-ca.crt in Chrome to download." -ForegroundColor Gray
Write-Host "     2. Go to Settings > Security > Encryption & credentials > Install a certificate." -ForegroundColor Gray
Write-Host "     3. Choose 'CA certificate' > Tap 'Install anyway' > Select ncst-ca.crt." -ForegroundColor Gray
Write-Host ""
Write-Host "   * iOS / Safari:" -ForegroundColor White
Write-Host "     1. Open http://$LanIp/NCST-Events-Tickets/ncst-ca.crt in Safari > Tap 'Allow'." -ForegroundColor Gray
Write-Host "     2. Go to Settings > Profile Downloaded > Tap 'Install' (top right)." -ForegroundColor Gray
Write-Host "     3. Go to Settings > General > About > Certificate Trust Settings." -ForegroundColor Gray
Write-Host "     4. Under 'Enable full trust for root certificates', turn ON 'mkcert'." -ForegroundColor Gray
Write-Host ""
Write-Host "   Once trusted, open https://$LanIp/NCST-Events-Tickets/#scanner" -ForegroundColor Green
Write-Host "   The camera scanner will start immediately with secure context enabled!" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
