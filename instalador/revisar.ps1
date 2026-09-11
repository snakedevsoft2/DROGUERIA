# ---------------------------------------------------------------------------
# Revisión del instalador antes de entregarlo.
#
#   .\instalador\revisar.ps1
#
# Comprueba las cosas que ya han roto una instalación al menos una vez. Cada
# regla está aquí porque algo falló en el equipo del cliente, no por
# completitud teórica.
#
# Devuelve 0 si todo está bien, 1 si hay algo que arreglar.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$base = $PSScriptRoot
$fallos = @()
$avisos = @()

function Titulo($t) {
    Write-Host ''
    Write-Host ('-' * 66) -ForegroundColor DarkGray
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ('-' * 66) -ForegroundColor DarkGray
}

function Bien($t) { Write-Host "    OK   $t" -ForegroundColor Green }
function Mal($t)  { Write-Host "    MAL  $t" -ForegroundColor Red;    $script:fallos += $t }
function Ojo($t)  { Write-Host "    !    $t" -ForegroundColor Yellow; $script:avisos += $t }


# --- 1. Codificación de los .bat -------------------------------------------

Titulo '1. Archivos .bat: ASCII puro, sin BOM, sin chcp'

# cmd.exe lee los .bat por posición de byte. Un carácter multibyte descuadra
# el analizador y a partir de ahí lee las líneas cortadas por delante; cuando
# le toca a la comprobación de administrador, el instalador entra en bucle.
$bats = @(Get-ChildItem $base -Filter '*.bat' -File)
$bats += @(Get-ChildItem (Join-Path $base 'scripts') -Recurse -Filter '*.bat' -File -ErrorAction SilentlyContinue)

foreach ($bat in $bats) {
    $crudo = [System.IO.File]::ReadAllBytes($bat.FullName)
    $problemas = @()

    if ($crudo | Where-Object { $_ -gt 0x7F }) { $problemas += 'caracteres no ASCII' }

    if ($crudo.Length -ge 3 -and $crudo[0] -eq 0xEF -and $crudo[1] -eq 0xBB -and $crudo[2] -eq 0xBF) {
        $problemas += 'BOM'
    }

    # Finales de línea de Windows. Con sólo LF, cmd.exe tolera lo sencillo
    # pero se atraganta con 'call', las etiquetas y los bloques if (...): son
    # justo las piezas de las que depende INSTALAR.bat.
    $texto = [System.Text.Encoding]::ASCII.GetString($crudo)
    $saltos = ([regex]::Matches($texto, "`n")).Count
    $conRetorno = ([regex]::Matches($texto, "`r`n")).Count

    if ($saltos -gt 0 -and $conRetorno -lt $saltos) {
        $problemas += "finales de línea LF ($($saltos - $conRetorno) de $saltos); deben ser CRLF"
    }

    # 'chcp 65001' NO está prohibido: hace falta para que la salida UTF-8 de
    # PHP se lea bien. Lo que rompe es chcp junto a caracteres multibyte en
    # el propio .bat, y eso ya lo cubre la comprobación de ASCII de arriba.
    # INSTALAR.bat es la excepción: ahí quien escribe es PowerShell, que ya
    # codifica bien contra la página OEM, y es el archivo que entró en bucle.
    if ($bat.Name -eq 'INSTALAR.bat' -and $texto -match '(?m)^\s*chcp\b') {
        $problemas += 'INSTALAR.bat no debe usar chcp'
    }

    # 'set /p' NO LEE NADA cuando la consola está en UTF-8. Es un fallo de
    # cmd.exe, comprobado: la variable queda vacía y el script sigue como si
    # el usuario no hubiera escrito nada. Las preguntas van en PowerShell.
    # Se busca en cualquier posición de la línea, no sólo al principio: un
    # 'if exist X set /p ...' se coló por esa rendija y leía el puerto mal.
    # Se saltan los comentarios, donde la expresión aparece explicada.
    foreach ($linea in ($texto -split "`r?`n")) {
        $limpia = $linea.Trim()
        if ($limpia -match '^(rem\b|::)' ) { continue }
        if ($limpia -match '(?i)\bset\s+/p\b') {
            $problemas += "usa 'set /p', que no lee con chcp 65001 (pregunte desde PowerShell)"
            break
        }
    }

    if ($problemas.Count -gt 0) {
        Mal ("$($bat.Name): " + ($problemas -join ', '))
    }
}

