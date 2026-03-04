<?php
/**
 * Admin User Creation Script
 * 
 * This script creates admin users in the database
 * Usage: php create_admin.php [email] [password]
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Repositories\UserRepository;
use App\Core\Utils\Validator;

function createAdminUser($email, $password) {
    try {
        $userRepository = new UserRepository();
        $validator = new Validator();
        
        // Validate input
        if (!$validator->isValidEmail($email)) {
            throw new Exception("Invalid email format");
        }
        
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        // Check if user already exists
        $existingUser = $userRepository->findByEmail($email);
        if ($existingUser) {
            throw new Exception("User with this email already exists");
        }
        
        // Prepare admin data
        $adminData = [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_type' => 'admin',
            'phone' => '+1234567890', // Default phone, can be updated later
            'status' => 'active',
            'email_verified' => true
        ];
        
        // Create admin user
        $userId = $userRepository->create($adminData);
        
        if ($userId) {
            echo "✅ Admin user created successfully!\n";
            echo "User ID: $userId\n";
            echo "Email: $email\n";
            echo "Password: $password\n";
            echo "User Type: admin\n";
            echo "Status: active\n";
            echo "\n⚠️  Please change the password after first login!\n";
            return true;
        } else {
            throw new Exception("Failed to create admin user");
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        return false;
    }
}

function showUsage() {
    echo "Admin User Creation Script\n";
    echo "==========================\n\n";
    echo "Usage: php create_admin.php [email] [password]\n\n";
    echo "Example: php create_admin.php admin@airigo.com Admin@12345\n\n";
    echo "If no arguments provided, interactive mode will be used.\n";
}

function interactiveMode() {
    echo "Admin User Creation - Interactive Mode\n";
    echo "======================================\n\n";
    
    // Get email
    do {
        echo "Enter admin email: ";
        $email = trim(fgets(STDIN));
        if (empty($email)) {
            echo "Email is required.\n";
        }
    } while (empty($email));
    
    // Get password
    do {
        echo "Enter password (min 8 characters): ";
        $password = trim(fgets(STDIN));
        if (strlen($password) < 8) {
            echo "Password must be at least 8 characters long.\n";
        }
    } while (strlen($password) < 8);
    
    // Confirm password
    do {
        echo "Confirm password: ";
        $confirmPassword = trim(fgets(STDIN));
        if ($password !== $confirmPassword) {
            echo "Passwords do not match.\n";
        }
    } while ($password !== $confirmPassword);
    
    return [$email, $password];
}

// Main execution
if ($argc === 1) {
    // Interactive mode
    list($email, $password) = interactiveMode();
    createAdminUser($email, $password);
} elseif ($argc === 3) {
    // Command line arguments
    $email = $argv[1];
    $password = $argv[2];
    createAdminUser($email, $password);
} else {
    showUsage();
    exit(1);
}