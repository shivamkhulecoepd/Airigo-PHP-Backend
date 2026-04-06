-- Migration: Add perks_and_benefits column to jobs table
-- Run this on your production/development database to add support for perks and benefits

ALTER TABLE jobs 
ADD COLUMN perks_and_benefits JSON AFTER skills_required;

-- Verify the column was added
DESCRIBE jobs;
