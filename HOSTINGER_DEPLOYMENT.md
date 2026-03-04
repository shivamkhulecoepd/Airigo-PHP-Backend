# Airigo Job Portal Backend - Hostinger Deployment Guide

This guide provides specific instructions for deploying the Airigo Job Portal Backend on Hostinger shared hosting.

## Prerequisites

- Active Hostinger hosting account
- PHP 8.2 or higher enabled in hosting control panel
- MySQL database created via Hostinger control panel
- File Manager access or FTP access
- SSH access (if available with your plan)

## Step-by-Step Deployment

### 1. Prepare Your Local Files

1. Ensure all dependencies are installed:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

2. Optimize autoloader for production:
   ```bash
   composer dump-autoload --optimize
   ```

### 2. Upload Files to Hostinger

Choose one of these methods:

#### Option A: Using File Manager
1. Compress your project folder (excluding `vendor` if uploading separately)
2. Go to Hostinger Control Panel → File Manager
3. Upload the ZIP file to your desired directory
4. Extract the files

#### Option B: Using FTP
1. Connect to your Hostinger account via FTP
2. Upload all files to your public_html or www directory
3. Make sure to preserve the directory structure

### 3. Set Up Database

1. In Hostinger Control Panel:
   - Go to Databases section
   - Create a new MySQL database
   - Note down the database name, username, password, and host

2. Import the database schema:
   - Go to phpMyAdmin in your Hostinger Control Panel
   - Select your newly created database
   - Click the "Import" tab
   - Upload the `database_schema.sql` file from your project

### 4. Configure Environment Variables

1. Rename `.env.example` to `.env` or create a new `.env` file
2. Update with your Hostinger database details:
   ```
   # Database Configuration for Hostinger
   DB_HOST=your_hostinger_mysql_host
   DB_PORT=3306
   DB_NAME=your_database_name
   DB_USER=your_database_username
   DB_PASSWORD=your_database_password
   DB_CHARSET=utf8mb4
   
   # Application Settings
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   APP_TIMEZONE=UTC
   ```

### 5. Set File Permissions

1. Set the following permissions via File Manager or FTP:
   - `storage/` and all subdirectories: 755 or 775
   - `logs/` and all subdirectories: 755 or 775
   - `public/` directory: 755
   - All PHP files: 644

### 6. Configure Web Server

#### For Public Directory Access
If your Hostinger setup allows, point your domain to the `/public` directory to hide sensitive files.

#### If Not Possible
Ensure your `.htaccess` files are properly configured to prevent direct access to sensitive directories.

### 7. Configure PHP Settings

1. In Hostinger Control Panel, ensure PHP version is set to 8.2 or higher
2. Adjust these PHP settings if possible:
   - `memory_limit`: 256M or higher
   - `upload_max_filesize`: As needed for file uploads
   - `post_max_size`: Should match upload_max_filesize
   - `max_execution_time`: 300 seconds or higher

### 8. Test Your Installation

1. Visit your domain to ensure the application loads
2. Test the API endpoints:
   - `https://yourdomain.com/api/auth/register`
   - `https://yourdomain.com/api/auth/login`

### 9. Additional Hostinger-Specific Considerations

#### Cron Jobs (if needed)
If your application requires scheduled tasks:
1. Go to Cron Jobs section in Hostinger Control Panel
2. Add cron jobs as needed for maintenance tasks

#### SSL Certificate
1. Hostinger provides free SSL certificates
2. Enable SSL for your domain in the Security section
3. Update your `.env` to use `https://` in `APP_URL`

#### Email Configuration
If your application sends emails, configure Hostinger's mail server settings in your application.

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify database credentials in `.env`
   - Check if the database is properly created
   - Ensure the database user has proper permissions

2. **Permission Errors**
   - Ensure proper file permissions are set
   - Contact Hostinger support if permissions cannot be changed

3. **Performance Issues**
   - Optimize your database queries
   - Use Redis caching if available with your plan
   - Consider upgrading to a VPS if shared hosting is too limited

4. **File Upload Issues**
   - Check Hostinger's file size limits
   - Verify upload directory permissions
   - Ensure Firebase Storage is properly configured

## Performance Optimization for Shared Hosting

1. **Enable OPcache** in Hostinger Control Panel
2. **Optimize database queries** with proper indexing
3. **Use CDN** for static assets
4. **Minimize external API calls** to improve response times

## Security Best Practices

1. **Keep `.env` secure** - never expose it publicly
2. **Regular backups** - use Hostinger's backup features
3. **Update dependencies** regularly
4. **Monitor logs** for suspicious activity

## Support

For Hostinger-specific issues, contact Hostinger support. For application-specific issues, refer to the main documentation.

---

**Note**: While this guide focuses on Hostinger deployment, the Airigo Job Portal Backend is compatible with any hosting provider that meets the system requirements.