# ---------------------------------------------------------------------------
# Arma la memoria USB de instalación a partir de este repositorio.
#
#   .\instalador\construir-paquete.ps1
#   .\instalador\construir-paquete.ps1 -Usb D:
#
# Deja el paquete en una carpeta de trabajo y, si se le indica una unidad,
# lo copia también a la memoria USB.
#
# Qué hace, en orden:
#   1. Compila los assets de Vite (npm run build).
#   2. Copia la aplicación sin lo que no va al cliente.
#   3. Instala las dependencias de PHP sin las de desarrollo.
#   4. Descarga el PHP portable de Windows si todavía no está.
#   5. Añade el instalador, las plantillas y las herramientas.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    # Carpeta de trabajo donde se arma el paquete.
    [string] $Salida = "$env:USERPROFILE\Drogueria-USB",

    # Unidad de la memoria USB, p. ej. "D:". Si se omite, sólo se arma la
    # carpeta y la copia a la USB queda para después.
    [string] $Usb = '',

    # Versión de PHP que se empaqueta. Debe ser una compilación NTS x64 de
    # Windows y cumplir el requisito de Laravel (PHP >= 8.2).
    [string] $VersionPhp = '8.4.25',

    # Versión del paquete NuGet de WebView2, del que salen las bibliotecas
    # que necesita la ventana de escritorio.
    [string] $VersionWebView2 = '1.0.4191.47',

    # Rehace la carpeta de salida desde cero.
    [switch] $Limpiar
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

$repo = Split-Path -Parent $PSScriptRoot
$recursos = Join-Path $Salida 'recursos'

function Titulo($t) {
    Write-Host ''
    Write-Host ('=' * 62) -ForegroundColor Cyan
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ('=' * 62) -ForegroundColor Cyan
}
function Paso($t)  { Write-Host "  > $t" -ForegroundColor White }
function Ok($t)    { Write-Host "    OK  $t" -ForegroundColor Green }
function Aviso($t) { Write-Host "    !   $t" -ForegroundColor Yellow }

Titulo 'CONSTRUCCIÓN DEL PAQUETE DE INSTALACIÓN'
Write-Host "  Repositorio : $repo"
Write-Host "  Salida      : $Salida"
Write-Host ''

# Antes de gastar cinco minutos armando el paquete: si el instalador tiene
# un fallo conocido, mejor saberlo ahora que cuando esté delante del cliente.
Titulo 'REVISIÓN PREVIA'
& (Join-Path $PSScriptRoot 'revisar.ps1')
if ($LASTEXITCODE -ne 0) {
    throw 'La revisión encontró problemas. Arréglelos antes de armar el paquete.'
}

if ($Limpiar -and (Test-Path -LiteralPath $Salida)) {
    Paso 'Borrando la carpeta de salida anterior...'
    Remove-Item -LiteralPath $Salida -Recurse -Force
    Ok 'Borrada.'
}

New-Item -ItemType Directory -Force -Path $recursos | Out-Null


# --- 1. Assets --------------------------------------------------------------

Titulo 'PASO 1  -  ASSETS DE VITE'

Paso 'Compilando (npm run build)...'
Push-Location $repo
try {
    # Vía cmd y no '& npm': npm es un .cmd y al invocarlo directo desde
    # PowerShell 5.1 el argumento llega mutilado ("Unknown command: pm").
    #
    # Y sin '2>&1': PowerShell 5.1 convierte cada línea que un programa
    # externo escriba en stderr en un error de PowerShell, y como aquí
    # ErrorActionPreference es 'Stop', el avance normal de npm o composer
    # abortaría la construcción.
    & cmd /c 'npm run build' | Select-Object -Last 5
    if ($LASTEXITCODE -ne 0) { throw "npm run build falló con código $LASTEXITCODE" }
} finally {
    Pop-Location
}
Ok 'Assets compilados.'


# --- 2. Aplicación ----------------------------------------------------------

Titulo 'PASO 2  -  APLICACIÓN'

$app = Join-Path $recursos 'app'

