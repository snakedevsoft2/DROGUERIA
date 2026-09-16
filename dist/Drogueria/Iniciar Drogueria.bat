@echo off
setlocal enabledelayedexpansion
title Drogueria - Punto de Venta
cd /d "%~dp0"

set "PHP_EXE=%~dp0php\php.exe"
set "APP_DIR=%~dp0app"
set HOST=127.0.0.1
set PORT=8000
set "URL=http://%HOST%:%PORT%/"

cd /d "%APP_DIR%"

echo ============================================
echo   Drogueria - iniciando el sistema...
echo ============================================
echo.

if not exist "database\database.sqlite" (
    echo Primera vez: preparando la base de datos local...
    type nul > "database\database.sqlite"
    "%PHP_EXE%" artisan key:generate --ansi
)

"%PHP_EXE%" artisan migrate --force --ansi

echo.
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient).Connect('%HOST%', %PORT%); exit 0 } catch { exit 1 }" >nul 2>nul
if not errorlevel 1 (
    echo El sistema ya esta abierto en otra ventana. Abriendo el navegador...
    start "" "%URL%"
    exit /b 0
)

start "" cmd /c "timeout /t 2 >nul & start "" "%URL%""

echo El sistema quedo abierto en: %URL%
echo NO CIERRE ESTA VENTANA mientras trabaje: al cerrarla se apaga el sistema.
echo.

"%PHP_EXE%" artisan serve --host=%HOST% --port=%PORT%
