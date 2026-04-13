<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use App\Repositories\WishlistRepository;
use App\Repositories\JobseekerRepository;

class JobController extends BaseController
{
    private JobRepository $jobRepository;
    private UserRepository $userRepository;
    private WishlistRepository $wishlistRepository;
    private \Firebase\FirebaseStorageService $firebaseStorage;
    private JobseekerRepository $jobseekerRepository;

    public function __construct()
    {
        parent::__construct();
        $this->jobRepository = new JobRepository();
        $this->userRepository = new UserRepository();
        $this->wishlistRepository = new WishlistRepository();
        $this->firebaseStorage = new \Firebase\FirebaseStorageService();
        $this->jobseekerRepository = new JobseekerRepository();
    }

    private function processFormData(array $data): array
    {
        $processed = $data;
        
        // Convert string representations of arrays to actual arrays
        if (isset($processed['requirements']) && is_string($processed['requirements'])) {
            $decoded = json_decode($processed['requirements'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $processed['requirements'] = $decoded;
            }
        }
        
        if (isset($processed['skills_required']) && is_string($processed['skills_required'])) {
            $decoded = json_decode($processed['skills_required'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $processed['skills_required'] = $decoded;
            }
        }
        
        if (isset($processed['perks_and_benefits']) && is_string($processed['perks_and_benefits'])) {
            $decoded = json_decode($processed['perks_and_benefits'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $processed['perks_and_benefits'] = $decoded;
            }
        }
        
        // Convert boolean-like values
        if (isset($processed['is_active'])) {
            if (is_string($processed['is_active'])) {
                $processed['is_active'] = filter_var($processed['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
        }
        
        if (isset($processed['is_urgent_hiring'])) {
            if (is_string($processed['is_urgent_hiring'])) {
                $processed['is_urgent_hiring'] = filter_var($processed['is_urgent_hiring'], FILTER_VALIDATE_BOOLEAN);
            }
        }
        
        return $processed;
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
        $uploadedFiles = $request->getUploadedFiles();
        
        // Extract form data if request body is empty (for formdata requests)
        if (empty($data)) {
            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody)) {
                $data = $parsedBody;
            }
        }
        
        // Convert formdata values to appropriate types
        $processedData = $this->processFormData($data);
        
        // Validate required fields
        $errors = $this->validateJobData($processedData);
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
                'company_name' => $processedData['company_name'],
                'company_url' => $processedData['company_url'] ?? null,
                'company_logo_url' => $processedData['company_logo_url'] ?? null,
                'designation' => $processedData['designation'],
                'ctc' => $processedData['ctc'],
                'location' => $processedData['location'],
                'category' => $processedData['category'],
                'description' => $processedData['description'] ?? null,
                'requirements' => isset($processedData['requirements']) ? json_encode($processedData['requirements']) : json_encode([]),
                'skills_required' => isset($processedData['skills_required']) ? json_encode($processedData['skills_required']) : json_encode([]),
                'perks_and_benefits' => isset($processedData['perks_and_benefits']) ? json_encode($processedData['perks_and_benefits']) : json_encode([]),
                'experience_required' => $processedData['experience_required'] ?? null,
                'is_active' => isset($processedData['is_active']) ? (int)$processedData['is_active'] : 1,
                'approval_status' => 'pending', // Jobs need admin approval
            ];
            
            // Handle company logo upload if provided
            if (isset($uploadedFiles['logo']) && $uploadedFiles['logo']->getError() === UPLOAD_ERR_OK) {
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
                $uniqueFilename = 'company_logo_' . time() . '_' . uniqid() . '.' . $extension;
                
                // Upload to Firebase Storage
                $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);
                
                if (!$fileUrl) {
                    return ResponseBuilder::serverError(['message' => 'Failed to upload logo to storage']);
                }
                
                // Add logo URL to job data
                $jobData['company_logo_url'] = $fileUrl;
                
                // Clean up temp file
                unlink($tempPath);
            }
            
            // Add remaining fields to job data
            $jobData['is_urgent_hiring'] = isset($processedData['is_urgent_hiring']) ? (int)$processedData['is_urgent_hiring'] : 0;
            $jobData['job_type'] = $processedData['job_type'] ?? 'Full-time';

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
        $uploadedFiles = $request->getUploadedFiles();
        
        error_log('[JOB UPDATE] ========== START ==========');
        error_log('[JOB UPDATE] Job ID: ' . $jobId);
        error_log('[JOB UPDATE] User ID: ' . $userId);
        error_log('[JOB UPDATE] Request body data: ' . print_r($data, true));
        error_log('[JOB UPDATE] Uploaded files: ' . print_r(array_keys($uploadedFiles), true));
        
        // Extract form data if request body is empty (for formdata requests)
        if (empty($data)) {
            error_log('[JOB UPDATE] Empty request body, checking parsed body for FormData');
            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody)) {
                $data = $parsedBody;
                error_log('[JOB UPDATE] Parsed body data: ' . print_r($data, true));
            }
        }

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

            // Process form data to convert types appropriately
            $processedData = $this->processFormData($data);
            
            // Validate updated data
            $errors = $this->validateJobData($processedData, false); // Not required for partial updates
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
                'category', 'description', 'experience_required', 'perks_and_benefits',
                'is_active', 'is_urgent_hiring', 'job_type'
            ];

