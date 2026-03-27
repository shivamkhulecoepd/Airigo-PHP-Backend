<?php
/**
 * Database Schema Update Script
 * 
 * This script updates the existing database schema to include the new columns:
 * - company_url column in jobs table
 * - recruiter_user_id column in applications table
 * - Populates existing applications with recruiter_user_id based on job ownership
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;

class SchemaUpdater
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
     * Run the schema update
     */
    public function updateSchema(): bool
    {
        echo "=== Starting Schema Update ===\n\n";
        
        try {
            // Read the SQL file
            $sqlFile = __DIR__ . '/update_existing_schema.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("SQL update file not found: {$sqlFile}");
            }
            
            $sql = file_get_contents($sqlFile);
            echo "✅ Loaded SQL update script\n";
            
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
                        strpos($e->getMessage(), 'Duplicate index') !== false ||
                        strpos($e->getMessage(), 'already exists') !== false) {
                        echo "✅ (already exists)\n";
                        $successCount++;
                    } else {
                        echo "❌ Error: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo "\n=== Schema Update Complete ===\n";
            echo "Successfully executed {$successCount}/{$totalCount} statements\n";
            
            // Verify the changes
            $this->verifyChanges();
            
            return $successCount > 0;
            
        } catch (Exception $e) {
            echo "❌ Schema update failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Verify that the schema changes were applied correctly
     */
    public function verifyChanges(): void
    {
        echo "\n=== Verification ===\n";
        
        // Check if company_url column exists in jobs table
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'jobs' 
                AND COLUMN_NAME = 'company_url'
        ");
        $stmt->execute();
        $companyUrlExists = $stmt->fetch();
        echo "Company URL column in jobs table: " . ($companyUrlExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Check if recruiter_user_id column exists in applications table
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'applications' 
                AND COLUMN_NAME = 'recruiter_user_id'
        ");
        $stmt->execute();
        $recruiterUserIdExists = $stmt->fetch();
        echo "Recruiter user ID column in applications table: " . ($recruiterUserIdExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Check if index exists for company_url
        $stmt = $this->pdo->prepare("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'jobs' 
                AND INDEX_NAME = 'idx_company_url'
        ");
        $stmt->execute();
        $companyUrlIndexExists = $stmt->fetch();
        echo "Index for company_url: " . ($companyUrlIndexExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Check if index exists for recruiter_user_id
        $stmt = $this->pdo->prepare("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'applications' 
                AND INDEX_NAME = 'idx_recruiter_user_id'
        ");
        $stmt->execute();
        $recruiterUserIdIndexExists = $stmt->fetch();
        echo "Index for recruiter_user_id: " . ($recruiterUserIdIndexExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Check if foreign key exists
        $stmt = $this->pdo->prepare("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'applications' 
                AND CONSTRAINT_NAME = 'fk_applications_recruiter_user_id'
        ");
        $stmt->execute();
        $foreignKeyExists = $stmt->fetch();
        echo "Foreign key constraint: " . ($foreignKeyExists ? "✅ Exists" : "❌ Missing") . "\n";
        
        // Count applications with recruiter_user_id populated
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM applications WHERE recruiter_user_id IS NOT NULL");
        $stmt->execute();
        $populatedCount = $stmt->fetchColumn();
        echo "Applications with recruiter_user_id populated: {$populatedCount}\n";
    }
    
    /**
     * Show current table structure
     */
    public function showTableStructure(): void
    {
        echo "\n=== Current Table Structure ===\n";
        
        // Show jobs table structure
        echo "\nJobs table columns:\n";
        $stmt = $this->pdo->prepare("DESCRIBE jobs");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
        
        // Show applications table structure
        echo "\nApplications table columns:\n";
        $stmt = $this->pdo->prepare("DESCRIBE applications");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'update';
    $updater = new SchemaUpdater();
    
    switch ($action) {
        case 'update':
            $updater->updateSchema();
            break;
            
        case 'structure':
            $updater->showTableStructure();
            break;
            
        case 'verify':
            $updater->verifyChanges();
            break;
            
        default:
            echo "Usage: php update_schema.php [update|structure|verify]\n";
            echo "\nCommands:\n";
            echo "  update     - Update database schema (default)\n";
            echo "  structure  - Show current table structure\n";
            echo "  verify     - Verify schema changes\n";
            break;
    }
} else {
    // Web interface
    $updater = new SchemaUpdater();
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'update':
                $updater->updateSchema();
                break;
            case 'structure':
                $updater->showTableStructure();
                break;
            case 'verify':
                $updater->verifyChanges();
                break;
        }
    } else {
        echo "<h2>Airigo Job Portal Schema Update</h2>";
        echo "<p><a href='?action=structure'>Show Table Structure</a></p>";
        echo "<p><a href='?action=verify'>Verify Changes</a></p>";
        echo "<p><a href='?action=update'>Update Schema</a></p>";
    }
}
?>