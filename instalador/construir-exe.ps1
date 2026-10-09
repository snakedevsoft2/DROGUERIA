# ---------------------------------------------------------------------------
# Arma Instalar-Drogueria.exe: el instalador en un solo archivo.
#
#   .\instalador\construir-paquete.ps1 -Descarga     # primero, el zip
#   .\instalador\construir-exe.ps1                   # -> Instalar-Drogueria.exe
#   .\instalador\construir-exe.ps1 -Datos C:\ruta\database.sqlite
#                                                    # -> Instalar-Drogueria-con-datos.exe
#
# El .exe lleva dentro el mismo Drogueria-Instalador.zip que baja el botón
# del sitio. Con -Datos, además, una base SQLite con la que arranca el
# equipo nuevo.
#
# EL DE DATOS NO SE PUBLICA: lleva costos, ventas y las claves del programa.
# Se entrega en mano (WhatsApp, USB). El que va a GitHub es el sin datos.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    # El zip que deja construir-paquete.ps1 -Descarga.
    [string] $Zip = "$env:USERPROFILE\Drogueria-Instalador.zip",

    # Base de datos que se incluye. Sin este parámetro, el instalador sale
    # sin datos y es el que se puede publicar.
    [string] $Datos = '',

    # Carpeta donde queda el .exe.
    [string] $Destino = $env:USERPROFILE
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

function Paso($texto) { Write-Host "  > $texto" -ForegroundColor White }
function Ok($texto)   { Write-Host "    OK  $texto" -ForegroundColor Green }

if (-not (Test-Path -LiteralPath $Zip)) {
    throw "No existe $Zip. Ejecute antes: .\instalador\construir-paquete.ps1 -Descarga"
}

$csc = "$env:SystemRoot\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if (-not (Test-Path $csc)) { throw "No se encontró el compilador de C# de Windows en $csc" }

$fuentes = Join-Path $PSScriptRoot 'app-escritorio'
$trabajo = Join-Path $env:TEMP ('drogueria-exe-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $trabajo | Out-Null

try {
    $paquete = Join-Path $trabajo 'paquete.zip'
    Copy-Item -LiteralPath $Zip -Destination $paquete

    if ($Datos) {
        if (-not (Test-Path -LiteralPath $Datos)) { throw "No existe la base de datos $Datos" }

        Paso 'Agregando la base de datos al paquete...'
        Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
        $archivo = [System.IO.Compression.ZipFile]::Open($paquete, 'Update')
        try {
            [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archivo, (Resolve-Path -LiteralPath $Datos).Path,
                'recursos/datos/database.sqlite', [System.IO.Compression.CompressionLevel]::Optimal)
        } finally {
            $archivo.Dispose()
        }
        Ok 'Base de datos incluida.'
        $nombre = 'Instalar-Drogueria-con-datos.exe'
    } else {
        $nombre = 'Instalar-Drogueria.exe'
    }

    $exe = Join-Path $Destino $nombre
    Paso "Compilando $nombre..."

    $argumentos = @(
        '/nologo', '/target:exe', '/platform:anycpu', '/optimize+',
        "/out:$exe",
        "/win32manifest:$fuentes\Instalador.manifest",
        "/resource:$paquete,paquete.zip",
        '/reference:System.dll',
        '/reference:System.IO.Compression.dll',
        '/reference:System.IO.Compression.FileSystem.dll',
        "$fuentes\Instalador.cs"
    )

    # El icono del programa, si el paquete lo trae como .ico de verdad.
    $icono = Join-Path $trabajo 'icono.ico'
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $lectura = [System.IO.Compression.ZipFile]::OpenRead($Zip)
    try {
        $entrada = $lectura.Entries | Where-Object { $_.FullName -eq 'recursos/app/public/favicon.ico' } | Select-Object -First 1
        if ($entrada -and $entrada.Length -gt 0) {
            [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entrada, $icono, $true)
            $argumentos = @("/win32icon:$icono") + $argumentos
        }
    } finally {
        $lectura.Dispose()
    }

    $salida = & $csc $argumentos
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $exe)) {
        Write-Host ($salida | Out-String) -ForegroundColor DarkGray
        throw "No se pudo compilar $nombre."
    }

    $mb = [math]::Round((Get-Item -LiteralPath $exe).Length / 1MB, 1)
    Ok "Listo: $exe ($mb MB)"
    if ($Datos) {
        Write-Host '    !   Lleva datos del negocio: entréguelo en mano, NO lo publique.' -ForegroundColor Yellow
    }
} finally {
    Remove-Item -LiteralPath $trabajo -Recurse -Force -ErrorAction SilentlyContinue
}
