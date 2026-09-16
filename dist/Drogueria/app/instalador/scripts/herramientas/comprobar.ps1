# ---------------------------------------------------------------------------
# Comprobación de la instalación.
#
# Se ejecuta en el equipo del cliente, recién instalado, y dice en claro si
# quedó bien o qué falta. Está pensada para usarse EN LA DROGUERÍA, sin
# nadie a quien preguntar.
#
# Cada comprobación que falla explica qué hacer, no sólo que falló.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string] $Raiz = ''
)

$ErrorActionPreference = 'Continue'

if (-not $Raiz) { $Raiz = Split-Path -Parent $PSScriptRoot }

$php = Join-Path $Raiz 'php\php.exe'
$appDir = Join-Path $Raiz 'app'
$artisan = Join-Path $appDir 'artisan'
$archivoEnv = Join-Path $appDir '.env'

$bien = 0
$mal = 0
$avisos = 0
$pendientes = @()

function Titulo($t) {
    Write-Host ''
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ('  ' + ('-' * 56)) -ForegroundColor DarkGray
}

function Ok($t) {
    Write-Host "    [OK]  $t" -ForegroundColor Green
    $script:bien++
}

function Falla($t, $queHacer) {
    Write-Host "    [MAL] $t" -ForegroundColor Red
    $script:mal++
    $script:pendientes += $queHacer
}

function Aviso($t, $queHacer) {
    Write-Host "    [ ! ] $t" -ForegroundColor Yellow
    $script:avisos++
    if ($queHacer) { $script:pendientes += $queHacer }
}

Clear-Host
Write-Host ''
Write-Host ('  ' + ('=' * 56)) -ForegroundColor Cyan
Write-Host '    COMPROBACION DE LA INSTALACION' -ForegroundColor Cyan
Write-Host ('  ' + ('=' * 56)) -ForegroundColor Cyan
Write-Host ''
Write-Host "    Carpeta: $Raiz" -ForegroundColor Gray


# --- 1. Archivos ------------------------------------------------------------

Titulo '1. Archivos del programa'

$piezas = @{
    'php\php.exe'                                 = 'el motor'
    'php\vcruntime140.dll'                        = 'biblioteca que necesita el motor'
    'php\msvcp140.dll'                            = 'biblioteca que necesita el motor'
    'app\artisan'                                 = 'la aplicación'
    'app\vendor\autoload.php'                     = 'las dependencias'
    'app\public\build\manifest.json'              = 'los estilos'
    'Drogueria.exe'                               = 'la ventana del programa'
    'WebView2Loader.dll'                          = 'el visor de la ventana'
}

$faltan = @()
foreach ($p in $piezas.Keys) {
    if (-not (Test-Path (Join-Path $Raiz $p))) { $faltan += "$p ($($piezas[$p]))" }
}

if ($faltan.Count -eq 0) {
    Ok "Están los $($piezas.Count) archivos imprescindibles."
} else {
    Falla ("Faltan: " + ($faltan -join ', ')) `
          'Vuelva a ejecutar INSTALAR.bat desde la memoria USB.'
}


# --- 2. El motor ------------------------------------------------------------

Titulo '2. El motor funciona'

if (-not (Test-Path $php)) {
    Falla 'No se encontró php.exe.' 'Vuelva a instalar desde la memoria USB.'
} else {
    $version = & $php -r "echo PHP_VERSION;" 2>&1

    if ($LASTEXITCODE -eq 0 -and $version -match '^\d+\.') {
        Ok "PHP $version arranca correctamente."
    } else {
        Falla "PHP no arranca: $version" `
              'Vuelva a instalar desde la memoria USB.'
    }

    $faltanExt = & $php -r "`$n=['mbstring','fileinfo','openssl','pdo_sqlite','sqlite3','gd','intl','zip','curl']; echo implode(',', array_diff(`$n, get_loaded_extensions()));" 2>&1

    if ([string]::IsNullOrWhiteSpace($faltanExt)) {
        Ok 'Todas las piezas del motor cargan.'
    } else {
        Falla "Al motor le faltan piezas: $faltanExt" `
              'Vuelva a instalar desde la memoria USB.'
    }
}


# --- 3. Configuración -------------------------------------------------------

Titulo '3. Configuración'

if (-not (Test-Path $archivoEnv)) {
    Falla 'No existe el archivo de ajustes (app\.env).' `
          'Vuelva a ejecutar INSTALAR.bat.'
} else {
    $texto = Get-Content -LiteralPath $archivoEnv -Raw -Encoding UTF8

    if ($texto -match '(?m)^APP_KEY=base64:.+') {
        Ok 'La clave de seguridad está generada.'
    } else {
        Falla 'Falta la clave de seguridad (APP_KEY).' `
              'Vuelva a ejecutar INSTALAR.bat.'
    }

    if ($texto -match '(?m)^STORE_NAME="?([^"\r\n]+)') {
        Ok "Nombre en el tiquete: $($Matches[1])"
    } else {
        Aviso 'No hay nombre de droguería configurado.' `
              'Edite app\.env con el Bloc de notas y ponga STORE_NAME.'
    }

    if ($texto -match '(?m)^PRINTER_COLUMNS=(\d+)') {
        $cols = [int]$Matches[1]
        $rollo = if ($cols -ge 40) { '80mm' } else { '58mm' }
        Ok "Ancho del tiquete: $cols columnas (rollo de $rollo)"
    }
}


