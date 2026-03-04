# Airigo Job Portal - MySQL Database Documentation

## Table of Contents
1. [Overview](#overview)
2. [Database Schema](#database-schema)

## Overview

The Airigo Job Portal uses a MySQL database to store all application data. The database is designed to support a scalable job portal platform with features for job seekers, recruiters, and administrators. The database schema is normalized and optimized for performance with proper indexing and foreign key relationships.

### Core Entities
The database consists of six main tables that work together to provide the full functionality of the job portal:

1. **users** - Core user account information
2. **jobseekers** - Extended job seeker profiles
3. **recruiters** - Extended recruiter profiles
4. **jobs** - Job posting information
5. **applications** - Job application records
6. **issues_reports** - User feedback and reports

## Database Schema

### 1. users Table
Stores core user account information for both job seekers and recruiters.

```sql
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
);
```

**Columns Description:**
- `id`: Primary key, auto-incrementing unique identifier
- `email`: User's email address (unique constraint)
- `password_hash`: BCrypt hashed password
- `user_type`: User role (jobseeker or recruiter)
- `phone`: Optional phone number
- `status`: Account status (active, inactive, suspended)
- `email_verified`: Boolean indicating email verification status
- `created_at`: Timestamp of account creation
- `updated_at`: Timestamp of last update

### 2. jobseekers Table
Extended profile information for job seekers.

```sql
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
);
```

**Columns Description:**
- `user_id`: Primary key and foreign key to users table
- `name`: Job seeker's full name
- `qualification`: Educational qualifications
- `experience`: Years of work experience
- `location`: Current location
- `date_of_birth`: Date of birth
- `resume_url`: URL to resume stored in Firebase
- `resume_filename`: Original filename of resume
- `profile_image_url`: URL to profile image
- `skills`: JSON array of skills
- `bio`: Professional biography
- `created_at`: Profile creation timestamp
- `updated_at`: Last profile update timestamp

### 3. recruiters Table
Extended profile information for recruiters.

```sql
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
);
```

**Columns Description:**
- `user_id`: Primary key and foreign key to users table
- `company_name`: Company the recruiter represents
- `designation`: Recruiter's position
- `location`: Company location
- `photo_url`: URL to recruiter's photo
- `id_card_url`: URL to verification document
- `approval_status`: Verification status (pending/approved/rejected)
- `approved_by`: User ID of approving admin
- `approved_at`: Approval timestamp
- `rejection_reason`: Reason if application was rejected
- `created_at`: Profile creation timestamp
- `updated_at`: Last profile update timestamp

### 4. jobs Table
Job posting information from recruiters.

```sql
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
);
```

**Columns Description:**
- `id`: Primary key, auto-incrementing job ID
- `recruiter_user_id`: Foreign key to the recruiter who posted
- `company_name`: Name of the company offering the job
- `designation`: Job title/position
- `ctc`: Cost to company (salary package)
- `location`: Job location
- `category`: Job category/field
- `description`: Detailed job description
- `requirements`: JSON array of job requirements
- `skills_required`: JSON array of required skills
- `experience_required`: Required years of experience
- `is_active`: Boolean indicating if job is active
- `approval_status`: Admin approval status
- `is_urgent_hiring`: Boolean for urgent positions
- `job_type`: Employment type (full-time, part-time, etc.)
- `created_at`: Job posting timestamp
- `updated_at`: Last update timestamp

### 5. applications Table
Job application records linking job seekers to jobs.

```sql
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
);
```

**Columns Description:**
- `id`: Primary key, auto-incrementing application ID
- `job_id`: Foreign key to the job being applied for
- `jobseeker_user_id`: Foreign key to the applying job seeker
- `resume_url`: URL to resume submitted with application
- `cover_letter`: Cover letter text
- `status`: Application status
- `applied_at`: Application submission timestamp
- `updated_at`: Last status update timestamp

### 6. issues_reports Table
User feedback, issues, and reports.

```sql
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
);
```

**Columns Description:**
- `id`: Primary key, auto-incrementing report ID
- `user_id`: Foreign key to the user reporting
- `user_type`: Type of user making the report
- `type`: Report type (issue or report)
- `title`: Report title
- `description`: Detailed description of the issue/report
- `status`: Resolution status
- `admin_response`: Administrator's response
- `created_at`: Report creation timestamp
- `updated_at`: Last update timestamp
