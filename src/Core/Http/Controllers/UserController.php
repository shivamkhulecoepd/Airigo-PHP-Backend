<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\UserRepository;
use App\Repositories\JobseekerRepository;
use App\Repositories\RecruiterRepository;
use App\Repositories\WishlistRepository;
use Firebase\FirebaseStorageService;

class UserController extends BaseController
{
    private UserRepository $userRepository;
    private JobseekerRepository $jobseekerRepository;
    private RecruiterRepository $recruiterRepository;
    private WishlistRepository $wishlistRepository;
    private FirebaseStorageService $firebaseStorage;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->jobseekerRepository = new JobseekerRepository();
        $this->recruiterRepository = new RecruiterRepository();
        $this->wishlistRepository = new WishlistRepository();
        $this->firebaseStorage = new FirebaseStorageService();
    }

    public function getProfile(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        // Get profile based on user type
        if ($user['user_type'] === 'jobseeker') {
            $profile = $this->jobseekerRepository->findByUserId($user['id']);
            // Skills field is already decoded in the repository
        } else { // recruiter
            $profile = $this->recruiterRepository->findByUserId($user['id']);
        }

        // Get wishlist count
        $wishlistCount = $this->wishlistRepository->getWishlistCount($user['id']);

        return ResponseBuilder::ok([
            'user' => $user,
            'profile' => $profile,
            'wishlist_info' => [
                'count' => $wishlistCount
            ]
        ]);
    }

    public function updateProfile(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        // Validate the data based on user type
        $errors = $this->validateProfileUpdateData($data, $user['user_type']);
        if (!empty($errors)) {
            return ResponseBuilder::badRequest([
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }

        try {
            // Update user info
            $userUpdateData = [];
            if (isset($data['email'])) {
                $userUpdateData['email'] = $data['email'];
            }
            if (isset($data['phone'])) {
                $userUpdateData['phone'] = $data['phone'];
            }

            if (!empty($userUpdateData)) {
                $this->userRepository->update($user['id'], $userUpdateData);
            }

            // Update profile based on user type
            if ($user['user_type'] === 'jobseeker') {
                $profileUpdateData = $this->filterJobseekerProfileData($data);
                $this->jobseekerRepository->update($user['id'], $profileUpdateData);
            } else { // recruiter
                $profileUpdateData = $this->filterRecruiterProfileData($data);
                $this->recruiterRepository->update($user['id'], $profileUpdateData);
            }

            // Fetch updated user and profile
            $updatedUser = $this->userRepository->findById($user['id']);
            $updatedProfile = $user['user_type'] === 'jobseeker' ? 
                $this->jobseekerRepository->findByUserId($user['id']) : 
                $this->recruiterRepository->findByUserId($user['id']);
            
            // Skills field is already decoded in the repository

            // Get updated wishlist count
            $wishlistCount = $this->wishlistRepository->getWishlistCount($user['id']);

            return ResponseBuilder::ok([
                'message' => 'Profile updated successfully',
                'user' => $updatedUser,
                'profile' => $updatedProfile,
                'wishlist_info' => [
                    'count' => $wishlistCount
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteAccount(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            // Remove user's wishlist items
            $this->wishlistRepository->removeByUserId($user['id']);

            // Delete profile first based on user type
            if ($user['user_type'] === 'jobseeker') {
                $this->jobseekerRepository->delete($user['id']);
            } else { // recruiter
                $this->recruiterRepository->delete($user['id']);
            }

            // Then delete user
            $this->userRepository->delete($user['id']);

            return ResponseBuilder::ok([
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uploadResume(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'jobseeker') {
            return ResponseBuilder::forbidden(['message' => 'Only jobseekers can upload resumes']);
        }

        // Check if files were uploaded
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['resume']) || $uploadedFiles['resume']->getError() !== UPLOAD_ERR_OK) {
            return ResponseBuilder::badRequest(['message' => 'Resume file is required']);
        }

        $resumeFile = $uploadedFiles['resume'];
        
        // Validate file type and size
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($resumeFile->getClientMediaType(), $allowedTypes)) {
            return ResponseBuilder::badRequest(['message' => 'Invalid file type. Only PDF, DOC, and DOCX files are allowed']);
        }

        if ($resumeFile->getSize() > $maxFileSize) {
            return ResponseBuilder::badRequest(['message' => 'File size exceeds 5MB limit']);
        }

        try {
            // Move uploaded file to temporary location
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $resumeFile->getClientFilename();
            $resumeFile->moveTo($tempPath);

            // Generate unique filename
            $extension = pathinfo($resumeFile->getClientFilename(), PATHINFO_EXTENSION);
            $uniqueFilename = 'resume_' . $user['id'] . '_' . time() . '.' . $extension;
            
            // Upload to Firebase Storage
            $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

            if (!$fileUrl) {
                return ResponseBuilder::serverError(['message' => 'Failed to upload file to storage']);
            }

            // Update user's resume info in database
            $this->jobseekerRepository->update($user['id'], [
                'resume_url' => $fileUrl,
                'resume_filename' => $resumeFile->getClientFilename()
            ]);

            // Clean up temp file
            unlink($tempPath);

            // Get updated wishlist count
            // $wishlistCount = $this->wishlistRepository->getWishlistCount($user['id']);

            return ResponseBuilder::ok([
                'message' => 'Resume uploaded successfully',
                'resume_url' => $fileUrl,
                'filename' => $resumeFile->getClientFilename(),
                // 'wishlist_info' => [
                //     'count' => $wishlistCount
                // ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to upload resume',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uploadProfileImage(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        // Check if files were uploaded
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['image']) || $uploadedFiles['image']->getError() !== UPLOAD_ERR_OK) {
            return ResponseBuilder::badRequest(['message' => 'Profile image is required']);
        }

        $imageFile = $uploadedFiles['image'];
        
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($imageFile->getClientMediaType(), $allowedTypes)) {
            return ResponseBuilder::badRequest(['message' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed']);
        }

        if ($imageFile->getSize() > $maxFileSize) {
            return ResponseBuilder::badRequest(['message' => 'Image size exceeds 2MB limit']);
        }

        try {
            // Move uploaded file to temporary location
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $imageFile->getClientFilename();
            $imageFile->moveTo($tempPath);

            // Generate unique filename
            $extension = pathinfo($imageFile->getClientFilename(), PATHINFO_EXTENSION);
            $uniqueFilename = 'profile_' . $user['id'] . '_' . time() . '.' . $extension;
            
            // Upload to Firebase Storage
            $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

            if (!$fileUrl) {
                return ResponseBuilder::serverError(['message' => 'Failed to upload file to storage']);
            }

            // Update user's profile image info in database based on user type
            if ($user['user_type'] === 'jobseeker') {
                $this->jobseekerRepository->update($user['id'], [
                    'profile_image_url' => $fileUrl
                ]);
            } else { // recruiter
                $this->recruiterRepository->update($user['id'], [
                    'photo_url' => $fileUrl
                ]);
            }

            // Clean up temp file
            unlink($tempPath);

            // Get updated wishlist count
            // $wishlistCount = $this->wishlistRepository->getWishlistCount($user['id']);

            return ResponseBuilder::ok([
                'message' => 'Profile image uploaded successfully',
                'image_url' => $fileUrl,
                'filename' => $imageFile->getClientFilename(),
                // 'wishlist_info' => [
                //     'count' => $wishlistCount
                // ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to upload profile image',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function uploadIdCard(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'recruiter') {
            return ResponseBuilder::forbidden(['message' => 'Only recruiters can upload ID cards']);
        }

        // Check if files were uploaded
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['id_card']) || $uploadedFiles['id_card']->getError() !== UPLOAD_ERR_OK) {
            return ResponseBuilder::badRequest(['message' => 'ID card image is required']);
        }

        $idCardFile = $uploadedFiles['id_card'];
        
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($idCardFile->getClientMediaType(), $allowedTypes)) {
            return ResponseBuilder::badRequest(['message' => 'Invalid file type. Only JPEG, PNG, GIF, WebP, and PDF files are allowed']);
        }

        if ($idCardFile->getSize() > $maxFileSize) {
            return ResponseBuilder::badRequest(['message' => 'File size exceeds 5MB limit']);
        }

        try {
            // Move uploaded file to temporary location
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $idCardFile->getClientFilename();
            $idCardFile->moveTo($tempPath);

            // Generate unique filename
            $extension = pathinfo($idCardFile->getClientFilename(), PATHINFO_EXTENSION);
            $uniqueFilename = 'id_card_' . $user['id'] . '_' . time() . '.' . $extension;
            
            // Upload to Firebase Storage
            $fileUrl = $this->firebaseStorage->uploadFile($tempPath, $uniqueFilename);

            if (!$fileUrl) {
                return ResponseBuilder::serverError(['message' => 'Failed to upload file to storage']);
            }

            // Update recruiter's ID card info in database
            $this->recruiterRepository->update($user['id'], [
                'id_card_url' => $fileUrl
            ]);

            // Clean up temp file
            unlink($tempPath);

            // Get updated wishlist count
            // $wishlistCount = $this->wishlistRepository->getWishlistCount($user['id']);

            return ResponseBuilder::ok([
                'message' => 'ID card uploaded successfully',
                'id_card_url' => $fileUrl,
                'filename' => $idCardFile->getClientFilename(),
                // 'wishlist_info' => [
                //     'count' => $wishlistCount
                // ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to upload ID card',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateProfileUpdateData(array $data, string $userType): array
    {
        $errors = [];

        if ($userType === 'jobseeker') {
            // Validate jobseeker-specific fields
            if (isset($data['name']) && !$this->validator->isValidLength($data['name'], 1, 255)) {
                $errors['name'] = 'Name must be between 1 and 255 characters';
            }

            if (isset($data['date_of_birth']) && !$this->validator->isValidDate($data['date_of_birth'])) {
                $errors['date_of_birth'] = 'Invalid date format';
            }

            if (isset($data['experience']) && !$this->validator->isNumeric($data['experience'])) {
                $errors['experience'] = 'Experience must be a number';
            }

            if (isset($data['skills']) && !is_array($data['skills'])) {
                $errors['skills'] = 'Skills must be an array';
            }
        } else { // recruiter
            // Validate recruiter-specific fields
            if (isset($data['company_name']) && !$this->validator->isValidLength($data['company_name'], 1, 255)) {
                $errors['company_name'] = 'Company name must be between 1 and 255 characters';
            }

            if (isset($data['designation']) && !$this->validator->isValidLength($data['designation'], 1, 255)) {
                $errors['designation'] = 'Designation must be between 1 and 255 characters';
            }
        }

        // Common validations
        if (isset($data['email']) && !$this->validator->isValidEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        if (isset($data['phone']) && !$this->validator->isValidPhone($data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }

        if (isset($data['location']) && !$this->validator->isValidLength($data['location'], 1, 255)) {
            $errors['location'] = 'Location must be between 1 and 255 characters';
        }

        return $errors;
    }

    private function filterJobseekerProfileData(array $data): array
    {
        $allowedFields = [
            'name', 'qualification', 'experience', 'location', 
            'date_of_birth', 'skills', 'bio'
        ];

        $filteredData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'skills' && is_array($data[$field])) {
                    $filteredData[$field] = json_encode($data[$field]);
                } else {
                    $filteredData[$field] = $data[$field];
                }
            }
        }

        return $filteredData;
    }

    private function filterRecruiterProfileData(array $data): array
    {
        $allowedFields = [
            'company_name', 'designation', 'location'
        ];

        $filteredData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $filteredData[$field] = $data[$field];
            }
        }

        return $filteredData;
    }
}