# --- 4. La base de datos ----------------------------------------------------

Titulo '4. La información del negocio'

$bd = Join-Path $appDir 'database\database.sqlite'

if (-not (Test-Path $bd)) {
    Falla 'No existe la base de datos.' 'Vuelva a ejecutar INSTALAR.bat.'
} else {
    Push-Location $appDir
    try {
        $salida = & $php $artisan migrate:status 2>&1 | Out-String

        if ($salida -match 'Ran') {
            $cuenta = & $php $artisan tinker --execute="echo App\Models\Product::count().'|'.App\Models\Sale::count();" 2>&1 |
                Select-Object -Last 1

            $partes = "$cuenta".Trim() -split '\|'

            if ($partes.Count -eq 2) {
                Ok "Las tablas están listas. Productos: $($partes[0])  Ventas: $($partes[1])"

                if ([int]$partes[0] -eq 0) {
                    Write-Host '          (vacía: hay que cargar los productos en INVENTARIO)' -ForegroundColor Gray
                }
            } else {
                Ok 'Las tablas están listas.'
            }
        } else {
            Falla 'Las tablas no están preparadas.' 'Vuelva a ejecutar INSTALAR.bat.'
        }
    } finally {
        Pop-Location
    }

    # Que se pueda ESCRIBIR, no sólo leer: si la carpeta quedó de sólo
    # lectura, el programa abre y deja buscar, pero no deja vender.
    try {
        $prueba = Join-Path $appDir 'database\.prueba-escritura'
        Set-Content -LiteralPath $prueba -Value 'x' -ErrorAction Stop
        Remove-Item -LiteralPath $prueba -Force
        Ok 'Se puede guardar en la base de datos.'
    } catch {
        Falla 'La carpeta de la base de datos es de sólo lectura.' `
              'Instale el programa en otra carpeta, o dé permisos de escritura a app\database.'
    }
}


# --- 5. Que el programa arranque -------------------------------------------

Titulo '5. El programa arranca'

$puerto = 8347
$archivoPuerto = Join-Path $Raiz 'puerto.txt'
if (Test-Path $archivoPuerto) {
    $n = 0
    if ([int]::TryParse((Get-Content $archivoPuerto -Raw).Trim(), [ref]$n)) { $puerto = $n }
}

$yaAbierto = $false
try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:$puerto/up" -UseBasicParsing -TimeoutSec 3
    if ($r.StatusCode -eq 200) { $yaAbierto = $true }
} catch { }

if ($yaAbierto) {
    Ok "El programa ya está abierto y respondiendo (puerto $puerto)."
} else {
    Write-Host '    Probando a arrancarlo...' -ForegroundColor Gray

    Push-Location $appDir
    $proc = $null
    try {
        $proc = Start-Process $php -ArgumentList "`"$artisan`"", 'serve',
            '--host=127.0.0.1', "--port=$puerto" -PassThru -WindowStyle Hidden

        $arranco = $false
        for ($i = 0; $i -lt 40; $i++) {
            Start-Sleep -Milliseconds 500
            try {
                $r = Invoke-WebRequest -Uri "http://127.0.0.1:$puerto/up" -UseBasicParsing -TimeoutSec 2
                if ($r.StatusCode -eq 200) { $arranco = $true; break }
            } catch { }
        }

        if ($arranco) {
            Ok "El programa arranca y responde (puerto $puerto)."
        } else {
            Falla 'El programa no llegó a responder.' `
                  'Abra "Iniciar (modo diagnostico).bat" para ver el error exacto.'
        }
    } finally {
        if ($proc) {
            try { & taskkill /PID $proc.Id /T /F 2>&1 | Out-Null } catch { }
        }
        Pop-Location
    }
}


# --- 6. La impresora --------------------------------------------------------

Titulo '6. La impresora de tiquetes'

$nombreImpresora = ''
if (Test-Path $archivoEnv) {
    $t = Get-Content -LiteralPath $archivoEnv -Raw -Encoding UTF8
    if ($t -match '(?m)^PRINTER_NAME=(.*)$') { $nombreImpresora = $Matches[1].Trim() }
}

if (-not $nombreImpresora) {
    Aviso 'No hay impresora configurada: el tiquete no saldrá solo.' `
          'Clic derecho en "Configurar impresora" > Ejecutar como administrador.'
} else {
    $compartida = $null
    try {
        $compartida = Get-Printer -EA SilentlyContinue |
            Where-Object { $_.ShareName -eq $nombreImpresora -and $_.Shared }
    } catch { }

    if ($compartida) {
        Ok "Impresora '$nombreImpresora' configurada y compartida."
        Write-Host '          (para confirmar que imprime, use "Configurar impresora"' -ForegroundColor Gray
        Write-Host '           y acepte el tiquete de prueba)' -ForegroundColor Gray
    } else {
        Falla "Los ajustes apuntan a '$nombreImpresora', pero no hay ninguna impresora compartida con ese nombre." `
              'Clic derecho en "Configurar impresora" > Ejecutar como administrador.'
    }
}


# --- 7. Respaldos -----------------------------------------------------------

Titulo '7. Copias de seguridad'

$carpetaRespaldos = Join-Path $Raiz 'respaldos'

if (-not (Test-Path $carpetaRespaldos)) {
    New-Item -ItemType Directory -Force -Path $carpetaRespaldos | Out-Null
}

try {
    $prueba = Join-Path $carpetaRespaldos '.prueba'
    Set-Content -LiteralPath $prueba -Value 'x' -ErrorAction Stop
    Remove-Item -LiteralPath $prueba -Force

    $cuantos = @(Get-ChildItem $carpetaRespaldos -Filter '*.sqlite' -EA SilentlyContinue).Count
    Ok "Se pueden guardar respaldos. Hay $cuantos ahora mismo."
} catch {
    Falla 'No se puede escribir en la carpeta de respaldos.' `
          'Dé permisos de escritura a la carpeta respaldos.'
}


# --- 8. El acceso directo ---------------------------------------------------

Titulo '8. El icono del escritorio'

$iconos = @()
foreach ($d in @([Environment]::GetFolderPath('CommonDesktopDirectory'),
                 [Environment]::GetFolderPath('Desktop'))) {
    if ($d) {
        $lnk = Join-Path $d 'Droguería.lnk'
        if (Test-Path -LiteralPath $lnk) { $iconos += $lnk }
    }
}

if ($iconos.Count -ge 1) {
    Ok 'El icono "Droguería" está en el escritorio.'
    if ($iconos.Count -gt 1) {
        Aviso 'Hay dos iconos iguales; puede borrar uno.' $null
    }
} else {
    Aviso 'No hay icono en el escritorio.' `
          "Cree un acceso directo a: $Raiz\Drogueria.exe"
}


# --- Resultado --------------------------------------------------------------

Write-Host ''
Write-Host ('  ' + ('=' * 56)) -ForegroundColor Cyan

if ($mal -eq 0 -and $avisos -eq 0) {
    Write-Host '    LA INSTALACION QUEDO BIEN' -ForegroundColor Green
    Write-Host ''
    Write-Host "    $bien comprobaciones, todas correctas." -ForegroundColor Green
    Write-Host '    Puede empezar a usar el programa.' -ForegroundColor Green
} elseif ($mal -eq 0) {
    Write-Host '    LA INSTALACION FUNCIONA' -ForegroundColor Green
    Write-Host ''
    Write-Host "    $bien correctas, $avisos cosa(s) por terminar." -ForegroundColor Yellow
} else {
    Write-Host '    HAY QUE ARREGLAR ALGO' -ForegroundColor Red
    Write-Host ''
    Write-Host "    $bien correctas, $mal con problemas." -ForegroundColor Red
}

if ($pendientes.Count -gt 0) {
    Write-Host ''
    Write-Host '    Qué hacer:' -ForegroundColor White
    $n = 1
    foreach ($p in ($pendientes | Select-Object -Unique)) {
        Write-Host "      $n. $p" -ForegroundColor White
        $n++
    }
}

Write-Host ''
Write-Host ('  ' + ('=' * 56)) -ForegroundColor Cyan
Write-Host ''
Write-Host '    Lo único que esto NO puede comprobar solo:' -ForegroundColor Gray
Write-Host '      - Que la impresora imprima de verdad (use el tiquete de prueba)' -ForegroundColor Gray
Write-Host '      - Que el lector de códigos lea (dispárelo sobre un producto)' -ForegroundColor Gray
Write-Host ''

Read-Host '    Pulse Enter para cerrar'

if ($mal -gt 0) { exit 1 }
exit 0
