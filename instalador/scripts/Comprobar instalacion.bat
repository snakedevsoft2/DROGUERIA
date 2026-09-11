@echo off
setlocal
title Comprobar instalacion - Drogueria
cd /d "%~dp0"

REM Solo lanza. Las comprobaciones van en PowerShell: 'set /p' de cmd.exe no
REM lee nada con la pagina de codigos en UTF-8, y esa pagina hace falta para
REM que la salida de PHP salga con sus tildes.
REM Ver Configurar impresora.bat para la explicacion completa.

chcp 65001 >nul

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\comprobar.ps1"

endlocal
