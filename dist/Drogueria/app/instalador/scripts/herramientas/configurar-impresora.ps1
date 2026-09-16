# ---------------------------------------------------------------------------
# Configurar la impresora de tiquetes.
#
# Lista las impresoras del equipo, deja elegir una, la comparte si hace
# falta, guarda su nombre en los ajustes e imprime una prueba.
#
# Toda la interacción vive aquí y no en el .bat de al lado: 'set /p' de
# cmd.exe NO LEE NADA cuando la consola está en UTF-8 (chcp 65001), y esa
# página hace falta para que la salida de PHP se vea con sus tildes. Entre
# las dos cosas, la que se mueve es la lectura.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    # Carpeta del programa. Por defecto, la que contiene a esta.
    [string] $Raiz = ''
)

$ErrorActionPreference = 'Stop'

if (-not $Raiz) { $Raiz = Split-Path -Parent $PSScriptRoot }

$archivoEnv = Join-Path $Raiz 'app\.env'
$php = Join-Path $Raiz 'php\php.exe'
$artisan = Join-Path $Raiz 'app\artisan'

# escpos-php sólo admite letras, números, guiones y espacios sueltos. Con
# una tilde o un punto se niega a imprimir, y el fallo aparecería en plena
# venta en vez de aquí, que es cuando alguien está mirando.
$patronValido = '^[\d\w-]+(\s[\d\w-]+)*$'

function Titulo($t) {
    Write-Host ''
    Write-Host ('=' * 60) -ForegroundColor Cyan
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ('=' * 60) -ForegroundColor Cyan
    Write-Host ''
}

function Ok($t)    { Write-Host "  OK  $t" -ForegroundColor Green }
function Aviso($t) { Write-Host "  !   $t" -ForegroundColor Yellow }
function Malo($t)  { Write-Host "  X   $t" -ForegroundColor Red }

function EsValido($nombre) {
    return ($nombre -match $patronValido) -and ($nombre -notmatch '[^\x00-\x7F]')
}

function Salir($codigo) {
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit $codigo
}

Titulo 'CONFIGURAR LA IMPRESORA DE TIQUETES'

if (-not (Test-Path $archivoEnv)) {
    Malo "No se encontró el archivo de ajustes: $archivoEnv"
    Salir 1
}

$actual = ''
$texto = Get-Content -LiteralPath $archivoEnv -Raw -Encoding UTF8
if ($texto -match '(?m)^PRINTER_NAME=(.*)$') { $actual = $Matches[1].Trim() }

if ($actual) {
    Write-Host "  Impresora configurada ahora:  $actual" -ForegroundColor White
} else {
    Write-Host '  Ahora mismo no hay ninguna impresora configurada.' -ForegroundColor Gray
}
Write-Host ''

try {
    $impresoras = @(Get-Printer -ErrorAction Stop | Where-Object { $_.PortName -ne 'nul:' })
} catch {
    $impresoras = @()
}

if ($impresoras.Count -eq 0) {
    Aviso 'No se detectaron impresoras instaladas en Windows.'
    Write-Host ''
    Write-Host '  Instale primero el controlador de la impresora (el de Epson' -ForegroundColor Gray
    Write-Host '  para la TM-T20II) y vuelva a ejecutar esta herramienta.' -ForegroundColor Gray
    Salir 1
}

Write-Host '  Impresoras de este equipo:' -ForegroundColor Gray
Write-Host ''
for ($i = 0; $i -lt $impresoras.Count; $i++) {
    $p = $impresoras[$i]
    if ($p.Shared -and $p.ShareName) { $marca = "compartida como '$($p.ShareName)'" } else { $marca = 'NO compartida' }
    Write-Host ("    {0}) {1}  ({2})" -f ($i + 1), $p.Name, $marca)
}
Write-Host '    0) Cancelar, no cambiar nada'
Write-Host ''
Write-Host '  Elija la impresora de TIQUETES (la térmica de rollo).' -ForegroundColor Gray
Write-Host ''

$eleccion = Read-Host '  Número'
$indice = 0

if (-not [int]::TryParse($eleccion, [ref]$indice) -or $indice -lt 1 -or $indice -gt $impresoras.Count) {
    Write-Host ''
    Write-Host '  No se cambió nada.' -ForegroundColor Gray
    Salir 0
}

$elegida = $impresoras[$indice - 1]
$nombreCompartido = ''

# Windows no deja que PHP le escriba bytes crudos a una impresora USB salvo
# que esté compartida; el conector escribe a \\EQUIPO\RECURSO.
if ($elegida.Shared -and $elegida.ShareName -and (EsValido $elegida.ShareName)) {
    $nombreCompartido = $elegida.ShareName
    Ok "Ya estaba compartida como '$nombreCompartido'."
} else {
    if ($elegida.Shared -and $elegida.ShareName) {
        Aviso "Está compartida como '$($elegida.ShareName)', un nombre que el"
        Aviso 'sistema de impresión no admite (tildes o símbolos).'
    }

    Write-Host ''
    Write-Host '  Para imprimir directamente, Windows exige que la impresora' -ForegroundColor Gray
    Write-Host '  esté compartida. Se comparte sólo en este equipo.' -ForegroundColor Gray
    Write-Host ''

    $sugerido = Read-Host '  Nombre para compartirla [TIQUETERA]'
    if ([string]::IsNullOrWhiteSpace($sugerido)) { $sugerido = 'TIQUETERA' }
    $sugerido = $sugerido.Trim()

    if (-not (EsValido $sugerido)) {
        Write-Host ''
        Malo "El nombre `"$sugerido`" no sirve para imprimir."
        Aviso 'Use sólo letras, números y guiones, sin tildes ni puntos.'
        Aviso 'Por ejemplo: TIQUETERA'
        Salir 1
    }

    try {
        Set-Printer -Name $elegida.Name -Shared $true -ShareName $sugerido -ErrorAction Stop
        $nombreCompartido = $sugerido
        Ok "Compartida como '$nombreCompartido'."
    } catch {
        Malo "No se pudo compartir: $($_.Exception.Message)"
        Write-Host ''
        Aviso 'Hace falta ejecutar esta herramienta como administrador:'
        Aviso 'clic derecho sobre ella > "Ejecutar como administrador".'
        Salir 1
    }
}

# Sin BOM: Laravel lo leería como parte del nombre de la primera variable.
if ($texto -match '(?m)^PRINTER_NAME=.*$') {
    $texto = $texto -replace '(?m)^PRINTER_NAME=.*$', "PRINTER_NAME=$nombreCompartido"
} else {
    $texto = $texto.TrimEnd() + "`r`nPRINTER_NAME=$nombreCompartido`r`n"
}
[System.IO.File]::WriteAllText($archivoEnv, $texto, (New-Object System.Text.UTF8Encoding $false))
Ok "Guardado en los ajustes: PRINTER_NAME=$nombreCompartido"

& $php $artisan config:clear 2>&1 | Out-Null

Write-Host ''
$prueba = Read-Host '  ¿Imprimir un tiquete de prueba? [S/n]'

if ([string]::IsNullOrWhiteSpace($prueba) -or $prueba.Trim().ToUpper().StartsWith('S')) {
    Write-Host ''
    & $php $artisan drogueria:impresora
}

Write-Host ''
Write-Host '  Si el programa está abierto, ciérrelo y vuélvalo a abrir para' -ForegroundColor Gray
Write-Host '  que tome la configuración nueva.' -ForegroundColor Gray

Salir 0
