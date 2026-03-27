-- Migration script to add email column to recruiters table
-- This separates the email from recruiter_name field

-- 1. Add email column to recruiters table (if it doesn't exist)
ALTER TABLE recruiters 
ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER user_id;

-- Add index for email in recruiters table (if it doesn't exist)
SET @index_exists = (SELECT COUNT(*) FROM information_schema.statistics 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'recruiters' 
                   AND index_name = 'idx_email');

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE recruiters ADD INDEX idx_email (email)', 
    'SELECT "Index idx_email already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Populate email column with values from recruiter_name (where recruiter_name contains @)
-- This assumes recruiter_name currently contains email addresses
UPDATE recruiters 
SET email = recruiter_name 
WHERE recruiter_name IS NOT NULL 
  AND recruiter_name LIKE '%@%'
  AND email IS NULL;

-- 3. Optional: Update recruiter_name to contain actual names
-- This would require a mapping or manual update
-- For now, we'll keep the email in recruiter_name as well for backward compatibility

-- 4. Add foreign key constraint to link to users table email (if needed)
-- This would require joining with users table to get the actual email

-- 5. Verify the updates
SELECT 'Email column added to recruiters table successfully' as status;

-- Show the structure change
SELECT 
    COLUMN_NAME as 'Column',
    COLUMN_TYPE as 'Type',
    IS_NULLABLE as 'Nullable'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recruiters' 
    AND COLUMN_NAME IN ('email', 'recruiter_name')
ORDER BY ORDINAL_POSITION;

-- Show sample data to verify the migration
SELECT 
    user_id,
    email,
    recruiter_name,
    company_name
FROM recruiters 
WHERE email IS NOT NULL 
LIMIT 5;