-- Airigo Job Portal Database Schema
-- Generated on: 2026-02-28 09:09:00
-- From database: u233781988_airigoDB

-- applications Table
CREATE TABLE applications (
  id bigint(20) unsigned AUTO_INCREMENT,
  job_id bigint(20) unsigned NOT NULL,
  jobseeker_user_id bigint(20) unsigned NOT NULL,
  resume_url varchar(500) DEFAULT NULL,
  cover_letter text DEFAULT NULL,
  status enum('pending','shortlisted','rejected','accepted') DEFAULT 'pending',
  applied_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`id`)
  , FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , FOREIGN KEY (`jobseeker_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


-- issues_reports Table
CREATE TABLE issues_reports (
  id bigint(20) unsigned AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  user_type enum('jobseeker','recruiter') NOT NULL,
  type enum('issue','report') NOT NULL,
  title varchar(255) NOT NULL,
  description text NOT NULL,
  status enum('pending','in_progress','resolved') DEFAULT 'pending',
  admin_response text DEFAULT NULL,
  created_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`id`)
  , FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


-- jobs Table
CREATE TABLE jobs (
  id bigint(20) unsigned AUTO_INCREMENT,
  recruiter_user_id bigint(20) unsigned NOT NULL,
  company_name varchar(255) NOT NULL,
  designation varchar(255) NOT NULL,
  ctc varchar(50) NOT NULL,
  location varchar(255) NOT NULL,
  category varchar(100) NOT NULL,
  description text DEFAULT NULL,
  requirements longtext DEFAULT NULL,
  skills_required longtext DEFAULT NULL,
  experience_required varchar(50) DEFAULT NULL,
  is_active tinyint(1) DEFAULT 1,
  approval_status enum('pending','approved','rejected') DEFAULT 'pending',
  is_urgent_hiring tinyint(1) DEFAULT 0,
  job_type enum('Full-time','Part-time','Contract','Internship') DEFAULT 'Full-time',
  created_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`id`)
  , FOREIGN KEY (`recruiter_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


-- jobseekers Table
CREATE TABLE jobseekers (
  user_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL,
  qualification text DEFAULT NULL,
  experience int(10) unsigned DEFAULT 0,
  location varchar(255) DEFAULT NULL,
  date_of_birth date DEFAULT NULL,
  resume_url varchar(500) DEFAULT NULL,
  resume_filename varchar(255) DEFAULT NULL,
  profile_image_url varchar(500) DEFAULT NULL,
  skills longtext DEFAULT NULL,
  bio text DEFAULT NULL,
  created_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`user_id`)
  , FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


-- recruiters Table
CREATE TABLE recruiters (
  user_id bigint(20) unsigned NOT NULL,
  company_name varchar(255) NOT NULL,
  designation varchar(255) DEFAULT NULL,
  location varchar(255) DEFAULT NULL,
  photo_url varchar(500) DEFAULT NULL,
  id_card_url varchar(500) DEFAULT NULL,
  approval_status enum('pending','approved','rejected') DEFAULT 'pending',
  approved_by bigint(20) unsigned DEFAULT NULL,
  approved_at timestamp DEFAULT NULL,
  rejection_reason text DEFAULT NULL,
  created_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`user_id`)
  , FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
  , FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE RESTRICT
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


-- users Table
CREATE TABLE users (
  id bigint(20) unsigned AUTO_INCREMENT,
  email varchar(255) NOT NULL,
  password_hash varchar(255) NOT NULL,
  user_type enum('jobseeker','recruiter') NOT NULL,
  phone varchar(20) DEFAULT NULL,
  status enum('active','inactive','suspended') DEFAULT 'active',
  email_verified tinyint(1) DEFAULT 0,
  created_at timestamp DEFAULT 'current_timestamp()',
  updated_at timestamp DEFAULT 'current_timestamp()',
  PRIMARY KEY (`id`)
  , INDEX idx_custom_indexes -- Additional indexes would be listed here
) ENGINE=InnoDB;