            foreach ($fields as $field) {
                if (isset($processedData[$field])) {
                    $updateData[$field] = $processedData[$field];
                }
            }

            // Handle JSON fields separately
            if (isset($processedData['requirements'])) {
                $updateData['requirements'] = json_encode($processedData['requirements']);
            }
            if (isset($processedData['skills_required'])) {
                $updateData['skills_required'] = json_encode($processedData['skills_required']);
            }
            if (isset($processedData['perks_and_benefits'])) {
                $updateData['perks_and_benefits'] = json_encode($processedData['perks_and_benefits']);
            }

            // Handle company logo upload if provided
            if (isset($uploadedFiles['logo']) && $uploadedFiles['logo']->getError() === UPLOAD_ERR_OK) {
                error_log('[JOB UPDATE] Logo file detected, starting upload process...');
                
                $logoFile = $uploadedFiles['logo'];
                
                // Validate file type and size
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($logoFile->getClientMediaType(), $allowedTypes)) {
                    error_log('[JOB UPDATE] Invalid file type: ' . $logoFile->getClientMediaType());
                    return ResponseBuilder::badRequest(['message' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed']);
                }
                
                if ($logoFile->getSize() > $maxFileSize) {
                    error_log('[JOB UPDATE] File too large: ' . $logoFile->getSize() . ' bytes');
                    return ResponseBuilder::badRequest(['message' => 'Image size exceeds 2MB limit']);
                }
                
                error_log('[JOB UPDATE] File validation passed. Size: ' . $logoFile->getSize() . ', Type: ' . $logoFile->getClientMediaType());
                
                // Move uploaded file to temporary location
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $logoFile->getClientFilename();
                $logoFile->moveTo($tempPath);
                
                error_log('[JOB UPDATE] Temp file created: ' . $tempPath);
                
                // Generate unique filename
                $extension = pathinfo($logoFile->getClientFilename(), PATHINFO_EXTENSION);
                $uniqueFilename = 'company_logo_' . $jobId . '_' . time() . '.' . $extension;
                
                // Upload to Firebase Storage
                error_log('[JOB UPDATE] Uploading to Firebase with filename: ' . $uniqueFilename);
                $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);
                
                if (!$fileUrl) {
                    error_log('[JOB UPDATE] Firebase upload failed!');
                    return ResponseBuilder::serverError(['message' => 'Failed to upload logo to storage']);
                }
                
                error_log('[JOB UPDATE] Firebase upload successful! URL: ' . $fileUrl);
                
                // Add logo URL to update data
                $updateData['company_logo_url'] = $fileUrl;
                
                // Clean up temp file
                unlink($tempPath);
                error_log('[JOB UPDATE] Temp file cleaned up');
            } else {
                if (isset($uploadedFiles['logo'])) {
                    error_log('[JOB UPDATE] Logo file has error code: ' . $uploadedFiles['logo']->getError());
                } else {
                    error_log('[JOB UPDATE] No logo file in request');
                }
            }

