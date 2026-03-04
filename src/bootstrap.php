<?php

// Bootstrap file for the Airigo Job Portal Backend

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die('Composer autoload not found. Please run "composer install".');
}

// Load environment variables (reload to ensure latest values)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);
}

// Initialize logger
$log = new Logger('airigo_backend');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

// Set error reporting based on environment
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set default timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Define constants
define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('PUBLIC_PATH', APP_ROOT . '/public');
define('STORAGE_PATH', APP_ROOT . '/storage');

// Create logs directory if it doesn't exist
if (!file_exists(APP_ROOT . '/logs')) {
    mkdir(APP_ROOT . '/logs', 0755, true);
}

// Create storage directory if it doesn't exist
if (!file_exists(STORAGE_PATH)) {
    mkdir(STORAGE_PATH, 0755, true);
}

// Initialize database connection
try {
    \App\Core\Database\Connection::getInstance();
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please check your configuration.');
}

// Global error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $logMessage = "[Error] {$message} in {$file}:{$line}";
    error_log($logMessage);
    
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo $logMessage . "\n";
    }
});

// Global exception handler
set_exception_handler(function ($exception) {
    $logMessage = "[Exception] " . $exception->getMessage() . " in " . 
                  $exception->getFile() . ":" . $exception->getLine() . 
                  "\nStack trace:\n" . $exception->getTraceAsString();
    
    error_log($logMessage);
    
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo $logMessage . "\n";
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred'
        ]);
    }
});

// Shutdown function
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_CORE_ERROR || 
                   $error['type'] === E_COMPILE_ERROR || $error['type'] === E_PARSE)) {
        $logMessage = "[Fatal Error] {$error['message']} in {$error['file']}:{$error['line']}";
        error_log($logMessage);
        
        if ($_ENV['APP_DEBUG'] ?? false) {
            echo $logMessage . "\n";
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'A fatal error occurred'
            ]);
        }
    }
});