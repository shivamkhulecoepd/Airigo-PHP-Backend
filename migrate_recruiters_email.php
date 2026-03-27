<?php
/**
 * Add Email Column to Recruiters Table Migration
 * 
 * This script adds the email column to the recruiters table and populates it
 * with values from the recruiter_name field where appropriate.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;

class RecruitersEmailMigration
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = Connection::getInstance();
            echo "✅ Database connection established\n";
        } catch (Exception $e) {
            die("❌ Could not connect to database: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Run the email column migration
     */
    public function addEmailColumn(): bool
    {
        echo "=== Adding Email Column to Recruiters Table ===\n\n";
        
        try {
            // Read the SQL file
            $sqlFile = __DIR__ . '/add_email_to_recruiters.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("SQL migration file not found: {$sqlFile}");
            }
            
            $sql = file_get_contents($sqlFile);
            echo "✅ Loaded SQL migration script\n";
            
            // Split by semicolon to execute statements one by one
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $successCount = 0;
            $totalCount = count($statements);
            
            foreach ($statements as $index => $statement) {
                // Skip empty statements and comments
                if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'SELECT') === 0) {
                    continue;
                }
                
                echo "Executing statement " . ($index + 1) . "/{$totalCount}... ";
                
                try {
                    $this->pdo->exec($statement);
                    echo "✅\n";
                    $successCount++;
                } catch (Exception $e) {
                    // Skip duplicate column/index errors as they're expected
                    if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                        strpos($e->getMessage(), 'Duplicate index') !== false) {
                        echo "✅ (already exists)\n";
                        $successCount++;
                    } else {
                        echo "❌ Error: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo "\n=== Migration Complete ===\n";
            echo "Successfully executed {$successCount}/{$totalCount} statements\n";
            
            // Verify the changes
            $this->verifyChanges();
            
            return $successCount > 0;
            
        } catch (Exception $e) {
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Verify that the email column was added correctly
     */
    public function verifyChanges(): void
    {
        echo "\n=== Verification ===\n";
        
        // Check if email column exists
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'recruiters' 
                AND COLUMN_NAME = 'email'
        ");
        $stmt->execute();
        $emailExists = $stmt->fetch();
        echo "Email column in recruiters table: " . ($emailExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Check if index exists for email
        $stmt = $this->pdo->prepare("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'recruiters' 
                AND INDEX_NAME = 'idx_email'
        ");
        $stmt->execute();
        $emailIndexExists = $stmt->fetch();
        echo "Index for email column: " . ($emailIndexExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Count recruiters with email populated
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM recruiters WHERE email IS NOT NULL");
        $stmt->execute();
        $emailCount = $stmt->fetchColumn();
        echo "Recruiters with email populated: {$emailCount}\n";
        
        // Show sample data
        echo "\n=== Sample Data ===\n";
        $stmt = $this->pdo->prepare("
            SELECT user_id, email, recruiter_name, company_name 
            FROM recruiters 
            WHERE email IS NOT NULL 
            LIMIT 5
        ");
        $stmt->execute();
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($samples as $sample) {
            echo "  User ID: {$sample['user_id']}\n";
            echo "  Email: {$sample['email']}\n";
            echo "  Recruiter Name: {$sample['recruiter_name']}\n";
            echo "  Company: {$sample['company_name']}\n";
            echo "  ---\n";
        }
    }
    
    /**
     * Show current recruiters table structure
     */
    public function showTableStructure(): void
    {
        echo "\n=== Recruiters Table Structure ===\n";
        
        $stmt = $this->pdo->prepare("DESCRIBE recruiters");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo "  {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }
    
    /**
     * Manual data correction - if needed
     */
    public function correctEmailData(): void
    {
        echo "\n=== Manual Data Correction ===\n";
        echo "This would update recruiter_name to contain actual names instead of emails.\n";
        echo "Current approach keeps both email and recruiter_name with email values for compatibility.\n";
        
        // Example of what could be done:
        // UPDATE recruiters SET recruiter_name = 'Priya Bhosale' WHERE user_id = 4;
        // But this requires knowing the actual names
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'migrate';
    $migration = new RecruitersEmailMigration();
    
    switch ($action) {
        case 'migrate':
            $migration->addEmailColumn();
            break;
            
        case 'structure':
            $migration->showTableStructure();
            break;
            
        case 'verify':
            $migration->verifyChanges();
            break;
            
        case 'correct':
            $migration->correctEmailData();
            break;
            
        default:
            echo "Usage: php migrate_recruiters_email.php [migrate|structure|verify|correct]\n";
            echo "\nCommands:\n";
            echo "  migrate   - Add email column and populate data (default)\n";
            echo "  structure - Show current table structure\n";
            echo "  verify    - Verify the migration results\n";
            echo "  correct   - Show manual correction options\n";
            break;
    }
} else {
    // Web interface
    $migration = new RecruitersEmailMigration();
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'migrate':
                $migration->addEmailColumn();
                break;
            case 'structure':
                $migration->showTableStructure();
                break;
            case 'verify':
                $migration->verifyChanges();
                break;
            case 'correct':
                $migration->correctEmailData();
                break;
        }
    } else {
        echo "<h2>Recruiters Email Column Migration</h2>";
        echo "<p><a href='?action=structure'>Show Table Structure</a></p>";
        echo "<p><a href='?action=verify'>Verify Changes</a></p>";
        echo "<p><a href='?action=migrate'>Run Migration</a></p>";
        echo "<p><a href='?action=correct'>Data Correction Options</a></p>";
    }
}
?>