@echo off
setlocal
title Drogueria - modo diagnostico
cd /d "%~dp0"

REM Solo lanza. Todo lo demas va en PowerShell, incluido leer el puerto:
REM con la pagina de codigos en UTF-8, 'set /p' de cmd.exe no lee nada y el
REM puerto se quedaba siempre en el valor por defecto.
REM Ver Configurar impresora.bat para la explicacion completa.

chcp 65001 >nul

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\diagnostico.ps1"

endlocal
