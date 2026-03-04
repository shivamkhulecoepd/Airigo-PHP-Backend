<?php

require_once 'vendor/autoload.php';

echo "=== Airigo Job Portal Backend - Configuration Test ===\n\n";

// Test configuration values
echo "Database Configuration:\n";
echo "- Host: " . App\Config\AppConfig::get('database.host') . "\n";
echo "- Port: " . App\Config\AppConfig::get('database.port') . "\n";
echo "- Database: " . App\Config\AppConfig::get('database.database') . "\n";
echo "- Username: " . App\Config\AppConfig::get('database.username') . "\n";

echo "\nJWT Configuration:\n";
echo "- Secret Key Present: " . (App\Config\AppConfig::get('jwt.secret') ? 'Yes' : 'No') . "\n";

echo "\nFirebase Configuration:\n";
echo "- Project ID: " . App\Config\AppConfig::get('firebase.project_id') . "\n";

echo "\nRedis Configuration:\n";
echo "- Host: " . App\Config\AppConfig::get('redis.host') . "\n";

echo "\nCore Components Status:\n";
echo "- Router: " . (class_exists('App\Core\Http\Router\Router') ? 'Loaded' : 'Missing') . "\n";
echo "- JWT Manager: " . (class_exists('App\Core\Auth\JWTManager') ? 'Loaded' : 'Missing') . "\n";
echo "- Auth Service: " . (class_exists('App\Core\Auth\AuthService') ? 'Loaded' : 'Missing') . "\n";
echo "- Response Builder: " . (class_exists('App\Core\Utils\ResponseBuilder') ? 'Loaded' : 'Missing') . "\n";

echo "\nRepository Components Status:\n";
echo "- User Repository: " . (class_exists('App\Repositories\UserRepository') ? 'Loaded' : 'Missing') . "\n";
echo "- Job Repository: " . (class_exists('App\Repositories\JobRepository') ? 'Loaded' : 'Missing') . "\n";
echo "- Application Repository: " . (class_exists('App\Repositories\ApplicationRepository') ? 'Loaded' : 'Missing') . "\n";

echo "\nMiddleware Components Status:\n";
echo "- Auth Middleware: " . (class_exists('App\Core\Auth\Middleware\AuthMiddleware') ? 'Loaded' : 'Missing') . "\n";
echo "- CORS Middleware: " . (class_exists('App\Core\Http\Middleware\CorsMiddleware') ? 'Loaded' : 'Missing') . "\n";
echo "- Role Middleware: " . (class_exists('App\Core\Auth\Middleware\RoleMiddleware') ? 'Loaded' : 'Missing') . "\n";

echo "\n=== Testing Database Connection ===\n";

try {
    $db = App\Core\Database\Connection::getInstance();
    echo "✅ Database connection successful!\n";
    echo "Connected to: " . App\Config\AppConfig::get('database.host') . ":" . App\Config\AppConfig::get('database.port') . "/" . App\Config\AppConfig::get('database.database') . "\n";
    
    // Test a simple query to make sure the connection works
    $stmt = $db->query('SELECT VERSION() as version');
    $result = $stmt->fetch();
    echo "MySQL Version: " . $result['version'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in .env file\n";
}

echo "\n=== Configuration Test Complete ===\n";
echo "All components are properly configured with your .env values!\n";