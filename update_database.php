<?php
/**
 * Update Database Schema
 * 
 * This script updates the database schema to include 'admin' user type
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;

echo "=== Database Schema Update ===\n\n";

try {
    $connection = Connection::getInstance();
    
    // Update users table to include 'admin' in user_type enum
    echo "1. Updating users table user_type enum...\n";
    $stmt = $connection->prepare("ALTER TABLE users MODIFY user_type ENUM('jobseeker', 'recruiter', 'admin') NOT NULL");
    $result = $stmt->execute();
    
    if ($result) {
        echo "✅ Users table updated successfully\n";
    } else {
        echo "❌ Failed to update users table\n";
    }
    
    // Update issues_reports table to include 'admin' in user_type enum
    echo "2. Updating issues_reports table user_type enum...\n";
    $stmt = $connection->prepare("ALTER TABLE issues_reports MODIFY user_type ENUM('jobseeker', 'recruiter', 'admin') NOT NULL");
    $result = $stmt->execute();
    
    if ($result) {
        echo "✅ Issues reports table updated successfully\n";
    } else {
        echo "❌ Failed to update issues reports table\n";
    }
    
    // Verify the changes
    echo "3. Verifying changes...\n";
    $stmt = $connection->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'user_type') {
            echo "User type column: " . $column['Type'] . "\n";
            break;
        }
    }
    
    echo "\n✅ Database schema update completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error updating database: " . $e->getMessage() . "\n";
}