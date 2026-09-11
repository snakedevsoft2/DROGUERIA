# ---------------------------------------------------------------------------
# Copia de seguridad de la información del negocio.
#
# La interacción va aquí y no en el .bat: 'set /p' de cmd.exe no lee nada
# cuando la consola está en UTF-8, y esa página hace falta para que la
# salida de PHP salga con sus tildes.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string] $Raiz = ''
)

$ErrorActionPreference = 'Stop'

if (-not $Raiz) { $Raiz = Split-Path -Parent $PSScriptRoot }

$php = Join-Path $Raiz 'php\php.exe'
$artisan = Join-Path $Raiz 'app\artisan'
$carpeta = Join-Path $Raiz 'respaldos'

Write-Host ''
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host '  COPIA DE SEGURIDAD' -ForegroundColor Cyan
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host ''
Write-Host '  Se copia toda la información del negocio (productos, lotes' -ForegroundColor Gray
Write-Host '  y ventas) a la carpeta "respaldos".' -ForegroundColor Gray
Write-Host ''
Write-Host '  El programa puede estar abierto: la copia se hace igual.' -ForegroundColor Gray
Write-Host ''

& $php $artisan drogueria:respaldar --destino="$carpeta"
$codigo = $LASTEXITCODE

Write-Host ''
Write-Host ('=' * 60) -ForegroundColor DarkGray
Write-Host ''

if ($codigo -ne 0) {
    Write-Host '  X   No se pudo hacer la copia.' -ForegroundColor Red
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit 1
}

Write-Host '  IMPORTANTE: copie el archivo a una memoria USB o al correo.' -ForegroundColor Yellow
Write-Host '  Un respaldo guardado en este mismo computador se pierde' -ForegroundColor Yellow
Write-Host '  junto con el computador.' -ForegroundColor Yellow
Write-Host ''

$abrir = Read-Host '  ¿Abrir la carpeta de respaldos? [S/n]'

if ([string]::IsNullOrWhiteSpace($abrir) -or $abrir.Trim().ToUpper().StartsWith('S')) {
    try {
        Start-Process explorer.exe -ArgumentList """$carpeta"""
    } catch {
        Write-Host "  La carpeta es:  $carpeta" -ForegroundColor Gray
    }
}

Write-Host ''
exit 0
