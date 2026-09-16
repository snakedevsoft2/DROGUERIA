# Detiene el servidor del punto de venta.
#
# Sólo mata los php.exe que salieron de ESTA carpeta: en un equipo con XAMPP
# u otro programa en PHP, un "taskkill /im php.exe" se llevaría por delante
# cosas ajenas.

$ErrorActionPreference = 'Stop'

$base = Split-Path -Parent $PSScriptRoot
$php = Join-Path $base 'php\php.exe'

$procesos = @(Get-Process php -ErrorAction SilentlyContinue |
    Where-Object { $_.Path -eq $php })

if ($procesos.Count -eq 0) {
    Write-Host '  El programa no estaba abierto.' -ForegroundColor Yellow
    exit 0
}

$procesos | Stop-Process -Force
Write-Host ("  Detenido (" + $procesos.Count + " proceso/s).") -ForegroundColor Green

exit 0
