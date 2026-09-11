@echo off
setlocal
title Datos de ejemplo - Drogueria
cd /d "%~dp0"

REM Solo lanza. La confirmacion va en PowerShell: 'set /p' de cmd.exe no lee
REM nada con la pagina de codigos en UTF-8, y una confirmacion que no se lee
REM en esta herramienta significaria cargar datos falsos sin que nadie los
REM haya aceptado. Ver Configurar impresora.bat.

chcp 65001 >nul

if not exist "php\php.exe" (
    echo.
    echo   ERROR: falta la carpeta php. Vuelva a ejecutar el instalador.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\datos-ejemplo.ps1"

endlocal
