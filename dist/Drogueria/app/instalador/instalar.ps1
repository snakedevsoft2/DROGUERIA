# ---------------------------------------------------------------------------
# Instalador del punto de venta de la droguería.
#
# Copia el programa al disco duro del equipo, lo deja configurado y crea el
# acceso directo del escritorio. Después de instalar, la memoria USB se puede
# retirar: no queda nada dependiendo de ella.
#
# Lo lanza INSTALAR.bat, que se encarga de pedir permisos de administrador.
# ---------------------------------------------------------------------------

$ErrorActionPreference = 'Stop'

# Aquí NO se toca la codificación de la consola. INSTALAR.bat no hace
# 'chcp 65001' a propósito (descuadraba el analizador de .bat), así que la
# consola queda en su página OEM y PowerShell ya codifica bien contra ella.
# Forzar UTF-8 sería precisamente lo que rompería las tildes.

$origen = Split-Path -Parent $PSScriptRoot   # la raíz de la USB
$recursos = $PSScriptRoot

# --- Utilidades de presentación -------------------------------------------

function Titulo($texto) {
    Write-Host ''
    Write-Host ('=' * 62) -ForegroundColor Cyan
    Write-Host ("  $texto") -ForegroundColor Cyan
    Write-Host ('=' * 62) -ForegroundColor Cyan
    Write-Host ''
}

function Paso($texto)  { Write-Host "  > $texto" -ForegroundColor White }
function Ok($texto)    { Write-Host "    OK  $texto" -ForegroundColor Green }
function Aviso($texto) { Write-Host "    !   $texto" -ForegroundColor Yellow }
function Malo($texto)  { Write-Host "    X   $texto" -ForegroundColor Red }

function Preguntar($texto, $porDefecto) {
    if ($porDefecto) { $r = Read-Host "  $texto [$porDefecto]" } else { $r = Read-Host "  $texto" }
    if ([string]::IsNullOrWhiteSpace($r)) { return $porDefecto }
    return $r.Trim()
}

function PreguntarSiNo($texto, $porDefectoSi) {
    if ($porDefectoSi) { $sufijo = '[S/n]' } else { $sufijo = '[s/N]' }
    $r = Read-Host "  $texto $sufijo"
    if ([string]::IsNullOrWhiteSpace($r)) { return $porDefectoSi }
    return ($r.Trim().ToUpper().StartsWith('S'))
}

function Abortar($texto) {
    Write-Host ''
    Malo $texto
    Write-Host ''
    Read-Host '  Pulse Enter para cerrar'
    exit 1
}


# --- 0. Comprobaciones previas --------------------------------------------

Clear-Host
Titulo 'INSTALACIÓN DEL PUNTO DE VENTA'

Write-Host '  Este asistente instala el programa en el disco duro de este' -ForegroundColor Gray
Write-Host '  equipo. Al terminar podrá retirar la memoria USB.' -ForegroundColor Gray
Write-Host ''

foreach ($necesario in @("$recursos\app\artisan", "$recursos\php\php.exe", "$recursos\plantillas\env.plantilla")) {
    if (-not (Test-Path $necesario)) {
        Abortar "La memoria USB está incompleta: falta $necesario"
    }
}

# El PHP que viaja en la memoria es de 64 bits. En un Windows de 32 bits no
# arrancaría, y el error que da ("no es una aplicación Win32 válida") no le
# dice nada a nadie: mejor avisar aquí.
if (-not [Environment]::Is64BitOperatingSystem) {
    Abortar 'Este equipo tiene Windows de 32 bits y el programa necesita uno de 64.'
}

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()
        ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Aviso 'No se está ejecutando como administrador.'
    Aviso 'Se podrá instalar, pero no compartir la impresora automáticamente.'
    Write-Host ''
}

# La ventana del programa está hecha con .NET Framework 4.6.2, que es lo
# que exigen las bibliotecas de WebView2. Windows 10 lo trae desde la
# actualización de agosto de 2016 y Windows 11 siempre; en algo más viejo
# el .exe no arrancaría y Windows daría un diálogo que no explica nada.
$releaseNet = 0
$claveNet = 'HKLM:\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full'

if (Test-Path $claveNet) {
    $releaseNet = [int](Get-ItemProperty $claveNet -ErrorAction SilentlyContinue).Release
}

