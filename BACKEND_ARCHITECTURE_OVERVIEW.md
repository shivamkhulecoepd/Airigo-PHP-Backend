# Airigo Job Portal - Backend Architecture Overview

## 📁 Project Structure

```
PHP-Backend/
├── public/
│   └── index.php                    # Entry point, routing
├── src/
│   ├── Config/
│   │   ├── AppConfig.php            # App configuration
│   │   └── Database.php             # Database connection
│   ├── Core/
│   │   ├── Auth/                    # Authentication layer
│   │   │   ├── AuthService.php      # Login/Register logic
│   │   │   ├── JWTManager.php       # Token management
│   │   │   └── Middleware/          # Auth middleware
│   │   ├── Database/
│   │   │   ├── Connection.php       # PDO connection handler
│   │   │   └── BaseRepository.php   # Base CRUD operations
│   │   ├── Http/
│   │   │   ├── Controllers/         # API controllers
│   │   │   ├── Middleware/          # HTTP middleware
│   │   │   └── Router/              # Request routing
│   │   ├── Models/                  # Data models
│   │   └── Utils/                   # Helper utilities
│   ├── Repositories/                # Data access layer
│   │   ├── UserRepository.php
│   │   ├── JobseekerRepository.php
│   │   ├── RecruiterRepository.php
│   │   ├── JobRepository.php
│   │   ├── ApplicationRepository.php
│   │   └── NotificationRepository.php
│   ├── Firebase/
│   │   └── FirebaseNotificationService.php
│   └── bootstrap.php                # App bootstrap
└── database_schema.sql              # Database structure
```

---

## 🏗️ Architecture Layers

### 1️⃣ **HTTP Layer** (public/index.php + Router)

**Flow:**
```
Client Request → public/index.php → Router → Controller → Response
```

**Components:**
- **Router**: Maps URLs to controller methods
- **Middleware**: Authentication, authorization, CORS
- **Controllers**: Handle HTTP requests, validation, responses

**Example Routes:**
```php
// Authentication
POST /api/auth/register     → AuthController::register()
POST /api/auth/login        → AuthController::login()
GET  /api/auth/profile      → AuthController::getProfile()

// Jobs
POST /api/jobs              → JobController::create()
GET  /api/jobs              → JobController::getAll()

// Admin
GET  /api/admin/recruiters  → AdminController::getRecruiters()
PUT  /api/admin/recruiters/{id}/approve → AdminController::approveRecruiter()
```

---

### 2️⃣ **Authentication Layer** (src/Core/Auth/)

**Key Class: `AuthService`**

**Responsibilities:**
- User registration
- User login/authentication
- Password management (reset, change)
- Token generation (JWT)
- Profile creation based on user type

**Registration Flow:**
```
1. Receive registration data from controller
2. Validate input (email, password, user_type)
3. Check if email already exists
4. Hash password
5. Create user in `users` table
6. Create profile in appropriate table:
   - Jobseeker → `jobseekers` table
   - Recruiter → `recruiters` table
   - Admin → No profile needed
7. Generate JWT tokens
8. Send welcome notification
9. Return success response with tokens
```

---

### 3️⃣ **Repository Layer** (src/Repositories/)

**Pattern:** Repository Pattern (Data Access Abstraction)

**Inheritance:**
```
BaseRepository (abstract)
├── UserRepository
├── JobseekerRepository
├── RecruiterRepository
├── JobRepository
├── ApplicationRepository
└── ... other repositories
```

**BaseRepository provides:**
- `findById()` - Get record by ID
- `findAll()` - Get all records with filters
- `create()` - Insert new record
- `update()` - Update existing record
- `delete()` - Delete record
- `count()` - Count records
- Transaction management

**Each repository extends with custom methods:**

**Example - RecruiterRepository:**
```php
class RecruiterRepository extends BaseRepository
{
    protected string $table = 'recruiters';
    protected string $primaryKey = 'user_id';
    
    // Custom methods
    public function findByApprovalStatus(string $status): array
    public function findByCompanyName(string $companyName): array
    public function approveRecruiter(int $userId, int $approvedBy): bool
    public function rejectRecruiter(int $userId, string $reason, int $approvedBy): bool
    public function getRecruiterStats(): array
}
```

---

### 4️⃣ **Database Layer** (src/Core/Database/)

