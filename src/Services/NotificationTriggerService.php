<?php

namespace App\Services;

use App\Core\Http\Controllers\NotificationController;

/**
 * NotificationTriggerService - Central service for triggering all notification types
 * This service ensures notifications are sent at the right time during user actions
 */
class NotificationTriggerService
{
    private NotificationController $notificationController;

    public function __construct()
    {
        $this->notificationController = new NotificationController();
    }

    // ========================================
    // JOBSEEKER NOTIFICATIONS
    // ========================================

    /**
     * Trigger notification when new jobs match jobseeker's profile
     */
    public function triggerNewJobMatch(int $jobseekerUserId, string $jobseekerName, string $jobTitle, string $companyName, int $jobId): bool
    {
        try {
            return $this->notificationController->sendJobMatchingNotification(
                $jobseekerUserId,
                $jobseekerName,
                $jobTitle,
                $companyName,
                $jobId
            );
        } catch (\Exception $e) {
            error_log("Failed to send new job match notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when recruiter views jobseeker's profile
     */
    public function triggerProfileViewed(int $jobseekerUserId, string $jobseekerName, string $recruiterCompany, string $recruiterName): bool
    {
        try {
            return $this->notificationController->sendProfileViewNotification(
                $jobseekerUserId,
                $jobseekerName,
                $recruiterCompany,
                $recruiterName
            );
        } catch (\Exception $e) {
            error_log("Failed to send profile viewed notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when jobseeker's account is verified
     */
    public function triggerAccountVerified(int $jobseekerUserId, string $jobseekerName): bool
    {
        try {
            return $this->notificationController->sendAccountVerificationNotification(
                $jobseekerUserId,
                $jobseekerName,
                'verified'
            );
        } catch (\Exception $e) {
            error_log("Failed to send account verified notification: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // RECRUITER NOTIFICATIONS
    // ========================================

    /**
     * Trigger notification when new candidate applies to job
     */
    public function triggerNewApplication(int $recruiterUserId, string $recruiterName, string $jobTitle, string $applicantName, int $applicationId): bool
    {
        try {
            return $this->notificationController->sendNewApplicationNotification(
                $recruiterUserId,
                $recruiterName,
                $jobTitle,
                $applicantName,
                $applicationId
            );
        } catch (\Exception $e) {
            error_log("Failed to send new application notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when job is approved by admin
     */
    public function triggerJobApproved(int $recruiterUserId, string $recruiterName, string $jobTitle): bool
    {
        try {
            return $this->notificationController->sendJobApprovalNotification(
                $recruiterUserId,
                $recruiterName,
                $jobTitle,
                'approved'
            );
        } catch (\Exception $e) {
            error_log("Failed to send job approved notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when job is rejected by admin
     */
    public function triggerJobRejected(int $recruiterUserId, string $recruiterName, string $jobTitle, string $reason): bool
    {
        try {
            return $this->notificationController->sendJobApprovalNotification(
                $recruiterUserId,
                $recruiterName,
                $jobTitle,
                'rejected',
                $reason
            );
        } catch (\Exception $e) {
            error_log("Failed to send job rejected notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when high-profile candidate applies
     */
    public function triggerHighProfileCandidateApplied(int $recruiterUserId, string $recruiterName, string $jobTitle, string $candidateName, string $experienceLevel): bool
    {
        try {
            return $this->notificationController->sendHighProfileCandidateAppliedNotification(
                $recruiterUserId,
                $recruiterName,
                $jobTitle,
                $candidateName,
                $experienceLevel
            );
        } catch (\Exception $e) {
            error_log("Failed to send high-profile candidate notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when recruiter account is approved
     */
    public function triggerRecruiterApproved(int $recruiterUserId, string $recruiterName): bool
    {
        try {
            return $this->notificationController->sendRecruiterApprovalNotification(
                $recruiterUserId,
                $recruiterName,
                'approved'
            );
        } catch (\Exception $e) {
            error_log("Failed to send recruiter approved notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when recruiter account is rejected
     */
    public function triggerRecruiterRejected(int $recruiterUserId, string $recruiterName, string $reason): bool
    {
        try {
            return $this->notificationController->sendRecruiterApprovalNotification(
                $recruiterUserId,
                $recruiterName,
                'rejected',
                $reason
            );
        } catch (\Exception $e) {
            error_log("Failed to send recruiter rejected notification: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // ADMIN NOTIFICATIONS
    // ========================================

    /**
     * Trigger notification when new job is posted and needs review
     */
    public function triggerNewJobPosted(int $adminUserId, string $adminName, string $jobTitle, string $companyName, string $recruiterName): bool
    {
        try {
            return $this->notificationController->sendNewJobPostedNotification(
                $adminUserId,
                $adminName,
                $jobTitle,
                $companyName,
                $recruiterName
            );
        } catch (\Exception $e) {
            error_log("Failed to send new job posted notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when job is reported
     */
    public function triggerJobReported(int $adminUserId, string $adminName, string $jobTitle, string $reportedBy, string $reason): bool
    {
        try {
            return $this->notificationController->sendJobReportFiledNotification(
                $adminUserId,
                $adminName,
                $jobTitle,
                $reportedBy,
                $reason
            );
        } catch (\Exception $e) {
            error_log("Failed to send job reported notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when suspicious activity is detected
     */
    public function triggerSuspiciousActivity(int $adminUserId, string $adminName, string $userName, string $userType, string $activityDescription): bool
    {
        try {
            return $this->notificationController->sendSuspiciousActivityDetectedNotification(
                $adminUserId,
                $adminName,
                $userName,
                $userType,
                $activityDescription
            );
        } catch (\Exception $e) {
            error_log("Failed to send suspicious activity notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification when user is reported
     */
    public function triggerUserReported(int $adminUserId, string $adminName, string $reportedUserName, string $reporterUserName, string $reason): bool
    {
        try {
            return $this->notificationController->sendUserReportedNotification(
                $adminUserId,
                $adminName,
                $reportedUserName,
                $reporterUserName,
                $reason
            );
        } catch (\Exception $e) {
            error_log("Failed to send user reported notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger platform update notification to all users
     */
    public function triggerPlatformUpdate(int $userId, string $userName, string $featureName): bool
    {
        try {
            return $this->notificationController->sendPlatformUpdateNotification(
                $userId,
                $userName,
                $featureName
            );
        } catch (\Exception $e) {
            error_log("Failed to send platform update notification: " . $e->getMessage());
            return false;
        }
    }
}
