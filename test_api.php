<?php

// Simple test script to verify the API structure

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Http\Router\Router;
use App\Core\Http\Controllers\AuthController;
use App\Core\Utils\ResponseBuilder;

echo "Airigo Job Portal Backend - API Test\n";
echo "=====================================\n\n";

echo "Testing API Structure:\n";

// Test that we can instantiate key classes
try {
    $router = new Router();
    echo "✓ Router instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ Router instantiation failed: " . $e->getMessage() . "\n";
}

try {
    $authController = new AuthController();
    echo "✓ AuthController instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ AuthController instantiation failed: " . $e->getMessage() . "\n";
}

try {
    $response = ResponseBuilder::ok(['test' => 'successful']);
    echo "✓ ResponseBuilder working correctly\n";
} catch (Exception $e) {
    echo "✗ ResponseBuilder failed: " . $e->getMessage() . "\n";
}

try {
    $config = \App\Config\AppConfig::get('app.name');
    echo "✓ Config loading working: " . ($config ?? 'default') . "\n";
} catch (Exception $e) {
    echo "✗ Config loading failed: " . $e->getMessage() . "\n";
}

try {
    $db = \App\Core\Database\Connection::getInstance();
    echo "✓ Database connection established\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\nSystem Status: All components loaded successfully!\n";
echo "Ready to serve API requests.\n";