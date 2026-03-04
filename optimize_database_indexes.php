<?php

// Database Indexing Script for Performance Optimization
// Run this script to add essential database indexes

use App\Core\Database\Connection;

require_once __DIR__ . '/src/bootstrap.php';

echo "Starting database indexing optimization...\n";

try {
    $connection = Connection::getInstance();
    
    // Users table indexes
    $indexes = [
        // Users table
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone)",
        "CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)",
        "CREATE INDEX IF NOT EXISTS idx_users_user_type ON users(user_type)",
        "CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified)",
        "CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at)",
        
        // Jobs table
        "CREATE INDEX IF NOT EXISTS idx_jobs_recruiter_user_id ON jobs(recruiter_user_id)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_approval_status ON jobs(approval_status)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_is_active ON jobs(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_category ON jobs(category)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_location ON jobs(location)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_job_type ON jobs(job_type)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_is_urgent_hiring ON jobs(is_urgent_hiring)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_created_at ON jobs(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_company_name ON jobs(company_name)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_designation ON jobs(designation)",
        
        // Applications table
        "CREATE INDEX IF NOT EXISTS idx_applications_job_id ON applications(job_id)",
        "CREATE INDEX IF NOT EXISTS idx_applications_user_id ON applications(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_applications_status ON applications(status)",
        "CREATE INDEX IF NOT EXISTS idx_applications_created_at ON applications(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_applications_applied_at ON applications(applied_at)",
        
        // Jobseekers table
        "CREATE INDEX IF NOT EXISTS idx_jobseekers_user_id ON jobseekers(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_jobseekers_location ON jobseekers(location)",
        "CREATE INDEX IF NOT EXISTS idx_jobseekers_experience ON jobseekers(experience)",
        "CREATE INDEX IF NOT EXISTS idx_jobseekers_created_at ON jobseekers(created_at)",
        
        // Recruiters table
        "CREATE INDEX IF NOT EXISTS idx_recruiters_user_id ON recruiters(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_recruiters_approval_status ON recruiters(approval_status)",
        "CREATE INDEX IF NOT EXISTS idx_recruiters_company_name ON recruiters(company_name)",
        "CREATE INDEX IF NOT EXISTS idx_recruiters_created_at ON recruiters(created_at)",
        
        // Composite indexes for common query patterns
        "CREATE INDEX IF NOT EXISTS idx_jobs_active_approved ON jobs(is_active, approval_status, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_category_location ON jobs(category, location, is_active, approval_status)",
        "CREATE INDEX IF NOT EXISTS idx_jobs_search ON jobs(company_name, designation, location, category, is_active, approval_status)",
        "CREATE INDEX IF NOT EXISTS idx_applications_job_status ON applications(job_id, status, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_users_type_status ON users(user_type, status, created_at)"
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($indexes as $indexSql) {
        try {
            $connection->exec($indexSql);
            echo "✓ Created index: " . extractIndexName($indexSql) . "\n";
            $successCount++;
        } catch (Exception $e) {
            // Index might already exist
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "ℹ Index already exists: " . extractIndexName($indexSql) . "\n";
                $successCount++;
            } else {
                echo "✗ Failed to create index: " . extractIndexName($indexSql) . " - " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n=== Indexing Summary ===\n";
    echo "Successful: $successCount\n";
    echo "Failed: $errorCount\n";
    echo "Total: " . ($successCount + $errorCount) . "\n";
    
    // Show current indexes
    echo "\n=== Current Database Indexes ===\n";
    showCurrentIndexes($connection);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function extractIndexName($sql) {
    preg_match('/INDEX IF NOT EXISTS ([^ ]+)/', $sql, $matches);
    return $matches[1] ?? 'unknown';
}

function showCurrentIndexes($connection) {
    $tables = ['users', 'jobs', 'applications', 'jobseekers', 'recruiters'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $connection->prepare("SHOW INDEX FROM {$table}");
            $stmt->execute();
            $indexes = $stmt->fetchAll();
            
            if (!empty($indexes)) {
                echo "\nTable: {$table}\n";
                foreach ($indexes as $index) {
                    if ($index['Key_name'] !== 'PRIMARY') {
                        echo "  - {$index['Key_name']} (Column: {$index['Column_name']})\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "Could not fetch indexes for table {$table}: " . $e->getMessage() . "\n";
        }
    }
}