if ($fallos.Count -eq 0) { Bien "Los $($bats.Count) archivos .bat están limpios." }


# --- 2. Codificación de los .ps1 -------------------------------------------

Titulo '2. Archivos .ps1: UTF-8 con BOM'

# Windows PowerShell 5.1 lee un .ps1 sin BOM como ANSI y convierte cada tilde
# en dos caracteres basura.
$ps1s = @(Get-ChildItem $base -Recurse -Filter '*.ps1' -File |
    Where-Object { $_.Name -ne 'revisar.ps1' })

$sinBom = @()
foreach ($ps1 in $ps1s) {
    $crudo = [System.IO.File]::ReadAllBytes($ps1.FullName)
    $tieneBom = $crudo.Length -ge 3 -and $crudo[0] -eq 0xEF -and $crudo[1] -eq 0xBB -and $crudo[2] -eq 0xBF
    $tieneAcentos = $crudo | Where-Object { $_ -gt 0x7F }

    if (-not $tieneBom -and $tieneAcentos) { $sinBom += $ps1.Name }
}

if ($sinBom.Count -gt 0) {
    Mal ('Sin BOM y con acentos: ' + ($sinBom -join ', '))
} else {
    Bien "Los $($ps1s.Count) archivos .ps1 tienen BOM."
}


# --- 3. Sintaxis y compatibilidad con PowerShell 5.1 -----------------------

Titulo '3. Compatibilidad con Windows PowerShell 5.1'

# El equipo del cliente trae Windows PowerShell 5.1, no PowerShell 7. Estas
# construcciones existen en 7 y fallan en 5.1, algunas en tiempo de
# ejecución: no las detecta el analizador de sintaxis.
# Los patrones se cortan en ')', ';' y '{' para no cruzar a la siguiente
# orden de la misma línea: 'if (Test-Path $x) { New-Item -Force ... }' es
# correcto y no debe señalarse.
$incompatibles = @(
    @{ Patron = 'Test-Path[^)\r\n;{|]*-Force';       Razon = 'Test-Path no admite -Force en 5.1' }
    @{ Patron = '\?\?';                              Razon = 'el operador ?? no existe en 5.1' }
    @{ Patron = '\?\.';                              Razon = 'el operador ?. no existe en 5.1' }
    @{ Patron = '-AsHashtable';                      Razon = 'ConvertFrom-Json -AsHashtable no existe en 5.1' }
    @{ Patron = 'Join-String';                       Razon = 'Join-String no existe en 5.1' }
    @{ Patron = 'ForEach-Object[^\r\n|]*-Parallel';  Razon = 'ForEach-Object -Parallel no existe en 5.1' }
    @{ Patron = '-AsByteStream';                     Razon = 'en 5.1 se usa -Encoding Byte' }
    @{ Patron = 'Get-Content[^\r\n|]*-Raw[^\r\n|]*-AsByteStream'; Razon = 'no existe en 5.1' }
)

$encontrados = @()

foreach ($ps1 in $ps1s) {
    $texto = [System.IO.File]::ReadAllText($ps1.FullName)
    $lineas = $texto -split "`r?`n"

    for ($i = 0; $i -lt $lineas.Count; $i++) {
        $linea = $lineas[$i]
        if ($linea.TrimStart().StartsWith('#')) { continue }

        foreach ($regla in $incompatibles) {
            if ($linea -match $regla.Patron) {
                $encontrados += ("{0}:{1}  {2}" -f $ps1.Name, ($i + 1), $regla.Razon)
            }
        }
    }
}

if ($encontrados.Count -gt 0) {
    foreach ($e in $encontrados) { Mal $e }
} else {
    Bien 'Sin construcciones exclusivas de PowerShell 7.'
}

