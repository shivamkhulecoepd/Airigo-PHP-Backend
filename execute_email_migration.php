<?php
require_once 'vendor/autoload.php';
require_once 'src/bootstrap.php';

use App\Core\Database\Connection;

try {
    $pdo = Connection::getInstance();
    echo "✅ Database connection established\n";
    
    // Add email column
    echo "Adding email column...\n";
    $pdo->exec("ALTER TABLE recruiters ADD COLUMN email VARCHAR(255) NULL AFTER user_id");
    echo "✅ Email column added\n";
    
    // Add index
    echo "Adding email index...\n";
    $pdo->exec("ALTER TABLE recruiters ADD INDEX idx_email (email)");
    echo "✅ Email index added\n";
    
    // Populate email data
    echo "Populating email data...\n";
    $stmt = $pdo->prepare("UPDATE recruiters SET email = recruiter_name WHERE recruiter_name LIKE '%@%' AND email IS NULL");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "✅ Populated {$count} recruiter emails\n";
    
    // Verify
    echo "\n=== Verification ===\n";
    $stmt = $pdo->prepare("SELECT user_id, email, recruiter_name, company_name FROM recruiters WHERE email IS NOT NULL LIMIT 5");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        echo "User ID: {$row['user_id']}\n";
        echo "Email: {$row['email']}\n";
        echo "Recruiter Name: {$row['recruiter_name']}\n";
        echo "Company: {$row['company_name']}\n";
        echo "---\n";
    }
    
    echo "✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>