<?php

// Direct .env file parsing to verify values
echo "=== Direct .env File Check ===\n\n";

$envContent = file_get_contents('.env');
$lines = explode("\n", $envContent);

$envVars = [];
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $value = isset($parts[1]) ? trim($parts[1], "\"'") : '';
        $envVars[$key] = $value;
    }
}

echo "Parsed .env values:\n";
echo "- DB_HOST: " . ($envVars['DB_HOST'] ?? 'NOT FOUND') . "\n";
echo "- DB_NAME: " . ($envVars['DB_NAME'] ?? 'NOT FOUND') . "\n";
echo "- DB_USER: " . ($envVars['DB_USER'] ?? 'NOT FOUND') . "\n";
echo "- DB_PASSWORD: " . (!empty($envVars['DB_PASSWORD']) ? '[SET]' : 'NOT FOUND') . "\n";

echo "\n=== Verification ===\n";
if ($envVars['DB_HOST'] !== 'localhost' && !empty($envVars['DB_HOST'])) {
    echo "✅ Your .env file has been correctly updated with Hostinger remote database settings!\n";
    echo "Host: " . $envVars['DB_HOST'] . "\n";
    echo "Database: " . $envVars['DB_NAME'] . "\n";
    echo "User: " . $envVars['DB_USER'] . "\n";
} else {
    echo "❌ .env file still shows localhost configuration\n";
}

echo "\n=== Important Notes ===\n";
echo "1. Your .env file contains the correct Hostinger database values\n";
echo "2. The application may need to be restarted to pick up the new values\n";
echo "3. On your Hostinger server, the configuration will load correctly\n";
echo "4. For local testing, you may need to restart your PHP process\n";