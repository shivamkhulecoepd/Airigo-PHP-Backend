<?php

// Public entry point for the Airigo Job Portal API

use App\Core\Http\Router\Router;
use App\Core\Auth\Middleware\AuthMiddleware;
use App\Core\Auth\Middleware\OptionalAuthMiddleware;
use App\Core\Auth\Middleware\RoleMiddleware;
use App\Core\Http\Controllers\AuthController;
use App\Core\Http\Controllers\UserController;
use App\Core\Http\Controllers\JobController;
use App\Core\Http\Controllers\ApplicationController;
use App\Core\Http\Controllers\AdminController;
use App\Core\Http\Controllers\IssueReportController;
use App\Core\Http\Controllers\WishlistController;
use App\Core\Http\Controllers\NotificationController;
use App\Core\Utils\ResponseBuilder;
use GuzzleHttp\Psr7\ServerRequest;

// Bootstrap the application
require_once __DIR__ . '/../src/bootstrap.php';

// Create router instance
$router = new Router();

// Add home route
$router->get('/', function ($request) {
    $data = [
        'message' => 'Welcome to Airigo Job Portal API',
        'version' => '1.0',
        'endpoints' => [
            'auth' => '/api/auth/login, /api/auth/register',
            'jobs' => '/api/jobs',
            'users' => '/api/users/profile',
            'wishlist' => '/api/wishlist'
        ]
    ];
    return new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_PRETTY_PRINT));
});

// Add CORS middleware globally
$router->addGlobalMiddleware(new \App\Core\Http\Middleware\CorsMiddleware());

// Authentication routes
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'])->addMiddleware(new AuthMiddleware());
$router->post('/api/auth/refresh-token', [AuthController::class, 'refreshToken']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/api/auth/profile', [AuthController::class, 'getProfile'])->addMiddleware(new AuthMiddleware());

// User management routes
$router->get('/api/users/profile', [UserController::class, 'getProfile'])->addMiddleware(new AuthMiddleware());
$router->put('/api/users/profile', [UserController::class, 'updateProfile'])->addMiddleware(new AuthMiddleware());
$router->delete('/api/users/account', [UserController::class, 'deleteAccount'])->addMiddleware(new AuthMiddleware());
$router->post('/api/users/upload-resume', [UserController::class, 'uploadResume'])->addMiddleware(new AuthMiddleware());
$router->post('/api/users/upload-profile-image', [UserController::class, 'uploadProfileImage'])->addMiddleware(new AuthMiddleware());
$router->post('/api/users/upload-id-card', [UserController::class, 'uploadIdCard'])->addMiddleware(new AuthMiddleware());

// Notification routes
$router->post('/api/notifications/fcm-token', [NotificationController::class, 'storeFcmToken'])->addMiddleware(new AuthMiddleware());
$router->delete('/api/notifications/fcm-token', [NotificationController::class, 'removeFcmToken'])->addMiddleware(new AuthMiddleware());
$router->get('/api/notifications/tokens', [NotificationController::class, 'getUserTokens'])->addMiddleware(new AuthMiddleware());
$router->post('/api/notifications/test', [NotificationController::class, 'sendTestNotification'])->addMiddleware(new AuthMiddleware());

// Admin cleanup route
$router->post('/api/notifications/cleanup-invalid-tokens', [NotificationController::class, 'cleanupInvalidTokens'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));

// Job management routes
$router->post('/api/jobs', [JobController::class, 'create'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->get('/api/jobs', [JobController::class, 'getAll'])->addMiddleware(new OptionalAuthMiddleware());
$router->get('/api/jobs/{id}', [JobController::class, 'getById']);
$router->put('/api/jobs/{id}', [JobController::class, 'update'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->delete('/api/jobs/{id}', [JobController::class, 'delete'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->get('/api/jobs/search', [JobController::class, 'search']);
$router->get('/api/jobs/categories', [JobController::class, 'getCategories']);
$router->get('/api/jobs/locations', [JobController::class, 'getLocations']);
$router->post('/api/jobs/{id}/upload-logo', [JobController::class, 'uploadCompanyLogo'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));

// Application management routes
$router->post('/api/applications', [ApplicationController::class, 'apply'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['jobseeker']));
$router->get('/api/applications/my', [ApplicationController::class, 'getMyApplications'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['jobseeker']));
$router->get('/api/applications/job/{jobId}', [ApplicationController::class, 'getApplicationsForJob'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->get('/api/applications/recruiter', [ApplicationController::class, 'getApplicationsForRecruiter'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->put('/api/applications/{id}/status', [ApplicationController::class, 'updateStatus'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['recruiter']));
$router->delete('/api/applications/{id}', [ApplicationController::class, 'delete'])->addMiddleware(new AuthMiddleware());

// Wishlist routes
$router->post('/api/wishlist', [WishlistController::class, 'addToWishlist'])->addMiddleware(new AuthMiddleware());
$router->delete('/api/wishlist', [WishlistController::class, 'removeFromWishlist'])->addMiddleware(new AuthMiddleware());
$router->get('/api/wishlist', [WishlistController::class, 'getUserWishlist'])->addMiddleware(new AuthMiddleware());
$router->get('/api/wishlist/check/{jobId}', [WishlistController::class, 'isJobInWishlist'])->addMiddleware(new AuthMiddleware());
$router->get('/api/wishlist/ids', [WishlistController::class, 'getUserWishlistIds'])->addMiddleware(new AuthMiddleware());
$router->post('/api/wishlist/toggle', [WishlistController::class, 'toggleWishlist'])->addMiddleware(new AuthMiddleware());

// Admin panel routes
$router->get('/api/admin/dashboard/stats', [AdminController::class, 'getStats'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/dashboard/full-stats', [AdminController::class, 'getAdminStats'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/users', [AdminController::class, 'getUsers'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/jobseekers', [AdminController::class, 'getJobseekers'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/recruiters', [AdminController::class, 'getRecruiters'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/jobs', [AdminController::class, 'getJobs'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/jobs/pending', [AdminController::class, 'getPendingJobs'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/applications', [AdminController::class, 'getApplications'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->get('/api/admin/issues-reports', [AdminController::class, 'getIssueReports'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));

// Admin update routes
$router->put('/api/admin/jobs/{id}/approve', [AdminController::class, 'approveJob'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/jobs/{id}/reject', [AdminController::class, 'rejectJob'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/jobs/{id}/status', [AdminController::class, 'updateJobStatus'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->delete('/api/admin/jobs/{id}', [AdminController::class, 'deleteJob'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/users/{id}/status', [AdminController::class, 'updateUserStatus'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/recruiters/{id}/approve', [AdminController::class, 'approveRecruiter'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/recruiters/{id}/reject', [AdminController::class, 'rejectRecruiter'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/applications/{id}/status', [AdminController::class, 'updateApplicationStatus'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));
$router->put('/api/admin/issues-reports/{id}/status', [AdminController::class, 'updateIssueReportStatus'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));

// Admin search route
$router->get('/api/admin/search', [AdminController::class, 'searchAll'])->addMiddleware(new AuthMiddleware())->addMiddleware(new RoleMiddleware(['admin']));

// Handle the request
$request = ServerRequest::fromGlobals();
$response = $router->dispatch($request);

// Send response
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

echo $response->getBody();