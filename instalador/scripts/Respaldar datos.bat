@echo off
setlocal
title Respaldar datos - Drogueria
cd /d "%~dp0"

REM Solo lanza. Las preguntas van en PowerShell: 'set /p' de cmd.exe no lee
REM nada con la pagina de codigos en UTF-8, y esa pagina hace falta para
REM que la salida de PHP salga con sus tildes. Ver Configurar impresora.bat.

chcp 65001 >nul

if not exist "php\php.exe" (
    echo.
    echo   ERROR: falta la carpeta php. Vuelva a ejecutar el instalador.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\respaldar.ps1"

endlocal