if ($releaseNet -lt 394802) {
    Aviso 'Este Windows tiene una versión antigua de .NET Framework.'
    Write-Host ''
    Write-Host '  La ventana del programa necesita .NET Framework 4.6.2 o' -ForegroundColor Gray
    Write-Host '  posterior. Actualice Windows, o instale (una sola vez):' -ForegroundColor Gray
    Write-Host '    https://dotnet.microsoft.com/download/dotnet-framework' -ForegroundColor White
    Write-Host ''
    Write-Host '  Se puede instalar igual: si el programa no abriera, queda' -ForegroundColor Gray
    Write-Host '  "Iniciar (modo diagnostico).bat" para usarlo en el navegador.' -ForegroundColor Gray
    Write-Host ''
}

# La ventana del programa usa WebView2. Windows 11 lo trae de fábrica y
# Windows 10 lo recibe con las actualizaciones de Edge, pero en un equipo
# sin actualizar puede faltar. No impide instalar: el programa lo detecta y
# ofrece abrirse en el navegador. Pero conviene saberlo ANTES de estar
# delante del cliente.
$clavesWebView = @(
    'HKLM:\SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}',
    'HKLM:\SOFTWARE\Microsoft\EdgeUpdate\Clients\{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}'
)

$versionWebView = $null
foreach ($clave in $clavesWebView) {
    if (Test-Path $clave) {
        $pv = (Get-ItemProperty $clave -ErrorAction SilentlyContinue).pv
        if ($pv) { $versionWebView = $pv; break }
    }
}

if (-not $versionWebView) {
    Aviso 'Este equipo no tiene el "WebView2 Runtime" de Microsoft.'
    Write-Host ''
    Write-Host '  El programa se instalará y funcionará igual, pero se abrirá' -ForegroundColor Gray
    Write-Host '  en el navegador en vez de en su propia ventana.' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Para la ventana propia, instale (una sola vez, con internet):' -ForegroundColor Gray
    Write-Host '    https://go.microsoft.com/fwlink/p/?LinkId=2124703' -ForegroundColor White
    Write-Host ''
}


# --- 1. Dónde instalar -----------------------------------------------------

Titulo 'PASO 1 de 6  -  CARPETA DE INSTALACIÓN'

Write-Host '  Se recomienda dejar la carpeta por defecto.' -ForegroundColor Gray
Write-Host ''
$destino = Preguntar 'Carpeta de instalación' 'C:\Drogueria'

$actualizacion = $false
if (Test-Path "$destino\app\artisan") {
    Write-Host ''
    Aviso "Ya hay una instalación en $destino"
    Write-Host ''
    Write-Host '  Se actualizará el programa CONSERVANDO los datos y la' -ForegroundColor Gray
    Write-Host '  configuración actuales (productos, ventas, impresora).' -ForegroundColor Gray
    Write-Host ''
    if (-not (PreguntarSiNo '¿Continuar con la actualización?' $true)) { exit 0 }
    $actualizacion = $true
}

# Con el programa abierto, Windows tiene bloqueados el .exe y las DLL, y la
# copia se queda a medias. Se cierran tanto la ventana como su servidor.
$enUso = @(Get-Process Drogueria, php -ErrorAction SilentlyContinue |
    Where-Object { $_.Path -like "$destino\*" })

if ($enUso.Count -gt 0) {
    Paso 'Cerrando el programa, que estaba abierto...'
    $enUso | Stop-Process -Force
    # El WebView2 tarda un instante en soltar sus archivos.
    Start-Sleep -Seconds 3
    Ok 'Cerrado.'
}


# --- 2. Datos del negocio --------------------------------------------------

$nombreTienda = 'Droguería SUSALUD'
$ciudad = 'Jamundí'
$telefono = ''
$anchoRollo = '80mm'
$columnas = '42'