# Fuera: dependencias de desarrollo, historia de git, pruebas, y todo lo que
# era sólo para el despliegue en Vercel. El .env y la base de datos NO viajan:
# el instalador los crea en el equipo del cliente.
$excluirCarpetas = @(
    "$repo\node_modules", "$repo\.git", "$repo\tests", "$repo\api", "$repo\.claude",
    "$repo\instalador",
    "$repo\storage\logs", "$repo\storage\framework\cache",
    "$repo\storage\framework\sessions", "$repo\storage\framework\views",
    "$repo\storage\framework\testing", "$repo\bootstrap\cache"
)
$excluirArchivos = @(
    'vercel.json', '.vercelignore', '.env.vercel.example', 'DEPLOY.md', '.env',
    'database.sqlite', 'database.sqlite-wal', 'database.sqlite-shm',
    'phpunit.xml', 'package-lock.json', '*.log'
)

Paso 'Copiando la aplicación...'
robocopy $repo $app /E /XD $excluirCarpetas /XF $excluirArchivos /NFL /NDL /NJH /NJS /NP /R:1 /W:1 | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy falló con código $LASTEXITCODE" }
Ok 'Aplicación copiada.'

# Laravel no crea estas carpetas solo; si faltan, revienta al primer arranque.
foreach ($c in @('storage\logs', 'storage\framework\cache\data',
                 'storage\framework\sessions', 'storage\framework\views',
                 'bootstrap\cache', 'database')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $app $c) | Out-Null
}


# --- 3. Dependencias de PHP -------------------------------------------------

Titulo 'PASO 3  -  DEPENDENCIAS DE PHP'

Paso 'composer install --no-dev (tarda un par de minutos)...'
Push-Location $app
try {
    & cmd /c 'composer install --no-dev --optimize-autoloader --no-interaction --no-progress' | Select-Object -Last 3
    if ($LASTEXITCODE -ne 0) { throw "composer falló con código $LASTEXITCODE" }
} finally {
    Pop-Location
}
Ok 'Dependencias de producción instaladas.'

# package:discover deja en bootstrap\cache rutas absolutas de ESTE equipo.
# En el del cliente serían mentira, así que se van; Laravel las regenera.
Get-ChildItem (Join-Path $app 'bootstrap\cache') -Filter '*.php' -ErrorAction SilentlyContinue |
    Remove-Item -Force


# --- 4. PHP portable --------------------------------------------------------

Titulo 'PASO 4  -  PHP PORTABLE'

$php = Join-Path $recursos 'php'

if (Test-Path (Join-Path $php 'php.exe')) {
    Ok 'PHP ya estaba descargado (use -Limpiar para volver a bajarlo).'
} else {
    $zip = Join-Path $env:TEMP "php-$VersionPhp-nts.zip"
    $url = "https://windows.php.net/downloads/releases/php-$VersionPhp-nts-Win32-vs17-x64.zip"

    if (-not (Test-Path $zip)) {
        Paso "Descargando PHP $VersionPhp (unos 35 MB)..."
        # Sin la barra de progreso la descarga es mucho más rápida.
        $antes = $ProgressPreference
        $ProgressPreference = 'SilentlyContinue'
        try {
            Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
        } finally {
            $ProgressPreference = $antes
        }
        Ok 'Descargado.'
    }

    Paso 'Descomprimiendo...'
    New-Item -ItemType Directory -Force -Path $php | Out-Null
    Expand-Archive -LiteralPath $zip -DestinationPath $php -Force
    Ok 'PHP listo.'
}

Paso 'Aplicando el php.ini del punto de venta...'
Copy-Item (Join-Path $PSScriptRoot 'php\php.ini') -Destination $php -Force
Ok 'php.ini copiado.'

# --- El runtime de Visual C++ que PHP necesita y no trae ---
#
# php.exe depende de vcruntime140.dll, vcruntime140_1.dll y msvcp140.dll, y
# el .zip oficial de PHP NO las incluye: da por hecho que el equipo tiene
# instalado el "Microsoft Visual C++ Redistributable". En un Windows recién
# instalado no está, y PHP ni arranca.
#
# Se copian junto a php.exe porque Windows busca primero en la carpeta del
# ejecutable. Así el programa no depende de nada preinstalado y el cliente
# no tiene que instalar un redistribuible aparte.
Paso 'Empaquetando el runtime de Visual C++...'

