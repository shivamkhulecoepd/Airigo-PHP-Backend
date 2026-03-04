# Airigo Job Portal - MySQL Database Documentation

## Table of Contents
1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Database Configuration](#database-configuration)
4. [Database Management](#database-management)
5. [Implementation Details](#implementation-details)
6. [Security Considerations](#security-considerations)
7. [Performance Optimization](#performance-optimization)
8. [Troubleshooting](#troubleshooting)

## Overview

The Airigo Job Portal uses a MySQL database to store all application data. The database is designed to support a scalable job portal platform with features for job seekers, recruiters, and administrators. The database schema is normalized and optimized for performance with proper indexing and foreign key relationships.

### Database Specifications
- **Engine**: InnoDB
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Minimum Version**: MySQL 8.0+ / MariaDB 10.2+
- **Remote Host**: 193.203.184.189 (Hostinger)

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
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
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
) ENGINE=InnoDB;
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

## Database Configuration

### Environment Variables
The database connection is configured through environment variables in the `.env` file:

```env
# Database Configuration
DB_HOST=193.203.184.189
DB_PORT=3306
DB_NAME=u233781988_airigoDB
DB_USER=u233781988_airigoDB
DB_PASSWORD='Airigo@#2026'
DB_CHARSET=utf8mb4
```

### Connection Class
The database connection is managed by the `App\Core\Database\Connection` class which implements a singleton pattern with persistent connections:

```php
class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): ?PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        
        return self::$instance;
    }

    private static function connect(): void
    {
        try {
            $host = AppConfig::get('database.host');
            $port = AppConfig::get('database.port');
            $dbname = AppConfig::get('database.database');
            $username = AppConfig::get('database.username');
            $password = AppConfig::get('database.password');
            $charset = AppConfig::get('database.charset');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Enable persistent connections
            ];

            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException('Connection failed: ' . $e->getMessage());
        }
    }
}
```

## Database Management

### Database Manager Script
The `db_manager.php` script provides comprehensive database management capabilities:

#### Available Commands:
- `php db_manager.php health` - Run database health check
- `php db_manager.php create-tables` - Create all required tables
- `php db_manager.php check-tables` - Check which tables exist
- `php db_manager.php drop-all` - Drop all tables (with confirmation)
- `php db_manager.php sample-data` - Insert sample data for testing
- `php db_manager.php table-info <table_name>` - Show information about a specific table

#### Health Check Output:
The health check provides information about:
- Database connectivity
- Required tables existence
- Database version
- Total record count
- Overall health status

#### Sample Data:
The database manager can insert sample data for testing purposes:
- Admin user with email 'admin@example.com'
- Sample job seeker profile

### Schema Generation
The `generate_sql_schema.php` script can generate updated SQL schema files based on the current database structure:

```bash
php generate_sql_schema.php [output_filename.sql]
```

This is useful for:
- Documentation of current database state
- Migration reference
- Recovery procedures
- Audit purposes

## Implementation Details

### Foreign Key Relationships
The database implements proper foreign key relationships to maintain data integrity:

1. **users → jobseekers/recruiters**: One-to-one relationship
2. **users → jobs**: One-to-many (recruiter posts multiple jobs)
3. **users → applications**: One-to-many (user applies to multiple jobs)
4. **users → issues_reports**: One-to-many (user submits multiple reports)
5. **jobs → applications**: One-to-many (job receives multiple applications)

### Index Strategy
Comprehensive indexing strategy for optimal query performance:

- **Primary Keys**: Auto-incrementing BIGINT for all main tables
- **Foreign Key Indexes**: Indexes on all foreign key columns
- **Common Query Indexes**: Indexes on frequently queried columns
- **Composite Indexes**: Planned for complex queries
- **Performance Indexes**: Indexes on timestamp and status columns

### Data Types and Constraints
- **BIGINT UNSIGNED**: Primary and foreign keys for large scale support
- **ENUM**: Strict value constraints for statuses and types
- **JSON**: Flexible storage for arrays of skills, requirements
- **TEXT/LONGTEXT**: Variable length content storage
- **BOOLEAN**: Explicit boolean fields with defaults
- **TIMESTAMP**: Automatic creation and update timestamps

### Repository Pattern Implementation
Database access is abstracted through the Repository pattern:

```php
interface RepositoryInterface
{
    public function findById(int $id);
    public function findAll(array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
}

abstract class BaseRepository implements RepositoryInterface
{
    protected PDO $pdo;
    protected string $table;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    // Common database operations
}
```

## Security Considerations

### SQL Injection Prevention
- All database queries use prepared statements
- User inputs are properly escaped and validated
- Parameter binding prevents malicious SQL injection

### Authentication & Authorization
- Passwords are hashed using BCrypt algorithm
- JWT tokens provide secure session management
- Role-based access controls prevent unauthorized access

### Data Integrity
- Foreign key constraints maintain referential integrity
- Unique constraints prevent duplicate entries
- Check constraints enforce data validity

### Access Control
- Database user has limited permissions
- Read/write operations restricted to necessary tables
- No administrative privileges granted to application user

## Performance Optimization

### Query Optimization
- Prepared statements for repeated queries
- Proper indexing strategy for common queries
- Efficient join operations
- Pagination for large datasets

### Connection Pooling
- Persistent database connections
- Connection reuse across requests
- Efficient resource utilization

### Caching Strategy
- Planned Redis integration for frequently accessed data
- Cache invalidation strategies
- Performance improvement for read-heavy operations

### Index Optimization
The database schema includes comprehensive indexing:

**Users Table:**
- `idx_email`: For login and unique email verification
- `idx_user_type`: For role-based queries
- `idx_status`: For active/inactive filtering
- `idx_created_at`: For chronological sorting

**Jobseekers Table:**
- `idx_location`: For location-based job searches
- `idx_experience`: For experience-level filtering
- `idx_name`: For name-based searches

**Recruiters Table:**
- `idx_approval_status`: For admin approval workflows
- `idx_company_name`: For company-based searches
- `idx_location`: For location-based recruiter searches

**Jobs Table:**
- `idx_location`: For location-based job searches
- `idx_category`: For category-based filtering
- `idx_is_active`: For active job filtering
- `idx_approval_status`: For admin review queues
- `idx_created_at`: For newest job listings
- `idx_is_urgent_hiring`: For urgent job priority
- `idx_designation`: For position-based searches
- `idx_ctc`: For salary-based filtering

**Applications Table:**
- `idx_job_id`: For job-specific applications
- `idx_jobseeker_user_id`: For user's application history
- `idx_status`: For application status tracking
- `idx_applied_at`: For chronological application review

**Issues Reports Table:**
- `idx_user_type`: For role-based report management
- `idx_type`: For issue vs report filtering
- `idx_status`: For resolution tracking
- `idx_created_at`: For chronological report review
- `idx_title`: For report search functionality

## Troubleshooting

### Common Issues and Solutions

#### 1. Database Connection Failures
**Symptoms:** Unable to connect to database
**Solution:** Check `.env` file for correct database credentials

#### 2. Missing Tables
**Symptoms:** Table doesn't exist errors
**Solution:** Run `php db_manager.php create-tables`

#### 3. Foreign Key Constraint Violations
**Symptoms:** Cannot insert/update due to constraint violations
**Solution:** Ensure referenced records exist before creating relationships

#### 4. Performance Issues
**Symptoms:** Slow query responses
**Solution:** Check index usage and optimize queries

### Diagnostic Commands

#### Check Database Health
```bash
php db_manager.php health
```

#### Verify Table Existence
```bash
php db_manager.php check-tables
```

#### View Specific Table Info
```bash
php db_manager.php table-info users
```

### Monitoring Queries

#### Check Active Connections
```sql
SHOW PROCESSLIST;
```

#### Analyze Slow Queries
```sql
SHOW VARIABLES LIKE 'slow_query_log';
```

#### Monitor Database Size
```sql
SELECT 
    table_name AS `Table`,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS `Size (MB)`
FROM information_schema.tables
WHERE table_schema = 'u233781988_airigoDB'
ORDER BY (data_length + index_length) DESC;
```

## Deployment Notes

### Production Deployment
1. Ensure database server meets minimum requirements
2. Import schema using `database_schema_updated.sql`
3. Configure environment variables appropriately
4. Test database connectivity before launching
5. Monitor performance metrics post-deployment

### Scaling Considerations
- Database connection limits
- Query optimization for high load
- Potential need for read replicas
- Caching layer implementation
- Database backup strategies

### Backup and Recovery
- Regular automated backups
- Point-in-time recovery capabilities
- Schema version tracking
- Disaster recovery procedures