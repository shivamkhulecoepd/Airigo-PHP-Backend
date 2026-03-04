@echo off
echo Starting Airigo Job Portal Backend Development Server...
echo.
echo Server will be available at: http://localhost:8000
echo.
echo Press Ctrl+C to stop the server
echo.

cd /d "%~dp0"
php -S localhost:8000 -t public

pause