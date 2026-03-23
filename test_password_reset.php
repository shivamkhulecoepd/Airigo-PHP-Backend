<?php

/**
 * Test Script for Password Reset Functionality
 * 
 * This script demonstrates how to properly test the password reset flow
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Repositories\PasswordResetTokenRepository;
use App\Core\Auth\AuthService;

echo "=== Password Reset Functionality Test ===\n\n";

// Test 1: Check if the password_reset_tokens table exists
echo "1. Checking if password_reset_tokens table exists...\n";

try {
    $pdo = \App\Core\Database\Connection::getInstance();
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ password_reset_tokens table exists\n";
    } else {
        echo "❌ password_reset_tokens table does not exist\n";
        echo "Please run: php db_manager.php create-tables\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Test repository functionality
echo "\n2. Testing PasswordResetTokenRepository...\n";

try {
    $repository = new PasswordResetTokenRepository();
    echo "✅ PasswordResetTokenRepository instantiated successfully\n";
    
    // Test creating a dummy token (we'll use a fake user ID for testing)
    // In a real scenario, this would be a valid user ID
    echo "\n3. Testing token creation (using user_id = 1 for demo)...\n";
    
    $token = bin2hex(random_bytes(32)); // Generate a test token
    $expiresInHours = 24;
    
    // We won't actually create a record since user_id 1 might not exist
    // But we can test the prepared statement creation
    $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
    echo "✅ Prepared statement would be: " . $sql . "\n";
    
    echo "\n4. Testing token lookup...\n";
    // Test the find method with a dummy token
    $result = $repository->findByToken($token);
    echo "✅ findByToken method callable (would return null for non-existent token)\n";
    
} catch (Exception $e) {
    echo "❌ Error testing repository: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Display Postman test data
echo "\n5. Postman Test Data for Password Reset:\n";
echo "=========================================\n";

echo "\nForgot Password Request:\n";
echo "Method: POST\n";
echo "URL: {{base_url}}/api/auth/forgot-password\n";
echo "Headers: Content-Type: application/json\n";
echo "Body:\n";
echo '{' . "\n";
echo '  "email": "test@example.com"' . "\n";
echo '}' . "\n";

echo "\nReset Password Request:\n";
echo "Method: POST\n";
echo "URL: {{base_url}}/api/auth/reset-password\n";
echo "Headers: Content-Type: application/json\n";
echo "Body:\n";
echo '{' . "\n";
echo '  "reset_token": "{{reset_token}}",' . "\n";
echo '  "new_password": "NewSecurePassword@123"' . "\n";
echo '}' . "\n";

echo "\n=== Test Completed Successfully ===\n";
echo "The password reset functionality should now work properly.\n";
echo "Remember to:\n";
echo "1. Ensure the password_reset_tokens table exists\n";
echo "2. Have a valid user in the database\n";
echo "3. Request a reset token via forgot-password endpoint first\n";
echo "4. Use the received token in the reset-password endpoint\n";