<?php

require_once __DIR__ . '/vendor/autoload.php';

use Firebase\FirebaseStorageService;
use App\Config\AppConfig;

echo "========================================\n";
echo "Firebase Storage Connection Test\n";
echo "========================================\n\n";

// Test 1: Check Configuration
echo "TEST 1: Checking Configuration...\n";
$projectId = AppConfig::get('firebase.project_id');
$bucketName = AppConfig::get('firebase.storage_bucket');
$clientEmail = AppConfig::get('firebase.client_email');
$privateKey = AppConfig::get('firebase.private_key');
$tokenUri = AppConfig::get('firebase.token_uri');

echo "Project ID: {$projectId}\n";
echo "Storage Bucket: {$bucketName}\n";
echo "Client Email: {$clientEmail}\n";
echo "Token URI: {$tokenUri}\n";
echo "Private Key Length: " . strlen($privateKey) . " characters\n";
echo "Private Key Starts With: " . substr($privateKey, 0, 27) . "\n";
echo "Private Key Ends With: " . substr(trim($privateKey), -25) . "\n\n";

// Validate configuration
if (!$projectId || !$bucketName || !$clientEmail || !$privateKey || !$tokenUri) {
    echo "❌ FAIL: Missing configuration values!\n";
    exit(1);
}

// Test 2: Create FirebaseStorageService
echo "TEST 2: Creating FirebaseStorageService...\n";
try {
    $service = new FirebaseStorageService();
    echo "✓ Service created successfully\n\n";
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Get Access Token
echo "TEST 3: Getting Access Token...\n";
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getAccessToken');
$method->setAccessible(true);

$accessToken = $method->invoke($service);

if ($accessToken) {
    echo "✓ Access token obtained successfully\n";
    echo "Token (first 50 chars): " . substr($accessToken, 0, 50) . "...\n\n";
} else {
    echo "❌ FAIL: Could not obtain access token\n";
    echo "Check error logs for details\n\n";
}

// Test 4: Upload a test file
echo "TEST 4: Uploading test file...\n";

// Create a temporary test image
$tempFile = tempnam(sys_get_temp_dir(), 'test_');
file_put_contents($tempFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

$testFilename = 'test_logo_' . time() . '.png';

try {
    $url = $service->uploadFile($tempFile, $testFilename, 'image/png');
    
    if ($url) {
        echo "✓ File uploaded successfully!\n";
        echo "Upload URL: {$url}\n\n";
        
        // Test 5: Verify URL is accessible
        echo "TEST 5: Testing URL accessibility...\n";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "✓ URL is accessible (HTTP {$httpCode})\n\n";
        } else {
            echo "⚠ WARNING: URL returned HTTP {$httpCode}\n\n";
        }
    } else {
        echo "❌ FAIL: Upload failed, no URL returned\n\n";
    }
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
} finally {
    // Clean up temp file
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}

echo "========================================\n";
echo "Test Complete\n";
echo "========================================\n";
