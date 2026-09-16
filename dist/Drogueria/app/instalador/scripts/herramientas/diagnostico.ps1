# ---------------------------------------------------------------------------
# Arranca el punto de venta mostrando los errores en pantalla.
#
# Es la herramienta a la que se recurre cuando el icono del escritorio no
# abre nada: aquí se ve lo que PHP tenga que decir.
#
# Va en PowerShell y no en el .bat porque leer el puerto con 'set /p' no
# funciona: con la consola en UTF-8, 'set /p' de cmd.exe no lee nada, y el
# puerto se quedaba siempre en el valor por defecto.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string] $Raiz = ''
)

$ErrorActionPreference = 'Stop'

if (-not $Raiz) { $Raiz = Split-Path -Parent $PSScriptRoot }

$php = Join-Path $Raiz 'php\php.exe'
$appDir = Join-Path $Raiz 'app'
$artisan = Join-Path $appDir 'artisan'

$puerto = 8347
$archivoPuerto = Join-Path $Raiz 'puerto.txt'

if (Test-Path -LiteralPath $archivoPuerto) {
    $leido = 0
    $texto = (Get-Content -LiteralPath $archivoPuerto -Raw).Trim()
    if ([int]::TryParse($texto, [ref]$leido) -and $leido -gt 0 -and $leido -lt 65536) {
        $puerto = $leido
    }
}

# Si el puerto de siempre no admite un enlace nuevo —lo tiene otro
# programa, o acaba de cerrarse y Windows aún no lo ha soltado— se coge el
# siguiente que sí. Es lo mismo que hace Drogueria.exe: el número de puerto
# es un detalle interno y no vale la pena quedarse sin programa por él.
function SePuedeEnlazar($n) {
    $oyente = $null
    try {
        $oyente = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $n)
        $oyente.Start()
        return $true
    } catch {
        return $false
    } finally {
        if ($oyente) { try { $oyente.Stop() } catch { } }
    }
}

$original = $puerto
for ($i = 0; $i -lt 12; $i++) {
    if (SePuedeEnlazar ($original + $i)) { $puerto = $original + $i; break }
}

$url = "http://127.0.0.1:$puerto"

Write-Host ''
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host '  DROGUERÍA - MODO DIAGNÓSTICO' -ForegroundColor Cyan
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host ''
Write-Host '  Use esta ventana sólo cuando el icono del escritorio no abra' -ForegroundColor Gray
Write-Host '  el programa: aquí se ven los mensajes de error.' -ForegroundColor Gray
Write-Host ''
Write-Host "  Dirección: $url" -ForegroundColor White
Write-Host ''
Write-Host '  NO CIERRE esta ventana mientras use el programa.' -ForegroundColor Yellow
Write-Host '  Para cerrarlo: pulse Ctrl+C aquí, o cierre la ventana.' -ForegroundColor Yellow
Write-Host ''
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host ''

foreach ($necesario in @($php, $artisan)) {
    if (-not (Test-Path -LiteralPath $necesario)) {
        Write-Host "  X   Falta un archivo del programa:" -ForegroundColor Red
        Write-Host "      $necesario" -ForegroundColor Red
        Write-Host ''
        Write-Host '  Vuelva a ejecutar el instalador desde la memoria USB.' -ForegroundColor Yellow
        Write-Host ''
        Read-Host '  Pulse Enter para cerrar'
        exit 1
    }
}

# El navegador se abre aparte: si el servidor no llega a arrancar, el
# mensaje de error se queda a la vista en esta ventana.
Start-Process $url

# artisan resuelve storage\ y database\ desde la carpeta de trabajo.
Push-Location $appDir
try {
    & $php $artisan serve --host=127.0.0.1 --port=$puerto
} finally {
    Pop-Location
}

Write-Host ''
Write-Host '  El servidor se detuvo.' -ForegroundColor Yellow
Write-Host ''
Read-Host '  Pulse Enter para cerrar'
exit 0
