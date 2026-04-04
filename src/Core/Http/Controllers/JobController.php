<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use App\Repositories\WishlistRepository;

class JobController extends BaseController
{
    private JobRepository $jobRepository;
    private UserRepository $userRepository;
    private WishlistRepository $wishlistRepository;
    private \Firebase\FirebaseStorageService $firebaseStorage;

    public function __construct()
    {
        parent::__construct();
        $this->jobRepository = new JobRepository();
        $this->userRepository = new UserRepository();
        $this->wishlistRepository = new WishlistRepository();
        $this->firebaseStorage = new \Firebase\FirebaseStorageService();
    }

    public function create(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can create jobs']);
        }

        $data = $this->getRequestBody($request);

        // Validate required fields
        $errors = $this->validateJobData($data);
        if (!empty($errors)) {
            return ResponseBuilder::unprocessableEntity([
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }

        try {
            // Prepare job data
            $jobData = [
                'recruiter_user_id' => $user['id'],
                'company_name' => $data['company_name'],
                'company_url' => $data['company_url'] ?? null,
                'company_logo_url' => $data['company_logo_url'] ?? null,
                'designation' => $data['designation'],
                'ctc' => $data['ctc'],
                'location' => $data['location'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'requirements' => isset($data['requirements']) ? json_encode($data['requirements']) : json_encode([]),
                'skills_required' => isset($data['skills_required']) ? json_encode($data['skills_required']) : json_encode([]),
                'experience_required' => $data['experience_required'] ?? null,
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'approval_status' => 'pending', // Jobs need admin approval
                'is_urgent_hiring' => isset($data['is_urgent_hiring']) ? (int)$data['is_urgent_hiring'] : 0,
                'job_type' => $data['job_type'] ?? 'Full-time'
            ];

            $jobId = $this->jobRepository->create($jobData);

            if (!$jobId) {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to create job'
                ]);
            }

            // Fetch the created job
            $job = $this->jobRepository->findById($jobId);
            // JSON fields are already decoded in the repository

            return ResponseBuilder::created([
                'message' => 'Job created successfully. Awaiting admin approval.',
                'job' => $job
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to create job',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getAll(ServerRequestInterface $request)
    {
        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $location = $this->getQueryParam($request, 'location');
        $category = $this->getQueryParam($request, 'category');
        $jobType = $this->getQueryParam($request, 'job_type');
        $minCtc = $this->getQueryParam($request, 'min_ctc');
        $maxCtc = $this->getQueryParam($request, 'max_ctc');
        $isUrgent = $this->getQueryParam($request, 'is_urgent_hiring');
        $recruiterUserId = $this->getQueryParam($request, 'recruiter_user_id'); // Get recruiter user ID

        $filters = [];
        if ($location) $filters['location'] = $location;
        if ($category) $filters['category'] = $category;
        if ($jobType) $filters['job_type'] = $jobType;
        if ($minCtc) $filters['min_ctc'] = $minCtc;
        if ($maxCtc) $filters['max_ctc'] = $maxCtc;
        if ($isUrgent !== null) $filters['is_urgent_hiring'] = filter_var($isUrgent, FILTER_VALIDATE_BOOLEAN);

        try {
            $user = $this->getUser($request);
            $userId = $this->getUserId($request); // Get user ID from request attributes
            $userType = $this->getUserType($request); // Get user type from request attributes
            $jobs = [];
            $totalCount = 0;

            // If recruiter_user_id is provided, fetch jobs for that specific recruiter
            if ($recruiterUserId) {
                // Allow authenticated recruiters to access their own jobs
                // If user is authenticated and is a recruiter, allow access to their own jobs
                if ($user && $userType === 'recruiter' && $userId == $recruiterUserId) {
                    // User is accessing their own jobs
                    $jobs = $this->jobRepository->findByRecruiter((int)$recruiterUserId, $filters, $limit, ($page - 1) * $limit);
                    $totalCount = $this->jobRepository->count(['recruiter_user_id' => $recruiterUserId]);
                } else if ($user && $userType === 'admin') {
                    // Admin can access any recruiter's jobs
                    $jobs = $this->jobRepository->findByRecruiter((int)$recruiterUserId, $filters, $limit, ($page - 1) * $limit);
                    $totalCount = $this->jobRepository->count(['recruiter_user_id' => $recruiterUserId]);
                } else if (!$user) {
                    // Unauthenticated user cannot access specific recruiter jobs
                    return ResponseBuilder::forbidden(['message' => 'Authentication required to access specific recruiter jobs']);
                } else {
                    // User is trying to access another recruiter's jobs
                    return ResponseBuilder::forbidden(['message' => 'You can only access your own jobs']);
                }
            } else {
                // Otherwise, fetch all approved jobs for general users
                $jobs = $this->jobRepository->findApprovedJobs($filters, $limit, ($page - 1) * $limit);
                $totalCount = $this->jobRepository->count(['approval_status' => 'approved', 'is_active' => true]);
            }

            // Add wishlist status for authenticated users
            if ($user) {
                $userId = $user['id'];
                $wishlistJobIds = $this->wishlistRepository->getWishlistJobIds($userId);
                
                // Enhance jobs with wishlist status
                foreach ($jobs as &$job) {
                    $job['is_in_wishlist'] = in_array($job['id'], $wishlistJobIds);
                }
            } else {
                // Add wishlist status as false for unauthenticated users
                foreach ($jobs as &$job) {
                    $job['is_in_wishlist'] = false;
                }
            }

            return ResponseBuilder::ok([
                'jobs' => $jobs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch jobs',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getById(ServerRequestInterface $request)
    {
        $jobId = (int) $request->getAttribute('id');

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            // Only return approved and active jobs unless the requester is the recruiter
            $user = $this->getUser($request);
            $canView = $job['approval_status'] === 'approved' && $job['is_active'] === 1;
            
            if (!$canView && (!$user || $user['id'] != $job['recruiter_user_id'])) {
                return ResponseBuilder::forbidden(['message' => 'Job is not available']);
            }

            // Add wishlist status
            if ($user) {
                $isInWishlist = $this->wishlistRepository->isInWishlist($user['id'], $jobId);
                $job['is_in_wishlist'] = $isInWishlist;
            } else {
                $job['is_in_wishlist'] = false;
            }
            // JSON fields are already decoded in the repository

            return ResponseBuilder::ok(['job' => $job]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch job',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $userId = $this->getUserId($request); // Get user ID from request attributes
        $jobId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            if ($userId != $job['recruiter_user_id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only update your own jobs']);
            }

            // Validate updated data
            $errors = $this->validateJobData($data, false); // Not required for partial updates
            if (!empty($errors)) {
                return ResponseBuilder::unprocessableEntity([
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
            }

            // Prepare update data
            $updateData = [];
            $fields = [
                'company_name', 'company_url', 'company_logo_url', 'designation', 'ctc', 'location', 
                'category', 'description', 'experience_required', 
                'is_active', 'is_urgent_hiring', 'job_type'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle JSON fields separately
            if (isset($data['requirements'])) {
                $updateData['requirements'] = json_encode($data['requirements']);
            }
            if (isset($data['skills_required'])) {
                $updateData['skills_required'] = json_encode($data['skills_required']);
            }

            // Fix boolean to integer conversion to prevent PDO empty string DB errors
            if (isset($data['is_active'])) {
                $updateData['is_active'] = (int)$data['is_active'];
            }
            if (isset($data['is_urgent_hiring'])) {
                $updateData['is_urgent_hiring'] = (int)$data['is_urgent_hiring'];
            }

            // Reset approval status if significant changes were made
            if (isset($data['designation']) || isset($data['company_name']) || 
                isset($data['ctc']) || isset($data['location']) || isset($data['category'])) {
                $updateData['approval_status'] = 'pending';
            }

            $result = $this->jobRepository->update($jobId, $updateData);

            if (!$result) {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to update job'
                ]);
            }

            // Fetch updated job
            $updatedJob = $this->jobRepository->findById($jobId);
            // JSON fields are already decoded in the repository
            
            // Add wishlist status
            if ($user) {
                $isInWishlist = $this->wishlistRepository->isInWishlist($user['id'], $jobId);
                $updatedJob['is_in_wishlist'] = $isInWishlist;
            } else {
                $updatedJob['is_in_wishlist'] = false;
            }

            $message = 'Job updated successfully';
            if ($updateData['approval_status'] ?? null === 'pending') {
                $message .= '. Changes are awaiting admin approval.';
            }

            return ResponseBuilder::ok([
                'message' => $message,
                'job' => $updatedJob
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update job',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uploadCompanyLogo(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can upload company logos']);
        }

        $userId = $this->getUserId($request); // Get user ID from request attributes
        $jobId = (int) $request->getAttribute('id');
        
        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            if ($userId != $job['recruiter_user_id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only update logos for your own jobs']);
            }

            // Check if files were uploaded
            $uploadedFiles = $request->getUploadedFiles();
            
            if (!isset($uploadedFiles['logo']) || $uploadedFiles['logo']->getError() !== UPLOAD_ERR_OK) {
                return ResponseBuilder::badRequest(['message' => 'Company logo is required']);
            }

            $logoFile = $uploadedFiles['logo'];
            
            // Validate file type and size
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($logoFile->getClientMediaType(), $allowedTypes)) {
                return ResponseBuilder::badRequest(['message' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed']);
            }

            if ($logoFile->getSize() > $maxFileSize) {
                return ResponseBuilder::badRequest(['message' => 'Image size exceeds 2MB limit']);
            }

            // Move uploaded file to temporary location
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $logoFile->getClientFilename();
            $logoFile->moveTo($tempPath);

            // Generate unique filename
            $extension = pathinfo($logoFile->getClientFilename(), PATHINFO_EXTENSION);
            $uniqueFilename = 'company_logo_' . $jobId . '_' . time() . '.' . $extension;
            
            // Upload to Firebase Storage
            $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

            if (!$fileUrl) {
                return ResponseBuilder::serverError(['message' => 'Failed to upload logo to storage']);
            }

            // Update job with logo URL
            $result = $this->jobRepository->update($jobId, [
                'company_logo_url' => $fileUrl
            ]);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update job with logo URL']);
            }

            // Clean up temp file
            unlink($tempPath);

            return ResponseBuilder::ok([
                'message' => 'Company logo uploaded successfully',
                'logo_url' => $fileUrl
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to upload company logo',
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

        $userId = $this->getUserId($request); // Get user ID from request attributes
        $jobId = (int) $request->getAttribute('id');

        if ($jobId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid job ID']);
        }

        try {
            $job = $this->jobRepository->findById($jobId);

            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            if ($userId != $job['recruiter_user_id']) {
                return ResponseBuilder::forbidden(['message' => 'You can only delete your own jobs']);
            }

            // Remove from wishlist when job is deleted
            $this->wishlistRepository->removeByJobId($jobId);

            $result = $this->jobRepository->delete($jobId);

            if (!$result) {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to delete job'
                ]);
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

    public function search(ServerRequestInterface $request)
    {
        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $location = $this->getQueryParam($request, 'location');
        $category = $this->getQueryParam($request, 'category');
        $designation = $this->getQueryParam($request, 'designation');
        $companyName = $this->getQueryParam($request, 'company_name');
        $jobType = $this->getQueryParam($request, 'job_type');
        $minCtc = $this->getQueryParam($request, 'min_ctc');
        $maxCtc = $this->getQueryParam($request, 'max_ctc');
        $experienceRequired = $this->getQueryParam($request, 'experience_required');
        $isUrgent = $this->getQueryParam($request, 'is_urgent_hiring');

        $searchParams = [];
        if ($location) $searchParams['location'] = $location;
        if ($category) $searchParams['category'] = $category;
        if ($designation) $searchParams['designation'] = $designation;
        if ($companyName) $searchParams['company_name'] = $companyName;
        if ($jobType) $searchParams['job_type'] = $jobType;
        if ($minCtc) $searchParams['min_ctc'] = $minCtc;
        if ($maxCtc) $searchParams['max_ctc'] = $maxCtc;
        if ($experienceRequired) $searchParams['experience_required'] = $experienceRequired;
        if ($isUrgent !== null) $searchParams['is_urgent_hiring'] = filter_var($isUrgent, FILTER_VALIDATE_BOOLEAN);

        try {
            $jobs = $this->jobRepository->searchJobs($searchParams, $limit, ($page - 1) * $limit);
            $totalCount = $this->jobRepository->count(['is_active' => 1, 'approval_status' => 'approved']);

            // Add wishlist status for authenticated users
            $user = $this->getUser($request);
            if ($user) {
                $userId = $user['id'];
                $wishlistJobIds = $this->wishlistRepository->getWishlistJobIds($userId);
                
                // Enhance jobs with wishlist status
                foreach ($jobs as &$job) {
                    $job['is_in_wishlist'] = in_array($job['id'], $wishlistJobIds);
                }
            } else {
                // Add wishlist status as false for unauthenticated users
                foreach ($jobs as &$job) {
                    $job['is_in_wishlist'] = false;
                }
            }

            return ResponseBuilder::ok([
                'jobs' => $jobs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to search jobs',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getCategories(ServerRequestInterface $request)
    {
        try {
            $categories = $this->jobRepository->getTopCategories(50); // Get top 50 categories
            
            return ResponseBuilder::ok([
                'categories' => array_column($categories, 'category')
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getLocations(ServerRequestInterface $request)
    {
        try {
            $locations = $this->jobRepository->getTopLocations(50); // Get top 50 locations
            
            return ResponseBuilder::ok([
                'locations' => array_column($locations, 'location')
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch locations',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateJobData(array $data, bool $required = true): array
    {
        $errors = [];

        // Validate required fields
        if ($required) {
            if (empty($data['company_name'])) {
                $errors['company_name'] = 'Company name is required';
            } elseif (!$this->validator->isValidLength($data['company_name'], 1, 255)) {
                $errors['company_name'] = 'Company name must be between 1 and 255 characters';
            }

            if (empty($data['designation'])) {
                $errors['designation'] = 'Designation is required';
            } elseif (!$this->validator->isValidLength($data['designation'], 1, 255)) {
                $errors['designation'] = 'Designation must be between 1 and 255 characters';
            }

            if (empty($data['ctc'])) {
                $errors['ctc'] = 'CTC is required';
            }

            if (empty($data['location'])) {
                $errors['location'] = 'Location is required';
            } elseif (!$this->validator->isValidLength($data['location'], 1, 255)) {
                $errors['location'] = 'Location must be between 1 and 255 characters';
            }

            if (empty($data['category'])) {
                $errors['category'] = 'Category is required';
            } elseif (!$this->validator->isValidLength($data['category'], 1, 100)) {
                $errors['category'] = 'Category must be between 1 and 100 characters';
            }
        } else {
            // Validate if fields are present
            if (isset($data['company_name']) && !$this->validator->isValidLength($data['company_name'], 1, 255)) {
                $errors['company_name'] = 'Company name must be between 1 and 255 characters';
            }

            if (isset($data['designation']) && !$this->validator->isValidLength($data['designation'], 1, 255)) {
                $errors['designation'] = 'Designation must be between 1 and 255 characters';
            }

            if (isset($data['location']) && !$this->validator->isValidLength($data['location'], 1, 255)) {
                $errors['location'] = 'Location must be between 1 and 255 characters';
            }

            if (isset($data['category']) && !$this->validator->isValidLength($data['category'], 1, 100)) {
                $errors['category'] = 'Category must be between 1 and 100 characters';
            }
        }

        // Validate optional fields if present
        if (isset($data['experience_required']) && !empty($data['experience_required'])) {
            if (!$this->validator->isValidLength($data['experience_required'], 1, 50)) {
                $errors['experience_required'] = 'Experience required must be between 1 and 50 characters';
            } elseif (!preg_match('/^(\d+-\d+\s*(year|years)|\d+\+\s*(year|years))$/i', $data['experience_required'])) {
                $errors['experience_required'] = 'Experience required must be in format like "0-1 year", "2-5 years", or "8+ years".';
            }
        }

        if (isset($data['company_url']) && !$this->validator->isValidUrl($data['company_url'])) {
            $errors['company_url'] = 'Company URL must be a valid URL';
        }

        if (isset($data['job_type']) && !$this->validator->isIn($data['job_type'], ['Full-time', 'Part-time', 'Contract', 'Internship'])) {
            $errors['job_type'] = 'Job type must be Full-time, Part-time, Contract, or Internship';
        }

        // Validate experience_required format if present
        if (isset($data['experience_required']) && !empty($data['experience_required'])) {
            if (!$this->validator->isValidLength($data['experience_required'], 1, 50)) {
                $errors['experience_required'] = 'Experience required must be between 1 and 50 characters';
            } elseif (!preg_match('/^(\d+-\d+\s*(year|years)|\d+\+\s*(year|years))$/i', $data['experience_required'])) {
                $errors['experience_required'] = 'Experience required must be in format like "0-1 year", "2-5 years", or "8+ years".';
            }
        }

        if (isset($data['is_active']) && !$this->validator->isBoolean($data['is_active'])) {
            $errors['is_active'] = 'Is active must be a boolean value';
        }

        if (isset($data['is_urgent_hiring']) && !$this->validator->isBoolean($data['is_urgent_hiring'])) {
            $errors['is_urgent_hiring'] = 'Is urgent hiring must be a boolean value';
        }

        if (isset($data['requirements']) && !is_array($data['requirements'])) {
            $errors['requirements'] = 'Requirements must be an array';
        }

        if (isset($data['skills_required']) && !is_array($data['skills_required'])) {
            $errors['skills_required'] = 'Skills required must be an array';
        }

        return $errors;
    }
}