            // Fix boolean to integer conversion to prevent PDO empty string DB errors
            if (isset($processedData['is_active'])) {
                $updateData['is_active'] = (int)$processedData['is_active'];
                error_log('[JOB UPDATE] is_active set to: ' . $updateData['is_active']);
            }
            if (isset($processedData['is_urgent_hiring'])) {
                $updateData['is_urgent_hiring'] = (int)$processedData['is_urgent_hiring'];
                error_log('[JOB UPDATE] is_urgent_hiring set to: ' . $updateData['is_urgent_hiring']);
            }

            // Reset approval status if significant changes were made
            if (isset($data['designation']) || isset($data['company_name']) || 
                isset($data['ctc']) || isset($data['location']) || isset($data['category'])) {
                $updateData['approval_status'] = 'pending';
                error_log('[JOB UPDATE] Approval status reset to pending due to significant changes');
            }

            error_log('[JOB UPDATE] Final update data: ' . print_r($updateData, true));

            $result = $this->jobRepository->update($jobId, $updateData);

            if (!$result) {
                error_log('[JOB UPDATE] Repository update returned false!');
                return ResponseBuilder::serverError([
                    'message' => 'Failed to update job'
                ]);
            }

            error_log('[JOB UPDATE] Repository update successful!');

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
            error_log('[JOB UPDATE] EXCEPTION CAUGHT: ' . $e->getMessage());
            error_log('[JOB UPDATE] Stack trace: ' . $e->getTraceAsString());
            return ResponseBuilder::serverError([
                'message' => 'Failed to update job',
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

        if (isset($data['perks_and_benefits']) && !is_array($data['perks_and_benefits'])) {
            $errors['perks_and_benefits'] = 'Perks and benefits must be an array';
        }

        return $errors;
    }

    /**
     * Get latest jobs (most recently posted)
     * GET /api/jobs/latest
     */
    public function getLatestJobs(ServerRequestInterface $request)
    {
        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $location = $this->getQueryParam($request, 'location');
        $category = $this->getQueryParam($request, 'category');
        $jobType = $this->getQueryParam($request, 'job_type');

        $filters = [];
        if ($location) $filters['location'] = $location;
        if ($category) $filters['category'] = $category;
        if ($jobType) $filters['job_type'] = $jobType;

        try {
            $user = $this->getUser($request);
            
            // Fetch latest approved jobs sorted by created_at DESC
            $jobs = $this->jobRepository->findLatestJobs($filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->jobRepository->count(['approval_status' => 'approved', 'is_active' => true]);

            // Add wishlist status for authenticated users
            if ($user) {
                $userId = $user['id'];
                $wishlistJobIds = $this->wishlistRepository->getWishlistJobIds($userId);
                
                foreach ($jobs as &$job) {
                    $job['is_in_wishlist'] = in_array($job['id'], $wishlistJobIds);
                }
            } else {
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
                'message' => 'Failed to fetch latest jobs',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get top companies by job count
     * GET /api/jobs/top-companies
     */
    public function getTopCompanies(ServerRequestInterface $request)
    {
        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 20);

        try {
            $companies = $this->jobRepository->getTopCompanies($limit, ($page - 1) * $limit);
            
            return ResponseBuilder::ok([
                'companies' => $companies,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($companies)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch top companies',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get complete jobseeker profile with all details
     * GET /api/jobs/jobseeker/:userId
     */
    public function getJobseekerProfile(ServerRequestInterface $request)
    {
        $jobseekerUserId = (int) $request->getAttribute('userId');

        if ($jobseekerUserId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid jobseeker ID']);
        }

        try {
            // Get jobseeker with user details
            $jobseeker = $this->jobseekerRepository->getJobseekerWithUserDetails($jobseekerUserId);

            if (!$jobseeker) {
                return ResponseBuilder::notFound(['message' => 'Jobseeker not found']);
            }

            // Format the response with all jobseeker data
            $profileData = [
                'user_id' => $jobseeker['user_id'],
                'name' => $jobseeker['name'],
                'email' => $jobseeker['email'],
                'phone' => $jobseeker['phone'],
                'profile_image_url' => $jobseeker['profile_image_url'],
                'bio' => $jobseeker['bio'],
                'skills' => $jobseeker['skills'] ?? [],
                'qualification' => $jobseeker['qualification'],
                'experience' => $jobseeker['experience'],
                'location' => $jobseeker['location'],
                'date_of_birth' => $jobseeker['date_of_birth'],
                'resume_url' => $jobseeker['resume_url'],
                'resume_filename' => $jobseeker['resume_filename'],
                'status' => $jobseeker['status'],
                'email_verified' => $jobseeker['email_verified'],
                'created_at' => $jobseeker['created_at'],
                'updated_at' => $jobseeker['updated_at'],
            ];

            return ResponseBuilder::ok([
                'message' => 'Jobseeker profile fetched successfully',
                'jobseeker' => $profileData
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch jobseeker profile',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get complete recruiter profile with all details
     * GET /api/jobs/recruiter/:userId
     */
    public function getRecruiterProfile(ServerRequestInterface $request)
    {
        $recruiterUserId = (int) $request->getAttribute('userId');

        if ($recruiterUserId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid recruiter ID']);
        }

        try {
            // Get recruiter with user details
            $recruiterRepository = new \App\Repositories\RecruiterRepository();
            $recruiter = $recruiterRepository->getRecruiterWithUserDetails($recruiterUserId);

            if (!$recruiter) {
                return ResponseBuilder::notFound(['message' => 'Recruiter not found']);
            }

            // Format the response with all recruiter data
            $profileData = [
                'user_id' => $recruiter['user_id'],
                'email' => $recruiter['email'],
                'recruiter_name' => $recruiter['recruiter_name'],
                'company_name' => $recruiter['company_name'],
                'company_website' => $recruiter['company_website'],
                'designation' => $recruiter['designation'],
                'location' => $recruiter['location'],
                'photo_url' => $recruiter['photo_url'],
                'id_card_url' => $recruiter['id_card_url'],
                'approval_status' => $recruiter['approval_status'],
                'approved_by' => $recruiter['approved_by'],
                'approved_at' => $recruiter['approved_at'],
                'rejection_reason' => $recruiter['rejection_reason'],
                'created_at' => $recruiter['created_at'],
                'updated_at' => $recruiter['updated_at'],
                'user_email' => $recruiter['user_email'],
                'phone' => $recruiter['phone'],
                'status' => $recruiter['status'],
                'email_verified' => $recruiter['email_verified'],
                'user_created_at' => $recruiter['user_created_at'],
                'user_updated_at' => $recruiter['user_updated_at'],
            ];

            return ResponseBuilder::ok([
                'message' => 'Recruiter profile fetched successfully',
                'recruiter' => $profileData
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch recruiter profile',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get jobs posted by a specific recruiter
     * GET /api/jobs/by-recruiter/:recruiterId
     */
    public function getJobsByRecruiter(ServerRequestInterface $request)
    {
        $recruiterId = (int) $request->getAttribute('recruiterId');

        if ($recruiterId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid recruiter ID']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);

        try {
            // Get jobs posted by the specific recruiter
            $jobs = $this->jobRepository->findByRecruiter($recruiterId, [], $limit, ($page - 1) * $limit);
            $totalCount = $this->jobRepository->count(['recruiter_user_id' => $recruiterId]);

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
                'message' => 'Failed to fetch jobs by recruiter',
                'error' => $e->getMessage()
            ]);
        }
    }
}