if (-not $actualizacion) {
    Titulo 'PASO 2 de 6  -  DATOS DE LA DROGUERÍA'

    Write-Host '  Estos datos salen impresos en el encabezado del tiquete.' -ForegroundColor Gray
    Write-Host '  Se pueden cambiar después.' -ForegroundColor Gray
    Write-Host ''

    $nombreTienda = Preguntar 'Nombre de la droguería' $nombreTienda
    $ciudad       = Preguntar 'Ciudad' $ciudad
    $telefono     = Preguntar 'Teléfono' '312 848 8952'

    Write-Host ''
    Write-Host '  Ancho del rollo de papel:' -ForegroundColor Gray
    Write-Host '    1) 80mm  - el normal, y el que trae de fábrica la Epson TM-T20II' -ForegroundColor Gray
    Write-Host '    2) 58mm  - rollo angosto (sólo si le pusieron la guía plástica)' -ForegroundColor Gray
    Write-Host ''
    if ((Preguntar 'Opción' '1') -eq '2') {
        $anchoRollo = '58mm'
        $columnas = '32'
    }
} else {
    Titulo 'PASO 2 de 6  -  DATOS DE LA DROGUERÍA'
    Ok 'Se conserva la configuración actual.'
}


# --- 3. Copiar archivos ----------------------------------------------------

Titulo 'PASO 3 de 6  -  COPIANDO ARCHIVOS'

Paso 'Creando la carpeta del programa...'
New-Item -ItemType Directory -Force -Path $destino | Out-Null
Ok $destino

Paso 'Copiando el programa (puede tardar un minuto)...'
# /XD .env y database: en una actualización no se deben pisar los datos.
robocopy "$recursos\app" "$destino\app" /E /NFL /NDL /NJH /NJS /NP /R:1 /W:1 /XF ".env" | Out-Null
if ($LASTEXITCODE -ge 8) { Abortar "No se pudieron copiar los archivos (robocopy $LASTEXITCODE)." }
Ok 'Programa copiado.'

Paso 'Copiando PHP...'
robocopy "$recursos\php" "$destino\php" /E /NFL /NDL /NJH /NJS /NP /R:1 /W:1 | Out-Null
if ($LASTEXITCODE -ge 8) { Abortar "No se pudo copiar PHP (robocopy $LASTEXITCODE)." }
Ok 'PHP copiado.'

Paso 'Copiando herramientas...'

# Las herramientas van sueltas en la raíz, revueltas con cosas que NO se
# pueden tocar (app\, php\, respaldos\, la base de datos...), así que aquí
# no sirve un robocopy /MIR.
#
# En su lugar se deja un inventario de lo que se instaló. En la siguiente
# actualización se borra lo que figuraba en el inventario viejo y ya no
# existe: si no, una herramienta renombrada o retirada se queda conviviendo
# con la nueva en el equipo del cliente, arrastrando fallos ya corregidos.
#
# El archivo va dentro de herramientas\ y SIN atributo oculto. Estuvo oculto
# y en la raíz, y dio problemas: Get-Item no ve un archivo oculto si no se
# le pasa -Force, así que la segunda instalación fallaba al intentar
# releerlo. Un archivo de texto más en una carpeta de uso interno no molesta
# a nadie; esconderlo sí costaba caro.
$inventario = "$destino\herramientas\inventario.txt"

