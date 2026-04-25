<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Auth\AuthService;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    public function register(ServerRequestInterface $request)
    {
        $data = $this->getRequestBody($request);

        // Validate required fields
        $errors = $this->validateRegistrationData($data);
        if (!empty($errors)) {
            return ResponseBuilder::badRequest([
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }
        
        $result = $this->authService->register($data);

        if ($result['success']) {
            return ResponseBuilder::created([
                'message' => $result['message'],
                'user' => $result['user'],
                'tokens' => $result['tokens']
            ]);
        }

        return ResponseBuilder::unprocessableEntity([
            'message' => $result['message']
        ]);
    }

    public function login(ServerRequestInterface $request)
    {
        $data = $this->getRequestBody($request);

        // Validate required fields - accept either email or phone
        if (empty($data['email']) && empty($data['phone'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Either email or phone number is required'
            ]);
        }

        if (empty($data['password'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Password is required'
            ]);
        }

        // Use either email or phone as identifier
        $identifier = $data['email'] ?? $data['phone'];
        
        $result = $this->authService->login($identifier, $data['password']);

        if ($result['success']) {
            return ResponseBuilder::ok([
                'message' => $result['message'],
                'user' => $result['user'],
                'tokens' => $result['tokens']
            ]);
        }

        return ResponseBuilder::unauthorized([
            'message' => $result['message']
        ]);
    }

    public function logout(ServerRequestInterface $request)
    {
        $result = $this->authService->logout();

        return ResponseBuilder::ok([
            'message' => $result['message']
        ]);
    }

    public function refreshToken(ServerRequestInterface $request)
    {
        $data = $this->getRequestBody($request);

        if (empty($data['refresh_token'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Refresh token is required'
            ]);
        }

        $result = $this->authService->refreshToken($data['refresh_token']);

        if ($result['success']) {
            return ResponseBuilder::ok([
                'message' => $result['message'],
                'tokens' => $result['tokens']
            ]);
        }

        return ResponseBuilder::unauthorized([
            'message' => $result['message']
        ]);
    }

    public function changePassword(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized([
                'message' => 'User not authenticated'
            ]);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['current_password']) || empty($data['new_password'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Current password and new password are required'
            ]);
        }

        // Validate new password strength
        if (strlen($data['new_password']) < 8) {
            return ResponseBuilder::badRequest([
                'message' => 'New password must be at least 8 characters long'
            ]);
        }

        $result = $this->authService->changePassword($user['id'], $data['current_password'], $data['new_password']);

        if ($result['success']) {
            return ResponseBuilder::ok([
                'message' => $result['message']
            ]);
        }

        return ResponseBuilder::badRequest([
            'message' => $result['message']
        ]);
    }

    public function forgotPassword(ServerRequestInterface $request)
    {
        $data = $this->getRequestBody($request);

        if (empty($data['email']) && empty($data['phone'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Either email or phone number is required'
            ]);
        }

        $identifier = $data['email'] ?? $data['phone'];
        $result = $this->authService->forgotPassword($identifier);

        return ResponseBuilder::ok([
            'message' => $result['message']
        ]);
    }

    public function resetPassword(ServerRequestInterface $request)
    {
        $data = $this->getRequestBody($request);

        if (empty($data['reset_token']) || empty($data['new_password'])) {
            return ResponseBuilder::badRequest([
                'message' => 'Reset token and new password are required'
            ]);
        }

        $result = $this->authService->resetPassword($data['reset_token'], $data['new_password']);

        if ($result['success']) {
            return ResponseBuilder::ok([
                'message' => $result['message']
            ]);
        }

        return ResponseBuilder::badRequest([
            'message' => $result['message']
        ]);
    }

    public function getProfile(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized([
                'message' => 'User not authenticated'
            ]);
        }

        // Get user profile with details
        if ($user['user_type'] === 'jobseeker') {
            $profileRepo = new \App\Repositories\JobseekerRepository();
            $profile = $profileRepo->getJobseekerWithUserDetails($user['id']);
        } else {
            $profileRepo = new \App\Repositories\RecruiterRepository();
            $profile = $profileRepo->getRecruiterWithUserDetails($user['id']);
        }

        return ResponseBuilder::ok([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'phone' => $user['phone'],
                'status' => $user['status'],
                'email_verified' => $user['email_verified'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at']
            ],
            'profile' => $profile
        ]);
    }

    private function validateRegistrationData(array $data): array
    {
        $errors = [];

        // Validate required fields
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!$this->validator->isValidEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }

        if (empty($data['user_type']) || !in_array($data['user_type'], ['jobseeker', 'recruiter', 'admin'])) {
            $errors['user_type'] = 'User type must be either jobseeker, recruiter, or admin';
        }

        if ($data['user_type'] !== 'admin' && empty($data['name'] ?? $data['company_name'])) {
            $errors['name'] = $data['user_type'] === 'jobseeker' ? 'Name is required' : 'Company name is required';
        }

        if (!empty($data['phone']) && !$this->validator->isValidPhone($data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }

        return $errors;
    }
}