-- Migration script for Airigo Job Portal Database Schema Updates

-- 1. Add recruiter_id column to applications table
ALTER TABLE applications ADD COLUMN recruiter_user_id BIGINT UNSIGNED NULL AFTER job_id;

-- Populate the recruiter_user_id in applications table based on the job's recruiter_user_id
UPDATE applications a 
JOIN jobs j ON a.job_id = j.id 
SET a.recruiter_user_id = j.recruiter_user_id;

-- Add foreign key constraint for recruiter_user_id in applications table
ALTER TABLE applications 
ADD CONSTRAINT fk_applications_recruiter_user_id 
FOREIGN KEY (recruiter_user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Add index for recruiter_user_id in applications table
ALTER TABLE applications 
ADD INDEX idx_recruiter_user_id (recruiter_user_id);

-- 2. Add recruiter_name and company_website columns to recruiters table
ALTER TABLE recruiters 
ADD COLUMN recruiter_name VARCHAR(255) NULL AFTER user_id,
ADD COLUMN company_website VARCHAR(255) NULL AFTER photo_url;

-- Update existing recruiter records to set recruiter_name based on user email or company_name
-- This is a placeholder - you would need to populate with actual names if available elsewhere
UPDATE recruiters r 
JOIN users u ON r.user_id = u.id 
SET r.recruiter_name = u.email 
WHERE r.recruiter_name IS NULL;

-- 3. Add company_url column to jobs table
ALTER TABLE jobs 
ADD COLUMN company_url VARCHAR(500) NULL AFTER company_logo_url;

-- Add index for company_url in jobs table
ALTER TABLE jobs 
ADD INDEX idx_company_url (company_url);