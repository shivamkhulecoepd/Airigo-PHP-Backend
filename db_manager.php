<?php

/**
 * Database Manager Script
 * 
 * This script handles database operations for the Airigo Job Portal Backend:
 * - Creates all required tables
 * - Checks database connectivity
 * - Verifies table structures
 * - Provides database health status
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;
use App\Config\AppConfig;

class DatabaseManager
{
    private $pdo;
    private $dbConfig;

    public function __construct()
    {
        $this->dbConfig = [
            'host' => AppConfig::get('database.host'),
            'port' => AppConfig::get('database.port'),
            'database' => AppConfig::get('database.database'),
            'username' => AppConfig::get('database.username'),
            'password' => AppConfig::get('database.password'),
            'charset' => AppConfig::get('database.charset'),
        ];

        try {
            $this->pdo = Connection::getInstance();
        } catch (Exception $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
    }

    /**
     * Check database connectivity
     */
    public function checkConnection(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            echo "❌ Database connection failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Create all required tables
     */
    public function createTables(): bool
    {
        $tablesCreated = 0;
        $sqlStatements = $this->getCreateTableStatements();

        foreach ($sqlStatements as $tableName => $sql) {
            echo "Creating table: {$tableName}... ";
            
            try {
                $this->pdo->exec($sql);
                echo "✅\n";
                $tablesCreated++;
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }

        echo "\nSuccessfully created {$tablesCreated} tables.\n";
        return $tablesCreated > 0;
    }

    /**
     * Get all CREATE TABLE statements
     */
    private function getCreateTableStatements(): array
    {
        return [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                user_type ENUM('jobseeker', 'recruiter', 'admin') NOT NULL,
                phone VARCHAR(20),
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                email_verified BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_user_type (user_type),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at DESC)
            ) ENGINE=InnoDB",

            'jobseekers' => "CREATE TABLE IF NOT EXISTS jobseekers (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                qualification TEXT,
                experience INT UNSIGNED DEFAULT 0,
                location VARCHAR(255),
                date_of_birth DATE,
                resume_url VARCHAR(500),
                resume_filename VARCHAR(255),
                profile_image_url VARCHAR(500),
                skills JSON,
                bio TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_location (location),
                INDEX idx_experience (experience),
                INDEX idx_name (name)
            ) ENGINE=InnoDB",

            'recruiters' => "CREATE TABLE IF NOT EXISTS recruiters (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                email VARCHAR(255) NULL,
                recruiter_name VARCHAR(255) NULL,
                company_name VARCHAR(255) NOT NULL,
                designation VARCHAR(255),
                location VARCHAR(255),
                photo_url VARCHAR(500),
                company_website VARCHAR(255) NULL,
                id_card_url VARCHAR(500),
                approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                approved_by BIGINT UNSIGNED,
                approved_at TIMESTAMP NULL,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_email (email),
                INDEX idx_approval_status (approval_status),
                INDEX idx_company_name (company_name),
                INDEX idx_location (location)
            ) ENGINE=InnoDB",

            'jobs' => "CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recruiter_user_id BIGINT UNSIGNED NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                company_logo_url VARCHAR(500),
                company_url VARCHAR(500) NULL,
                designation VARCHAR(255) NOT NULL,
                ctc VARCHAR(50) NOT NULL,
                location VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                description TEXT,
                requirements JSON,
                skills_required JSON,
                experience_required VARCHAR(50),
                is_active BOOLEAN DEFAULT TRUE,
                approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                is_urgent_hiring BOOLEAN DEFAULT FALSE,
                job_type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') DEFAULT 'Full-time',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (recruiter_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_location (location),
                INDEX idx_category (category),
                INDEX idx_is_active (is_active),
                INDEX idx_approval_status (approval_status),
                INDEX idx_created_at (created_at DESC),
                INDEX idx_is_urgent_hiring (is_urgent_hiring),
                INDEX idx_designation (designation),
                INDEX idx_ctc (ctc),
                INDEX idx_company_url (company_url)
            ) ENGINE=InnoDB",

            'applications' => "CREATE TABLE IF NOT EXISTS applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_id BIGINT UNSIGNED NOT NULL,
                recruiter_user_id BIGINT UNSIGNED NULL,
                jobseeker_user_id BIGINT UNSIGNED NOT NULL,
                resume_url VARCHAR(500),
                cover_letter TEXT,
                status ENUM('pending', 'shortlisted', 'rejected', 'accepted') DEFAULT 'pending',
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                FOREIGN KEY (recruiter_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (jobseeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_job_id (job_id),
                INDEX idx_recruiter_user_id (recruiter_user_id),
                INDEX idx_jobseeker_user_id (jobseeker_user_id),
                INDEX idx_status (status),
                INDEX idx_applied_at (applied_at DESC)
            ) ENGINE=InnoDB",

            'issues_reports' => "CREATE TABLE IF NOT EXISTS issues_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                user_type ENUM('jobseeker', 'recruiter', 'admin') NOT NULL,
                type ENUM('issue', 'report') NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
                admin_response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_type (user_type),
                INDEX idx_type (type),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at DESC),
                INDEX idx_title (title)
            ) ENGINE=InnoDB",

            'wishlist_items' => "CREATE TABLE IF NOT EXISTS wishlist_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_job (user_id, job_id),
                INDEX idx_user_id (user_id),
                INDEX idx_job_id (job_id),
                INDEX idx_created_at (created_at DESC)
            ) ENGINE=InnoDB",
            
            'password_reset_tokens' => "CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB"
        ];
    }

    /**
     * Check if all required tables exist
     */
    public function checkTablesExistence(): array
    {
        $requiredTables = array_keys($this->getCreateTableStatements());
        $existingTables = [];
        $missingTables = [];

        $stmt = $this->pdo->query("SHOW TABLES");
        $dbTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($requiredTables as $table) {
            if (in_array($table, $dbTables)) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }

        return [
            'existing' => $existingTables,
            'missing' => $missingTables,
            'all_exist' => empty($missingTables)
        ];
    }

    /**
     * Get table information
     */
    public function getTableInfo(string $tableName): array
    {
        try {
            $columns = $this->pdo->query("DESCRIBE {$tableName}")->fetchAll(PDO::FETCH_ASSOC);
            $indexes = $this->pdo->query("SHOW INDEXES FROM {$tableName}")->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'columns' => $columns,
                'indexes' => $indexes,
                'count' => $this->getTableRecordCount($tableName)
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get record count for a table
     */
    private function getTableRecordCount(string $tableName): int
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$tableName}");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get database health status
     */
    public function getHealthStatus(): array
    {
        $tablesCheck = $this->checkTablesExistence();
        $dbVersion = $this->getDatabaseVersion();
        $connectionStatus = $this->checkConnection();

        return [
            'connection' => $connectionStatus,
            'database_version' => $dbVersion,
            'tables_status' => $tablesCheck,
            'total_tables' => count($tablesCheck['existing']) + count($tablesCheck['missing']),
            'tables_complete' => $tablesCheck['all_exist'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion(): string
    {
        try {
            $stmt = $this->pdo->query("SELECT VERSION()");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Drop all tables (use with caution!)
     */
    public function dropAllTables(): bool
    {
        $requiredTables = array_keys($this->getCreateTableStatements());
        $droppedCount = 0;

        // Disable foreign key checks temporarily
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        foreach ($requiredTables as $table) {
            echo "Dropping table: {$table}... ";
            
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
                echo "✅\n";
                $droppedCount++;
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }

        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        echo "\nSuccessfully dropped {$droppedCount} tables.\n";
        return $droppedCount > 0;
    }

    /**
     * Insert sample data for testing
     */
    public function insertSampleData(): bool
    {
        try {
            // Insert sample admin user
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO users (email, password_hash, user_type, status, email_verified, created_at) 
                VALUES (?, ?, 'jobseeker', 'active', 1, NOW())
            ");
            
            $adminPassword = password_hash('Admin@2026', PASSWORD_DEFAULT);
            $stmt->execute(['admin@example.com', $adminPassword]);

            // Insert sample jobseeker
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO jobseekers (user_id, name, qualification, experience, location, skills, bio) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([1, 'John Doe', 'Bachelor Degree', 3, 'New York', json_encode(['PHP', 'JavaScript', 'MySQL']), 'Experienced developer']);

            echo "✅ Sample data inserted successfully\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Error inserting sample data: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Run database health check
     */
    public function runHealthCheck(): void
    {
        echo "=== Airigo Job Portal Database Health Check ===\n\n";
        
        echo "1. Checking database connection... ";
        if ($this->checkConnection()) {
            echo "✅ Connected\n";
        } else {
            echo "❌ Failed\n";
            return;
        }

        echo "\n2. Checking required tables...\n";
        $tablesCheck = $this->checkTablesExistence();
        
        echo "   Existing tables (" . count($tablesCheck['existing']) . "): " . implode(', ', $tablesCheck['existing']) . "\n";
        
        if (!empty($tablesCheck['missing'])) {
            echo "   Missing tables (" . count($tablesCheck['missing']) . "): " . implode(', ', $tablesCheck['missing']) . "\n";
        } else {
            echo "   All required tables exist ✅\n";
        }

        echo "\n3. Database information:\n";
        echo "   - Database Version: " . $this->getDatabaseVersion() . "\n";
        echo "   - Total Records: " . $this->getTotalRecords() . "\n";
        echo "   - Status: " . ($tablesCheck['all_exist'] ? 'Healthy ✅' : 'Incomplete ❌') . "\n";

        echo "\n=== Health Check Complete ===\n";
    }

    /**
     * Get total records across all tables
     */
    private function getTotalRecords(): int
    {
        $requiredTables = array_keys($this->getCreateTableStatements());
        $total = 0;

        foreach ($requiredTables as $table) {
            $total += $this->getTableRecordCount($table);
        }

        return $total;
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'health';
    $dbManager = new DatabaseManager();

    switch ($action) {
        case 'health':
            $dbManager->runHealthCheck();
            break;
            
        case 'create-tables':
            echo "Creating all required tables...\n";
            $dbManager->createTables();
            break;
            
        case 'check-tables':
            $result = $dbManager->checkTablesExistence();
            echo "Existing tables: " . implode(', ', $result['existing']) . "\n";
            if (!empty($result['missing'])) {
                echo "Missing tables: " . implode(', ', $result['missing']) . "\n";
            } else {
                echo "All required tables exist.\n";
            }
            break;
            
        case 'drop-all':
            echo "WARNING: This will drop all tables! Are you sure? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) === 'y') {
                echo "Dropping all tables...\n";
                $dbManager->dropAllTables();
            } else {
                echo "Operation cancelled.\n";
            }
            break;
            
        case 'sample-data':
            echo "Inserting sample data...\n";
            $dbManager->insertSampleData();
            break;
            
        case 'table-info':
            $tableName = $argv[2] ?? null;
            if ($tableName) {
                $info = $dbManager->getTableInfo($tableName);
                echo "Information for table '{$tableName}':\n";
                print_r($info);
            } else {
                echo "Usage: php db_manager.php table-info <table_name>\n";
            }
            break;
            
        default:
            echo "Usage: php db_manager.php [health|create-tables|check-tables|drop-all|sample-data|table-info]\n";
            echo "\nCommands:\n";
            echo "  health        - Run database health check (default)\n";
            echo "  create-tables - Create all required tables\n";
            echo "  check-tables  - Check which tables exist\n";
            echo "  drop-all      - Drop all tables (use with caution!)\n";
            echo "  sample-data   - Insert sample data for testing\n";
            echo "  table-info    - Show information about a specific table\n";
            break;
    }
}