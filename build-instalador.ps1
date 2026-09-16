<#
Arma el instalador portable de Drogueria: una carpeta con PHP incluido y el
sistema listo para copiar a CUALQUIER computador con Windows y usarse sin
instalar nada ni tener internet. El resultado queda en dist\Drogueria y,
comprimido, en Drogueria-Instalador.zip.

Uso: clic derecho > "Ejecutar con PowerShell", o desde una consola:
    powershell -ExecutionPolicy Bypass -File build-instalador.ps1
#>

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$dist = Join-Path $root 'dist\Drogueria'
$cache = Join-Path $root 'build-cache'
$phpVersion = '8.2.33'
$phpZipName = "php-$phpVersion-nts-Win32-vs16-x64.zip"
$phpUrl = "https://windows.php.net/downloads/releases/$phpZipName"

Write-Host "== Armando el instalador de Drogueria ==" -ForegroundColor Cyan

# 1. PHP portable (se guarda en cache para no volver a descargarlo en cada build)
New-Item -ItemType Directory -Force -Path $cache | Out-Null
$phpZipPath = Join-Path $cache $phpZipName
if (-not (Test-Path $phpZipPath)) {
    Write-Host "Descargando PHP $phpVersion portable..."
    Invoke-WebRequest -Uri $phpUrl -OutFile $phpZipPath
} else {
    Write-Host "PHP portable ya estaba en cache ($phpZipPath)."
}

# 2. Carpeta de salida limpia
if (Test-Path $dist) {
    Remove-Item -Recurse -Force $dist
}
New-Item -ItemType Directory -Force -Path "$dist\php" | Out-Null
New-Item -ItemType Directory -Force -Path "$dist\app" | Out-Null

Write-Host "Extrayendo PHP..."
Expand-Archive -Path $phpZipPath -DestinationPath "$dist\php" -Force

# 3. php.ini con las extensiones que Laravel necesita
Write-Host "Configurando php.ini..."
$ini = Get-Content "$dist\php\php.ini-production"
$ini = $ini -replace '^;?\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
foreach ($ext in @('curl', 'fileinfo', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'sodium')) {
    $ini = $ini -replace "^;extension=$ext$", "extension=$ext"
}
$ini += ''
$ini += 'date.timezone = America/Bogota'
Set-Content -Path "$dist\php\php.ini" -Value $ini -Encoding ASCII

# 4. La aplicacion, sin lo que sobra para distribuir
Write-Host "Copiando la aplicacion..."
$excluirCarpetas = @('.git', 'node_modules', 'tests', 'dist', 'build-cache', '.vscode', '.idea', '.fleet', '.nova', '.zed')
$excluirArchivos = @('.env', '.env.backup', '.env.production', '.env.instalador', 'database.sqlite', '.phpunit.result.cache', '*.bat', 'build-instalador.ps1')

robocopy $root "$dist\app" /E /XD $excluirCarpetas /XF $excluirArchivos /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy fallo copiando la aplicacion (codigo $LASTEXITCODE)."
}

Copy-Item "$root\.env.instalador" "$dist\app\.env" -Force

# 5. El lanzador que ve el usuario final
Write-Host "Escribiendo el lanzador..."
$launcher = @'
@echo off
setlocal enabledelayedexpansion
title Drogueria - Punto de Venta
cd /d "%~dp0"

set "PHP_EXE=%~dp0php\php.exe"
set "APP_DIR=%~dp0app"
set HOST=127.0.0.1
set PORT=8000
set "URL=http://%HOST%:%PORT%/"

cd /d "%APP_DIR%"

echo ============================================
echo   Drogueria - iniciando el sistema...
echo ============================================
echo.

if not exist "database\database.sqlite" (
    echo Primera vez: preparando la base de datos local...
    type nul > "database\database.sqlite"
    "%PHP_EXE%" artisan key:generate --ansi
)

"%PHP_EXE%" artisan migrate --force --ansi

echo.
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient).Connect('%HOST%', %PORT%); exit 0 } catch { exit 1 }" >nul 2>nul
if not errorlevel 1 (
    echo El sistema ya esta abierto en otra ventana. Abriendo el navegador...
    start "" "%URL%"
    exit /b 0
)

start "" cmd /c "timeout /t 2 >nul & start "" "%URL%""

echo El sistema quedo abierto en: %URL%
echo NO CIERRE ESTA VENTANA mientras trabaje: al cerrarla se apaga el sistema.
echo.

"%PHP_EXE%" artisan serve --host=%HOST% --port=%PORT%
'@
Set-Content -Path "$dist\Iniciar Drogueria.bat" -Value $launcher -Encoding ASCII

# 6. Comprimido listo para copiar a una USB o enviar
$zipPath = Join-Path $root 'Drogueria-Instalador.zip'
if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath
}
Write-Host "Comprimiendo en Drogueria-Instalador.zip..."
Compress-Archive -Path "$dist\*" -DestinationPath $zipPath -CompressionLevel Optimal

Write-Host ""
Write-Host "Listo. Copie 'Drogueria-Instalador.zip' al computador de la farmacia," -ForegroundColor Green
Write-Host "descomprimalo donde quiera (por ejemplo el Escritorio) y abra" -ForegroundColor Green
Write-Host "'Iniciar Drogueria.bat'. No necesita internet ni instalar nada mas." -ForegroundColor Green
