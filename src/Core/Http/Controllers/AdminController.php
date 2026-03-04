<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\UserRepository;
use App\Repositories\JobRepository;
use App\Repositories\RecruiterRepository;
use App\Repositories\JobseekerRepository;
use App\Repositories\ApplicationRepository;
use App\Repositories\IssueReportRepository;

class AdminController extends BaseController
{
    private UserRepository $userRepository;
    private JobRepository $jobRepository;
    private RecruiterRepository $recruiterRepository;
    private JobseekerRepository $jobseekerRepository;
    private ApplicationRepository $applicationRepository;
    private IssueReportRepository $issueReportRepository;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->jobRepository = new JobRepository();
        $this->recruiterRepository = new RecruiterRepository();
        $this->jobseekerRepository = new JobseekerRepository();
        $this->applicationRepository = new ApplicationRepository();
        $this->issueReportRepository = new IssueReportRepository();
    }

    public function getStats(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        try {
            // Get user stats
            $userStats = [
                'total_users' => $this->userRepository->count(),
                'total_jobseekers' => $this->userRepository->getTotalCountByUserType('jobseeker'),
                'total_recruiters' => $this->userRepository->getTotalCountByUserType('recruiter'),
                'active_users' => $this->userRepository->getTotalCountByStatus('active'),
                'inactive_users' => $this->userRepository->getTotalCountByStatus('inactive'),
                'suspended_users' => $this->userRepository->getTotalCountByStatus('suspended')
            ];

            // Get job stats
            $jobStats = $this->jobRepository->getStats();

            // Get recruiter stats
            $recruiterStats = $this->recruiterRepository->getRecruiterStats();

            // Get application stats
            $applicationStats = (new \App\Repositories\ApplicationRepository())->getStats();

            return ResponseBuilder::ok([
                'stats' => [
                    'users' => $userStats,
                    'jobs' => $jobStats,
                    'recruiters' => $recruiterStats,
                    'applications' => $applicationStats
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getUsers(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $userType = $this->getQueryParam($request, 'user_type');
        $status = $this->getQueryParam($request, 'status');
        $search = $this->getQueryParam($request, 'search');

        $filters = [];
        if ($userType) $filters['user_type'] = $userType;
        if ($status) $filters['status'] = $status;

        try {
            $result = $this->userRepository->getPaginated($page, $limit, $filters);

            // If search term is provided, filter the results
            if ($search) {
                $filteredData = [];
                foreach ($result['data'] as $userRecord) {
                    if (stripos($userRecord['email'], $search) !== false) {
                        $filteredData[] = $userRecord;
                    }
                }
                $result['data'] = $filteredData;
                $result['pagination']['total'] = count($filteredData);
            }

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getPendingJobs(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);

        try {
            $pendingJobs = $this->jobRepository->getPendingJobs([], $limit, ($page - 1) * $limit);
            $totalCount = $this->jobRepository->count(['approval_status' => 'pending']);

            return ResponseBuilder::ok([
                'jobs' => $pendingJobs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch pending jobs',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function approveJob(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $jobId = (int) $request->getAttribute('id');

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            if ($job['approval_status'] === 'approved') {
                return ResponseBuilder::badRequest(['message' => 'Job is already approved']);
            }

            $result = $this->jobRepository->update($jobId, [
                'approval_status' => 'approved'
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to approve job']);
            }

            // Fetch updated job
            $updatedJob = $this->jobRepository->findById($jobId);

            return ResponseBuilder::ok([
                'message' => 'Job approved successfully',
                'job' => $updatedJob
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to approve job',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateUserStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $userId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $newStatus = $data['status'] ?? null;

        if ($userId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid user ID']);
        }

        if (!$newStatus || !in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            return ResponseBuilder::badRequest(['message' => 'Valid status is required: active, inactive, or suspended']);
        }

        try {
            $targetUser = $this->userRepository->findById($userId);

            if (!$targetUser) {
                return ResponseBuilder::notFound(['message' => 'User not found']);
            }

            if ($targetUser['status'] === $newStatus) {
                return ResponseBuilder::badRequest(['message' => 'User already has this status']);
            }

            $result = $this->userRepository->update($userId, [
                'status' => $newStatus
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update user status']);
            }

            // Fetch updated user
            $updatedUser = $this->userRepository->findById($userId);

            return ResponseBuilder::ok([
                'message' => 'User status updated successfully',
                'user' => $updatedUser
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function approveRecruiter(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $userId = (int) $request->getAttribute('id');

        if ($userId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid user ID']);
        }

        try {
            $targetUser = $this->userRepository->findById($userId);

            if (!$targetUser) {
                return ResponseBuilder::notFound(['message' => 'User not found']);
            }

            if ($targetUser['user_type'] !== 'recruiter') {
                return ResponseBuilder::badRequest(['message' => 'User is not a recruiter']);
            }

            $result = $this->recruiterRepository->approveRecruiter($userId, $user['id']);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to approve recruiter']);
            }

            // Fetch updated recruiter
            $updatedRecruiter = $this->recruiterRepository->findByUserId($userId);

            return ResponseBuilder::ok([
                'message' => 'Recruiter approved successfully',
                'recruiter' => $updatedRecruiter
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to approve recruiter',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function rejectRecruiter(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $userId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $rejectionReason = $data['rejection_reason'] ?? 'Not specified';

        if ($userId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid user ID']);
        }

        try {
            $targetUser = $this->userRepository->findById($userId);

            if (!$targetUser) {
                return ResponseBuilder::notFound(['message' => 'User not found']);
            }

            if ($targetUser['user_type'] !== 'recruiter') {
                return ResponseBuilder::badRequest(['message' => 'User is not a recruiter']);
            }

            $result = $this->recruiterRepository->rejectRecruiter($userId, $rejectionReason, $user['id']);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to reject recruiter']);
            }

            // Fetch updated recruiter
            $updatedRecruiter = $this->recruiterRepository->findByUserId($userId);

            return ResponseBuilder::ok([
                'message' => 'Recruiter rejected successfully',
                'recruiter' => $updatedRecruiter
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to reject recruiter',
                'error' => $e->getMessage()
            ]);
        }
    }

    // Enhanced Admin Module Functions

    public function getJobseekers(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $search = $this->getQueryParam($request, 'search');
        $location = $this->getQueryParam($request, 'location');
        $experience = $this->getQueryParam($request, 'experience');

        try {
            $filters = [];
            if ($location) $filters['location'] = $location;
            if ($experience) $filters['experience'] = $experience;

            $result = $this->jobseekerRepository->getPaginated($page, $limit, $filters);

            // Apply search if provided
            if ($search) {
                $filteredData = [];
                foreach ($result['data'] as $jobseeker) {
                    if (stripos($jobseeker['name'] ?? '', $search) !== false || 
                        stripos($jobseeker['email'] ?? '', $search) !== false) {
                        $filteredData[] = $jobseeker;
                    }
                }
                $result['data'] = $filteredData;
                $result['pagination']['total'] = count($filteredData);
            }

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch jobseekers',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getApplications(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');
        $jobId = $this->getQueryParam($request, 'job_id');

        try {
            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($jobId) $filters['job_id'] = $jobId;

            $result = $this->applicationRepository->getPaginated($page, $limit, $filters);

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch applications',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateApplicationStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
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
            $application = $this->applicationRepository->findById($applicationId);

            if (!$application) {
                return ResponseBuilder::notFound(['message' => 'Application not found']);
            }

            if ($application['status'] === $newStatus) {
                return ResponseBuilder::badRequest(['message' => 'Application already has this status']);
            }

            $result = $this->applicationRepository->update($applicationId, [
                'status' => $newStatus
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update application status']);
            }

            // Fetch updated application
            $updatedApplication = $this->applicationRepository->findById($applicationId);

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

    public function getJobs(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');
        $approvalStatus = $this->getQueryParam($request, 'approval_status');
        $category = $this->getQueryParam($request, 'category');
        $location = $this->getQueryParam($request, 'location');

        try {
            $filters = [];
            if ($status) $filters['is_active'] = $status === 'active' ? 1 : 0;
            if ($approvalStatus) $filters['approval_status'] = $approvalStatus;
            if ($category) $filters['category'] = $category;
            if ($location) $filters['location'] = $location;

            $result = $this->jobRepository->getPaginated($page, $limit, $filters);

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch jobs',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateJobStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $jobId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $isActive = $data['is_active'] ?? null;

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        if (!is_bool($isActive) && !is_null($isActive)) {
            return ResponseBuilder::badRequest(['message' => 'Valid is_active value is required']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            $result = $this->jobRepository->update($jobId, [
                'is_active' => $isActive
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update job status']);
            }

            // Fetch updated job
            $updatedJob = $this->jobRepository->findById($jobId);

            return ResponseBuilder::ok([
                'message' => 'Job status updated successfully',
                'job' => $updatedJob
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update job status',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteJob(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $jobId = (int) $request->getAttribute('id');

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            $result = $this->jobRepository->delete($jobId);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to delete job']);
            }

            return ResponseBuilder::ok([
                'message' => 'Job deleted successfully'
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to delete job',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getRecruiters(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $approvalStatus = $this->getQueryParam($request, 'approval_status');
        $search = $this->getQueryParam($request, 'search');
        $location = $this->getQueryParam($request, 'location');

        try {
            $filters = [];
            if ($approvalStatus) $filters['approval_status'] = $approvalStatus;
            if ($location) $filters['location'] = $location;

            $result = $this->recruiterRepository->getPaginated($page, $limit, $filters);

            // Apply search if provided
            if ($search) {
                $filteredData = [];
                foreach ($result['data'] as $recruiter) {
                    if (stripos($recruiter['company_name'] ?? '', $search) !== false || 
                        stripos($recruiter['email'] ?? '', $search) !== false) {
                        $filteredData[] = $recruiter;
                    }
                }
                $result['data'] = $filteredData;
                $result['pagination']['total'] = count($filteredData);
            }

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch recruiters',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getIssueReports(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $status = $this->getQueryParam($request, 'status');
        $type = $this->getQueryParam($request, 'type');
        $userType = $this->getQueryParam($request, 'user_type');

        try {
            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($type) $filters['type'] = $type;
            if ($userType) $filters['user_type'] = $userType;

            $result = $this->issueReportRepository->getPaginated($page, $limit, $filters);

            return ResponseBuilder::ok($result);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch issue reports',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateIssueReportStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $issueId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $newStatus = $data['status'] ?? null;
        $adminResponse = $data['admin_response'] ?? null;

        if ($issueId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid issue ID']);
        }

        if (!$newStatus || !in_array($newStatus, ['pending', 'in_progress', 'resolved'])) {
            return ResponseBuilder::badRequest(['message' => 'Valid status is required: pending, in_progress, or resolved']);
        }

        try {
            $issue = $this->issueReportRepository->findById($issueId);

            if (!$issue) {
                return ResponseBuilder::notFound(['message' => 'Issue report not found']);
            }

            $updateData = ['status' => $newStatus];
            if ($adminResponse) {
                $updateData['admin_response'] = $adminResponse;
            }

            $result = $this->issueReportRepository->update($issueId, $updateData);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update issue report status']);
            }

            // Fetch updated issue
            $updatedIssue = $this->issueReportRepository->findById($issueId);

            return ResponseBuilder::ok([
                'message' => 'Issue report status updated successfully',
                'issue' => $updatedIssue
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update issue report status',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getAdminStats(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        try {
            // Get comprehensive statistics
            $stats = [
                'users' => [
                    'total' => $this->userRepository->count(),
                    'active' => $this->userRepository->getTotalCountByStatus('active'),
                    'inactive' => $this->userRepository->getTotalCountByStatus('inactive'),
                    'suspended' => $this->userRepository->getTotalCountByStatus('suspended'),
                    'jobseekers' => $this->userRepository->getTotalCountByUserType('jobseeker'),
                    'recruiters' => $this->userRepository->getTotalCountByUserType('recruiter')
                ],
                'jobs' => [
                    'total' => $this->jobRepository->count(),
                    'active' => $this->jobRepository->count(['is_active' => 1]),
                    'inactive' => $this->jobRepository->count(['is_active' => 0]),
                    'pending_approval' => $this->jobRepository->count(['approval_status' => 'pending']),
                    'approved' => $this->jobRepository->count(['approval_status' => 'approved']),
                    'rejected' => $this->jobRepository->count(['approval_status' => 'rejected'])
                ],
                'applications' => [
                    'total' => $this->applicationRepository->count(),
                    'pending' => $this->applicationRepository->count(['status' => 'pending']),
                    'shortlisted' => $this->applicationRepository->count(['status' => 'shortlisted']),
                    'accepted' => $this->applicationRepository->count(['status' => 'accepted']),
                    'rejected' => $this->applicationRepository->count(['status' => 'rejected'])
                ],
                'recruiters' => [
                    'total' => $this->recruiterRepository->count(),
                    'pending_approval' => $this->recruiterRepository->count(['approval_status' => 'pending']),
                    'approved' => $this->recruiterRepository->count(['approval_status' => 'approved']),
                    'rejected' => $this->recruiterRepository->count(['approval_status' => 'rejected'])
                ],
                'issues_reports' => [
                    'total' => $this->issueReportRepository->count(),
                    'pending' => $this->issueReportRepository->count(['status' => 'pending']),
                    'in_progress' => $this->issueReportRepository->count(['status' => 'in_progress']),
                    'resolved' => $this->issueReportRepository->count(['status' => 'resolved'])
                ]
            ];

            return ResponseBuilder::ok([
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch admin statistics',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function searchAll(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $query = $this->getQueryParam($request, 'q');
        $limit = (int) $this->getQueryParam($request, 'limit', 10);

        if (!$query) {
            return ResponseBuilder::badRequest(['message' => 'Search query is required']);
        }

        try {
            $results = [
                'users' => $this->userRepository->searchByEmail($query, $limit),
                'jobseekers' => $this->jobseekerRepository->searchByName($query, $limit),
                'recruiters' => $this->recruiterRepository->findByCompanyName($query, [], $limit),
                'jobs' => $this->jobRepository->search($query, [], $limit),
                'issues' => $this->issueReportRepository->search($query, $limit)
            ];

            return ResponseBuilder::ok($results);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to perform search',
                'error' => $e->getMessage()
            ]);
        }
    }
}