# El analizador coge los errores de sintaxis, que son los baratos.
foreach ($ps1 in $ps1s) {
    $errores = $null
    $tokens = $null
    [System.Management.Automation.Language.Parser]::ParseFile($ps1.FullName, [ref]$tokens, [ref]$errores) | Out-Null

    if ($errores) {
        foreach ($e in $errores) { Mal ("$($ps1.Name):$($e.Extent.StartLineNumber)  $($e.Message)") }
    }
}


# --- 4. Que exista lo que los scripts invocan ------------------------------

Titulo '4. Referencias entre archivos'

# Un .bat que llama a una herramienta que se renombró falla sólo cuando el
# cliente la usa, semanas después.
$esperados = @(
    'INSTALAR.bat',
    'LEEME.txt',
    'instalar.ps1',
    'construir-paquete.ps1',
    'php\php.ini',
    'plantillas\env.plantilla',
    'app-escritorio\Drogueria.cs',
    'scripts\Configurar impresora.bat',
    'scripts\Cerrar programa.bat',
    'scripts\Respaldar datos.bat',
    'scripts\Cargar datos de ejemplo.bat',
    'scripts\Iniciar (modo diagnostico).bat',
    'scripts\Manual de uso.txt',
    'scripts\herramientas\configurar-impresora.ps1',
    'scripts\herramientas\respaldar.ps1',
    'scripts\herramientas\datos-ejemplo.ps1',
    'scripts\herramientas\cerrar.ps1',
    'scripts\herramientas\diagnostico.ps1',
    'scripts\herramientas\comprobar.ps1',
    'scripts\Comprobar instalacion.bat'
)

foreach ($e in $esperados) {
    if (-not (Test-Path (Join-Path $base $e))) { Mal "Falta $e" }
}

# Los .bat referencian herramientas con %~dp0, es decir, relativas a su
# propia carpeta. INSTALAR.bat vive en la raíz de la memoria y apunta a
# recursos\; los demás acaban en la raíz de la instalación, donde se
# desparrama el contenido de scripts\. Así que cada grupo se resuelve contra
# una carpeta distinta.
foreach ($bat in $bats) {
    $texto = [System.IO.File]::ReadAllText($bat.FullName)

    if ($bat.DirectoryName -eq $base) {
        $raizRelativa = $base
    } else {
        $raizRelativa = Join-Path $base 'scripts'
    }

    foreach ($m in ([regex]::Matches($texto, '%~dp0([A-Za-z0-9_\\\-\. ]+\.(ps1|bat|exe))'))) {
        $relativa = $m.Groups[1].Value

        # En la memoria montada el instalador cuelga de 'recursos\'; en este
        # repositorio esa carpeta no existe todavía, la crea el constructor.
        # Se comprueba contra el archivo fuente equivalente.
        $enRepo = $relativa -replace '^recursos\\', ''

        if (-not (Test-Path (Join-Path $raizRelativa $enRepo))) {
            Mal "$($bat.Name) llama a '$relativa', que no existe"
        }
    }
}

if ($fallos.Count -eq 0) { Bien 'Todas las referencias resuelven.' }


# --- 5. El .env de plantilla -----------------------------------------------

Titulo '5. Plantilla de configuración'

$plantilla = [System.IO.File]::ReadAllText((Join-Path $base 'plantillas\env.plantilla'))

# Cada marcador tiene que tener quien lo reemplace en instalar.ps1; si no,
# el cliente acaba con un literal '__STORE_NAME__' impreso en el tiquete.
$instalador = [System.IO.File]::ReadAllText((Join-Path $base 'instalar.ps1'))

foreach ($m in ([regex]::Matches($plantilla, '__[A-Z_]+__'))) {
    $marcador = $m.Value
    if ($instalador -notmatch [regex]::Escape($marcador)) {
        Mal "El marcador $marcador de la plantilla no lo reemplaza nadie"
    }
}

