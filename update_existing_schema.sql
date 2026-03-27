-- Migration script to update existing database schema
-- This script adds the missing columns to existing tables

-- 1. Add company_url column to jobs table (if it doesn't exist)
ALTER TABLE jobs 
ADD COLUMN IF NOT EXISTS company_url VARCHAR(500) NULL AFTER company_logo_url;

-- Add index for company_url in jobs table (if it doesn't exist)
SET @index_exists = (SELECT COUNT(*) FROM information_schema.statistics 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'jobs' 
                   AND index_name = 'idx_company_url');

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE jobs ADD INDEX idx_company_url (company_url)', 
    'SELECT "Index idx_company_url already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add recruiter_user_id column to applications table (if it doesn't exist)
ALTER TABLE applications 
ADD COLUMN IF NOT EXISTS recruiter_user_id BIGINT UNSIGNED NULL AFTER job_id;

-- Add foreign key constraint for recruiter_user_id in applications table (if it doesn't exist)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'applications' 
                 AND constraint_name = 'fk_applications_recruiter_user_id');

SET @sql_fk = IF(@fk_exists = 0, 
    'ALTER TABLE applications ADD CONSTRAINT fk_applications_recruiter_user_id FOREIGN KEY (recruiter_user_id) REFERENCES users(id) ON DELETE CASCADE', 
    'SELECT "Foreign key fk_applications_recruiter_user_id already exists" as message');

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Add index for recruiter_user_id in applications table (if it doesn't exist)
SET @index_app_exists = (SELECT COUNT(*) FROM information_schema.statistics 
                        WHERE table_schema = DATABASE() 
                        AND table_name = 'applications' 
                        AND index_name = 'idx_recruiter_user_id');

SET @sql_app_index = IF(@index_app_exists = 0, 
    'ALTER TABLE applications ADD INDEX idx_recruiter_user_id (recruiter_user_id)', 
    'SELECT "Index idx_recruiter_user_id already exists" as message');

PREPARE stmt_app_index FROM @sql_app_index;
EXECUTE stmt_app_index;
DEALLOCATE PREPARE stmt_app_index;

-- 3. Populate the recruiter_user_id in existing applications based on job ownership
UPDATE applications a 
JOIN jobs j ON a.job_id = j.id 
SET a.recruiter_user_id = j.recruiter_user_id 
WHERE a.recruiter_user_id IS NULL;

-- 4. Verify the updates
SELECT 'Schema update completed successfully' as status;
SELECT 
    COLUMN_NAME as 'Column',
    COLUMN_TYPE as 'Type',
    IS_NULLABLE as 'Nullable'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'jobs' 
    AND COLUMN_NAME = 'company_url'
UNION ALL
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'applications' 
    AND COLUMN_NAME = 'recruiter_user_id';

-- Show index information
SELECT 
    INDEX_NAME as 'Index',
    COLUMN_NAME as 'Column',
    NON_UNIQUE as 'Non Unique'
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN ('jobs', 'applications')
    AND INDEX_NAME IN ('idx_company_url', 'idx_recruiter_user_id', 'fk_applications_recruiter_user_id')
ORDER BY TABLE_NAME, INDEX_NAME;