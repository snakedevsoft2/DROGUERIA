# ---------------------------------------------------------------------------
# Carga un catálogo de prueba para practicar o para hacer una demostración.
#
# La interacción va aquí y no en el .bat: 'set /p' de cmd.exe no lee nada
# cuando la consola está en UTF-8, y con una confirmación que no se lee,
# esta herramienta metería datos falsos sin que nadie lo haya aceptado.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string] $Raiz = ''
)

$ErrorActionPreference = 'Stop'

if (-not $Raiz) { $Raiz = Split-Path -Parent $PSScriptRoot }

$php = Join-Path $Raiz 'php\php.exe'
$artisan = Join-Path $Raiz 'app\artisan'
$respaldos = Join-Path $Raiz 'respaldos'

Write-Host ''
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host '  CARGAR DATOS DE EJEMPLO' -ForegroundColor Cyan
Write-Host ('=' * 60) -ForegroundColor Cyan
Write-Host ''
Write-Host '  Llena el programa con un catálogo de prueba (28 productos,' -ForegroundColor Gray
Write-Host '  sus lotes y un historial de ventas) para practicar o para' -ForegroundColor Gray
Write-Host '  mostrarle el sistema a alguien.' -ForegroundColor Gray
Write-Host ''
Write-Host '  ATENCIÓN: esto AGREGA datos falsos sobre los que ya existan.' -ForegroundColor Yellow
Write-Host '  No lo haga si la droguería ya está cargando productos reales,' -ForegroundColor Yellow
Write-Host '  porque quedarían mezclados con los de prueba.' -ForegroundColor Yellow
Write-Host ''
Write-Host '  Escriba EJEMPLO en mayúsculas para continuar.' -ForegroundColor Gray
Write-Host '  Cualquier otra cosa cancela.' -ForegroundColor Gray
Write-Host ''

$confirmacion = Read-Host '  Confirmación'

if ($confirmacion -cne 'EJEMPLO') {
    Write-Host ''
    Write-Host '  Cancelado. No se cambió nada.' -ForegroundColor Green
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit 0
}

Write-Host ''
Write-Host '  Haciendo una copia de seguridad antes de continuar...' -ForegroundColor Gray
& $php $artisan drogueria:respaldar --destino="$respaldos" 2>&1 | Out-Null

if ($LASTEXITCODE -ne 0) {
    # Sin red de seguridad no se tocan los datos: quien ejecuta esto suele
    # tener ya productos cargados y no hay vuelta atrás.
    Write-Host ''
    Write-Host '  X   No se pudo hacer la copia de seguridad previa.' -ForegroundColor Red
    Write-Host '      No se cargaron los datos de ejemplo.' -ForegroundColor Red
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit 1
}

Write-Host '  Copia hecha.' -ForegroundColor Green
Write-Host ''
Write-Host '  Cargando datos de ejemplo...' -ForegroundColor Gray
Write-Host ''

& $php $artisan db:seed --class=DemoSeeder --force

if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host '  X   No se pudieron cargar los datos de ejemplo.' -ForegroundColor Red
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit 1
}

Write-Host ''
Write-Host '  Listo. Abra el programa para verlos.' -ForegroundColor Green
Write-Host ''
Read-Host '  Pulse Enter para cerrar'
exit 0
