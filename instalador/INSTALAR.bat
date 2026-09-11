@echo off
setlocal

REM ---------------------------------------------------------------------
REM  Pide permisos de administrador y arranca el asistente de instalacion.
REM
REM  Hacen falta para dos cosas: escribir en C:\ y compartir la impresora
REM  (Windows no deja que PHP le hable a una impresora USB de otra forma).
REM
REM  ESTE ARCHIVO VA EN ASCII PURO Y SIN 'chcp'. A PROPOSITO:
REM  cmd.exe lee los .bat por posicion de byte. Con la pagina de codigos en
REM  UTF-8, cada tilde ocupa dos bytes pero cuenta como un caracter, el
REM  analizador se descuadra y empieza a cortar las lineas siguientes por
REM  delante ("REM" se vuelve "EM", luego "M"...). Cuando eso le pasa a la
REM  comprobacion de administrador, el script se relanza a si mismo en
REM  bucle. Los mensajes con acentos los imprime instalar.ps1, que no
REM  tiene este problema.
REM ---------------------------------------------------------------------

net session >nul 2>&1
if %errorlevel% equ 0 goto :instalar

REM Si ya venimos de un intento de elevacion y seguimos sin permisos, se
REM para aqui: sin esta guarda, un UAC que falle deja el bucle otra vez.
if /i "%~1"=="elevado" (
    echo.
    echo   No se pudieron obtener permisos de administrador.
    echo.
    echo   Haga clic derecho sobre INSTALAR.bat y elija
    echo   "Ejecutar como administrador".
    echo.
    pause
    exit /b 1
)

echo.
echo   Se necesitan permisos de administrador.
echo   Acepte la ventana que va a aparecer...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -ArgumentList 'elevado' -Verb RunAs"
exit /b 0

:instalar
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0recursos\instalar.ps1"

if %errorlevel% neq 0 (
    echo.
    echo   El instalador termino con errores.
    echo.
    pause
)

endlocal
exit /b 0
