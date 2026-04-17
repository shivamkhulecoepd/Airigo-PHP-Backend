# Testing Guide - Recruiter Registration Fix

## Prerequisites

1. **Backend server running**
   ```bash
   cd s:\Airigo App\PHP-Backend
   php -S localhost:8000 -t public
   ```

2. **Database accessible**
   - Ensure MySQL/MariaDB is running
   - Database `airigo_job_portal` exists
   - Tables are created per `database_schema.sql`

3. **Flutter app configured**
   - Backend URL set correctly in `app_config.dart`
   - Dependencies installed (`flutter pub get`)

---

## Test 1: Register New Recruiter via Flutter App

### Steps:

1. **Launch Flutter App**
   ```bash
   cd s:\Airigo App\Jobportal-New
   flutter run
   ```

2. **Navigate to Registration**
   - Select "Recruiter" role
   - Click "Sign up" or "Register"

3. **Fill Registration Form**
   ```
   Full Name: Test Recruiter Company
   Email: testrecruiter@example.com
   Phone: 9876543210
   Password: testpassword123
   ```

4. **Submit Registration**
   - Click "Register" button
   - Wait for success message

### Expected Results:

✅ **Success Indicators:**
- "Registration Successful" message appears
- App navigates to Recruiter Dashboard/Main Screen
- User is logged in automatically

---

## Test 2: Verify Database Records

### Check Users Table:

```sql
SELECT id, email, user_type, phone, status, created_at 
FROM users 
WHERE email = 'testrecruiter@example.com';
```

**Expected Output:**
```
id | email                      | user_type | phone      | status | created_at
---|----------------------------|-----------|------------|--------|------------------
5  | testrecruiter@example.com  | recruiter | 9876543210 | active | 2026-04-17 10:30:00
```

### Check Recruiters Table:

```sql
SELECT 
    r.user_id,
    r.email,
    r.recruiter_name,
    r.company_name,
    r.designation,
    r.location,
    r.approval_status,
    u.status as user_status,
    r.created_at
FROM recruiters r
JOIN users u ON r.user_id = u.id
WHERE u.email = 'testrecruiter@example.com';
```

**Expected Output (BEFORE FIX - ALL NULLS):**
```
user_id | email | recruiter_name | company_name | designation | location | approval_status
--------|-------|----------------|--------------|-------------|----------|----------------
5       | NULL  | NULL           | NULL         | NULL        | NULL     | pending
```

**Expected Output (AFTER FIX - DATA POPULATED):**
```
user_id | email                     | recruiter_name      | company_name        | designation | location | approval_status
--------|---------------------------|---------------------|---------------------|-------------|----------|----------------
5       | testrecruiter@example.com | Test Recruiter Company | Test Recruiter Company | NULL        | NULL     | pending
```

---

## Test 3: Test via API Client (Postman/Thunder Client)

### Request:

```http
POST http://localhost:8000/api/auth/register
Content-Type: application/json

{
    "email": "apitest@example.com",
    "password": "apipassword123",
    "user_type": "recruiter",
    "name": "API Test Recruiter",
    "phone": "1234567890"
}
```

### Expected Response (201 Created):

```json
{
    "success": true,
    "message": "Registration successful",
    "user": {
        "id": 6,
        "email": "apitest@example.com",
        "user_type": "recruiter",
        "status": "active"
    },
    "tokens": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

### Verify Database:

```sql
SELECT user_id, email, recruiter_name, company_name 
FROM recruiters 
WHERE email = 'apitest@example.com';
```

**Expected:**
```
user_id | email              | recruiter_name      | company_name
--------|--------------------|---------------------|--------------------
6       | apitest@example.com| API Test Recruiter  | API Test Recruiter
```

---

## Test 4: Login After Registration

### Via Flutter App:

1. Logout from the app
2. Login with registered credentials:
   ```
   Email: testrecruiter@example.com
   Password: testpassword123
   ```
3. Select "Recruiter" user type

### Expected:
- ✅ Login successful
- ✅ Profile data loads correctly
- ✅ Recruiter name displays in UI
- ✅ Can navigate to profile and see details

---

## Test 5: Check Admin Panel

### Via Admin Account:

1. Login as admin
2. Navigate to "Pending Recruiters"
3. Verify the new recruiter appears in the list

### Expected Admin View:

```
Recruiter Details:
├─ Name: Test Recruiter Company ✅
├─ Email: testrecruiter@example.com ✅
├─ Company: Test Recruiter Company ✅
├─ Status: Pending Approval ✅
└─ Registered: 2026-04-17 10:30:00 ✅
```

---

## Test 6: Edge Cases

### Test 6.1: Registration with Company Name (if supported)

**Request:**
```json
{
    "email": "company@example.com",
    "password": "companypass123",
    "user_type": "recruiter",
    "name": "John Doe",
    "company_name": "Tech Corp Inc.",
    "phone": "1112223333"
}
```

**Expected:**
```sql
SELECT recruiter_name, company_name 
FROM recruiters 
WHERE email = 'company@example.com';
```

**Result:**
```
recruiter_name | company_name
---------------|------------------
John Doe       | Tech Corp Inc.
```

### Test 6.2: Missing Optional Fields

**Request:**
```json
{
    "email": "minimal@example.com",
    "password": "minimalpass123",
    "user_type": "recruiter",
    "name": "Minimal Recruiter"
}
```

**Expected:**
- ✅ Registration succeeds
- ✅ Only required fields populated
- ✅ No errors for missing optional fields (phone, location, etc.)

---

## Test 7: Verify Jobseeker Registration Unaffected

### Test:

```http
POST http://localhost:8000/api/auth/register
Content-Type: application/json

