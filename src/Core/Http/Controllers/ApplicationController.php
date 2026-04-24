<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\ApplicationRepository;
use App\Repositories\JobRepository;
use App\Repositories\JobseekerRepository;
use App\Repositories\RecruiterRepository;
use Firebase\FirebaseStorageService;
use Firebase\FirebaseNotificationService;

class ApplicationController extends BaseController
{
    private ApplicationRepository $applicationRepository;
    private JobRepository $jobRepository;
    private JobseekerRepository $jobseekerRepository;
    private RecruiterRepository $recruiterRepository;
    private FirebaseStorageService $firebaseStorage;
    private FirebaseNotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->applicationRepository = new ApplicationRepository();
        $this->jobRepository = new JobRepository();
        $this->jobseekerRepository = new JobseekerRepository();
        $this->recruiterRepository = new RecruiterRepository();
        $this->firebaseStorage = new FirebaseStorageService();
        $this->notificationService = new FirebaseNotificationService();
    }

    public function apply(ServerRequestInterface $request)
    {
        error_log("========== APPLICATION SUBMISSION STARTED ==========");
        $user = $this->getUser($request);
        if (!$user) {
            error_log("ApplicationController: User not authenticated");
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        error_log("ApplicationController: User ID: " . $user['id'] . ", User Type: " . $user['user_type']);

        if ($user['user_type'] !== 'jobseeker') {
            error_log("ApplicationController: Forbidden - Only jobseekers can apply");
            return ResponseBuilder::forbidden(['message' => 'Only jobseekers can apply for jobs']);
        }

        $data = $this->getRequestBody($request);
        $jobId = (int) ($data['job_id'] ?? 0);
        error_log("ApplicationController: Job ID from request: " . $jobId);

        if ($jobId <= 0) {
            error_log("ApplicationController: Bad Request - Job ID is missing or invalid");
            return ResponseBuilder::badRequest(['message' => 'Job ID is required']);
        }

        // Check if job exists and is active
        $job = $this->jobRepository->findById($jobId);
        if (!$job) {
            error_log("ApplicationController: Not Found - Job ID " . $jobId . " does not exist");
            return ResponseBuilder::notFound(['message' => 'Job not found']);
        }

        error_log("ApplicationController: Job found: " . $job['designation'] . " at " . $job['company_name']);
        error_log("ApplicationController: Job Status: is_active=" . ($job['is_active'] ? 'true' : 'false') . ", approval_status=" . $job['approval_status']);

        if (!$job['is_active'] || $job['approval_status'] !== 'approved') {
            error_log("ApplicationController: Forbidden - Job is not available for applications");
            return ResponseBuilder::forbidden(['message' => 'Job is not available for applications']);
        }

        // Check if user has already applied for this job
        if ($this->applicationRepository->hasApplied($jobId, $user['id'])) {
            error_log("ApplicationController: Conflict - User already applied for Job ID " . $jobId);
            return ResponseBuilder::conflict(['message' => 'You have already applied for this job']);
        }

        try {
            // Prepare application data
            $applicationData = [
                'job_id' => $jobId,
                'recruiter_user_id' => $job['recruiter_user_id'],
                'jobseeker_user_id' => $user['id'],
                'cover_letter' => $data['cover_letter'] ?? null,
                'status' => 'pending'
            ];

            error_log("ApplicationController: Preparing application data. Recruiter ID: " . $job['recruiter_user_id']);

            // Handle resume if provided
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['resume']) && $uploadedFiles['resume']->getError() === UPLOAD_ERR_OK) {
                $resumeFile = $uploadedFiles['resume'];
                error_log("ApplicationController: Resume file uploaded: " . $resumeFile->getClientFilename() . " (" . $resumeFile->getClientMediaType() . ")");
                
                // Validate file type and size
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($resumeFile->getClientMediaType(), $allowedTypes)) {
                    error_log("ApplicationController: Invalid file type: " . $resumeFile->getClientMediaType());
                    return ResponseBuilder::badRequest(['message' => 'Invalid resume file type. Only PDF, DOC, and DOCX files are allowed']);
                }

                if ($resumeFile->getSize() > $maxFileSize) {
                    error_log("ApplicationController: File size too large: " . $resumeFile->getSize());
                    return ResponseBuilder::badRequest(['message' => 'Resume file size exceeds 5MB limit']);
                }

                // Move uploaded file to temporary location
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $resumeFile->getClientFilename();
                $resumeFile->moveTo($tempPath);
                error_log("ApplicationController: File moved to temp: " . $tempPath);

                // Generate unique filename
                $extension = pathinfo($resumeFile->getClientFilename(), PATHINFO_EXTENSION);
                $uniqueFilename = 'application_resume_' . $user['id'] . '_' . $jobId . '_' . time() . '.' . $extension;
                
                // Upload to Firebase Storage
                error_log("ApplicationController: Uploading to Firebase Storage as: " . $uniqueFilename);
                $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

                if (!$fileUrl) {
                    error_log("ApplicationController: Firebase upload failed");
                    return ResponseBuilder::serverError(['message' => 'Failed to upload resume to storage']);
                }

                error_log("ApplicationController: Resume uploaded successfully: " . $fileUrl);

                // Add resume URL to application data
                $applicationData['resume_url'] = $fileUrl;

                // Clean up temp file
                unlink($tempPath);
            } else {
                error_log("ApplicationController: No resume file uploaded, checking jobseeker profile");
                // Use existing resume from jobseeker profile if available
                $jobseeker = $this->jobseekerRepository->findByUserId($user['id']);
                if ($jobseeker && !empty($jobseeker['resume_url'])) {
                    error_log("ApplicationController: Found resume in profile: " . $jobseeker['resume_url']);
                    $applicationData['resume_url'] = $jobseeker['resume_url'];
                } else {
                    error_log("ApplicationController: No resume found in profile or upload");
                }
            }

            error_log("ApplicationController: Creating application record in database");
            $applicationId = $this->applicationRepository->create($applicationData);

            if (!$applicationId) {
                error_log("ApplicationController: Database insertion failed");
                return ResponseBuilder::serverError(['message' => 'Failed to submit application']);
            }

            error_log("ApplicationController: Application record created with ID: " . $applicationId);

            // Fetch the created application
            $application = $this->applicationRepository->findById($applicationId);

            // Send notification to recruiter about new application
            try {
                error_log("ApplicationController: Sending notification to recruiter");
                $jobseeker = $this->jobseekerRepository->findByUserId($user['id']);
                $jobseekerName = $jobseeker['name'] ?? 'Jobseeker';
                $recruiter = $this->recruiterRepository->findByUserId($job['recruiter_user_id']);
                
                if ($recruiter) {
                    $recruiterName = $recruiter['company_name'] ?? 'Recruiter';
                    
                    $this->notificationService->sendNewApplicationNotification(
                        $job['recruiter_user_id'],
                        $recruiterName,
                        $job['designation'],
                        $jobseekerName,
                        $applicationId
                    );
                    error_log("ApplicationController: Notification sent to Recruiter ID: " . $job['recruiter_user_id']);
                } else {
                    error_log("ApplicationController: Recruiter not found, skipping notification");
                }
            } catch (\Exception $e) {
                error_log("ApplicationController: Failed to send new application notification: " . $e->getMessage());
            }

            error_log("========== APPLICATION SUBMISSION COMPLETED SUCCESSFULLY ==========");
            return ResponseBuilder::created([
                'message' => 'Application submitted successfully',
                'application' => $application
            ]);
        } catch (\Exception $e) {
            error_log("ApplicationController: EXCEPTION - " . $e->getMessage());
            error_log("ApplicationController: Stack trace: " . $e->getTraceAsString());
            return ResponseBuilder::serverError([
                'message' => 'Failed to submit application',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getMyApplications(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'jobseeker') {
            return ResponseBuilder::forbidden(['message' => 'Only jobseekers can view their applications']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');

        $filters = ['jobseeker_user_id' => $user['id']];
        if ($status) {
            $filters['status'] = $status;
        }

        try {
            // Use optimized query that only fetches job and recruiter details (not jobseeker's own details)
            $applications = $this->applicationRepository->getApplicationsForJobseeker($filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->applicationRepository->count(['jobseeker_user_id' => $user['id']] + ($status ? ['status' => $status] : []));

            return ResponseBuilder::ok([
                'applications' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch applications',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getApplicationsForRecruiter(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can view their applications']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');

        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }

        try {
            $applications = $this->applicationRepository->getApplicationsForRecruiter($user['id'], $filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->applicationRepository->countForRecruiter($user['id'], $filters);

            return ResponseBuilder::ok([
                'applications' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch applications',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getApplicationsForJob(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can view job applications']);
        }

        $jobId = (int) $request->getAttribute('jobId');
        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        // Check if the job belongs to the recruiter
        $job = $this->jobRepository->findById($jobId);
        if (!$job || $job['recruiter_user_id'] != $user['id']) {
            return ResponseBuilder::forbidden(['message' => 'You can only view applications for your own jobs']);
        }

        $filters = ['job_id' => $jobId];
        if ($status) {
            $filters['status'] = $status;
        }

        try {
            $applications = $this->applicationRepository->getApplicationsWithJobDetails($filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->applicationRepository->count(['job_id' => $jobId] + ($status ? ['status' => $status] : []));

            return ResponseBuilder::ok([
                'applications' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch applications',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can update application status']);
        }

        $applicationId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $newStatus = $data['status'] ?? null;

        if ($applicationId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid application ID']);
        }

        if (!$newStatus || !in_array($newStatus, ['pending', 'shortlisted', 'rejected', 'accepted'])) {
            return ResponseBuilder::badRequest(['message' => 'Valid status is required: pending, shortlisted, rejected, or accepted']);
        }

        try {
            $application = $this->applicationRepository->getApplicationWithDetails($applicationId);

            if (!$application) {
                return ResponseBuilder::notFound(['message' => 'Application not found']);
            }

            // Check if the application is for a job owned by the recruiter
            $job = $this->jobRepository->findById($application['job_id']);
            if (!$job || $job['recruiter_user_id'] != $user['id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only update status for applications to your own jobs']);
            }

            $result = $this->applicationRepository->update($applicationId, [
                'status' => $newStatus
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update application status']);
            }

            // Fetch updated application
            $updatedApplication = $this->applicationRepository->getApplicationWithDetails($applicationId);

            // Send status update notification to jobseeker
            try {
                $jobseeker = $this->jobseekerRepository->findByUserId($application['jobseeker_user_id']);
                $jobseekerName = $jobseeker['name'] ?? 'Jobseeker';
                
                $this->notificationService->sendApplicationStatusNotification(
                    $application['jobseeker_user_id'],
                    $jobseekerName,
                    $job['designation'],
                    $newStatus,
                    $job['company_name']
                );
            } catch (\Exception $e) {
                error_log("Failed to send application status notification: " . $e->getMessage());
            }

            return ResponseBuilder::ok([
                'message' => 'Application status updated successfully',
                'application' => $updatedApplication
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update application status',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $applicationId = (int) $request->getAttribute('id');

        if ($applicationId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid application ID']);
        }

        try {
            $application = $this->applicationRepository->getApplicationWithDetails($applicationId);

            if (!$application) {
                return ResponseBuilder::notFound(['message' => 'Application not found']);
            }

            // Allow jobseeker to delete their own application or recruiter to delete application for their job
            if (($user['user_type'] === 'jobseeker' && $application['jobseeker_user_id'] != $user['id']) ||
                ($user['user_type'] === 'recruiter' && $application['recruiter_user_id'] != $user['id'])) {
                return ResponseBuilder::forbidden(['message' => 'You can only delete your own application']);
            }

            $result = $this->applicationRepository->delete($applicationId);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to delete application']);
            }

            return ResponseBuilder::ok([
                'message' => 'Application deleted successfully'
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to delete application',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getApplicationStats(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            if ($user['user_type'] === 'jobseeker') {
                // Get stats for jobseeker's applications
                $stats = $this->applicationRepository->getApplicationStatsForJobseeker($user['id']);
                
                return ResponseBuilder::ok([
                    'stats' => $stats,
                    'user_type' => 'jobseeker'
                ]);
            } elseif ($user['user_type'] === 'recruiter') {
                // Get overall stats for recruiter
                $stats = $this->applicationRepository->getApplicationStatsForRecruiter($user['id']);
                
                return ResponseBuilder::ok([
                    'stats' => $stats,
                    'user_type' => 'recruiter'
                ]);
            } else {
                return ResponseBuilder::forbidden(['message' => 'Invalid user type']);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch application statistics',
                'error' => $e->getMessage()
            ]);
        }
    }
}