$nuevas = @(Get-ChildItem "$recursos\scripts" -Recurse -File |
    ForEach-Object { $_.FullName.Substring("$recursos\scripts".Length).TrimStart('\') })

# Restos de versiones anteriores al inventario. El lanzador de VBScript lo
# sustituyó Drogueria.exe; si se queda, el equipo acaba con dos formas de
# abrir el programa y una de ellas es la que fallaba.
foreach ($obsoleta in @('Drogueria.vbs', 'Iniciar (modo diagnóstico).bat', '.herramientas.txt')) {
    $ruta = Join-Path $destino $obsoleta
    # Test-Path ve los archivos ocultos sin más; el -Force que llevaba aquí
    # no existe en Windows PowerShell 5.1 y reventaba la instalación.
    if (Test-Path -LiteralPath $ruta) {
        Remove-Item -LiteralPath $ruta -Force -ErrorAction SilentlyContinue
    }
}

# Todo el mantenimiento del inventario es una comodidad, no un requisito: si
# algo aquí falla, la instalación tiene que seguir. Antes no era así y un
# tropiezo tonto dejaba el programa a medio copiar.
try {
    if (Test-Path -LiteralPath $inventario) {
        $sobran = @(Get-Content -LiteralPath $inventario -Encoding UTF8 |
            Where-Object { $_ -and ($nuevas -notcontains $_) })

        foreach ($vieja in $sobran) {
            $ruta = Join-Path $destino $vieja
            if (Test-Path -LiteralPath $ruta) {
                Remove-Item -LiteralPath $ruta -Force -ErrorAction SilentlyContinue
            }
        }

        if ($sobran.Count -gt 0) {
            Ok ("Se retiraron $($sobran.Count) herramienta(s) que ya no se usan.")
        }
    }
} catch {
    Aviso 'No se pudo revisar el inventario anterior (se sigue igual).'
}

# -Recurse: las herramientas incluyen la subcarpeta 'herramientas\' con los
# scripts de PowerShell que invocan los .bat.
Copy-Item "$recursos\scripts\*" -Destination $destino -Force -Recurse

try {
    Set-Content -LiteralPath $inventario -Value $nuevas -Encoding UTF8
} catch {
    Aviso 'No se pudo guardar el inventario de herramientas.'
}

Ok 'Herramientas copiadas.'

# opcache tiene que apuntar a una carpeta real y escribible de ESTA
# instalación. Sin ese caché en disco, PHP falla al arrancar de vez en
# cuando por el problema de ASLR de Windows (está explicado en php.ini) y
# el programa simplemente no abre. Va antes de cualquier llamada a PHP.
Paso 'Ajustando la caché de PHP...'
$cachePhp = "$destino\php\cache"
New-Item -ItemType Directory -Force -Path $cachePhp | Out-Null

$ini = Get-Content "$destino\php\php.ini" -Raw -Encoding UTF8
$ini = $ini -replace '(?m)^opcache\.file_cache=.*$', ('opcache.file_cache="' + $cachePhp + '"')

# Y el registro de avisos de PHP a un archivo: si se queda en la salida de
# error, se cuela en medio de las herramientas de diagnóstico y confunde a
# quien las está usando.
$ini = $ini -replace '(?m)^error_log=.*$', ('error_log="' + $destino + '\php\php-errores.log"')
$ini = $ini -replace '(?m)^error_log = .*$', ('error_log = "' + $destino + '\php\php-errores.log"')

[System.IO.File]::WriteAllText("$destino\php\php.ini", $ini, (New-Object System.Text.UTF8Encoding $false))
Ok 'Caché ajustada.'

foreach ($carpeta in @('respaldos',
                       'app\storage\logs', 'app\storage\framework\cache\data',
                       'app\storage\framework\sessions', 'app\storage\framework\views',
                       'app\bootstrap\cache', 'app\database')) {
    New-Item -ItemType Directory -Force -Path "$destino\$carpeta" | Out-Null
}

$puerto = '8347'
Set-Content -Path "$destino\puerto.txt" -Value $puerto -Encoding ASCII


# --- 4. Configuración ------------------------------------------------------

Titulo 'PASO 4 de 6  -  CONFIGURACIÓN'

$env_destino = "$destino\app\.env"

if (Test-Path $env_destino) {
    Ok 'Se conserva el archivo de ajustes existente.'
} else {
    Paso 'Creando el archivo de ajustes...'
    $plantilla = Get-Content "$recursos\plantillas\env.plantilla" -Raw -Encoding UTF8
    $plantilla = $plantilla.Replace('__STORE_NAME__', $nombreTienda)
    $plantilla = $plantilla.Replace('__STORE_CITY__', $ciudad)
    $plantilla = $plantilla.Replace('__STORE_PHONE__', $telefono)
    $plantilla = $plantilla.Replace('__RECEIPT_WIDTH__', $anchoRollo)
    $plantilla = $plantilla.Replace('__PRINTER_COLUMNS__', $columnas)
    $plantilla = $plantilla.Replace('__PRINTER_NAME__', '')
    $plantilla = $plantilla.Replace('__PORT__', $puerto)
    # Sin BOM: Laravel lee el .env carácter a carácter y el BOM se le cuela
    # dentro del nombre de la primera variable.
    [System.IO.File]::WriteAllText($env_destino, $plantilla, (New-Object System.Text.UTF8Encoding $false))
    Ok 'Ajustes creados.'
}

$php = "$destino\php\php.exe"
$artisan = "$destino\app\artisan"

Paso 'Generando la clave de seguridad...'
& $php $artisan key:generate --force --no-interaction 2>&1 | Out-Null
Ok 'Clave generada.'

$bd = "$destino\app\database\database.sqlite"
if (-not (Test-Path $bd)) {
    Paso 'Creando la base de datos...'
    New-Item -ItemType File -Path $bd | Out-Null
    Ok 'Base de datos creada (vacía).'
} else {
    Paso 'Respaldando la base de datos antes de actualizarla...'
    & $php $artisan drogueria:respaldar --destino="$destino\respaldos" 2>&1 | Out-Null
    Ok 'Respaldo hecho.'
}

Paso 'Preparando las tablas...'
$salida = & $php $artisan migrate --force --no-interaction 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host ($salida | Out-String) -ForegroundColor DarkGray
    Abortar 'No se pudieron preparar las tablas de la base de datos.'
}
Ok 'Tablas listas.'

Paso 'Optimizando para que abra rápido...'
& $php $artisan config:clear 2>&1 | Out-Null
& $php $artisan route:cache 2>&1 | Out-Null
& $php $artisan view:cache 2>&1 | Out-Null
Ok 'Optimizado.'


# --- 5. Impresora ----------------------------------------------------------

Titulo 'PASO 5 de 6  -  IMPRESORA DE TIQUETES'

$nombreCompartido = ''

try {
    $impresoras = @(Get-Printer -ErrorAction Stop | Where-Object { $_.PortName -ne 'nul:' })
} catch {
    $impresoras = @()
}

if ($impresoras.Count -eq 0) {
    Aviso 'No se detectaron impresoras instaladas en Windows.'
    Write-Host ''
    Write-Host '  Instale primero el controlador de la Epson TM-T20II, y luego' -ForegroundColor Gray
    Write-Host '  ejecute "Configurar impresora" en la carpeta del programa.' -ForegroundColor Gray
    Write-Host ''
} else {
    Write-Host '  Impresoras encontradas:' -ForegroundColor Gray
    Write-Host ''
    for ($i = 0; $i -lt $impresoras.Count; $i++) {
        $p = $impresoras[$i]
        if ($p.Shared) { $marca = "compartida como '$($p.ShareName)'" } else { $marca = 'no compartida' }
        Write-Host ("    {0}) {1}  ({2})" -f ($i + 1), $p.Name, $marca)
    }
    Write-Host ("    0) Ninguna / configurarla después")
    Write-Host ''
    Write-Host '  Elija la impresora de TIQUETES (la térmica de rollo).' -ForegroundColor Gray
    Write-Host ''

    $eleccion = Preguntar 'Número' '0'
    $indice = 0
    if ([int]::TryParse($eleccion, [ref]$indice) -and $indice -ge 1 -and $indice -le $impresoras.Count) {
        $elegida = $impresoras[$indice - 1]

        # escpos-php sólo puede mandarle bytes crudos a una impresora
        # compartida: es la única vía que Windows le deja a PHP.
        #
        # Y sólo acepta nombres de letras, números, guiones y espacios
        # sueltos: con una tilde o un punto se niega a imprimir. Por eso un
        # nombre ya existente hay que validarlo antes de darlo por bueno.
        $patronValido = '^[\d\w-]+(\s[\d\w-]+)*$'

        if ($elegida.Shared -and $elegida.ShareName -and
            ($elegida.ShareName -match $patronValido) -and
            ($elegida.ShareName -notmatch '[^\x00-\x7F]')) {
            $nombreCompartido = $elegida.ShareName
            Ok "Ya estaba compartida como '$nombreCompartido'."
        } else {
            if ($elegida.Shared -and $elegida.ShareName) {
                Aviso "Está compartida como '$($elegida.ShareName)', un nombre que"
                Aviso 'el sistema de impresión no admite (tildes o símbolos).'
                Write-Host ''
            }
            $sugerido = 'TIQUETERA'
            Write-Host ''
            Write-Host '  Para que el programa pueda imprimir directamente, Windows' -ForegroundColor Gray
            Write-Host '  exige que la impresora esté compartida. Se comparte sólo' -ForegroundColor Gray
            Write-Host '  en este equipo; no queda visible en internet.' -ForegroundColor Gray
            Write-Host ''
            if (PreguntarSiNo "¿Compartirla como '$sugerido'?" $true) {
                try {
                    Set-Printer -Name $elegida.Name -Shared $true -ShareName $sugerido -ErrorAction Stop
                    $nombreCompartido = $sugerido
                    Ok "Compartida como '$nombreCompartido'."
                } catch {
                    Malo "No se pudo compartir: $($_.Exception.Message)"
                    Aviso 'Compártala a mano y luego use "Configurar impresora".'
                }
            }
        }

        if ($nombreCompartido) {
            $texto = Get-Content $env_destino -Raw -Encoding UTF8
            $texto = $texto -replace '(?m)^PRINTER_NAME=.*$', "PRINTER_NAME=$nombreCompartido"
            [System.IO.File]::WriteAllText($env_destino, $texto, (New-Object System.Text.UTF8Encoding $false))
            & $php $artisan config:clear 2>&1 | Out-Null

            Write-Host ''
            if (PreguntarSiNo '¿Imprimir un tiquete de prueba ahora?' $true) {
                Write-Host ''

                # artisan escribe en UTF-8 y la consola está en su página
                # OEM, donde cada tilde saldría partida en dos símbolos. Se
                # cambia sólo para esta llamada y se deja como estaba: el
                # resto del asistente lo escribe PowerShell, que ya codifica
                # bien contra la página original.
                $paginaPrevia = 850
                try { $paginaPrevia = [Console]::OutputEncoding.CodePage } catch { }

                try { chcp 65001 | Out-Null } catch { }
                & $php $artisan drogueria:impresora
                try { chcp $paginaPrevia | Out-Null } catch { }
            }
        }
    } else {
        Aviso 'Se omitió la impresora. Use "Configurar impresora" cuando quiera.'
    }
}


# --- 6. Accesos directos ---------------------------------------------------

Titulo 'PASO 6 de 6  -  ACCESO DIRECTO'

$lanzador = "$destino\Drogueria.exe"
$icono = "$destino\app\public\favicon.ico"
if (-not (Test-Path $icono)) { $icono = "$env:SystemRoot\System32\shell32.dll,21" }

function CrearAccesoDirecto($ruta, $nombre) {
    $shell = New-Object -ComObject WScript.Shell
    $lnk = $shell.CreateShortcut((Join-Path $ruta "$nombre.lnk"))
    # Apunta directo al programa: no hay intérprete de por medio, así que
    # ni wscript.exe ni las políticas de scripts pueden estorbar.
    $lnk.TargetPath = $lanzador
    $lnk.WorkingDirectory = $destino
    $lnk.IconLocation = $icono
    $lnk.Description = 'Punto de venta de la droguería'
    $lnk.Save()
}

# El escritorio público necesita permisos de administrador. Si el instalador
# se ejecutó sin ellos, se cae al escritorio del usuario actual: el icono es
# lo único que el cajero necesita, así que no puede quedarse sin él.
function CrearEnElPrimeroQuePueda($carpetas, $nombre) {
    foreach ($carpeta in $carpetas) {
        if (-not $carpeta) { continue }
        try {
            if (-not (Test-Path $carpeta)) { New-Item -ItemType Directory -Force -Path $carpeta | Out-Null }
            CrearAccesoDirecto $carpeta $nombre
            return $carpeta
        } catch {
            continue
        }
    }
    return $null
}

Paso 'Creando el acceso directo del escritorio...'
$escritorios = @(
    [Environment]::GetFolderPath('CommonDesktopDirectory'),
    [Environment]::GetFolderPath('Desktop')
)
$donde = CrearEnElPrimeroQuePueda $escritorios 'Droguería'

# Un icono y no dos: si una instalación anterior dejó uno en el otro
# escritorio (por ejemplo el público, cuando se instaló como administrador),
# se retira para no dejar al cajero eligiendo entre dos iconos iguales, uno
# de ellos apuntando al lanzador viejo.
if ($donde) {
    foreach ($otro in $escritorios) {
        if ($otro -and $otro -ne $donde) {
            $duplicado = Join-Path $otro 'Droguería.lnk'
            if (Test-Path -LiteralPath $duplicado) {
                Remove-Item -LiteralPath $duplicado -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

if ($donde) {
    Ok "Acceso directo creado en el escritorio."
} else {
    Malo 'No se pudo crear el acceso directo en el escritorio.'
    Aviso "Cree uno a mano hacia:  $lanzador"
}

Paso 'Agregando al menú Inicio...'
$menu = CrearEnElPrimeroQuePueda @(
    (Join-Path ([Environment]::GetFolderPath('CommonPrograms')) 'Droguería'),
    (Join-Path ([Environment]::GetFolderPath('Programs')) 'Droguería')
) 'Droguería'

if ($menu) { Ok 'Agregado al menú Inicio.' }
else { Aviso 'No se pudo agregar al menú Inicio (no es indispensable).' }

Write-Host ''
if (PreguntarSiNo '¿Abrir el programa automáticamente al encender el equipo?' $false) {
    $auto = CrearEnElPrimeroQuePueda @(
        [Environment]::GetFolderPath('CommonStartup'),
        [Environment]::GetFolderPath('Startup')
    ) 'Droguería'

    if ($auto) { Ok 'Se abrirá solo al encender el equipo.' }
    else { Aviso 'No se pudo configurar el inicio automático.' }
}


# --- Final -----------------------------------------------------------------

Titulo 'INSTALACIÓN TERMINADA'

Write-Host "  El programa quedó instalado en:  $destino" -ForegroundColor White
Write-Host ''
Write-Host '  Para usarlo: doble clic en el icono "Droguería" del escritorio.' -ForegroundColor White
Write-Host ''
Write-Host '  Ya puede retirar la memoria USB.' -ForegroundColor Green
Write-Host ''
Write-Host '  ---------------------------------------------------------' -ForegroundColor DarkGray
Write-Host '  En la carpeta del programa quedaron estas herramientas:' -ForegroundColor Gray
Write-Host ''
Write-Host '    Respaldar datos ............ copia de seguridad (úsela seguido)' -ForegroundColor Gray
Write-Host '    Configurar impresora ....... si cambian la impresora' -ForegroundColor Gray
Write-Host '    Cargar datos de ejemplo .... catálogo de prueba' -ForegroundColor Gray
Write-Host '    Cerrar programa ............ detiene el punto de venta' -ForegroundColor Gray
Write-Host '    Iniciar (modo diagnostico) . para ver errores si algo falla' -ForegroundColor Gray
Write-Host '  ---------------------------------------------------------' -ForegroundColor DarkGray
Write-Host ''

if (PreguntarSiNo '¿Abrir el programa ahora?' $true) {
    Write-Host ''
    Paso 'Abriendo el programa (la primera vez tarda unos segundos)...'

    # A través de explorer.exe y no con Start-Process directo.
    #
    # El instalador corre como administrador y todo lo que lance hereda esa
    # elevación, pero el punto de venta tiene que correr como el usuario
    # normal: es quien lo va a usar todos los días, y los archivos que cree
    # (sesiones, caché) deben pertenecerle. explorer.exe corre con la
    # integridad del usuario, así que lo que arranca desde él vuelve al
    # nivel correcto.
    $abierto = $false
    try {
        Start-Process -FilePath "$env:SystemRoot\explorer.exe" -ArgumentList """$lanzador"""
        $abierto = $true
    } catch {
        Aviso "No se pudo abrir automáticamente: $($_.Exception.Message)"
    }

    if ($abierto) {
        # Se espera a que el servidor responda para poder decir si arrancó
        # de verdad, en vez de dar por bueno el lanzamiento.
        $listo = $false
        for ($i = 0; $i -lt 40; $i++) {
            Start-Sleep -Milliseconds 750
            try {
                $r = Invoke-WebRequest -Uri "http://127.0.0.1:$puerto/up" -UseBasicParsing -TimeoutSec 2
                if ($r.StatusCode -ge 200) { $listo = $true; break }
            } catch {
                # Todavía no responde; se sigue esperando.
            }
        }

        if ($listo) {
            Ok 'El programa está funcionando.'
        } else {
            Aviso 'El programa tardó más de lo normal en responder.'
            Aviso 'Abra el icono "Droguería" del escritorio en un momento.'
        }
    }

    Write-Host ''
    Write-Host "  Si no se abrió ninguna ventana, use el icono del escritorio" -ForegroundColor Gray
    Write-Host "  o escriba esta dirección en el navegador:" -ForegroundColor Gray
    Write-Host "      http://127.0.0.1:$puerto" -ForegroundColor White
}

Write-Host ''
Read-Host '  Pulse Enter para cerrar el instalador'

exit 0
