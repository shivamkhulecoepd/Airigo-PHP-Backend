<?php
/**
 * Debug User Registration Issue
 * 
 * This script helps diagnose why user_type is empty in responses
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Repositories\UserRepository;

echo "=== User Registration Debug ===\n\n";

try {
    $userRepository = new UserRepository();
    
    // Test 1: Check if we can create a user
    echo "1. Creating test user...\n";
    $testData = [
        'email' => 'debug@example.com',
        'password_hash' => password_hash('Test@12345', PASSWORD_DEFAULT),
        'user_type' => 'admin',
        'phone' => '+1234567890',
        'status' => 'active',
        'email_verified' => true
    ];
    
    $userId = $userRepository->create($testData);
    echo "User created with ID: $userId\n\n";
    
    // Test 2: Check if user exists and has correct data
    echo "2. Fetching user by ID...\n";
    $user = $userRepository->findById($userId);
    
    if ($user) {
        echo "User data from database:\n";
        foreach ($user as $key => $value) {
            echo "  $key: " . ($value === null ? 'NULL' : $value) . "\n";
        }
        echo "\n";
    } else {
        echo "User not found!\n\n";
    }
    
    // Test 3: Check database directly
    echo "3. Direct database query...\n";
    $connection = \App\Core\Database\Connection::getInstance();
    $stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $directResult = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($directResult) {
        echo "Direct query result:\n";
        foreach ($directResult as $key => $value) {
            echo "  $key: " . ($value === null ? 'NULL' : $value) . "\n";
        }
        echo "\n";
    }
    
    // Test 4: Check column information
    echo "4. Checking table structure...\n";
    $stmt = $connection->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Users table columns:\n";
    foreach ($columns as $column) {
        echo "  {$column['Field']}: {$column['Type']} ({$column['Null']})\n";
    }
    echo "\n";
    
    // Clean up test user
    echo "5. Cleaning up test user...\n";
    $userRepository->delete($userId);
    echo "Test user deleted.\n\n";
    
    echo "=== Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}