@echo off
setlocal
title NCST Events - Mobile Public Tunnel
color 0A

echo =====================================================================
echo         NCST EVENTS - INSTANT MOBILE ACCESS TUNNEL
echo =====================================================================
echo.
echo Starting secure public HTTPS tunnel to port 5173...
echo.
echo (If prompted on mobile, enter your public IP from https://loca.lt/mytunnelpassword)
echo =====================================================================
echo.

npx --yes localtunnel --port 5173 --local-https --allow-invalid-cert

pause
