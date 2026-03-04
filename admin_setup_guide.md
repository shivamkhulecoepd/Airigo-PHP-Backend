# Admin Setup Guide

## Creating Admin Users

### Option 1: Normal Registration (Recommended)

Admins can now register through the normal registration flow just like jobseekers and recruiters:

```bash
# Register as Admin
POST /api/auth/register
{
  "email": "admin@airigo.com",
  "password": "Admin@12345",
  "user_type": "admin",
  "phone": "+1234567890"
}
```

### Option 2: Direct Database Insertion

```sql
-- Insert admin user directly into database
INSERT INTO users (email, password_hash, user_type, phone, status, email_verified) 
VALUES (
  'admin@airigo.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'admin',
  '+1234567890',
  'active',
  TRUE
);
```

### Option 3: Using Admin Creation Script

```bash
# Interactive mode
php create_admin.php

# Command line mode
php create_admin.php admin@airigo.com Admin@12345
```

## Admin Login Process

### 1. Login Request
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@airigo.com",
    "password": "Admin@12345"
  }'
```

### 2. Response
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "email": "admin@airigo.com",
    "user_type": "admin",
    "status": "active"
  },
  "tokens": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "refresh_token_here",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

## Admin Functionality

### Available Admin Endpoints

1. **Dashboard Statistics**
   - `GET /api/admin/dashboard/stats` - Basic statistics
   - `GET /api/admin/dashboard/full-stats` - Comprehensive statistics

2. **User Management**
   - `GET /api/admin/users` - Get all users
   - `GET /api/admin/jobseekers` - Get jobseekers
   - `GET /api/admin/recruiters` - Get recruiters
   - `PUT /api/admin/users/{id}/status` - Update user status

3. **Job Management**
   - `GET /api/admin/jobs` - Get all jobs
   - `GET /api/admin/jobs/pending` - Get pending jobs
   - `PUT /api/admin/jobs/{id}/approve` - Approve jobs
   - `PUT /api/admin/jobs/{id}/status` - Update job status
   - `DELETE /api/admin/jobs/{id}` - Delete jobs

4. **Application Management**
   - `GET /api/admin/applications` - Get all applications
   - `PUT /api/admin/applications/{id}/status` - Update application status

5. **Recruiter Management**
   - `PUT /api/admin/recruiters/{id}/approve` - Approve recruiters
   - `PUT /api/admin/recruiters/{id}/reject` - Reject recruiters

6. **Issue Reports**
   - `GET /api/admin/issues-reports` - Get all reports
   - `PUT /api/admin/issues-reports/{id}/status` - Update report status

7. **Search**
   - `GET /api/admin/search?q=query` - Universal search

## Testing Admin Functionality

### Using Postman

1. **Login as Admin**
   - Use the Authentication > Login User endpoint
   - Enter admin credentials
   - Copy the access_token to environment variable

2. **Access Admin Endpoints**
   - All admin endpoints require Bearer token authentication
   - Token is automatically used from environment variables

### Example Admin Workflow

1. **Login**
   ```
   POST /api/auth/login
   {
     "email": "admin@airigo.com",
     "password": "Admin@12345"
   }
   ```

2. **Get Dashboard Stats**
   ```
   GET /api/admin/dashboard/full-stats
   Authorization: Bearer {access_token}
   ```

3. **Manage Users**
   ```
   GET /api/admin/users?status=active&page=1&limit=10
   Authorization: Bearer {access_token}
   ```

4. **Approve Pending Jobs**
   ```
   GET /api/admin/jobs/pending
   Authorization: Bearer {access_token}
   
   PUT /api/admin/jobs/{job_id}/approve
   Authorization: Bearer {access_token}
   ```

## Security Considerations

1. **Admin Account Creation**
   - Should be done securely (not through public APIs)
   - Passwords should be strong and changed after first login
   - Consider implementing 2FA for admin accounts

2. **Access Control**
   - All admin endpoints are protected by RoleMiddleware
   - Only users with `user_type = 'admin'` can access admin functions
   - Tokens are JWT-based with expiration

3. **Audit Logging**
   - Consider implementing audit logs for admin actions
   - Track who performed what action and when

## Best Practices

1. **Initial Setup**
   - Create admin account with strong password
   - Change default password immediately
   - Limit number of admin accounts

2. **Ongoing Management**
   - Regular password updates
   - Monitor admin activity
   - Review access logs periodically

3. **Security**
   - Use HTTPS in production
   - Implement rate limiting
   - Consider IP whitelisting for admin access