# Postman Collection Guide for Airigo Job Portal API

This document provides comprehensive instructions for using the Airigo Job Portal API Postman collection.

## Setup Instructions

1. **Import the Collection**:
   - Open Postman
   - Click "Import" and select the `Airigo_Job_Portal_API.postman_collection.json` file

2. **Import the Environment**:
   - Click "Import" and select the `Airigo_Job_Portal_Environment.postman_environment.json` file

3. **Select Environment**:
   - Click the environment dropdown (top right)
   - Select "Airigo Job Portal - Development"
   - The environment is pre-configured with `base_url = http://localhost:8000` for local testing
   - Variables will be automatically updated during API calls

## API Flow

### 1. Authentication Flow

#### Register New User
- Use the `Authentication > Register User` endpoint
- Supports both jobseeker and recruiter registration
- Sample payloads provided in the collection

#### Login
- Use the `Authentication > Login User` endpoint
- **NEW FEATURE**: Users can now login with either email OR phone number
- Sample payloads provided for both email and phone login

#### Get Profile
- Use the `Authentication > Get Profile` endpoint
- Requires valid access token

#### Refresh Token
- Use the `Authentication > Refresh Token` endpoint
- Exchanges expired access token for a new one

### 2. User Management Flow

#### Update Profile
- Use the `User Management > Update Profile` endpoint
- Requires authentication

#### Upload Resume
- Use the `User Management > Upload Resume` endpoint
- Requires authentication

#### Upload Profile Image
- Use the `User Management > Upload Profile Image` endpoint
- Requires authentication

### 3. Job Management Flow (Recruiters)

#### Create Job
- Use the `Job Management > Create Job` endpoint
- Requires recruiter role and authentication

#### Get Jobs
- Use the `Job Management > Get All Jobs` endpoint
- Available to all users

#### Update Job
- Use the `Job Management > Update Job` endpoint
- Requires recruiter role and job ownership

#### Delete Job
- Use the `Job Management > Delete Job` endpoint
- Requires recruiter role and job ownership

### 4. Application Management Flow (Jobseekers)

#### Apply for Job
- Use the `Application Management > Apply for Job` endpoint
- Requires jobseeker role and authentication

#### Get My Applications
- Use the `Application Management > Get My Applications` endpoint
- Requires jobseeker role and authentication

#### Update Application Status
- Use the `Application Management > Update Application Status` endpoint
- Requires recruiter role and job ownership

### 5. Admin Panel Flow (Admins Only)

#### Get Dashboard Stats
- Use the `Admin Panel > Get Dashboard Stats` endpoint

#### Manage Users
- Use the `Admin Panel > Get Users` endpoint
- Use the `Admin Panel > Update User Status` endpoint

#### Manage Jobs
- Use the `Admin Panel > Get Pending Jobs` endpoint
- Use the `Admin Panel > Approve Job` endpoint

## Testing Scenarios

### Basic User Registration and Login
1. Register as a new user
2. Login with either email OR phone number
3. Access protected endpoints using the returned token
4. Refresh token when needed

### Job Application Process
1. Register as a jobseeker
2. Login and obtain tokens
3. Browse available jobs
4. Apply for a job
5. Check application status

### Recruiter Job Posting
1. Register as a recruiter
2. Login and obtain tokens
3. Create new job listings
4. Monitor applications

## Environment Variables Explained

- `base_url`: The base URL of your API (e.g., `http://localhost:8000` or your deployed URL)
- `access_token`: Bearer token for authenticated requests
- `refresh_token`: Token for refreshing access tokens
- `user_id`: Current user's ID
- `jobseeker_id`: Jobseeker profile ID
- `recruiter_id`: Recruiter profile ID
- `job_id`: Job listing ID
- `application_id`: Application ID
- `issue_report_id`: Issue report ID
- `admin_id`: Admin user ID
- `test_email`: Test user email
- `test_phone`: Test user phone number
- `test_password`: Test user password

## Troubleshooting

### Common Issues:
1. **401 Unauthorized Errors**: Ensure your access token is valid and hasn't expired
2. **403 Forbidden Errors**: Check that your user role has permission for the requested action
3. **422 Validation Errors**: Verify that all required fields are present and correctly formatted
4. **500 Server Errors**: Check server logs for detailed error information

### Token Management:
- Access tokens expire after a short period (typically 1 hour)
- Use the refresh token endpoint to get a new access token
- Refresh tokens have a longer expiry (typically 7 days)

### API Versioning:
- All endpoints are prefixed with `/api/v1/`
- The collection is designed for v1 of the API

## Security Notes

- All sensitive data is transmitted over HTTPS in production
- Passwords are hashed using bcrypt
- JWT tokens are signed with HS256 algorithm
- Rate limiting is implemented to prevent abuse
- CORS is configured to allow only trusted origins

## API Limits

- Rate limiting: 60 requests per minute per IP
- File upload size: 10MB max for resumes, 5MB max for images
- Pagination: 20 items per page by default

## Support

For technical support, please contact the development team with specific error messages and steps to reproduce issues.