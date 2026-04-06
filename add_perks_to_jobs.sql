-- Migration: Add perks_and_benefits column to jobs table
-- This adds support for job perks and benefits similar to skills_required

ALTER TABLE jobs 
ADD COLUMN perks_and_benefits JSON AFTER skills_required;

-- Add index for better query performance (optional)
-- INDEX idx_perks_and_benefits (perks_and_benefits(255))
