<?php

namespace App\Core\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use Firebase\FirebaseNotificationService;
use App\Core\Utils\ResponseBuilder;
use Psr\Http\Message\ServerRequestInterface;
use App\Repositories\NotificationRepository;

class NotificationController extends BaseController
{
    private FirebaseNotificationService $notificationService;
    private NotificationRepository $notificationRepository;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new FirebaseNotificationService();
        $this->notificationRepository = new NotificationRepository();
    }

    /**
     * Store FCM token for user
     */
    public function storeFcmToken(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['fcm_token'])) {
            return ResponseBuilder::badRequest([
                'message' => 'FCM token is required'
            ]);
        }

        $deviceType = $data['device_type'] ?? 'mobile';
        $deviceInfo = $data['device_info'] ?? null;

        $result = $this->notificationService->storeUserToken(
            $user['id'], 
            $data['fcm_token'], 
            $deviceType
        );

        if ($result) {
            return ResponseBuilder::ok([
                'message' => 'FCM token stored successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to store FCM token'
        ]);
    }

    /**
     * Remove FCM token (logout/unregister device)
     */
    public function removeFcmToken(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['fcm_token'])) {
            return ResponseBuilder::badRequest([
                'message' => 'FCM token is required'
            ]);
        }

        $result = $this->notificationService->removeUserToken($data['fcm_token']);

        if ($result) {
            return ResponseBuilder::ok([
                'message' => 'FCM token removed successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to remove FCM token'
        ]);
    }

    /**
     * Get user's FCM tokens
     */
    public function getUserTokens(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            $tokens = $this->notificationService->getUserActiveTokens($user['id']);
            
            return ResponseBuilder::ok([
                'tokens' => $tokens,
                'message' => 'User tokens retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to retrieve user tokens',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);
        
        $notificationData = [
            'title' => $data['title'] ?? 'Test Notification',
            'body' => $data['body'] ?? 'This is a test notification from Airigo Jobs'
        ];

        $customData = $data['data'] ?? [];

        try {
            $result = $this->notificationService->sendToUser(
                $user['id'], 
                $notificationData, 
                $customData
            );

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'Test notification sent successfully'
                ]);
            } else {
                return ResponseBuilder::ok([
                    'message' => 'Test attempted but no valid tokens found for user. This is expected in Postman testing.'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup invalid FCM tokens (admin only)
     */
    public function cleanupInvalidTokens(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        try {
            $deletedCount = $this->notificationService->cleanupInvalidTokens();
            
            return ResponseBuilder::ok([
                'message' => 'Invalid tokens cleanup completed',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to cleanup invalid tokens',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $onlyUnread = filter_var($this->getQueryParam($request, 'unread_only', 'false'), FILTER_VALIDATE_BOOLEAN);

        try {
            $offset = ($page - 1) * $limit;
            $notifications = $this->notificationRepository->getByUserId(
                $user['id'], 
                $limit, 
                $offset, 
                $onlyUnread
            );
            $totalCount = $this->notificationRepository->countByUser($user['id'], $onlyUnread);

            return ResponseBuilder::ok([
                'notifications' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $notificationId = (int) $request->getAttribute('id');

        if ($notificationId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid notification ID']);
        }

        try {
            $notification = $this->notificationRepository->findById($notificationId);

            if (!$notification) {
                return ResponseBuilder::notFound(['message' => 'Notification not found']);
            }

            if ($notification['user_id'] != $user['id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only mark your own notifications as read']);
            }

            $result = $this->notificationRepository->markAsRead($notificationId);

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'Notification marked as read successfully'
                ]);
            } else {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to mark notification as read'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            $result = $this->notificationRepository->markAllAsRead($user['id']);

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'All notifications marked as read successfully'
                ]);
            } else {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to mark all notifications as read'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get notification count for user
     */
    public function getUnreadCount(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            $unreadCount = $this->notificationRepository->countByUser($user['id'], true);

            return ResponseBuilder::ok([
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to retrieve unread count',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Archive notification
     */
    public function archiveNotification(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $notificationId = (int) $request->getAttribute('id');

        if ($notificationId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid notification ID']);
        }

        try {
            $notification = $this->notificationRepository->findById($notificationId);

            if (!$notification) {
                return ResponseBuilder::notFound(['message' => 'Notification not found']);
            }

            if ($notification['user_id'] != $user['id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only archive your own notifications']);
            }

            $result = $this->notificationRepository->archive($notificationId);

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'Notification archived successfully'
                ]);
            } else {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to archive notification'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to archive notification',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete notification
     */
    public function deleteNotification(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $notificationId = (int) $request->getAttribute('id');

        if ($notificationId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid notification ID']);
        }

        try {
            $notification = $this->notificationRepository->findById($notificationId);

            if (!$notification) {
                return ResponseBuilder::notFound(['message' => 'Notification not found']);
            }

            if ($notification['user_id'] != $user['id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only delete your own notifications']);
            }

            $result = $this->notificationRepository->delete($notificationId);

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'Notification deleted successfully'
                ]);
            } else {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to delete notification'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ]);
        }
    }

    // Jobseeker Notification Methods
    
    /**
     * Send application status notification to jobseeker
     */
    public function sendApplicationStatusNotification(
        int $userId, 
        string $userName, 
        string $jobTitle, 
        string $status,
        string $companyName
    ): bool {
        $statusMessages = [
            'shortlisted' => "Great news {$userName}! Your application for '{$jobTitle}' at {$companyName} has been shortlisted.",
            'accepted' => "Congratulations {$userName}! You've been accepted for '{$jobTitle}' at {$companyName}!",
            'rejected' => "We're sorry {$userName}, your application for '{$jobTitle}' at {$companyName} was not selected.",
            'pending' => "Your application for '{$jobTitle}' at {$companyName} is now under review.",
            'interview_scheduled' => "Great news {$userName}! Your interview for '{$jobTitle}' at {$companyName} has been scheduled.",
            'interview_rescheduled' => "{$userName}, your interview for '{$jobTitle}' at {$companyName} has been rescheduled.",
            'interview_rejected' => "{$userName}, your interview request for '{$jobTitle}' at {$companyName} has been declined."
        ];

        $notificationData = [
            'title' => 'Application Status Update',
            'body' => $statusMessages[$status] ?? "Your application status has been updated to: {$status}"
        ];

        $data = [
            'type' => 'application_status',
            'status' => $status,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'action' => 'status_updated'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'application_status',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send job matching notification to jobseeker
     */
    public function sendJobMatchingNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $companyName,
        int $jobId
    ): bool {
        $notificationData = [
            'title' => 'New Job Match',
            'body' => "Hi {$userName}! We found a new job that matches your profile: '{$jobTitle}' at {$companyName}"
        ];

        $data = [
            'type' => 'job_matching',
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'job_id' => (string)$jobId,
            'action' => 'job_matched'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'job_matching',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send profile view notification to jobseeker
     */
    public function sendProfileViewNotification(
        int $userId,
        string $userName,
        string $recruiterCompanyName,
        string $recruiterName
    ): bool {
        $notificationData = [
            'title' => 'Profile Viewed',
            'body' => "Hi {$userName}! A recruiter from {$recruiterCompanyName} viewed your profile"
        ];

        $data = [
            'type' => 'profile_view',
            'recruiter_company' => $recruiterCompanyName,
            'recruiter_name' => $recruiterName,
            'action' => 'profile_viewed'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'profile_view',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }


    /**
     * Send interview scheduled notification to jobseeker
     */
    public function sendInterviewScheduledNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $companyName,
        string $interviewTime
    ): bool {
        $notificationData = [
            'title' => 'Interview Scheduled',
            'body' => "Hi {$userName}! Your interview for '{$jobTitle}' at {$companyName} is scheduled for {$interviewTime}"
        ];

        $data = [
            'type' => 'interview_scheduled',
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'interview_time' => $interviewTime,
            'action' => 'interview_scheduled'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'interview_scheduled',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send offer extended notification to jobseeker
     */
    public function sendOfferExtendedNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $companyName,
        string $offerAmount
    ): bool {
        $notificationData = [
            'title' => 'Job Offer Extended',
            'body' => "Hi {$userName}! Congratulations! You've received a job offer for '{$jobTitle}' at {$companyName} with CTC of {$offerAmount}"
        ];

        $data = [
            'type' => 'offer_extended',
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'offer_amount' => $offerAmount,
            'action' => 'offer_extended'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'offer_extended',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send message received notification to jobseeker
     */
    public function sendMessageReceivedNotification(
        int $userId,
        string $userName,
        string $senderName,
        string $messagePreview
    ): bool {
        $notificationData = [
            'title' => 'New Message',
            'body' => "Hi {$userName}! You have a new message from {$senderName}: {$messagePreview}"
        ];

        $data = [
            'type' => 'message_received',
            'sender_name' => $senderName,
            'message_preview' => $messagePreview,
            'action' => 'message_received'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'message_received',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send job expiry notification to jobseeker
     */
    public function sendJobExpiryNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $daysLeft
    ): bool {
        $notificationData = [
            'title' => 'Job Expiring Soon',
            'body' => "Hi {$userName}! The job posting '{$jobTitle}' is expiring in {$daysLeft} days"
        ];

        $data = [
            'type' => 'job_expiry',
            'job_title' => $jobTitle,
            'days_left' => $daysLeft,
            'action' => 'job_expiring'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'job_expiry',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send account verification notification to jobseeker
     */
    public function sendAccountVerificationNotification(
        int $userId,
        string $userName,
        string $verificationStatus
    ): bool {
        $title = $verificationStatus === 'verified' ? 'Account Verified!' : 'Verification Required';
        $body = $verificationStatus === 'verified' 
            ? "Hi {$userName}! Your account has been verified successfully."
            : "Hi {$userName}! Please verify your account to unlock all features.";

        $notificationData = [
            'title' => $title,
            'body' => $body
        ];

        $data = [
            'type' => 'account_verification',
            'status' => $verificationStatus,
            'action' => $verificationStatus === 'verified' ? 'account_verified' : 'verification_required'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'account_verification',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    // Recruiter Notification Methods
    
    /**
     * Send new application notification to recruiter
     */
    public function sendNewApplicationNotification(
        int $recruiterId,
        string $recruiterName,
        string $jobTitle,
        string $applicantName,
        int $applicationId
    ): bool {
        $notificationData = [
            'title' => 'New Job Application',
            'body' => "Hi {$recruiterName}! {$applicantName} has applied for '{$jobTitle}'."
        ];

        $data = [
            'type' => 'new_application',
            'job_title' => $jobTitle,
            'applicant_name' => $applicantName,
            'application_id' => (string)$applicationId,
            'action' => 'new_application_received'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $recruiterId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'new_application',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($recruiterId, $notificationData, $data);
    }

    /**
     * Send job approval notification to recruiter
     */
    public function sendJobApprovalNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $status,
        ?string $rejectionReason = null
    ): bool {
        $title = $status === 'approved' ? 'Job Posting Approved!' : 'Job Posting Rejected';
        $body = $status === 'approved' 
            ? "Great news {$userName}! Your job posting '{$jobTitle}' has been approved and is now live."
            : "Sorry {$userName}, your job posting '{$jobTitle}' has been rejected. Reason: {$rejectionReason}";

        $notificationData = [
            'title' => $title,
            'body' => $body
        ];

        $data = [
            'type' => 'job_approval',
            'status' => $status,
            'job_title' => $jobTitle,
            'action' => $status === 'approved' ? 'job_approved' : 'job_rejected'
        ];

        if ($rejectionReason) {
            $data['rejection_reason'] = $rejectionReason;
        }

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'job_approval',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send welcome notification for new user registration
     */
    public function sendWelcomeNotification(int $userId, string $userName, string $userType): bool
    {
        $notificationData = [
            'title' => 'Welcome to Airigo Jobs!',
            'body' => "Hi {$userName}, welcome to Airigo Jobs! Your {$userType} account has been created successfully."
        ];

        $data = [
            'type' => 'welcome',
            'user_type' => $userType,
            'action' => 'account_created'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'welcome',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send recruiter approval notification
     */
    public function sendRecruiterApprovalNotification(int $userId, string $userName, string $status, ?string $rejectionReason = null): bool
    {
        $title = $status === 'approved' ? 'Recruiter Account Approved!' : 'Recruiter Account Rejected';
        $body = $status === 'approved' 
            ? "Congratulations {$userName}! Your recruiter account has been approved. You can now post jobs."
            : "Sorry {$userName}, your recruiter account has been rejected. Reason: {$rejectionReason}";

        $notificationData = [
            'title' => $title,
            'body' => $body
        ];

        $data = [
            'type' => 'recruiter_approval',
            'status' => $status,
            'action' => $status === 'approved' ? 'account_approved' : 'account_rejected'
        ];

        if ($rejectionReason) {
            $data['rejection_reason'] = $rejectionReason;
        }

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'recruiter_approval',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification(
        int $userId,
        string $userName,
        string $resetToken,
        string $identifier
    ): bool {
        $notificationData = [
            'title' => 'Password Reset Request',
            'body' => "Hi {$userName}! You requested a password reset. Your reset code is: {$resetToken}"
        ];

        $data = [
            'type' => 'password_reset',
            'reset_token' => $resetToken,
            'identifier' => $identifier,
            'action' => 'password_reset_requested'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'password_reset',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send system maintenance notification
     */
    public function sendSystemMaintenanceNotification(
        int $userId,
        string $userName,
        string $maintenanceTime,
        string $duration
    ): bool {
        $notificationData = [
            'title' => 'System Maintenance Scheduled',
            'body' => "Hi {$userName}! System maintenance is scheduled for {$maintenanceTime} (Duration: {$duration})"
        ];

        $data = [
            'type' => 'system_maintenance',
            'maintenance_time' => $maintenanceTime,
            'duration' => $duration,
            'action' => 'maintenance_scheduled'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'system_maintenance',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send platform update notification
     */
    public function sendPlatformUpdateNotification(
        int $userId,
        string $userName,
        string $featureName
    ): bool {
        $notificationData = [
            'title' => 'New Feature Available',
            'body' => "Hi {$userName}! We've launched a new feature: {$featureName}"
        ];

        $data = [
            'type' => 'platform_update',
            'feature_name' => $featureName,
            'action' => 'feature_launched'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'platform_update',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send policy update notification
     */
    public function sendPolicyUpdateNotification(
        int $userId,
        string $userName,
        string $policyType
    ): bool {
        $notificationData = [
            'title' => 'Policy Update',
            'body' => "Hi {$userName}! Our {$policyType} policy has been updated. Please review the changes."
        ];

        $data = [
            'type' => 'policy_update',
            'policy_type' => $policyType,
            'action' => 'policy_updated'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'policy_update',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send job expiry notification to recruiter
     */
    public function sendJobExpiryToRecruiterNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $daysLeft
    ): bool {
        $notificationData = [
            'title' => 'Job Expiring Soon',
            'body' => "Hi {$userName}! Your job posting '{$jobTitle}' is expiring in {$daysLeft} days"
        ];

        $data = [
            'type' => 'job_expiry_to_recruiter',
            'job_title' => $jobTitle,
            'days_left' => $daysLeft,
            'action' => 'job_expiring_to_recruiter'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'job_expiry_to_recruiter',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send candidate responded notification to recruiter
     */
    public function sendCandidateRespondedNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $candidateName
    ): bool {
        $notificationData = [
            'title' => 'Candidate Responded',
            'body' => "Hi {$userName}! {$candidateName} has responded to your message regarding '{$jobTitle}'"
        ];

        $data = [
            'type' => 'candidate_responded',
            'job_title' => $jobTitle,
            'candidate_name' => $candidateName,
            'action' => 'candidate_responded'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'candidate_responded',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send high-profile candidate applied notification to recruiter
     */
    public function sendHighProfileCandidateAppliedNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $candidateName,
        string $experienceLevel
    ): bool {
        $notificationData = [
            'title' => 'High-Profile Candidate Applied',
            'body' => "Hi {$userName}! A high-profile candidate with {$experienceLevel} experience has applied for '{$jobTitle}'"
        ];

        $data = [
            'type' => 'high_profile_candidate_applied',
            'job_title' => $jobTitle,
            'candidate_name' => $candidateName,
            'experience_level' => $experienceLevel,
            'action' => 'high_profile_candidate_applied'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'high_profile_candidate_applied',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send candidate scheduled interview notification to recruiter
     */
    public function sendCandidateScheduledInterviewNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $candidateName,
        string $interviewTime
    ): bool {
        $notificationData = [
            'title' => 'Candidate Scheduled Interview',
            'body' => "Hi {$userName}! {$candidateName} has scheduled an interview for '{$jobTitle}' at {$interviewTime}"
        ];

        $data = [
            'type' => 'candidate_scheduled_interview',
            'job_title' => $jobTitle,
            'candidate_name' => $candidateName,
            'interview_time' => $interviewTime,
            'action' => 'candidate_scheduled_interview'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'candidate_scheduled_interview',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send candidate missed interview notification to recruiter
     */
    public function sendCandidateMissedInterviewNotification(
        int $userId,
        string $userName,
        string $jobTitle,
        string $candidateName,
        string $interviewTime
    ): bool {
        $notificationData = [
            'title' => 'Candidate Missed Interview',
            'body' => "Hi {$userName}! {$candidateName} missed the interview for '{$jobTitle}' scheduled at {$interviewTime}"
        ];

        $data = [
            'type' => 'candidate_missed_interview',
            'job_title' => $jobTitle,
            'candidate_name' => $candidateName,
            'interview_time' => $interviewTime,
            'action' => 'candidate_missed_interview'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'candidate_missed_interview',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send subscription renewal notification to recruiter
     */
    public function sendSubscriptionRenewalNotification(
        int $userId,
        string $userName,
        string $renewalDate
    ): bool {
        $notificationData = [
            'title' => 'Subscription Renewal Due',
            'body' => "Hi {$userName}! Your subscription is due for renewal on {$renewalDate}"
        ];

        $data = [
            'type' => 'subscription_renewal',
            'renewal_date' => $renewalDate,
            'action' => 'subscription_renewal_due'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'subscription_renewal',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send payment successful notification to recruiter
     */
    public function sendPaymentSuccessfulNotification(
        int $userId,
        string $userName,
        string $amount,
        string $transactionId
    ): bool {
        $notificationData = [
            'title' => 'Payment Successful',
            'body' => "Hi {$userName}! Payment of {$amount} has been processed successfully. Transaction ID: {$transactionId}"
        ];

        $data = [
            'type' => 'payment_successful',
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'action' => 'payment_successful'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'payment_successful',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send payment failed notification to recruiter
     */
    public function sendPaymentFailedNotification(
        int $userId,
        string $userName,
        string $amount,
        string $errorMessage
    ): bool {
        $notificationData = [
            'title' => 'Payment Failed',
            'body' => "Hi {$userName}! Payment of {$amount} failed. Error: {$errorMessage}"
        ];

        $data = [
            'type' => 'payment_failed',
            'amount' => $amount,
            'error_message' => $errorMessage,
            'action' => 'payment_failed'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'payment_failed',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send invoice ready notification to recruiter
     */
    public function sendInvoiceReadyNotification(
        int $userId,
        string $userName,
        string $invoiceNumber,
        string $amount
    ): bool {
        $notificationData = [
            'title' => 'Invoice Ready',
            'body' => "Hi {$userName}! Invoice {$invoiceNumber} for {$amount} is ready for download"
        ];

        $data = [
            'type' => 'invoice_ready',
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'action' => 'invoice_ready'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'invoice_ready',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send admin message notification to recruiter
     */
    public function sendAdminMessageNotification(
        int $userId,
        string $userName,
        string $subject,
        string $message
    ): bool {
        $notificationData = [
            'title' => $subject,
            'body' => "Hi {$userName}! Admin message: {$message}"
        ];

        $data = [
            'type' => 'admin_message',
            'subject' => $subject,
            'message' => $message,
            'action' => 'admin_message_sent'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'admin_message',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send support response notification to recruiter
     */
    public function sendSupportResponseNotification(
        int $userId,
        string $userName,
        string $ticketId,
        string $response
    ): bool {
        $notificationData = [
            'title' => 'Support Response',
            'body' => "Hi {$userName}! Support response for ticket #{$ticketId}: {$response}"
        ];

        $data = [
            'type' => 'support_response',
            'ticket_id' => $ticketId,
            'response' => $response,
            'action' => 'support_responded'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $userId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'support_response',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send feature request notification to admin
     */
    public function sendFeatureRequestNotification(
        int $adminUserId,
        string $adminName,
        string $featureName,
        string $requestorName,
        string $requestorEmail
    ): bool {
        $notificationData = [
            'title' => 'New Feature Request',
            'body' => "Hi {$adminName}! New feature requested by {$requestorName} ({$requestorEmail}): {$featureName}"
        ];

        $data = [
            'type' => 'feature_request',
            'feature_name' => $featureName,
            'requestor_name' => $requestorName,
            'requestor_email' => $requestorEmail,
            'action' => 'feature_requested'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'feature_request',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send new user registration notification to admin
     */
    public function sendNewUserRegistrationNotification(
        int $adminUserId,
        string $adminName,
        string $newUserName,
        string $newUserType,
        string $email
    ): bool {
        $notificationData = [
            'title' => 'New User Registration',
            'body' => "Hi {$adminName}! A new {$newUserType} has registered: {$newUserName} ({$email})"
        ];

        $data = [
            'type' => 'new_user_registration',
            'new_user_name' => $newUserName,
            'new_user_type' => $newUserType,
            'email' => $email,
            'action' => 'new_user_registered'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'new_user_registration',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send user verification required notification to admin
     */
    public function sendUserVerificationRequiredNotification(
        int $adminUserId,
        string $adminName,
        string $userName,
        string $userType,
        string $email
    ): bool {
        $notificationData = [
            'title' => 'User Verification Required',
            'body' => "Hi {$adminName}! {$userType} {$userName} ({$email}) requires verification"
        ];

        $data = [
            'type' => 'user_verification_required',
            'user_name' => $userName,
            'user_type' => $userType,
            'email' => $email,
            'action' => 'verification_required'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'user_verification_required',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send suspicious activity detected notification to admin
     */
    public function sendSuspiciousActivityDetectedNotification(
        int $adminUserId,
        string $adminName,
        string $userName,
        string $userType,
        string $activityDescription
    ): bool {
        $notificationData = [
            'title' => 'Suspicious Activity Detected',
            'body' => "Hi {$adminName}! Suspicious activity detected for {$userType} {$userName}: {$activityDescription}"
        ];

        $data = [
            'type' => 'suspicious_activity_detected',
            'user_name' => $userName,
            'user_type' => $userType,
            'activity_description' => $activityDescription,
            'action' => 'suspicious_activity_detected'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'suspicious_activity_detected',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send user reported notification to admin
     */
    public function sendUserReportedNotification(
        int $adminUserId,
        string $adminName,
        string $reportedUserName,
        string $reporterUserName,
        string $reason
    ): bool {
        $notificationData = [
            'title' => 'User Reported',
            'body' => "Hi {$adminName}! {$reportedUserName} was reported by {$reporterUserName} for: {$reason}"
        ];

        $data = [
            'type' => 'user_reported',
            'reported_user_name' => $reportedUserName,
            'reporter_user_name' => $reporterUserName,
            'reason' => $reason,
            'action' => 'user_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'user_reported',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send account deletion request notification to admin
     */
    public function sendAccountDeletionRequestNotification(
        int $adminUserId,
        string $adminName,
        string $userName,
        string $userType,
        string $email
    ): bool {
        $notificationData = [
            'title' => 'Account Deletion Request',
            'body' => "Hi {$adminName}! {$userType} {$userName} ({$email}) has requested account deletion"
        ];

        $data = [
            'type' => 'account_deletion_request',
            'user_name' => $userName,
            'user_type' => $userType,
            'email' => $email,
            'action' => 'account_deletion_requested'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'account_deletion_request',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send new job posted notification to admin
     */
    public function sendNewJobPostedNotification(
        int $adminUserId,
        string $adminName,
        string $jobTitle,
        string $companyName,
        string $recruiterName
    ): bool {
        $notificationData = [
            'title' => 'New Job Posted',
            'body' => "Hi {$adminName}! New job posted: '{$jobTitle}' by {$recruiterName} at {$companyName}"
        ];

        $data = [
            'type' => 'new_job_posted',
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'recruiter_name' => $recruiterName,
            'action' => 'job_posted'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'new_job_posted',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send job report filed notification to admin
     */
    public function sendJobReportFiledNotification(
        int $adminUserId,
        string $adminName,
        string $jobTitle,
        string $reporterName,
        string $reason
    ): bool {
        $notificationData = [
            'title' => 'Job Report Filed',
            'body' => "Hi {$adminName}! Job '{$jobTitle}' was reported by {$reporterName} for: {$reason}"
        ];

        $data = [
            'type' => 'job_report_filed',
            'job_title' => $jobTitle,
            'reporter_name' => $reporterName,
            'reason' => $reason,
            'action' => 'job_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'job_report_filed',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send application report filed notification to admin
     */
    public function sendApplicationReportFiledNotification(
        int $adminUserId,
        string $adminName,
        string $jobTitle,
        string $applicantName,
        string $reason
    ): bool {
        $notificationData = [
            'title' => 'Application Report Filed',
            'body' => "Hi {$adminName}! Application for '{$jobTitle}' by {$applicantName} was reported for: {$reason}"
        ];

        $data = [
            'type' => 'application_report_filed',
            'job_title' => $jobTitle,
            'applicant_name' => $applicantName,
            'reason' => $reason,
            'action' => 'application_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'application_report_filed',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send profile report filed notification to admin
     */
    public function sendProfileReportFiledNotification(
        int $adminUserId,
        string $adminName,
        string $profileName,
        string $reporterName,
        string $reason
    ): bool {
        $notificationData = [
            'title' => 'Profile Report Filed',
            'body' => "Hi {$adminName}! Profile of {$profileName} was reported by {$reporterName} for: {$reason}"
        ];

        $data = [
            'type' => 'profile_report_filed',
            'profile_name' => $profileName,
            'reporter_name' => $reporterName,
            'reason' => $reason,
            'action' => 'profile_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'profile_report_filed',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send fraudulent job detected notification to admin
     */
    public function sendFraudulentJobDetectedNotification(
        int $adminUserId,
        string $adminName,
        string $jobTitle,
        string $companyName,
        string $details
    ): bool {
        $notificationData = [
            'title' => 'Fraudulent Job Detected',
            'body' => "Hi {$adminName}! Potentially fraudulent job detected: '{$jobTitle}' at {$companyName}. Details: {$details}"
        ];

        $data = [
            'type' => 'fraudulent_job_detected',
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'details' => $details,
            'action' => 'fraudulent_job_detected'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'fraudulent_job_detected',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send spam content detected notification to admin
     */
    public function sendSpamContentDetectedNotification(
        int $adminUserId,
        string $adminName,
        string $contentType,
        string $details
    ): bool {
        $notificationData = [
            'title' => 'Spam Content Detected',
            'body' => "Hi {$adminName}! Spam {$contentType} detected. Details: {$details}"
        ];

        $data = [
            'type' => 'spam_content_detected',
            'content_type' => $contentType,
            'details' => $details,
            'action' => 'spam_detected'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'spam_content_detected',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send system downtime notification to admin
     */
    public function sendSystemDowntimeNotification(
        int $adminUserId,
        string $adminName,
        string $startTime,
        string $estimatedDuration
    ): bool {
        $notificationData = [
            'title' => 'System Downtime',
            'body' => "Hi {$adminName}! System downtime started at {$startTime}. Estimated duration: {$estimatedDuration}"
        ];

        $data = [
            'type' => 'system_downtime',
            'start_time' => $startTime,
            'estimated_duration' => $estimatedDuration,
            'action' => 'system_down'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'system_downtime',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send performance issue notification to admin
     */
    public function sendPerformanceIssueNotification(
        int $adminUserId,
        string $adminName,
        string $severity,
        string $description
    ): bool {
        $notificationData = [
            'title' => 'Performance Issue',
            'body' => "Hi {$adminName}! Performance issue detected. Severity: {$severity}. Description: {$description}"
        ];

        $data = [
            'type' => 'performance_issue',
            'severity' => $severity,
            'description' => $description,
            'action' => 'performance_issue_detected'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'performance_issue',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send security breach alert to admin
     */
    public function sendSecurityBreachAlert(
        int $adminUserId,
        string $adminName,
        string $severity,
        string $description
    ): bool {
        $notificationData = [
            'title' => 'Security Breach Alert',
            'body' => "Hi {$adminName}! CRITICAL: Security breach detected. Severity: {$severity}. Description: {$description}"
        ];

        $data = [
            'type' => 'security_breach',
            'severity' => $severity,
            'description' => $description,
            'action' => 'security_breach_detected'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'security_breach',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send database backup complete notification to admin
     */
    public function sendDatabaseBackupCompleteNotification(
        int $adminUserId,
        string $adminName,
        string $backupTime,
        string $size
    ): bool {
        $notificationData = [
            'title' => 'Database Backup Complete',
            'body' => "Hi {$adminName}! Database backup completed at {$backupTime}. Size: {$size}"
        ];

        $data = [
            'type' => 'database_backup_complete',
            'backup_time' => $backupTime,
            'size' => $size,
            'action' => 'backup_completed'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'database_backup_complete',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send server resource alert to admin
     */
    public function sendServerResourceAlert(
        int $adminUserId,
        string $adminName,
        string $resourceType,
        string $currentUsage,
        string $threshold
    ): bool {
        $notificationData = [
            'title' => 'Server Resource Alert',
            'body' => "Hi {$adminName}! {$resourceType} usage is at {$currentUsage}% (Threshold: {$threshold}%)"
        ];

        $data = [
            'type' => 'server_resource_alert',
            'resource_type' => $resourceType,
            'current_usage' => $currentUsage,
            'threshold' => $threshold,
            'action' => 'resource_alert'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'server_resource_alert',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send daily activity summary to admin
     */
    public function sendDailyActivitySummary(
        int $adminUserId,
        string $adminName,
        array $stats
    ): bool {
        $notificationData = [
            'title' => 'Daily Activity Summary',
            'body' => "Hi {$adminName}! Today's activity summary: " . json_encode($stats)
        ];

        $data = [
            'type' => 'daily_activity_summary',
            'stats' => $stats,
            'action' => 'daily_summary'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'daily_activity_summary',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send weekly platform metrics to admin
     */
    public function sendWeeklyPlatformMetrics(
        int $adminUserId,
        string $adminName,
        array $metrics
    ): bool {
        $notificationData = [
            'title' => 'Weekly Platform Metrics',
            'body' => "Hi {$adminName}! This week's platform metrics: " . json_encode($metrics)
        ];

        $data = [
            'type' => 'weekly_platform_metrics',
            'metrics' => $metrics,
            'action' => 'weekly_metrics'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'weekly_platform_metrics',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send monthly analytics report to admin
     */
    public function sendMonthlyAnalyticsReport(
        int $adminUserId,
        string $adminName,
        array $report
    ): bool {
        $notificationData = [
            'title' => 'Monthly Analytics Report',
            'body' => "Hi {$adminName}! This month's analytics report: " . json_encode($report)
        ];

        $data = [
            'type' => 'monthly_analytics_report',
            'report' => $report,
            'action' => 'monthly_report'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'monthly_analytics_report',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send revenue report to admin
     */
    public function sendRevenueReport(
        int $adminUserId,
        string $adminName,
        string $period,
        string $amount
    ): bool {
        $notificationData = [
            'title' => 'Revenue Report',
            'body' => "Hi {$adminName}! Revenue for {$period}: {$amount}"
        ];

        $data = [
            'type' => 'revenue_report',
            'period' => $period,
            'amount' => $amount,
            'action' => 'revenue_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'revenue_report',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send user growth statistics to admin
     */
    public function sendUserGrowthStatistics(
        int $adminUserId,
        string $adminName,
        array $stats
    ): bool {
        $notificationData = [
            'title' => 'User Growth Statistics',
            'body' => "Hi {$adminName}! User growth statistics: " . json_encode($stats)
        ];

        $data = [
            'type' => 'user_growth_statistics',
            'stats' => $stats,
            'action' => 'growth_stats'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'user_growth_statistics',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send new support ticket notification to admin
     */
    public function sendNewSupportTicketNotification(
        int $adminUserId,
        string $adminName,
        string $ticketId,
        string $subject,
        string $requestorName
    ): bool {
        $notificationData = [
            'title' => 'New Support Ticket',
            'body' => "Hi {$adminName}! New support ticket #{$ticketId} from {$requestorName}: {$subject}"
        ];

        $data = [
            'type' => 'new_support_ticket',
            'ticket_id' => $ticketId,
            'subject' => $subject,
            'requestor_name' => $requestorName,
            'action' => 'ticket_created'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'new_support_ticket',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send escalated issue notification to admin
     */
    public function sendEscalatedIssueNotification(
        int $adminUserId,
        string $adminName,
        string $issueId,
        string $description,
        string $escalationReason
    ): bool {
        $notificationData = [
            'title' => 'Escalated Issue',
            'body' => "Hi {$adminName}! Issue #{$issueId} has been escalated. Reason: {$escalationReason}. Description: {$description}"
        ];

        $data = [
            'type' => 'escalated_issue',
            'issue_id' => $issueId,
            'description' => $description,
            'escalation_reason' => $escalationReason,
            'action' => 'issue_escalated'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'escalated_issue',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send critical bug reported notification to admin
     */
    public function sendCriticalBugReportedNotification(
        int $adminUserId,
        string $adminName,
        string $bugId,
        string $description,
        string $severity
    ): bool {
        $notificationData = [
            'title' => 'Critical Bug Reported',
            'body' => "Hi {$adminName}! CRITICAL BUG #{$bugId} reported. Severity: {$severity}. Description: {$description}"
        ];

        $data = [
            'type' => 'critical_bug_reported',
            'bug_id' => $bugId,
            'description' => $description,
            'severity' => $severity,
            'action' => 'bug_reported'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'critical_bug_reported',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send privacy policy violation notification to admin
     */
    public function sendPrivacyPolicyViolationNotification(
        int $adminUserId,
        string $adminName,
        string $violationType,
        string $details,
        string $violatorName
    ): bool {
        $notificationData = [
            'title' => 'Privacy Policy Violation',
            'body' => "Hi {$adminName}! Privacy policy violation by {$violatorName}. Type: {$violationType}. Details: {$details}"
        ];

        $data = [
            'type' => 'privacy_policy_violation',
            'violation_type' => $violationType,
            'details' => $details,
            'violator_name' => $violatorName,
            'action' => 'privacy_violation'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'privacy_policy_violation',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send terms of service violation notification to admin
     */
    public function sendTermsOfServiceViolationNotification(
        int $adminUserId,
        string $adminName,
        string $violationType,
        string $details,
        string $violatorName
    ): bool {
        $notificationData = [
            'title' => 'Terms of Service Violation',
            'body' => "Hi {$adminName}! Terms of service violation by {$violatorName}. Type: {$violationType}. Details: {$details}"
        ];

        $data = [
            'type' => 'terms_of_service_violation',
            'violation_type' => $violationType,
            'details' => $details,
            'violator_name' => $violatorName,
            'action' => 'tos_violation'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'terms_of_service_violation',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send GDPR compliance alert to admin
     */
    public function sendGdprComplianceAlert(
        int $adminUserId,
        string $adminName,
        string $alertType,
        string $details
    ): bool {
        $notificationData = [
            'title' => 'GDPR Compliance Alert',
            'body' => "Hi {$adminName}! GDPR compliance alert. Type: {$alertType}. Details: {$details}"
        ];

        $data = [
            'type' => 'gdpr_compliance_alert',
            'alert_type' => $alertType,
            'details' => $details,
            'action' => 'gdpr_alert'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'gdpr_compliance_alert',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }

    /**
     * Send legal request received notification to admin (final copy to ensure it's included)
     */
    public function sendLegalRequestReceivedNotification(
        int $adminUserId,
        string $adminName,
        string $requestType,
        string $details
    ): bool {
        $notificationData = [
            'title' => 'Legal Request Received',
            'body' => "Hi {$adminName}! Legal request received. Type: {$requestType}. Details: {$details}"
        ];

        $data = [
            'type' => 'legal_request_received',
            'request_type' => $requestType,
            'details' => $details,
            'action' => 'legal_request_received'
        ];

        // Save to database
        $this->notificationRepository->create([
            'user_id' => $adminUserId,
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'type' => 'legal_request_received',
            'data' => $data
        ]);

        return $this->notificationService->sendToUser($adminUserId, $notificationData, $data);
    }
}