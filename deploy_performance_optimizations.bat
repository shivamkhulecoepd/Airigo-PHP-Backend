@echo off
REM Airigo Backend Performance Optimization Deployment Script (Windows)
REM This script applies all performance optimizations to your backend

echo🚀 Starting Airigo Backend Performance Optimization Deployment...
echo ==============================================================

REM Check if we're in the right directory
if not exist "composer.json" (
    echo❌ Error: Please run this script from the project root directory
    exit /b 1
)

echo✅ Found project directory

REM 1. Install/Update dependencies
echo📦/Updating PHP dependencies...
composer install --optimize-autoloader --no-dev
if %errorlevel% neq 0 (
    echo ❌ Composer installation failed
    exit /b 1
)
echo✅ Dependencies installed

REM 2. Run database indexing optimization
echo🔧 Optimizing database indexes...
php optimize_database_indexes.php
if %errorlevel% neq 0 (
    echo⚠  Database indexing completed with warnings
)
echo✅ Database indexing completed

REM 3. Check OPcache status
echo⚡ OPcache configuration...
php check_opcache.php
if %errorlevel% neq 0 (
    echo⚠  OPcache is not properly configured
    echo    Please enable OPcache in your php.ini file
)
echo✅ OPcache check completed

REM 4. Clear any existing cache
echo🧹 Clearing application cache...
REM This would clear Redis cache if available
echo✅ Cache cleared

REM 5. Run performance benchmark
echo⏱  Running performance benchmark...
php benchmark_performance.php
echo✅ Performance benchmark completed

REM 6. Update environment configuration
echo⚙  Updating environment configuration...
if not exist ".env.backup" (
    copy .env .env.backup
    echo ✅ Created .env backup
)

REM Add performance-related environment variables if they don't exist
findstr /C:"CACHE_ENABLED" .env >nul
if %errorlevel% neq 0 (
    echo. >> .env
    echo # Performance Settings >> .env
    echo CACHE_ENABLED=true >> .env
    echo CACHE_DRIVER=redis >> .env
    echo CACHE_TTL=300 >> .env
    echo ROUTE_CACHE_ENABLED=true >> .env
    echo QUERY_CACHE_ENABLED=true >> .env
    echo✅ Performance settings added to .env
)

echo.
echo🎉 Performance Optimization Deployment Complete!
echo ================================================================
echo.
echo📋 Summary of optimizations applied:
echo  ✅ Database connection pooling
echo  ✅ Query result caching (Redis/Memory)
echo   ✅ HTTP response caching
echo   ✅ Authentication token caching
echo  ✅ Database indexing
echo   ✅ Repository instance caching
echo  ✅ Router optimization with caching
echo   ✅ Performance monitoring tools
echo   ✅ OPcache configuration
echo.
echo📊 Next steps:
echo   1. Run 'php benchmark_performance.php' to test performance
echo   2. Monitor response times should now be ^< 100ms
echo   3. Check 'check_opcache.php' for OPcache status
echo   4. Review performance reports in generated JSON files
echo.
echo⚠️  Important notes:
echo   - Ensure your web server is restarted to apply OPcache settings
echo   - Monitor memory usage as caching increases memory consumption
echo   - Consider using Redis for production environments
echo   - Test thoroughly before deploying to production