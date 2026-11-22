@echo off
REM Starts both TarpaLink applications and opens the CMS.
REM
REM The CMS is a separate application from the engine, so it is a separate
REM server on a separate port. Starting only one of them is the most common way
REM to get a confusing screen: the CMS loads but every figure says "engine
REM unreachable", or the storefront loads but with no edited copy.

set ENGINE=C:\Users\Temp\Desktop\TARPALINK
set CMS=C:\Users\Temp\Desktop\TARPALINK-CMS

echo Starting the engine on http://127.0.0.1:8000 ...
start "TarpaLink engine" cmd /k "cd /d %ENGINE% && php artisan serve --host=127.0.0.1 --port=8000"

echo Starting the CMS on http://127.0.0.1:8001 ...
start "TarpaLink CMS" cmd /k "cd /d %CMS% && php artisan serve --host=127.0.0.1 --port=8001"

REM Give both servers a moment to bind before the browser asks for a page.
timeout /t 4 /nobreak >nul

echo Opening the CMS ...
start http://127.0.0.1:8001/admin

echo.
echo   CMS        http://127.0.0.1:8001/admin
echo   Storefront http://127.0.0.1:8000
echo.
echo   Sign in with sofia@tarpalink.ph / password
echo.
echo Close the two server windows to stop everything.