**Connection Management:**
```php
class Connection
{
    private static $instance;
    private PDO $pdo;
    
    public static function getInstance(): Connection
    {
        // Singleton pattern
    }
    
    public function getConnection(): PDO
    {
        // Returns PDO instance
    }
}
```

**Features:**
- PDO with prepared statements (SQL injection protection)
- Connection pooling
- Error handling
- Transaction support

---

## 🗄️ Database Schema

### **Core Tables:**

```
┌─────────────────────────────────────────────────────────────┐
│                          USERS                               │
├─────────────────────────────────────────────────────────────┤
│ id (PK)         │ Auto-increment primary key                │
│ email           │ Unique email address                       │
│ password_hash   │ Bcrypt hashed password                     │
│ user_type       │ ENUM: 'jobseeker', 'recruiter', 'admin'    │
│ phone           │ Phone number                               │
│ status          │ ENUM: 'active', 'inactive', 'suspended'    │
│ email_verified  │ Boolean                                    │
│ created_at      │ Timestamp                                  │
│ updated_at      │ Timestamp                                  │
└─────────────────────────────────────────────────────────────┘
              ↓ (1:1 relationship via user_id)
              
┌─────────────────────────────────────────────────────────────┐
│                     JOBSEEKERS                               │
├─────────────────────────────────────────────────────────────┤
│ user_id (PK, FK)│ References users.id                       │
│ name            │ Full name                                  │
│ qualification   │ Educational qualification                  │
│ experience      │ Years of experience                        │
│ location        │ City/Location                              │
│ date_of_birth   │ DOB                                        │
│ resume_url      │ Resume file URL                            │
│ skills          │ JSON array of skills                       │
│ bio             │ Professional bio                           │
│ profile_image_url│ Profile picture URL                       │
│ created_at      │ Timestamp                                  │
│ updated_at      │ Timestamp                                  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                     RECRUITERS                               │
├─────────────────────────────────────────────────────────────┤
│ user_id (PK, FK)│ References users.id                       │
│ email           │ Email address                              │
│ recruiter_name  │ Recruiter's full name                      │
│ company_name    │ Company name                               │
│ designation     │ Job title/designation                      │
│ location        │ City/Location                              │
│ photo_url       │ Profile photo URL                          │
│ company_website │ Company website URL                        │
│ id_card_url     │ ID card document URL                       │
│ approval_status │ ENUM: 'pending', 'approved', 'rejected'    │
│ approved_by     │ Admin user who approved                    │
│ approved_at     │ Approval timestamp                         │
│ rejection_reason│ Reason for rejection                       │
│ created_at      │ Timestamp                                  │
│ updated_at      │ Timestamp                                  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                        JOBS                                  │
├─────────────────────────────────────────────────────────────┤
│ id (PK)         │ Auto-increment                             │
│ recruiter_user_id│ FK to users.id (who posted)               │
│ company_name    │ Company name                               │
│ company_logo_url│ Company logo URL                           │
│ company_url     │ Company website                            │
│ designation     │ Job title                                  │
│ ctc             │ Salary/CTC                                 │
│ location        │ Job location                               │
│ category        │ Job category                               │
│ description     │ Job description                            │
│ requirements    │ JSON array of requirements                 │
│ skills_required │ JSON array of required skills              │
│ perks_and_benefits│ JSON array of perks                      │
│ experience_required│ Required experience                     │
│ is_active       │ Boolean                                    │
│ approval_status │ ENUM: 'pending', 'approved', 'rejected'    │
│ is_urgent_hiring│ Boolean                                    │
│ job_type        │ ENUM: Full-time, Part-time, Contract, etc. │
│ created_at      │ Timestamp                                  │
│ updated_at      │ Timestamp                                  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    APPLICATIONS                              │
├─────────────────────────────────────────────────────────────┤
│ id (PK)         │ Auto-increment                             │
│ job_id (FK)     │ References jobs.id                         │
│ recruiter_user_id│ FK to users.id (recruiter)                │
│ jobseeker_user_id│ FK to users.id (applicant)                │
│ resume_url      │ Resume URL                                 │
│ cover_letter    │ Cover letter text                          │
│ status          │ ENUM: pending, shortlisted, rejected, etc. │
│ applied_at      │ Application timestamp                      │
│ updated_at      │ Timestamp                                  │
└─────────────────────────────────────────────────────────────┘
```

### **Additional Tables:**
- `password_reset_tokens` - Password reset tokens
- `issues_reports` - User issue reports
- `wishlist_items` - Job bookmarks
- `notifications` - In-app notifications
- `user_fcm_tokens` - Firebase Cloud Messaging tokens

