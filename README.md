# Airigo Job Portal Backend

A comprehensive job portal backend built with PHP 8.2+ featuring authentication, job management, application tracking, and admin panel.

## Features

- User authentication with JWT tokens
- Role-based access control (Jobseeker, Recruiter, Admin)
- Job posting and search functionality
- Application management
- Wishlist functionality
- Admin panel with analytics
- Password reset with email/SMS verification
- Firebase Cloud Messaging integration
- Redis caching
- Rate limiting

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Redis (for caching)

## Installation

1. Clone the repository
2. Navigate to the project directory
3. Install dependencies:
   ```bash
   composer install
   ```
4. Copy `.env.example` to `.env` and configure your environment variables:
   ```bash
   cp .env.example .env
   ```
5. Set up your database credentials in the `.env` file
6. Create the database tables:
   ```bash
   php db_manager.php create-tables
   ```
7. Start the development server:
   ```bash
   php -S localhost:8000 -t public/
   ```

## Database Setup

The application requires several tables to function properly. Use the database manager script to create them:

```bash
# Create all required tables
php db_manager.php create-tables

# Check which tables exist
php db_manager.php check-tables

# Insert sample data for testing
php db_manager.php sample-data

# Run health check
php db_manager.php health
```

**Important:** The `password_reset_tokens` table was recently added to support the password reset functionality. If you're upgrading from an older version, run the create-tables command to add it.

## API Documentation

API documentation is available in [API_Documentation.md](./API_Documentation.md).

## Postman Collection

Import the included Postman collection (`Airigo_Job_Portal_API.postman_collection.json`) to test the API endpoints easily.

## Environment Configuration

Key environment variables in `.env`:

- `DB_*` - Database connection settings
- `JWT_SECRET_KEY` - Secret key for JWT tokens
- `FIREBASE_*` - Firebase configuration
- `REDIS_*` - Redis configuration
- `SMTP_*` - Email configuration for password reset and notifications
- `APP_DEBUG` - Enable/disable debug mode

### Email Configuration
To enable email notifications (password reset, welcome emails, etc.), set these variables:
- `SMTP_HOST` - SMTP server (e.g., smtp.gmail.com)
- `SMTP_PORT` - Port number (e.g., 587)
- `SMTP_USERNAME` - Your email address
- `SMTP_PASSWORD` - Your email password or app password
- `FROM_EMAIL` - Sender email address
- `FROM_NAME` - Sender name

For Gmail, use an App Password instead of your regular password.

## Available Scripts

- `php db_manager.php create-tables` - Create all required database tables
- `php db_manager.php check-tables` - Check which tables exist
- `php db_manager.php sample-data` - Insert sample data for testing
- `php db_manager.php health` - Run database health check
- `php db_manager.php drop-all` - Drop all tables (use with caution!)
- `php benchmark_performance.php` - Run performance benchmarks
- `php check_config.php` - Verify configuration
- `php check_opcache.php` - Check OPcache status
- `php optimize_database_indexes.php` - Optimize database indexes

## Running with Postman

1. Import the Postman collection: `Airigo_Job_Portal_API.postman_collection.json`
2. Create a new environment with variables from the documentation
3. Set `base_url` to your server URL (e.g., `http://localhost:8000`)
4. Register a new user using the registration endpoint
5. Login to get your JWT tokens
6. Test the various endpoints

### Password Reset Flow Testing

1. Call `POST /api/auth/forgot-password` with user email/phone
2. Use the received reset token in `POST /api/auth/reset-password` with new password

## License

MIT