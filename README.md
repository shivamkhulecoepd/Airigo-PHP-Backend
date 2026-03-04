# Airigo Job Portal Backend

A complete, production-ready backend system for a job portal mobile application built with PHP and MySQL. This system replaces Firebase with a dedicated backend while retaining Firebase Storage for file management.

## Features

- **User Authentication**: JWT-based authentication with refresh tokens
- **Job Management**: Full CRUD operations for job postings
- **Application Management**: Complete job application workflow
- **Admin Panel**: Admin controls for managing users and jobs
- **File Storage**: Integration with Firebase Storage for resumes and images
- **Performance Optimized**: Redis caching and optimized database queries
- **Security**: Input validation, rate limiting, and authentication middleware
- **Scalable Architecture**: PSR-compliant, modular design for millions of users
- **Remote Database Support**: Compatible with hosting providers like Hostinger

## Tech Stack

- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+ (supports both local and remote databases)
- **File Storage**: Firebase Storage (via REST API)
- **Caching**: Redis
- **Authentication**: JWT with refresh tokens
- **Standards**: PSR-7, PSR-11, PSR-14, PSR-15 compliant

## Installation

1. Clone the repository
2. Install dependencies using Composer:
   ```bash
   composer install
   ```
3. Create a `.env` file based on `.env.example` and configure your database and other settings
4. Set up your MySQL database using the schema in `database_schema.sql`
5. Configure your web server to point to the `public/` directory

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register a new user
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/auth/refresh-token` - Refresh access token
- `POST /api/auth/forgot-password` - Forgot password
- `POST /api/auth/reset-password` - Reset password
- `GET /api/auth/profile` - Get user profile

### User Management
- `GET /api/users/profile` - Get user profile
- `PUT /api/users/profile` - Update user profile
- `DELETE /api/users/account` - Delete user account
- `POST /api/users/upload-resume` - Upload resume
- `POST /api/users/upload-profile-image` - Upload profile image

### Job Management
- `POST /api/jobs` - Create a new job
- `GET /api/jobs` - Get all jobs
- `GET /api/jobs/{id}` - Get job by ID
- `PUT /api/jobs/{id}` - Update job
- `DELETE /api/jobs/{id}` - Delete job
- `GET /api/jobs/search` - Search jobs
- `GET /api/jobs/categories` - Get job categories
- `GET /api/jobs/locations` - Get job locations

### Application Management
- `POST /api/applications` - Apply for a job
- `GET /api/applications/my` - Get my applications
- `GET /api/applications/job/{jobId}` - Get applications for a job
- `PUT /api/applications/{id}/status` - Update application status
- `DELETE /api/applications/{id}` - Delete application

### Admin Panel
- `GET /api/admin/dashboard/stats` - Dashboard statistics
- `GET /api/admin/users` - Get all users
- `GET /api/admin/jobs/pending` - Get pending jobs
- `PUT /api/admin/jobs/{id}/approve` - Approve job
- `PUT /api/admin/users/{id}/status` - Update user status
- `GET /api/admin/issues-reports` - Get all issues/reports
- `PUT /api/admin/issues-reports/{id}/status` - Update issue/report status

## Architecture

The backend follows a clean, modular architecture with separation of concerns:

- **Controllers**: Handle HTTP requests and responses
- **Services**: Business logic encapsulation
- **Repositories**: Data access layer
- **Models**: Data representation
- **Middleware**: Request processing
- **Events**: Event-driven architecture

## Security

- Passwords are securely hashed using PHP's password_hash function
- JWT tokens with configurable expiration
- Input validation and sanitization
- Rate limiting to prevent abuse
- SQL injection prevention with prepared statements

## Performance

- Redis caching for frequently accessed data
- Optimized database queries with proper indexing
- Connection pooling with PDO
- Pagination for large datasets

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License.