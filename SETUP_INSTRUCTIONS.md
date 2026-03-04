# Airigo Job Portal Backend - Setup Instructions

## System Requirements

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Redis (optional, for caching)
- Composer

## Installation Steps

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

1. Copy `.env` file and update the configuration:
   ```bash
   cp .env .env.local  # or just edit .env directly
   ```

2. Update database settings in `.env` for **Hostinger remote database**:
   ```
   DB_HOST=your_hostinger_database_host
   DB_PORT=3306
   DB_NAME=your_database_name
   DB_USER=your_database_username
   DB_PASSWORD=your_database_password
   DB_CHARSET=utf8mb4
   ```

3. Update JWT settings:
   ```
   JWT_SECRET_KEY=your-super-secret-jwt-key-change-in-production
   JWT_REFRESH_SECRET_KEY=your-super-secret-refresh-key-change-in-production
   ```

4. Update Firebase settings (if using Firebase Storage):
   ```
   FIREBASE_PROJECT_ID=your-firebase-project-id
   FIREBASE_STORAGE_BUCKET=your-storage-bucket.appspot.com
   ```

### 3. Create Database Tables

Since you're using a **remote Hostinger database**, you'll need to import the database schema through Hostinger's control panel:

1. Log into your Hostinger control panel
2. Access phpMyAdmin or database management
3. Select your database
4. Go to the "Import" tab
5. Upload the `database_schema.sql` file to create all tables

Alternatively, you can run the SQL commands from `database_schema.sql` manually in the Hostinger database console.

### 4. Configure Web Server

#### Apache
Add to your virtual host configuration:
```apache
<VirtualHost *:80>
    DocumentRoot "S:/Airigo App/Backend/PHP-Backend/public"
    ServerName your-domain.com
    
    <Directory "S:/Airigo App/Backend/PHP-Backend/public">
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root S:/Airigo App/Backend/PHP-Backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Test the Installation

Run the test script to verify the system is working:
```bash
php test_api.php
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/refresh-token` - Refresh access token
- `GET /api/auth/profile` - Get user profile

### User Management
- `GET /api/users/profile` - Get user profile
- `PUT /api/users/profile` - Update profile
- `POST /api/users/upload-resume` - Upload resume
- `POST /api/users/upload-profile-image` - Upload profile image

### Job Management
- `POST /api/jobs` - Create job (recruiters only)
- `GET /api/jobs` - Get all jobs
- `GET /api/jobs/{id}` - Get job by ID
- `PUT /api/jobs/{id}` - Update job (recruiters only)
- `DELETE /api/jobs/{id}` - Delete job (recruiters only)
- `GET /api/jobs/search` - Search jobs
- `GET /api/jobs/categories` - Get job categories
- `GET /api/jobs/locations` - Get job locations

### Application Management
- `POST /api/applications` - Apply for job (jobseekers only)
- `GET /api/applications/my` - Get my applications (jobseekers only)
- `GET /api/applications/job/{jobId}` - Get applications for job (recruiters only)
- `PUT /api/applications/{id}/status` - Update application status (recruiters only)

### Admin Panel
- `GET /api/admin/dashboard/stats` - Dashboard statistics (admin only)
- `GET /api/admin/users` - Get all users (admin only)
- `GET /api/admin/jobs/pending` - Get pending jobs (admin only)
- `PUT /api/admin/jobs/{id}/approve` - Approve job (admin only)
- `PUT /api/admin/users/{id}/status` - Update user status (admin only)

## Security Features

- JWT-based authentication with refresh tokens
- Password hashing using PHP's password_hash()
- Input validation and sanitization
- Rate limiting (requires Redis)
- SQL injection prevention with prepared statements
- CORS support

## Performance Features

- Redis caching for frequently accessed data
- Database connection pooling
- Optimized database queries with proper indexing
- Pagination for large datasets

## Testing

Run the test suite:
```bash
./vendor/bin/phpunit
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check if MySQL is running
   - Verify database credentials in `.env`
   - Ensure the database exists

2. **Class Not Found Errors**
   - Run `composer dump-autoload`
   - Check file permissions
   - Verify PSR-4 autoloading configuration

3. **JWT Errors**
   - Ensure JWT secret keys are properly configured
   - Check token expiration settings

4. **File Upload Issues**
   - Verify Firebase Storage configuration
   - Check file permissions
   - Ensure upload directory exists

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false` in `.env`
3. Use a proper web server (Apache/Nginx)
4. Configure SSL/HTTPS
5. Set up proper error logging
6. Configure Redis for caching
7. Set up database backups
8. Monitor application performance

## Support

For issues and support, please contact the development team.