$dllsVC = @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll')
$faltanVC = @()

foreach ($dll in $dllsVC) {
    $origenDll = Join-Path "$env:SystemRoot\System32" $dll

    if (Test-Path $origenDll) {
        Copy-Item $origenDll -Destination $php -Force
    } else {
        $faltanVC += $dll
    }
}

if ($faltanVC.Count -gt 0) {
    throw ("Este equipo no tiene las bibliotecas que PHP necesita: " +
           ($faltanVC -join ', ') + "`n" +
           "Instale el 'Microsoft Visual C++ Redistributable (x64)' y repita.")
}
Ok ("Runtime de Visual C++ incluido ($($dllsVC.Count) bibliotecas).")

# Comprobación real: sin alguna de estas extensiones el programa no arranca
# en el equipo del cliente y el error aparecería allá, no aquí.
Paso 'Verificando las extensiones...'
$faltan = & (Join-Path $php 'php.exe') -r @'
$necesarias = ['mbstring','fileinfo','openssl','curl','zip','intl','pdo_sqlite','sqlite3','gd'];
echo implode(',', array_diff($necesarias, get_loaded_extensions()));
'@
if ($LASTEXITCODE -ne 0) { throw 'El PHP empaquetado no arranca.' }
if ($faltan) { throw "Al PHP empaquetado le faltan extensiones: $faltan" }
Ok 'Todas las extensiones cargan.'


# --- 5. Instalador y herramientas -------------------------------------------

Titulo 'PASO 5  -  INSTALADOR Y HERRAMIENTAS'

Copy-Item (Join-Path $PSScriptRoot 'INSTALAR.bat') -Destination $Salida -Force
Copy-Item (Join-Path $PSScriptRoot 'LEEME.txt')    -Destination $Salida -Force
Copy-Item (Join-Path $PSScriptRoot 'instalar.ps1') -Destination $recursos -Force

# /MIR y no Copy-Item: Copy-Item deja lo que sobra. Al renombrar o borrar
# una herramienta, la versión vieja seguiría viajando a la memoria junto a
# la nueva, y el cliente acabaría con dos archivos casi iguales (pasó con
# 'Iniciar (modo diagnostico).bat', que además arrastraba un fallo ya
# corregido).
foreach ($carpeta in @('plantillas', 'scripts')) {
    $origenCarpeta = Join-Path $PSScriptRoot $carpeta
    $destinoCarpeta = Join-Path $recursos $carpeta
    New-Item -ItemType Directory -Force -Path $destinoCarpeta | Out-Null
    robocopy $origenCarpeta $destinoCarpeta /MIR /NFL /NDL /NJH /NJS /NP /R:1 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy de $carpeta falló con código $LASTEXITCODE" }
}
Ok 'Instalador y herramientas copiados.'


# --- La ventana de escritorio ----------------------------------------------
#
# Drogueria.exe es lo que abre el cajero: una ventana normal de Windows que
# arranca el servidor por dentro y muestra la aplicación en un WebView2. Sin
# navegador a la vista y sin internet.
#
# Se compila con el csc.exe que ya trae Windows (.NET Framework), así que no
# hace falta instalar Visual Studio ni el SDK de .NET en este equipo.

$scripts = Join-Path $recursos 'scripts'
$csc = "$env:SystemRoot\Microsoft.NET\Framework64\v4.0.30319\csc.exe"

if (-not (Test-Path $csc)) {
    throw "No se encontró el compilador de C# de Windows en:`n  $csc"
}

# Las tres bibliotecas de WebView2 salen del paquete oficial de NuGet. Son
# redistribuibles y viajan junto al .exe.
$nupkg = Join-Path $env:TEMP "webview2-$VersionWebView2.nupkg"

