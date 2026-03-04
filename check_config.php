<?php

require_once 'vendor/autoload.php';

$dbHost = App\Config\AppConfig::get('database.host');
$dbName = App\Config\AppConfig::get('database.database');
$dbUser = App\Config\AppConfig::get('database.username');

echo "=== Airigo Job Portal Backend - Configuration Check ===\n\n";

echo "Database Configuration:\n";
echo "- Host: " . $dbHost . "\n";
echo "- Database: " . $dbName . "\n";
echo "- Username: " . $dbUser . "\n";

if (strtolower($dbHost) === 'localhost' || empty($dbHost)) {
    echo "\n⚠️  Warning: Still showing localhost or empty host. \n";
    echo "Please make sure your .env file has been saved with the correct Hostinger database values.\n";
    echo "Verify that the .env file contains your remote database configuration.\n";
} else {
    echo "\n✅ Remote database configuration detected successfully!\n";
    echo "Your system is configured to use your Hostinger remote database.\n";
}

echo "\n=== Configuration Check Complete ===\n";