{
    "email": "jobseeker@example.com",
    "password": "jobpass123",
    "user_type": "jobseeker",
    "name": "Test Jobseeker",
    "phone": "5556667777"
}
```

### Verify Jobseekers Table:

```sql
SELECT user_id, name, email, phone 
FROM jobseekers 
WHERE email = 'jobseeker@example.com';
```

**Expected:**
```
user_id | name            | email                  | phone
--------|-----------------|------------------------|----------
7       | Test Jobseeker  | jobseeker@example.com  | 5556667777
```

**Confirm:** Jobseeker registration still works correctly ✅

---

## Common Issues & Troubleshooting

### Issue 1: Still Getting NULL Values

**Possible Causes:**
1. **Old code cached** - Restart PHP server
2. **Wrong database** - Check `.env` file
3. **Code not deployed** - Verify file changes saved

**Solution:**
```bash
# Restart PHP server
# Kill existing server (Ctrl+C)
cd s:\Airigo App\PHP-Backend
php -S localhost:8000 -t public
```

### Issue 2: SQL Error on Insert

**Check:**
```sql
-- Verify table structure
DESCRIBE recruiters;

-- Check if columns exist
SHOW COLUMNS FROM recruiters LIKE 'recruiter_name';
```

**Expected:** Column `recruiter_name` should exist with type `VARCHAR(255)`

### Issue 3: CORS Errors (Flutter)

**Fix in Backend:**
Ensure CORS middleware is active in `index.php`:
```php
$router->addGlobalMiddleware(new \App\Core\Http\Middleware\CorsMiddleware());
```

### Issue 4: 500 Internal Server Error

**Check Logs:**
```bash
# PHP error log
tail -f /var/log/php-fpm/error.log

# Or check Flutter console output
```

**Common Fix:**
```bash
# Check PHP syntax
php -l src/Core/Auth/AuthService.php
php -l src/Repositories/RecruiterRepository.php
```

---

## Test Results Checklist

Use this checklist to verify all aspects of the fix:

- [ ] Recruiter registration via Flutter app works
- [ ] `recruiter_name` field is populated in database
- [ ] `company_name` field is populated (falls back to name)
- [ ] `email` field is populated in recruiters table
- [ ] `approval_status` defaults to 'pending'
- [ ] User record created in `users` table
- [ ] Profile record created in `recruiters` table
- [ ] JWT tokens generated correctly
- [ ] Login works after registration
- [ ] Profile displays correctly in app
- [ ] Admin can see pending recruiter
- [ ] Jobseeker registration still works
- [ ] No PHP errors in server logs
- [ ] API returns correct response format
- [ ] Database constraints not violated

---

## Performance Testing

### Test Registration Speed:

```bash
# Time the registration request
time curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "perf@test.com",
    "password": "perfpass123",
    "user_type": "recruiter",
    "name": "Performance Test",
    "phone": "9999999999"
  }'
```

**Expected:** < 500ms response time

---

## Cleanup Test Data

After testing, clean up test records:

```sql
-- Delete test recruiters (cascades to users)
DELETE FROM recruiters 
WHERE email LIKE '%test%' OR email LIKE '%perf%';

-- Delete test users
DELETE FROM users 
WHERE email LIKE '%test%' OR email LIKE '%perf%';
```

---

## Next Steps After Testing

1. **Deploy to staging server**
2. **Test with production-like data**
3. **Monitor error logs for 24 hours**
4. **Get user acceptance testing (UAT)**
5. **Deploy to production**

---

## Monitoring

### Watch for Registration Errors:

```bash
# Tail PHP logs
tail -f /var/log/php-fpm/error.log | grep -i "recruiter"

# Monitor database
SELECT COUNT(*) as today_registrations 
FROM recruiters 
WHERE DATE(created_at) = CURDATE();
```

---

If all tests pass, the fix is working correctly! 🎉
