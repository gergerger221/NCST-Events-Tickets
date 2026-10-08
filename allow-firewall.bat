@echo off
setlocal
title Allow Mobile Access through Windows Firewall
color 0A

:: 1. Self-elevate to Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo Requesting Administrator privileges...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

echo =====================================================================
echo       ALLOW MOBILE ACCESS THROUGH WINDOWS FIREWALL (PORT 5173)
echo =====================================================================
echo.

:: 2. Set Network Profile to Private (Required so LAN devices can communicate)
echo [1/2] Setting Ethernet network profile to Private...
powershell -Command "Set-NetConnectionProfile -InterfaceAlias 'Ethernet' -NetworkCategory Private -ErrorAction SilentlyContinue"

:: 3. Add Firewall Rule for port 5173
echo [2/2] Adding Windows Firewall inbound rule for port 5173...
netsh advfirewall firewall delete rule name="NCST Vite Dev Server 5173" >nul 2>&1
netsh advfirewall firewall add rule name="NCST Vite Dev Server 5173" dir=in action=allow protocol=TCP localport=5173

if %ERRORLEVEL% EQU 0 (
    echo.
    echo =====================================================================
    echo [SUCCESS] Windows Firewall and Network Profile configured!
    echo.
    echo On your phone (connected to same Wi-Fi), open:
    echo   https://192.168.254.104:5173/
    echo.
    echo (Remember to tap "Advanced" -> "Proceed" if your phone shows SSL warning)
    echo =====================================================================
) else (
    echo.
    echo [ERROR] Failed to add firewall rule.
)

echo.
pause