foreach ($clave in @('DB_CONNECTION', 'PRINTER_NAME', 'PRINTER_COLUMNS', 'APP_KEY', 'APP_DEBUG')) {
    if ($plantilla -notmatch "(?m)^$clave=") { Mal "La plantilla no define $clave" }
}

if ($plantilla -notmatch '(?m)^APP_DEBUG=false') {
    Ojo 'APP_DEBUG no está en false: el cliente vería trazas de error'
}

if ($fallos.Count -eq 0) { Bien 'La plantilla está completa.' }


# --- 6. php.ini -------------------------------------------------------------

Titulo '6. Configuración de PHP'

$ini = [System.IO.File]::ReadAllText((Join-Path $base 'php\php.ini'))

foreach ($ext in @('mbstring', 'fileinfo', 'openssl', 'pdo_sqlite', 'sqlite3', 'gd', 'curl', 'zip', 'intl')) {
    if ($ini -notmatch "(?m)^extension=$ext\s*$") { Mal "php.ini no carga la extensión $ext" }
}

# Sin esto PHP aborta de vez en cuando al arrancar por el problema de ASLR
# de Windows, y el programa no abre.
if ($ini -notmatch '(?m)^opcache\.file_cache=') { Mal 'php.ini no define opcache.file_cache (fallo de ASLR)' }
if ($ini -notmatch '(?m)^opcache\.file_cache_fallback=1') { Mal 'php.ini no define opcache.file_cache_fallback' }
if ($ini -notmatch '(?m)^error_log') { Ojo 'php.ini no define error_log: los avisos saldrán por pantalla' }
if ($ini -notmatch '(?m)^display_errors\s*=\s*Off') { Mal 'php.ini muestra los errores en pantalla' }

if ($fallos.Count -eq 0) { Bien 'php.ini correcto.' }


# --- 7. Que el paquete no dependa de nada preinstalado ---------------------
#
# Estas piezas las añade construir-paquete.ps1, así que sobre el
# repositorio todavía no existen: aquí sólo se avisa. La comprobación que
# de verdad manda la hace el constructor cuando ya las ha puesto.

Titulo '7. Autonomía en un equipo recién formateado'

$paquete = "$env:USERPROFILE\Drogueria-USB\recursos"

if (-not (Test-Path (Join-Path $paquete 'php\php.exe'))) {
    Ojo 'No hay un paquete armado; el constructor lo comprobará al armarlo.'
} else {
    $ausentes = @()

    # php.exe depende de estas y el .zip oficial de PHP no las trae. En un
    # Windows recién instalado no están y PHP ni arranca.
    foreach ($dll in @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll')) {
        if (-not (Test-Path (Join-Path $paquete "php\$dll"))) { $ausentes += $dll }
    }

    foreach ($pieza in @('Drogueria.exe', 'WebView2Loader.dll',
                         'Microsoft.Web.WebView2.Core.dll',
                         'Microsoft.Web.WebView2.WinForms.dll')) {
        if (-not (Test-Path (Join-Path $paquete "scripts\$pieza"))) { $ausentes += $pieza }
    }

    if ($ausentes.Count -gt 0) {
        Ojo ('El paquete de ' + $paquete + ' está desactualizado; le faltan: ' +
             ($ausentes -join ', '))
    } else {
        Bien 'El paquete armado no depende de nada preinstalado.'
    }
}


# --- Resultado --------------------------------------------------------------

Write-Host ''
Write-Host ('=' * 66) -ForegroundColor DarkGray

if ($fallos.Count -eq 0) {
    Write-Host '  TODO CORRECTO' -ForegroundColor Green
    if ($avisos.Count -gt 0) {
        Write-Host "  ($($avisos.Count) aviso/s, ninguno impide la instalación)" -ForegroundColor Yellow
    }
    Write-Host ('=' * 66) -ForegroundColor DarkGray
    Write-Host ''
    exit 0
}

Write-Host "  $($fallos.Count) PROBLEMA(S) QUE ARREGLAR" -ForegroundColor Red
Write-Host ('=' * 66) -ForegroundColor DarkGray
Write-Host ''
exit 1