if (-not (Test-Path $nupkg)) {
    Paso "Descargando WebView2 $VersionWebView2..."
    $antes = $ProgressPreference
    $ProgressPreference = 'SilentlyContinue'
    try {
        Invoke-WebRequest -UseBasicParsing -OutFile $nupkg -Uri (
            'https://api.nuget.org/v3-flatcontainer/microsoft.web.webview2/' +
            "$VersionWebView2/microsoft.web.webview2.$VersionWebView2.nupkg")
    } finally {
        $ProgressPreference = $antes
    }
    Ok 'Descargado.'
}

Paso 'Extrayendo las bibliotecas de WebView2...'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($nupkg)
try {
    $piezas = @{
        'lib/net462/Microsoft.Web.WebView2.Core.dll'     = 'Microsoft.Web.WebView2.Core.dll'
        'lib/net462/Microsoft.Web.WebView2.WinForms.dll' = 'Microsoft.Web.WebView2.WinForms.dll'
        'runtimes/win-x64/native/WebView2Loader.dll'     = 'WebView2Loader.dll'
    }

    foreach ($clave in $piezas.Keys) {
        $entrada = $zip.Entries | Where-Object { $_.FullName -eq $clave }
        if (-not $entrada) { throw "El paquete de WebView2 no trae $clave" }
        [System.IO.Compression.ZipFileExtensions]::ExtractToFile(
            $entrada, (Join-Path $scripts $piezas[$clave]), $true)
    }
} finally {
    $zip.Dispose()
}
Ok 'Bibliotecas listas.'

Paso 'Compilando la ventana de escritorio...'
$fuente = Join-Path $PSScriptRoot 'app-escritorio\Drogueria.cs'
$exe = Join-Path $scripts 'Drogueria.exe'

# /target:winexe para que no abra una consola negra detrás de la ventana.
# /platform:x64 porque WebView2Loader.dll es de 64 bits.
$argumentos = @(
    '/nologo', '/target:winexe', '/platform:x64', '/optimize+',
    "/out:$exe",
    '/reference:System.dll',
    '/reference:System.Core.dll',
    '/reference:System.Drawing.dll',
    '/reference:System.Windows.Forms.dll',
    "/reference:$scripts\Microsoft.Web.WebView2.Core.dll",
    "/reference:$scripts\Microsoft.Web.WebView2.WinForms.dll",
    $fuente
)

$compilacion = & $csc $argumentos
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $exe)) {
    Write-Host ($compilacion | Out-String) -ForegroundColor DarkGray
    throw 'No se pudo compilar la ventana de escritorio.'
}
Ok ('Drogueria.exe compilado (' + [math]::Round((Get-Item $exe).Length / 1KB) + ' KB).')

# Windows PowerShell 5.1 lee un .ps1 sin BOM como ANSI y destroza cada
# tilde. Se revisan TODOS los que se empaquetan, no sólo instalar.ps1:
# las herramientas de la carpeta scripts\herramientas también llevan
# acentos en sus mensajes.
Paso 'Comprobando la codificación de los .ps1...'
$arreglados = 0
foreach ($ps1 in (Get-ChildItem $recursos -Recurse -Filter '*.ps1' -File)) {
    $bytes = [System.IO.File]::ReadAllBytes($ps1.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        continue
    }
    $texto = [System.IO.File]::ReadAllText($ps1.FullName, [System.Text.Encoding]::UTF8)
    [System.IO.File]::WriteAllText($ps1.FullName, $texto, (New-Object System.Text.UTF8Encoding $true))
    $arreglados++
}
$totalPs1 = (Get-ChildItem $recursos -Recurse -Filter '*.ps1' -File).Count
if ($arreglados -gt 0) {
    Ok ("Se le añadió el BOM a $arreglados de $totalPs1 archivo(s) .ps1.")
} else {
    Ok "Los $totalPs1 archivos .ps1 ya tenían BOM."
}


Paso 'Revisando la codificación de los .bat...'
# Un solo byte fuera de ASCII en un .bat descuadra el analizador de cmd.exe,
# que lee estos archivos por posición de byte: a partir de ahí empieza a
# cortar las líneas por delante. Cuando le toca a la comprobación de
# administrador de INSTALAR.bat, el instalador se relanza en bucle.
# Sólo los .bat propios: los de vendor\bin son de terceros, aquí no los
# ejecuta nadie, y si alguno trajera un acento abortaría una construcción
# que en realidad está bien.
$propios = @(Get-ChildItem $Salida -Filter '*.bat' -File)
$propios += @(Get-ChildItem (Join-Path $recursos 'scripts') -Recurse -Filter '*.bat' -File -ErrorAction SilentlyContinue)

