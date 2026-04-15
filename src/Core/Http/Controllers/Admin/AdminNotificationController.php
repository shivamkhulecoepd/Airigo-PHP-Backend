<?php

namespace App\Core\Http\Controllers\Admin;

use App\Core\Http\Controllers\BaseController;
use Firebase\FirebaseNotificationService;
use App\Core\Utils\ResponseBuilder;
use Psr\Http\Message\ServerRequestInterface;
use App\Repositories\UserRepository;
use App\Repositories\JobRepository;
use App\Repositories\ApplicationRepository;
use App\Repositories\NotificationRepository;

class AdminNotificationController extends BaseController
{
    private FirebaseNotificationService $notificationService;
    private UserRepository $userRepository;
    private JobRepository $jobRepository;
    private ApplicationRepository $applicationRepository;
    private NotificationRepository $notificationRepository;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new FirebaseNotificationService();
        $this->userRepository = new UserRepository();
        $this->jobRepository = new JobRepository();
        $this->applicationRepository = new ApplicationRepository();
        $this->notificationRepository = new NotificationRepository();
    }

    /**
     * Send notification to specific user
     */
    public function sendNotificationToUser(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $data = $this->getRequestBody($request);
        $userId = (int) $args['id'];

        if (empty($data['title']) || empty($data['body'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Title and body are required'
            ]);
        }

        // Check if user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ResponseBuilder::notFound([
                'message' => 'User not found'
            ]);
        }

        $notificationData = [
            'title' => $data['title'],
            'body' => $data['body']
        ];

        $additionalData = $data['data'] ?? [];

        $result = $this->notificationService->sendToUser($userId, $notificationData, $additionalData);

        if ($result) {
            // Save notification to database
            $this->notificationRepository->create([
                'user_id' => $userId, 
                'title' => $data['title'], 
                'body' => $data['body'], 
                'type' => 'admin_notification', 
                'data' => json_encode($additionalData)
            ]);

            return ResponseBuilder::ok([
                'message' => 'Notification sent successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to send notification'
        ]);
    }

    /**
     * Send notification to all users
     */
    public function sendNotificationToAll(ServerRequestInterface $request)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['title']) || empty($data['body'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Title and body are required'
            ]);
        }

        // Get all users
        $users = $this->userRepository->findAll();
        $userIds = array_column($users, 'id');

        $notificationData = [
            'title' => $data['title'],
            'body' => $data['body']
        ];

        $additionalData = $data['data'] ?? [];

        $results = $this->notificationService->sendToUsers($userIds, $notificationData, $additionalData);

        // Count successful deliveries
        $successCount = array_filter($results, function($result) {
            return $result === true;
        });

        // Save notifications to database for each user
        foreach ($userIds as $userId) {
            $this->notificationRepository->create([
                'user_id' => $userId,
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => 'admin_broadcast',
                'data' => json_encode($additionalData)
            ]);
        }

        return ResponseBuilder::ok([
            'message' => 'Notifications sent successfully',
            'total_sent' => count($successCount),
            'total_failed' => count($results) - count($successCount)
        ]);
    }

    /**
     * Send notification to specific user type (jobseeker/recruiter/admin)
     */
    public function sendNotificationByRole(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $data = $this->getRequestBody($request);
        $userType = $args['type']; // jobseeker, recruiter, admin

        if (!in_array($userType, ['jobseeker', 'recruiter', 'admin'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Invalid user type. Must be jobseeker, recruiter, or admin'
            ]);
        }

        if (empty($data['title']) || empty($data['body'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Title and body are required'
            ]);
        }

        // Get users by role
        $users = $this->userRepository->findAll();
        
        // Filter users by role
        $filteredUsers = [];
        foreach ($users as $user) {
            if (($userType === 'jobseeker' && $user['role'] === 'jobseeker') || 
                ($userType === 'recruiter' && $user['role'] === 'recruiter') ||
                ($userType === 'admin' && $user['role'] === 'admin')) {
                $filteredUsers[] = $user;
            }
        }
        $userIds = array_column($filteredUsers, 'id');

        $notificationData = [
            'title' => $data['title'],
            'body' => $data['body']
        ];

        $additionalData = $data['data'] ?? [];

        $results = $this->notificationService->sendToUsers($userIds, $notificationData, $additionalData);

        // Count successful deliveries
        $successCount = array_filter($results, function($result) {
            return $result === true;
        });

        // Save notifications to database for each filtered user
        foreach ($userIds as $userId) {
            $this->notificationRepository->create([
                'user_id' => $userId,
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => 'admin_broadcast',
                'data' => json_encode($additionalData)
            ]);
        }

        return ResponseBuilder::ok([
            'message' => "Notifications sent to {$userType}s successfully",
            'total_sent' => count($successCount),
            'total_failed' => count($results) - count($successCount)
        ]);
    }

    /**
     * Send job approval notification to recruiter
     */
    public function sendJobApprovalNotification(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $jobId = (int) $args['id'];
        
        // Get job details
        $job = $this->jobRepository->findById($jobId);
        if (!$job) {
            return ResponseBuilder::notFound([
                'message' => 'Job not found'
            ]);
        }

        // Get recruiter details
        $recruiter = $this->userRepository->findById($job['recruiter_id']);
        if (!$recruiter) {
            return ResponseBuilder::notFound([
                'message' => 'Recruiter not found'
            ]);
        }

        $notificationData = [
            'title' => 'Job Approved',
            'body' => "Your job posting '{$job['title']}' has been approved by admin."
        ];

        $data = [
            'type' => 'job_approval',
            'job_id' => (string)$jobId,
            'job_title' => $job['title'],
            'action' => 'job_approved'
        ];

        $result = $this->notificationService->sendToUser($recruiter['id'], $notificationData, $data);

        if ($result) {
            // Save notification to database
            $this->notificationRepository->create([
                'user_id' => $recruiter['id'], 
                'title' => $notificationData['title'], 
                'body' => $notificationData['body'], 
                'type' => 'job_approval', 
                'data' => json_encode($data)
            ]);

            return ResponseBuilder::ok([
                'message' => 'Job approval notification sent successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to send job approval notification'
        ]);
    }

    /**
     * Send job rejection notification to recruiter
     */
    public function sendJobRejectionNotification(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $jobId = (int) $args['id'];
        
        // Get job details
        $job = $this->jobRepository->findById($jobId);
        if (!$job) {
            return ResponseBuilder::notFound([
                'message' => 'Job not found'
            ]);
        }

        // Get recruiter details
        $recruiter = $this->userRepository->findById($job['recruiter_id']);
        if (!$recruiter) {
            return ResponseBuilder::notFound([
                'message' => 'Recruiter not found'
            ]);
        }

        $data = $this->getRequestBody($request);
        $reason = $data['reason'] ?? 'Job posting does not meet platform guidelines';

        $notificationData = [
            'title' => 'Job Rejected',
            'body' => "Your job posting '{$job['title']}' has been rejected by admin. Reason: {$reason}"
        ];

        $additionalData = [
            'type' => 'job_rejection',
            'job_id' => (string)$jobId,
            'job_title' => $job['title'],
            'reason' => $reason,
            'action' => 'job_rejected'
        ];

        $result = $this->notificationService->sendToUser($recruiter['id'], $notificationData, $additionalData);

        if ($result) {
            // Save notification to database
            $this->notificationRepository->create([
                'user_id' => $recruiter['id'], 
                'title' => $notificationData['title'], 
                'body' => $notificationData['body'], 
                'type' => 'job_rejection', 
                'data' => json_encode($additionalData)
            ]);

            return ResponseBuilder::ok([
                'message' => 'Job rejection notification sent successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to send job rejection notification'
        ]);
    }

    /**
     * Send recruiter approval notification
     */
    public function sendRecruiterApprovalNotification(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $userId = (int) $args['id'];
        
        // Get user details
        $user = $this->userRepository->findById($userId);
        if (!$user || $user['role'] !== 'recruiter') {
            return ResponseBuilder::notFound([
                'message' => 'Recruiter not found'
            ]);
        }

        $notificationData = [
            'title' => 'Account Approved',
            'body' => "Your recruiter account has been approved by admin. You can now post jobs."
        ];

        $data = [
            'type' => 'recruiter_approval',
            'user_id' => (string)$userId,
            'action' => 'recruiter_approved'
        ];

        $result = $this->notificationService->sendToUser($user['id'], $notificationData, $data);

        if ($result) {
            // Save notification to database
            $this->notificationRepository->create([
                'user_id' => $user['id'], 
                'title' => $notificationData['title'], 
                'body' => $notificationData['body'], 
                'type' => 'recruiter_approval', 
                'data' => json_encode($data)
            ]);

            return ResponseBuilder::ok([
                'message' => 'Recruiter approval notification sent successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to send recruiter approval notification'
        ]);
    }

    /**
     * Send recruiter rejection notification
     */
    public function sendRecruiterRejectionNotification(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $userId = (int) $args['id'];
        
        // Get user details
        $user = $this->userRepository->findById($userId);
        if (!$user || $user['role'] !== 'recruiter') {
            return ResponseBuilder::notFound([
                'message' => 'Recruiter not found'
            ]);
        }

        $data = $this->getRequestBody($request);
        $reason = $data['reason'] ?? 'Account does not meet platform guidelines';

        $notificationData = [
            'title' => 'Account Rejected',
            'body' => "Your recruiter account has been rejected by admin. Reason: {$reason}"
        ];

        $additionalData = [
            'type' => 'recruiter_rejection',
            'user_id' => (string)$userId,
            'reason' => $reason,
            'action' => 'recruiter_rejected'
        ];

        $result = $this->notificationService->sendToUser($user['id'], $notificationData, $additionalData);

        if ($result) {
            // Save notification to database
            $this->notificationRepository->create([
                'user_id' => $user['id'], 
                'title' => $notificationData['title'], 
                'body' => $notificationData['body'], 
                'type' => 'recruiter_rejection', 
                'data' => json_encode($additionalData)
            ]);

            return ResponseBuilder::ok([
                'message' => 'Recruiter rejection notification sent successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to send recruiter rejection notification'
        ]);
    }

    /**
     * Send system maintenance notification to all users
     */
    public function sendSystemMaintenanceNotification(ServerRequestInterface $request)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['title']) || empty($data['body'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Title and body are required'
            ]);
        }

        if (empty($data['maintenance_start_time']) || empty($data['maintenance_duration'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Maintenance start time and duration are required'
            ]);
        }

        // Get all users
        $users = $this->userRepository->findAll();
        $userIds = array_column($users, 'id');

        $notificationData = [
            'title' => $data['title'],
            'body' => $data['body']
        ];

        $additionalData = [
            'type' => 'system_maintenance',
            'maintenance_start_time' => $data['maintenance_start_time'],
            'maintenance_duration' => $data['maintenance_duration'],
            'action' => 'system_maintenance'
        ];

        $results = $this->notificationService->sendToUsers($userIds, $notificationData, $additionalData);

        // Count successful deliveries
        $successCount = array_filter($results, function($result) {
            return $result === true;
        });

        // Save notifications to database for each user
        foreach ($userIds as $userId) {
            $this->notificationRepository->create([
                'user_id' => $userId,
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => 'system_maintenance',
                'data' => json_encode($additionalData)
            ]);
        }

        return ResponseBuilder::ok([
            'message' => 'System maintenance notifications sent successfully',
            'total_sent' => count($successCount),
            'total_failed' => count($results) - count($successCount)
        ]);
    }

    /**
     * Send notification to users who applied for a job
     */
    public function sendJobStatusChangeNotification(ServerRequestInterface $request, array $args)
    {
        $admin = $this->getUser($request);
        if (!$admin || $admin['role'] !== 'admin') {
            return ResponseBuilder::unauthorized(['message' => 'Admin access required']);
        }

        $jobId = (int) $args['id'];
        
        // Get job details
        $job = $this->jobRepository->findById($jobId);
        if (!$job) {
            return ResponseBuilder::notFound([
                'message' => 'Job not found'
            ]);
        }

        // Get all applications for this job
        $applications = $this->applicationRepository->findByJobId($jobId);
        $applicants = [];
        
        foreach ($applications as $application) {
            $applicants[] = $this->userRepository->findById($application['jobseeker_id']);
        }

        $data = $this->getRequestBody($request);
        $status = $data['status'] ?? 'updated';
        $message = $data['message'] ?? "The job '{$job['title']}' status has been updated.";

        $notificationData = [
            'title' => 'Job Status Changed',
            'body' => $message
        ];

        $additionalData = [
            'type' => 'job_status_change',
            'job_id' => (string)$jobId,
            'job_title' => $job['title'],
            'status' => $status,
            'action' => 'job_status_updated'
        ];

        $userIds = array_column($applicants, 'id');
        $results = $this->notificationService->sendToUsers($userIds, $notificationData, $additionalData);

        // Count successful deliveries
        $successCount = array_filter($results, function($result) {
            return $result === true;
        });

        // Save notifications to database for each applicant
        foreach ($applicants as $applicant) {
            if ($applicant) {
                $this->notificationRepository->create([
                    'user_id' => $applicant['id'],
                    'title' => $notificationData['title'],
                    'body' => $notificationData['body'],
                    'type' => 'job_status_change',
                    'data' => json_encode($additionalData)
                ]);
            }
        }

        return ResponseBuilder::ok([
            'message' => 'Job status change notifications sent successfully',
            'total_sent' => count($successCount),
            'total_failed' => count($results) - count($successCount)
        ]);
    }
}