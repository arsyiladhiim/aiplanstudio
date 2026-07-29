@echo off
title AI Plan Studio - Server Launcher
cd /d "%~dp0"

echo [1/4] Menutup server lama...
taskkill /f /im php.exe >nul 2>&1
taskkill /f /im node.exe >nul 2>&1
timeout /t 2 /nobreak >nul

echo [2/4] Memulai Laravel API (localhost:8000)...
start "Laravel API" cmd /k "cd /d %~dp0api && php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 3 /nobreak >nul

echo [3/4] Memulai Next.js Frontend (localhost:3000)...
start "Next.js Frontend" cmd /k "cd /d %~dp0web && npm run dev"
timeout /t 3 /nobreak >nul

echo [4/4] Selesai! Kedua server berjalan.
echo.
echo   API  : http://localhost:8000
echo   Web  : http://localhost:3000
echo.
echo Jangan tutup jendela CMD ini.
pause
