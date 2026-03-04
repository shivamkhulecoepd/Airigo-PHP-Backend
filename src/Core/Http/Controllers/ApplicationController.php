<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\ApplicationRepository;
use App\Repositories\JobRepository;
use App\Repositories\JobseekerRepository;
use Firebase\FirebaseStorageService;

class ApplicationController extends BaseController
{
    private ApplicationRepository $applicationRepository;
    private JobRepository $jobRepository;
    private JobseekerRepository $jobseekerRepository;
    private FirebaseStorageService $firebaseStorage;

    public function __construct()
    {
        parent::__construct();
        $this->applicationRepository = new ApplicationRepository();
        $this->jobRepository = new JobRepository();
        $this->jobseekerRepository = new JobseekerRepository();
        $this->firebaseStorage = new FirebaseStorageService();
    }

    public function apply(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'jobseeker') {
            return ResponseBuilder::forbidden(['message' => 'Only jobseekers can apply for jobs']);
        }

        $data = $this->getRequestBody($request);
        $jobId = (int) ($data['job_id'] ?? 0);

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Job ID is required']);
        }

        // Check if job exists and is active
        $job = $this->jobRepository->findById($jobId);
        if (!$job) {
            return ResponseBuilder::notFound(['message' => 'Job not found']);
        }

        if (!$job['is_active'] || $job['approval_status'] !== 'approved') {
            return ResponseBuilder::forbidden(['message' => 'Job is not available for applications']);
        }

        // Check if user has already applied for this job
        if ($this->applicationRepository->hasApplied($jobId, $user['id'])) {
            return ResponseBuilder::conflict(['message' => 'You have already applied for this job']);
        }

        try {
            // Prepare application data
            $applicationData = [
                'job_id' => $jobId,
                'jobseeker_user_id' => $user['id'],
                'cover_letter' => $data['cover_letter'] ?? null,
                'status' => 'pending'
            ];

            // Handle resume if provided
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['resume']) && $uploadedFiles['resume']->getError() === UPLOAD_ERR_OK) {
                $resumeFile = $uploadedFiles['resume'];
                
                // Validate file type and size
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($resumeFile->getClientMediaType(), $allowedTypes)) {
                    return ResponseBuilder::badRequest(['message' => 'Invalid resume file type. Only PDF, DOC, and DOCX files are allowed']);
                }

                if ($resumeFile->getSize() > $maxFileSize) {
                    return ResponseBuilder::badRequest(['message' => 'Resume file size exceeds 5MB limit']);
                }

                // Move uploaded file to temporary location
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $resumeFile->getClientFilename();
                $resumeFile->moveTo($tempPath);

                // Generate unique filename
                $extension = pathinfo($resumeFile->getClientFilename(), PATHINFO_EXTENSION);
                $uniqueFilename = 'application_resume_' . $user['id'] . '_' . $jobId . '_' . time() . '.' . $extension;
                
                // Upload to Firebase Storage
                $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

                if (!$fileUrl) {
                    return ResponseBuilder::serverError(['message' => 'Failed to upload resume to storage']);
                }

                // Add resume URL to application data
                $applicationData['resume_url'] = $fileUrl;

                // Clean up temp file
                unlink($tempPath);
            } else {
                // Use existing resume from jobseeker profile if available
                $jobseeker = $this->jobseekerRepository->findByUserId($user['id']);
                if ($jobseeker && !empty($jobseeker['resume_url'])) {
                    $applicationData['resume_url'] = $jobseeker['resume_url'];
                }
            }

            $applicationId = $this->applicationRepository->create($applicationData);

            if (!$applicationId) {
                return ResponseBuilder::serverError(['message' => 'Failed to submit application']);
            }

            // Fetch the created application
            $application = $this->applicationRepository->findById($applicationId);

            return ResponseBuilder::created([
                'message' => 'Application submitted successfully',
                'application' => $application
            ]);
        } catch (\Exception $e) {
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
            $applications = $this->applicationRepository->getApplicationsWithJobDetails($filters, $limit, ($page - 1) * $limit);
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
                // Get overall stats for recruiter (could be expanded to show stats for all their jobs)
                $stats = $this->applicationRepository->getStats();
                
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