ALTER TABLE recruiters ADD COLUMN email VARCHAR(255) NULL AFTER user_id;
ALTER TABLE recruiters ADD INDEX idx_email (email);
UPDATE recruiters SET email = recruiter_name WHERE recruiter_name LIKE '%@%' AND email IS NULL;