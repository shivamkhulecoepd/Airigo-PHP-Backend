-- Airigo Job Portal Database Schema
-- Generated from the database manager script
-- Use this file to create the database schema in any MySQL-compatible database

-- Create the database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS airigo_job_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database (uncomment if needed)
-- USE airigo_job_portal;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  user_type ENUM('jobseeker', 'recruiter') NOT NULL,
  phone VARCHAR(20),
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  email_verified BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_user_type (user_type),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB;

-- Jobseekers Profile
CREATE TABLE IF NOT EXISTS jobseekers (
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
) ENGINE=InnoDB;

-- Recruiters Profile
CREATE TABLE IF NOT EXISTS recruiters (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  company_name VARCHAR(255) NOT NULL,
  designation VARCHAR(255),
  location VARCHAR(255),
  photo_url VARCHAR(500),
  id_card_url VARCHAR(500),
  approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  approved_by BIGINT UNSIGNED,
  approved_at TIMESTAMP NULL,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_approval_status (approval_status),
  INDEX idx_company_name (company_name),
  INDEX idx_location (location)
) ENGINE=InnoDB;

-- Jobs Table
CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recruiter_user_id BIGINT UNSIGNED NOT NULL,
  company_name VARCHAR(255) NOT NULL,
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
  INDEX idx_ctc (ctc)
) ENGINE=InnoDB;

-- Applications Table
CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  jobseeker_user_id BIGINT UNSIGNED NOT NULL,
  resume_url VARCHAR(500),
  cover_letter TEXT,
  status ENUM('pending', 'shortlisted', 'rejected', 'accepted') DEFAULT 'pending',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_job_id (job_id),
  INDEX idx_jobseeker_user_id (jobseeker_user_id),
  INDEX idx_status (status),
  INDEX idx_applied_at (applied_at DESC)
) ENGINE=InnoDB;

-- Issues Reports Table
CREATE TABLE IF NOT EXISTS issues_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  user_type ENUM('jobseeker', 'recruiter') NOT NULL,
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
) ENGINE=InnoDB;

-- Optional: Insert sample data for testing
-- Uncomment the following lines if you want to add sample data

/*
-- Insert sample admin user
INSERT IGNORE INTO users (email, password_hash, user_type, status, email_verified, created_at) 
VALUES ('admin@example.com', '$2y$10$example_hash_for_Admin@2026', 'jobseeker', 'active', 1, NOW());

-- Insert sample jobseeker
INSERT IGNORE INTO jobseekers (user_id, name, qualification, experience, location, skills, bio) 
VALUES (1, 'John Doe', 'Bachelor Degree', 3, 'New York', '["PHP", "JavaScript", "MySQL"]', 'Experienced developer');
*/