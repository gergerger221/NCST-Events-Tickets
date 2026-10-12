@echo off
setlocal
title NCST Events and Ticketing System - Launcher
color 0B

echo =====================================================================
echo           NCST EVENTS AND ATTENDANCE TICKETING SYSTEM
echo =====================================================================
echo.

:: 1. Check Node.js installation
echo [1/4] Checking Node.js environment...
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    color 0C
    echo [ERROR] Node.js is not installed or not found in system PATH.
    echo Please install Node.js from https://nodejs.org/ and try again.
    echo.
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node -v') do set NODE_VER=%%i
echo       - Node.js version: %NODE_VER%

:: 2. Check Dependencies (node_modules)
echo.
echo [2/4] Checking npm dependencies...
if not exist "node_modules\" (
    echo       - node_modules folder not found. Installing dependencies...
    call npm install
    if %ERRORLEVEL% NEQ 0 (
        color 0C
        echo [ERROR] Failed to install npm dependencies.
        pause
        exit /b 1
    )
) else (
    echo       - Dependencies already installed.
)

:: 3. Check PHP & Initialize MySQL Database
echo.
echo [3/4] Checking MySQL Database and Backend API...
set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" (
    where php >nul 2>nul
    if %ERRORLEVEL% EQU 0 (
        set PHP_BIN=php
    ) else (
        set PHP_BIN=
    )
)

if defined PHP_BIN (
    echo       - Running database setup migration...
    "%PHP_BIN%" api\setup.php >nul 2>&1
    if %ERRORLEVEL% EQU 0 (
        echo       - MySQL Database [event_attendance_db] verified and ready!
    ) else (
        echo       - [NOTE] Could not connect to MySQL automatically.
        echo         Make sure XAMPP MySQL is started if you want MySQL persistence.
    )
) else (
    echo       - [NOTE] PHP not found in C:\xampp\php or system PATH.
    echo         Using in-memory mock backend.
)

:: 4. Free port 5173 if already occupied by an old process
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":5173" ^| findstr "LISTENING"') do (
    echo       - Freeing port 5173 [Terminating previous PID: %%a]
    taskkill /F /PID %%a >nul 2>&1
)

:: 5. Launch Vite Development Server
echo.
echo [4/4] Starting Vite Development Server...
echo =====================================================================
echo   Application URL: https://localhost:5173
echo   MySQL API URL:   http://localhost/INTEG/api/
echo   Apache Web App:  http://localhost/INTEG/
echo =====================================================================
echo.
echo Press Ctrl+C in this terminal to stop the server at any time.
echo.

:: Open browser in background
start "" "https://localhost:5173"

:: Start Vite dev server
call npm run dev

echo.
echo Server has stopped.
pause
