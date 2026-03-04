# Airigo Job Portal Backend - Project Structure

## Directory Structure

```
airigo-job-portal-backend/
├── src/
│   ├── Config/
│   │   └── AppConfig.php              # Application configuration
│   ├── Core/
│   │   ├── Auth/
│   │   │   ├── Middleware/
│   │   │   │   ├── AuthMiddleware.php  # Authentication middleware
│   │   │   │   └── RoleMiddleware.php  # Role-based access control
│   │   │   ├── AuthService.php         # Authentication service
│   │   │   └── JWTManager.php          # JWT token management
│   │   ├── Database/
│   │   │   ├── BaseRepository.php      # Base repository class
│   │   │   ├── Connection.php          # Database connection
│   │   │   └── RepositoryInterface.php # Repository interface
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── AdminController.php         # Admin panel controller
│   │   │   │   ├── ApplicationController.php   # Application management
│   │   │   │   ├── AuthController.php          # Authentication controller
│   │   │   │   ├── BaseController.php          # Base controller
│   │   │   │   ├── IssueReportController.php   # Issue reporting
│   │   │   │   ├── JobController.php           # Job management
│   │   │   │   └── UserController.php          # User management
│   │   │   ├── Middleware/
│   │   │   │   ├── CorsMiddleware.php          # CORS handling
│   │   │   │   ├── RateLimitMiddleware.php     # Rate limiting
│   │   │   │   └── ValidationMiddleware.php    # Request validation
│   │   │   └── Router/
│   │   │       ├── Route.php                   # Route definition
│   │   │       └── Router.php                  # Main router
│   │   ├── Models/
│   │   │   ├── Application.php         # Application model
│   │   │   ├── Job.php                 # Job model
│   │   │   └── User.php                # User model
│   │   ├── Repositories/
│   │   │   ├── ApplicationRepository.php   # Application repository
│   │   │   ├── IssueReportRepository.php   # Issue report repository
│   │   │   ├── JobRepository.php           # Job repository
│   │   │   ├── JobseekerRepository.php     # Jobseeker repository
│   │   │   ├── RecruiterRepository.php     # Recruiter repository
│   │   │   └── UserRepository.php          # User repository
│   │   └── Utils/
│   │       ├── ResponseBuilder.php     # HTTP response builder
│   │       └── Validator.php           # Input validation
│   ├── Firebase/
│   │   └── FirebaseStorageService.php  # Firebase Storage integration
│   └── bootstrap.php                   # Application bootstrap
├── public/
│   └── index.php                       # Entry point
├── tests/                              # Unit and integration tests
├── vendor/                             # Composer dependencies
├── .env                                # Environment configuration
├── composer.json                       # Composer configuration
├── database_schema.sql                 # Database schema
├── README.md                           # Project documentation
├── SETUP_INSTRUCTIONS.md               # Setup guide
├── PROJECT_STRUCTURE.md                # This file
└── test_api.php                        # Test script
```

## Key Components

### Core Architecture
- **PSR-4 Autoloading**: Clean, standards-compliant class loading
- **Repository Pattern**: Separation of business logic and data access
- **Service Layer**: Business logic encapsulation
- **Middleware Pattern**: Request processing pipeline
- **Dependency Injection**: Loose coupling between components

### Authentication & Security
- **JWT Authentication**: Secure token-based authentication
- **Role-Based Access Control**: Different permissions for jobseekers, recruiters, and admins
- **Password Security**: Secure password hashing with PHP's password_hash()
- **Rate Limiting**: Protection against abuse (requires Redis)
- **Input Validation**: Comprehensive data validation and sanitization

### Database Design
- **Users Table**: Core user information
- **Jobseekers Table**: Jobseeker profiles
- **Recruiters Table**: Recruiter profiles and approval status
- **Jobs Table**: Job postings with approval workflow
- **Applications Table**: Job applications tracking
- **Issues Reports Table**: User feedback and reporting system

### API Design
- **RESTful Endpoints**: Standard REST API design
- **Consistent Response Format**: Unified JSON response structure
- **Error Handling**: Proper HTTP status codes and error messages
- **Pagination**: Efficient handling of large datasets
- **Search & Filtering**: Comprehensive job and user search capabilities

### File Management
- **Firebase Storage Integration**: Cloud-based file storage
- **Resume Uploads**: Secure resume handling for job applications
- **Profile Images**: User profile picture management
- **File Validation**: Security checks for uploaded files

### Performance & Scalability
- **Redis Caching**: Performance optimization for frequently accessed data
- **Database Optimization**: Proper indexing and query optimization
- **Connection Pooling**: Efficient database connection management
- **Modular Design**: Easy to scale and maintain

## Technology Stack

### Backend
- **PHP 8.2+**: Modern PHP with improved performance and features
- **MySQL 8.0+**: Robust relational database
- **Redis**: High-performance caching and session storage

### Libraries & Frameworks
- **Firebase JWT**: Secure JWT token generation and validation
- **Guzzle HTTP**: HTTP client for API requests
- **Monolog**: Comprehensive logging system
- **PHPDotEnv**: Environment variable management
- **Ramsey UUID**: UUID generation
- **Predis**: Redis client for PHP

### Standards Compliance
- **PSR-4**: Autoloading standard
- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface
- **PSR-14**: Event dispatcher
- **PSR-15**: HTTP server request handlers

## Development Workflow

1. **Local Development**: Use built-in PHP server or local web server
2. **Testing**: Comprehensive unit and integration tests
3. **Code Quality**: PSR-12 coding standards compliance
4. **Documentation**: Clear API documentation and setup guides
5. **Deployment**: Production-ready configuration and deployment scripts

## Future Enhancements

- **WebSocket Integration**: Real-time notifications
- **Microservices Architecture**: Further decoupling of services
- **Advanced Analytics**: Detailed usage and performance metrics
- **Multi-language Support**: Internationalization capabilities
- **Mobile Push Notifications**: Enhanced user engagement