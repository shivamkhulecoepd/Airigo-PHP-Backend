<?php
/**
 * Script to verify and add perks_and_benefits column to jobs table
 * Access this via browser: http://localhost:8000/execute_perks_migration.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>Perks & Benefits Migration</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .success { color: #27ae60; background: #d5f4e6; padding: 15px; border-left: 4px solid #27ae60; margin: 15px 0; }
    .error { color: #e74c3c; background: #fadbd8; padding: 15px; border-left: 4px solid #e74c3c; margin: 15px 0; }
    .info { color: #2980b9; background: #d6eaf8; padding: 15px; border-left: 4px solid #2980b9; margin: 15px 0; }
    pre { background: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #3498db; color: white; }
    tr:hover { background: #f5f5f5; }
</style>";
echo "</head><body>";
echo "<div class='container'>";
echo "<h1>🚀 Perks & Benefits Column Migration</h1>";
echo "<p>This script adds the <code>perks_and_benefits</code> JSON column to the <code>jobs</code> table.</p>";

try {
    // Get database connection
    $pdo = Connection::getInstance();
    
    echo "<div class='info'>✅ Successfully connected to database</div>";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM jobs LIKE 'perks_and_benefits'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<div class='success'>";
        echo "<strong>✅ Column Already Exists!</strong><br>";
        echo "The <code>perks_and_benefits</code> column is already present in the jobs table.";
        echo "</div>";
        
        // Show column details
        $stmt = $pdo->query("DESCRIBE jobs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Current Jobs Table Structure:</h2>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            $highlight = ($col['Field'] === 'perks_and_benefits') ? " style='background: #fff3cd;'" : "";
            echo "<tr{$highlight}>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div class='info'>";
        echo "<strong>⚠️ Column Not Found</strong><br>";
        echo "Adding <code>perks_and_benefits</code> column to jobs table...";
        echo "</div>";
        
        // Add the column
        $sql = "ALTER TABLE jobs ADD COLUMN perks_and_benefits JSON AFTER skills_required";
        $pdo->exec($sql);
        
        echo "<div class='success'>";
        echo "<strong>✅ Success!</strong><br>";
        echo "Column <code>perks_and_benefits</code> has been added to the jobs table.";
        echo "</div>";
        
        // Verify it was added
        $stmt = $pdo->query("SHOW COLUMNS FROM jobs LIKE 'perks_and_benefits'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h2>New Column Details:</h2>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    }
    
    // Test with sample data
    echo "<h2>🧪 Testing Data Insertion:</h2>";
    
    // Get first job or create test
    $stmt = $pdo->query("SELECT id, designation FROM jobs LIMIT 1");
    $testJob = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testJob) {
        $testPerks = json_encode([
            "Medical Insurance",
            "Free / Discounted Flights",
            "Paid Time Off"
        ]);
        
        $updateStmt = $pdo->prepare("UPDATE jobs SET perks_and_benefits = ? WHERE id = ?");
        $updateStmt->execute([$testPerks, $testJob['id']]);
        
        echo "<div class='success'>";
        echo "<strong>✅ Test Update Successful!</strong><br>";
        echo "Updated job ID {$testJob['id']} ('{$testJob['designation']}') with sample perks.";
        echo "</div>";
        
        // Verify the data was saved
        $stmt = $pdo->prepare("SELECT id, designation, perks_and_benefits FROM jobs WHERE id = ?");
        $stmt->execute([$testJob['id']]);
        $updatedJob = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Verification:</h3>";
        echo "<pre>";
        echo "Job ID: {$updatedJob['id']}\n";
        echo "Designation: {$updatedJob['designation']}\n";
        echo "Perks & Benefits: {$updatedJob['perks_and_benefits']}\n\n";
        echo "Decoded JSON:\n";
        print_r(json_decode($updatedJob['perks_and_benefits'], true));
        echo "</pre>";
        
        // Reset test data (optional - comment out if you want to keep it)
        // $resetStmt = $pdo->prepare("UPDATE jobs SET perks_and_benefits = NULL WHERE id = ?");
        // $resetStmt->execute([$testJob['id']]);
        
    } else {
        echo "<div class='info'>";
        echo "<strong>ℹ️ No Jobs Found</strong><br>";
        echo "Create a job first to test data insertion.";
        echo "</div>";
    }
    
    echo "<h2>✅ Migration Complete!</h2>";
    echo "<div class='info'>";
    echo "<p>The database is now ready to store Perks & Benefits data.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test creating a new job from the Flutter app</li>";
    echo "<li>Verify perks are saved when editing existing jobs</li>";
    echo "<li>You can safely delete this file after migration</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error Occurred:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";
echo "</body></html>";
?>