$sospechosos = @()
foreach ($bat in $propios) {
    $crudo = [System.IO.File]::ReadAllBytes($bat.FullName)
    if ($crudo | Where-Object { $_ -gt 0x7F }) {
        $sospechosos += $bat.FullName.Substring($Salida.Length)
    }
}
if ($sospechosos.Count -gt 0) {
    throw ("Estos .bat tienen caracteres no ASCII y romperían el instalador:`n  " +
           ($sospechosos -join "`n  "))
}
Ok ('Los ' + $propios.Count + ' archivos .bat del instalador son ASCII puro.')

# Comprobación final de autonomía: que el paquete no dé por hecho nada
# instalado en el equipo del cliente. Va aquí, con todo ya en su sitio, y
# no en revisar.ps1, que se ejecuta antes de armar nada.
Paso 'Comprobando que no dependa de nada preinstalado...'

$imprescindibles = @{
    'php\php.exe'                                     = 'el motor'
    'php\vcruntime140.dll'                            = 'runtime de VC++ (PHP no arranca sin él)'
    'php\vcruntime140_1.dll'                          = 'runtime de VC++'
    'php\msvcp140.dll'                                = 'runtime de VC++'
    'php\php.ini'                                     = 'la configuración de PHP'
    'scripts\Drogueria.exe'                           = 'la ventana del programa'
    'scripts\WebView2Loader.dll'                      = 'WebView2 (nativo)'
    'scripts\Microsoft.Web.WebView2.Core.dll'         = 'WebView2 (gestionado)'
    'scripts\Microsoft.Web.WebView2.WinForms.dll'     = 'WebView2 (formularios)'
    'app\vendor\autoload.php'                         = 'las dependencias de PHP'
    'app\public\build\manifest.json'                  = 'los estilos compilados'
    'app\database\migrations'                         = 'las migraciones'
}

$ausentes = @()
foreach ($relativa in $imprescindibles.Keys) {
    if (-not (Test-Path (Join-Path $recursos $relativa))) {
        $ausentes += ("$relativa  ($($imprescindibles[$relativa]))")
    }
}

if ($ausentes.Count -gt 0) {
    throw ("Al paquete le faltan piezas imprescindibles:`n  " + ($ausentes -join "`n  "))
}

Ok 'El paquete es autónomo: no necesita nada preinstalado.'


# --- 6. A la memoria USB ----------------------------------------------------

$archivos = Get-ChildItem $Salida -Recurse -File -Force
$mb = [math]::Round((($archivos | Measure-Object Length -Sum).Sum) / 1MB, 1)

if ($Usb) {
    Titulo 'PASO 6  -  COPIA A LA MEMORIA USB'

    $raiz = $Usb.TrimEnd('\') + '\'
    if (-not (Test-Path $raiz)) { throw "No se encuentra la unidad $Usb" }

    $etiqueta = (Get-Volume -DriveLetter $Usb.Substring(0,1)).FileSystemLabel
    Paso "Copiando $mb MB a $raiz ($etiqueta)..."

    robocopy $Salida $raiz /MIR /MT:16 /NFL /NDL /NJH /NJS /NP /R:1 /W:1 `
        /XD 'System Volume Information' '$RECYCLE.BIN' | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy falló con código $LASTEXITCODE" }
    Ok 'Memoria USB lista.'
}

Titulo 'PAQUETE TERMINADO'
Write-Host ("  Archivos : " + $archivos.Count)
Write-Host ("  Tamaño   : $mb MB")
Write-Host ("  Carpeta  : $Salida")
if ($Usb) { Write-Host ("  Memoria  : " + $Usb.TrimEnd('\') + '\') }
Write-Host ''
Write-Host '  En el equipo del cliente: doble clic en INSTALAR.bat' -ForegroundColor Green
Write-Host ''

exit 0
