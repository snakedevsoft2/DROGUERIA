@echo off
setlocal
title Configurar impresora - Drogueria
cd /d "%~dp0"

REM ---------------------------------------------------------------------
REM  Este archivo solo lanza; el trabajo lo hace el script de PowerShell.
REM
REM  Las preguntas NO se hacen aqui con 'set /p': con la pagina de codigos
REM  en UTF-8 (chcp 65001), 'set /p' de cmd.exe no lee absolutamente nada.
REM  Y esa pagina hace falta para que la salida de PHP salga con tildes.
REM
REM  Y va en ASCII puro: cmd.exe lee los .bat por posicion de byte, asi que
REM  un caracter de dos bytes descuadra el analizador y parte las lineas
REM  siguientes. revisar.ps1 lo comprueba.
REM ---------------------------------------------------------------------

chcp 65001 >nul

if not exist "php\php.exe" (
    echo.
    echo   ERROR: falta la carpeta php. Vuelva a ejecutar el instalador.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\configurar-impresora.ps1"

endlocal
