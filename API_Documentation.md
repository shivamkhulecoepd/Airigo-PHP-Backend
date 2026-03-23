# Airigo Job Portal API Documentation

## Table of Contents
1. [Getting Started](#getting-started)
2. [Authentication](#authentication)
3. [User Management](#user-management)
4. [Job Management](#job-management)
5. [Application Management](#application-management)
6. [Wishlist Management](#wishlist-management)
7. [Admin Panel](#admin-panel)
8. [Password Reset Flow](#password-reset-flow)
9. [Postman Setup](#postman-setup)

## Getting Started

The Airigo Job Portal API is a RESTful service built with PHP 8.2+. The API follows standard HTTP status codes and returns JSON responses.

Base URL: `http://localhost:8000` (for local development)

## Authentication

Most endpoints require authentication using JWT (JSON Web Tokens). After successful login, you will receive both an access token and a refresh token.

### Headers
- For authenticated requests: `Authorization: Bearer {access_token}`
- Content Type: `Content-Type: application/json`

## Password Reset Flow

### 1. Forgot Password
**Endpoint:** `POST /api/auth/forgot-password`

**Description:** Request a password reset token. The token will be sent to the user's email or phone number. If email is configured, the token will be sent via email. Otherwise, it will be sent via Firebase Cloud Messaging.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```
OR
```json
{
  "phone": "+1234567890"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Password reset instructions sent successfully"
}
```

### 2. Reset Password
**Endpoint:** `POST /api/auth/reset-password`

**Description:** Reset the user's password using the reset token received via email/phone.

**Request Body:**
```json
{
  "reset_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "new_password": "NewSecurePassword@123"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

**Error Responses:**
- `400 Bad Request`: Invalid token or weak password
- `404 Not Found`: Token not found or expired
- `422 Unprocessable Entity`: Token already used

### 3. Email Configuration

To enable email notifications for password reset (and other features), configure your email settings in the `.env` file:

```env
# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
FROM_EMAIL=your-email@gmail.com
FROM_NAME="Airigo Jobs"
SMTP_AUTH=true
SMTP_ENCRYPTION=tls
```

**Important:** For Gmail, use an App Password instead of your regular password. To generate an App Password:
1. Go to Google Account settings
2. Security > 2-Step Verification > App passwords
3. Generate a password for 'Mail'

Without proper email configuration, password reset tokens will still be generated and stored in the database, but users will only receive them via Firebase Cloud Messaging (if configured) or by checking the application logs.

## Postman Setup

### Environment Variables
Set up the following variables in your Postman environment:

- `base_url`: `http://localhost:8000` (or your server URL)
- `access_token`: (will be populated after login)
- `refresh_token`: (will be populated after login)
- `reset_token`: `eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjEsImp0aSI6IjEyMzQ1Njc4OTAiLCJpYXQiOjE3MDkxMjIxNDAsImVpcCI6MTcwOTIwODU0MH0.example_token_for_testing`
- `user_id`: `1`
- `job_id`: `1`
- `application_id`: `1`
- `test_email`: `test@example.com`
- `test_phone`: `+1234567890`
- `test_password`: `Test@12345`
- `test_user_type`: `jobseeker`

### Testing Password Reset Flow

1. **Register a user** (if not already registered):
   - Endpoint: `POST /api/auth/register`
   - Body: 
   ```json
   {
     "email": "{{test_email}}",
     "password": "{{test_password}}",
     "user_type": "{{test_user_type}}",
     "name": "Test User",
     "phone": "{{test_phone}}",
     "location": "Test Location"
   }
   ```

2. **Request password reset**:
   - Endpoint: `POST /api/auth/forgot-password`
   - Body:
   ```json
   {
     "email": "{{test_email}}"
   }
   ```

3. **Reset password** (use the token received):
   - Endpoint: `POST /api/auth/reset-password`
   - Body:
   ```json
   {
     "reset_token": "{{reset_token}}",
     "new_password": "NewSecurePassword@123"
   }
   ```

### Test Data for Development

#### Sample User Registration Data
```json
{
  "email": "testuser@example.com",
  "password": "TestPassword@123",
  "user_type": "jobseeker",
  "name": "Test User",
  "phone": "+1234567890",
  "location": "New York"
}
```

#### Sample Job Creation Data
```json
{
  "title": "Software Engineer",
  "description": "We are looking for a skilled software engineer...",
  "requirements": "Bachelor's degree in Computer Science, 3+ years experience...",
  "location": "San Francisco, CA",
  "salary_min": 80000,
  "salary_max": 120000,
  "job_type": "full-time",
  "category": "Technology",
  "experience_level": "mid-level",
  "remote_work": true,
  "benefits": "Health insurance, PTO, Stock options"
}
```

#### Sample Application Data
```json
{
  "job_id": 1,
  "cover_letter": "I am excited to apply for this position...",
  "expected_salary": 90000
}
```

## Database Structure

### Required Tables
The system requires the following tables to function properly:

1. `users` - Main user accounts
2. `jobseekers` - Jobseeker profile information
3. `recruiters` - Recruiter profile information
4. `jobs` - Job postings
5. `applications` - Job applications
6. `issues_reports` - User reports and issues
7. `wishlist_items` - User's saved jobs
8. `password_reset_tokens` - Password reset tokens (NEW)

### Password Reset Tokens Table
```sql
CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;
```

## Troubleshooting

### Common Issues

1. **Table doesn't exist errors**: Run the database setup script:
   ```bash
   php db_manager.php create-tables
   ```

2. **Connection errors**: Verify your `.env` file has correct database credentials.

3. **JWT token issues**: Ensure your JWT secret keys in `.env` are properly set.

4. **Password reset token not found**: The token might have expired (24-hour expiry) or already been used.

## Running the Application

### Local Development
1. Install dependencies: `composer install`
2. Set up environment: Copy `.env.example` to `.env` and configure
3. Create database tables: `php db_manager.php create-tables`
4. Start server: `php -S localhost:8000 -t public/`

### Database Management Commands
- Check tables: `php db_manager.php check-tables`
- Create tables: `php db_manager.php create-tables`
- Insert sample data: `php db_manager.php sample-data`
- Health check: `php db_manager.php health`
- Drop all tables: `php db_manager.php drop-all` (Use with caution!)

## Security Notes

- All passwords are hashed using PHP's `password_hash()` function
- JWT tokens have configurable expiration times
- Rate limiting is implemented to prevent abuse
- Input validation is performed on all API endpoints
- Password reset tokens expire after 24 hours and can only be used once