<?php
require_once 'vendor/autoload.php';
require_once 'src/bootstrap.php';

use App\Core\Database\Connection;

try {
    $pdo = Connection::getInstance();
    echo "=== Recruiters Table Structure ===\n";
    
    $stmt = $pdo->prepare('DESCRIBE recruiters');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "  {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    echo "\n=== Current Data Sample ===\n";
    $stmt = $pdo->prepare('SELECT user_id, recruiter_name, company_name FROM recruiters LIMIT 3');
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($data as $row) {
        echo "  User ID: {$row['user_id']}, Recruiter Name: {$row['recruiter_name']}, Company: {$row['company_name']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>