---

## 🔄 Request Flow Examples

### **Example 1: Recruiter Registration**

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Flutter App sends POST request                               │
│    POST /api/auth/register                                      │
│    Body: {                                                      │
│      "email": "recruiter@company.com",                          │
│      "password": "secure123",                                   │
│      "user_type": "recruiter",                                  │
│      "name": "John Doe",                                        │
│      "phone": "1234567890"                                      │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Router (index.php)                                           │
│    Routes to: AuthController::register()                        │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. AuthController                                               │
│    - Extracts request body                                      │
│    - Validates required fields                                  │
│    - Calls: AuthService::register($data)                        │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. AuthService                                                  │
│    a) Check if email exists (UserRepository::findByEmail)       │
│    b) Hash password with bcrypt                                 │
│    c) Create user (UserRepository::create)                      │
│       → INSERT INTO users (email, password_hash, user_type...)  │
│    d) Create profile (RecruiterRepository::create)              │
│       → INSERT INTO recruiters (user_id, recruiter_name...)     │
│    e) Generate JWT tokens (JWTManager)                          │
│    f) Send welcome notification                                 │
│    g) Return success response                                   │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Response to Flutter App                                      │
│    {                                                            │
│      "success": true,                                           │
│      "message": "Registration successful",                      │
│      "user": {                                                  │
│        "id": 5,                                                 │
│        "email": "recruiter@company.com",                        │
│        "user_type": "recruiter",                                │
│        "status": "active"                                       │
│      },                                                         │
│      "tokens": {                                                │
│        "access_token": "eyJ...",                                │
│        "refresh_token": "eyJ...",                               │
│        "token_type": "Bearer",                                  │
│        "expires_in": 3600                                       │
│      }                                                          │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
```

---

### **Example 2: Job Posting (Recruiter)**

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Flutter App sends POST request (with auth token)             │
│    POST /api/jobs                                               │
│    Headers: { Authorization: Bearer eyJ... }                    │
│    Body: { "designation": "Developer", "ctc": "50000", ... }    │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Router → JobController::create()                             │
│    Middleware:                                                  │
│    - AuthMiddleware (validate JWT token)                        │
│    - RoleMiddleware (check user is 'recruiter')                 │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. JobController                                                │
│    - Extract job data from request                              │
│    - Get authenticated user from token                          │
│    - Call: JobRepository::create($jobData)                      │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. JobRepository                                                │
│    - Filter allowed columns                                     │
│    - INSERT INTO jobs (recruiter_user_id, designation, ...)     │
│    - Return new job ID                                          │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Response: { "success": true, "job_id": 10 }                  │
└─────────────────────────────────────────────────────────────────┘
```

---

### **Example 3: Admin Approves Recruiter**

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Admin sends PUT request                                      │
│    PUT /api/admin/recruiters/{id}/approve                       │
│    Headers: { Authorization: Bearer eyJ... } (admin token)      │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Router → AdminController::approveRecruiter()                 │
│    Middleware:                                                  │
│    - AuthMiddleware (validate JWT)                              │
│    - RoleMiddleware (check user is 'admin')                     │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. AdminController                                              │
│    - Verify admin role                                          │
│    - Call: RecruiterRepository::approveRecruiter(userId, adminId)│
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. RecruiterRepository                                          │
│    UPDATE recruiters                                             │
│    SET approval_status = 'approved',                             │
│        approved_by = {adminId},                                  │
│        approved_at = NOW()                                       │
│    WHERE user_id = {recruiterId}                                 │
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Send notification to recruiter                               │
│    FirebaseNotificationService::sendRecruiterApprovalNotification│
└─────────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. Response: { "success": true, "message": "Recruiter approved" }│
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔐 Authentication & Authorization

### **JWT Token Flow:**

```
┌────────────────────────────────────────────────────────────┐
│                    Login/Registration                       │
│                                                             │
│  Client ←--- access_token (JWT) ---← Server                 │
│           ←--- refresh_token (JWT) ---←                     │
│                                                             │
│  access_token:  Expires in 1 hour                           │
│  refresh_token: Expires in 30 days                          │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│                  Making Authenticated Requests              │
│                                                             │
│  Client → GET /api/auth/profile                             │
│         Headers: { Authorization: Bearer {access_token} }   │
│                                                             │
│  Server → AuthMiddleware validates token                    │
│         → Extracts user info from token                     │
│         → Returns user data                                 │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│                  Token Refresh Flow                         │
│                                                             │
│  When access_token expires:                                 │
│  Client → POST /api/auth/refresh-token                      │
│           { "refresh_token": "{refresh_token}" }            │
│                                                             │
│  Server → Validates refresh_token                           │
│         → Generates new access_token                        │
│         → Returns new token pair                            │
└────────────────────────────────────────────────────────────┘
```

### **Role-Based Access Control:**

```php
// Middleware checks user role before allowing access

// Public routes (no auth needed
POST /api/auth/register
POST /api/auth/login

// Authenticated routes (any logged-in user
GET  /api/auth/profile
POST /api/auth/logout

// Jobseeker-only routes
POST /api/applications
GET  /api/applications/my

// Recruiter-only routes
POST /api/jobs
GET  /api/applications/recruiter

// Admin-only routes
GET  /api/admin/dashboard/stats
PUT  /api/admin/recruiters/{id}/approve
GET  /api/admin/jobs/pending
```

---

## 📊 Module Breakdown

### **1. User Management Module**

**Files:**
- `AuthController.php`
- `AuthService.php`
- `UserController.php`
- `UserRepository.php`
- `JobseekerRepository.php`
- `RecruiterRepository.php`

**Features:**
- Registration (Jobseeker/Recruiter/Admin)
- Login/Logout
- Profile management
- Password reset
- Profile image/resume upload

---

### **2. Job Management Module**

**Files:**
- `JobController.php`
- `JobRepository.php`
- `Job.php` (Model)

**Features:**
- Create job (Recruiter only)
- Browse jobs (Public/Authenticated)
- Search & filter jobs
- Job approval (Admin)
- Get jobs by recruiter

---

### **3. Application Module**

**Files:**
- `ApplicationController.php`
- `ApplicationRepository.php`
- `Application.php` (Model)

**Features:**
- Apply for job (Jobseeker)
- View applications (Recruiter/Admin)
- Update application status (Recruiter)
- Track applications (Jobseeker)

---

### **4. Admin Module**

**Files:**
- `AdminController.php`
- `AdminNotificationController.php`

**Features:**
- Dashboard statistics
- Manage users (Jobseekers/Recruiters)
- Approve/reject jobs
- Approve/reject recruiters
- View all applications
- Manage issue reports
- Send notifications

---

### **5. Notification Module**

**Files:**
- `NotificationController.php`
- `NotificationRepository.php`
- `FirebaseNotificationService.php`

**Features:**
- Firebase Cloud Messaging integration
- Store FCM tokens
- Send push notifications
- Mark notifications as read
- Archive notifications
- Admin broadcast notifications

---

### **6. Wishlist Module**

**Files:**
- `WishlistController.php`
- `WishlistRepository.php`
- `WishlistItem.php` (Model)

**Features:**
- Save jobs to wishlist
- Remove from wishlist
- Get user's wishlist
- Check if job is wishlisted

---

### **7. Issue Reports Module**

**Files:**
- `IssueReportController.php`
- `IssueReportRepository.php`

**Features:**
- Submit issues/reports
- View user's reports
- Admin manages reports
- Update report status

---

## 🛠️ Key Design Patterns

### **1. Repository Pattern**
```php
// Abstract base class
abstract class BaseRepository {
    public function findById(int $id);
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}

// Concrete implementations
class UserRepository extends BaseRepository { }
class JobseekerRepository extends BaseRepository { }
```

### **2. Singleton Pattern**
```php
class Connection {
    private static $instance;
    
    public static function getInstance(): Connection {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### **3. Middleware Pattern**
```php
// Chain of middleware for request processing
$router->get('/api/jobs', [JobController::class, 'getAll'])
    ->addMiddleware(new OptionalAuthMiddleware())
    ->addMiddleware(new CorsMiddleware());
```

### **4. Dependency Injection**
```php
class AuthController extends BaseController {
    private AuthService $authService;
    
    public function __construct() {
        $this->authService = new AuthService(); // Could be injected
    }
}
```

---

## 🔧 Configuration

### **Environment Variables (.env)**
```env
APP_NAME=Airigo Job Portal
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=airigo_job_portal
DB_USERNAME=root
DB_PASSWORD=your_password

JWT_SECRET=your-secret-key
JWT_EXPIRY=3600

FIREBASE_CREDENTIALS=path/to/firebase-credentials.json
```

---

## 📈 Performance Optimizations

1. **Prepared Statements** - SQL injection protection + query caching
2. **Singleton Pattern** - Reuse database connection
3. **Repository Caching** - Cache frequently accessed data
4. **Pagination** - Limit results for large datasets
5. **Database Indexes** - Fast lookups on email, status, user_type, etc.
6. **JWT Tokens** - Stateless authentication (no server-side session)

---

## 🚀 API Endpoints Summary

### **Authentication (8 endpoints)**
- POST `/api/auth/register`
- POST `/api/auth/login`
- POST `/api/auth/logout`
- POST `/api/auth/refresh-token`
- POST `/api/auth/forgot-password`
- POST `/api/auth/reset-password`
- POST `/api/auth/change-password`
- GET `/api/auth/profile`

### **User Management (6 endpoints)**
- GET `/api/users/profile`
- PUT `/api/users/profile`
- PATCH `/api/users/profile/section/{section}`
- DELETE `/api/users/account`
- POST `/api/users/upload-resume`
- POST `/api/users/upload-profile-image`

### **Jobs (11 endpoints)**
- POST `/api/jobs`
- GET `/api/jobs`
- GET `/api/jobs/latest`
- GET `/api/jobs/top-companies`
- GET `/api/jobs/search`
- GET `/api/jobs/categories`
- GET `/api/jobs/locations`
- GET `/api/jobs/{id}`
- PUT `/api/jobs/{id}`
- DELETE `/api/jobs/{id}`
- GET `/api/jobs/by-recruiter/{recruiterId}`

### **Applications (6 endpoints)**
- POST `/api/applications`
- GET `/api/applications/my`
- GET `/api/applications/job/{jobId}`
- GET `/api/applications/recruiter`
- PUT `/api/applications/{id}/status`
- DELETE `/api/applications/{id}`

### **Wishlist (6 endpoints)**
- POST `/api/wishlist`
- DELETE `/api/wishlist`
- GET `/api/wishlist`
- GET `/api/wishlist/check/{jobId}`
- GET `/api/wishlist/ids`
- POST `/api/wishlist/toggle`

### **Notifications (9 endpoints)**
- POST `/api/notifications/fcm-token`
- DELETE `/api/notifications/fcm-token`
- GET `/api/notifications/tokens`
- GET `/api/notifications`
- GET `/api/notifications/archived`
- PUT `/api/notifications/{id}/read`
- PUT `/api/notifications/mark-all-read`
- GET `/api/notifications/count`
- PUT `/api/notifications/{id}/archive`

### **Issue Reports (5 endpoints)**
- POST `/api/issue-reports`
- GET `/api/issue-reports/my`
- GET `/api/issue-reports/{id}`
- GET `/api/issue-reports`
- PATCH `/api/issue-reports/{id}/status`

### **Admin Panel (25+ endpoints)**
- GET `/api/admin/dashboard/stats`
- GET `/api/admin/dashboard/full-stats`
- GET `/api/admin/users`
- GET `/api/admin/jobseekers`
- GET `/api/admin/recruiters`
- GET `/api/admin/jobs`
- GET `/api/admin/jobs/pending`
- GET `/api/admin/applications`
- GET `/api/admin/issues-reports`
- PUT `/api/admin/jobs/{id}/approve`
- PUT `/api/admin/jobs/{id}/reject`
- PUT `/api/admin/recruiters/{id}/approve`
- PUT `/api/admin/recruiters/{id}/reject`
- PUT `/api/admin/users/{id}/status`
- POST `/api/admin/notifications/*` (7 endpoints)
- GET `/api/admin/search`
- ... and more

**Total: ~70+ API endpoints**

---

## 🎯 Key Takeaways

1. **Three User Types**: Jobseeker, Recruiter, Admin - each with distinct features
2. **Role-Based Access**: Middleware enforces permissions at route level
3. **Two-Step Registration**: Auto-creates user + profile in separate tables
4. **Approval Workflow**: Recruiters and Jobs require admin approval
5. **JWT Authentication**: Stateless, secure, scalable
6. **Repository Pattern**: Clean separation of concerns
7. **Firebase Integration**: Push notifications for real-time updates
8. **RESTful API**: Standard HTTP methods, consistent response format

---

This architecture provides a solid foundation for a scalable, maintainable job portal system with clear separation of concerns and proper security measures.
