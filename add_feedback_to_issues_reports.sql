-- Update the issues_reports table to only include 'issue' and 'feedback' as valid types (removing 'report')
ALTER TABLE issues_reports MODIFY COLUMN type ENUM('issue', 'feedback') NOT NULL;