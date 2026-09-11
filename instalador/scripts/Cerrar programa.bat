@echo off
setlocal
title Cerrar programa - Drogueria
cd /d "%~dp0"

REM ASCII puro y sin 'chcp': ver la explicacion en INSTALAR.bat.

echo.
echo   Cerrando el punto de venta...
echo.

REM Solo se detiene el PHP de esta carpeta. Si el equipo tiene otro PHP
REM corriendo (XAMPP u otro programa), no se toca.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\cerrar.ps1"

echo.
echo   Listo. Ya puede cerrar la ventana del navegador.
echo.
timeout /t 3 >nul
endlocal
