<?php

namespace App\Core\Auth;

use App\Core\Database\Connection;
use App\Core\Auth\JWTManager;
use App\Repositories\UserRepository;
use App\Repositories\PasswordResetTokenRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Config\AppConfig;
use Ramsey\Uuid\Uuid;
use Firebase\FirebaseNotificationService;

class AuthService
{
    private UserRepository $userRepository;
    private PasswordResetTokenRepository $passwordResetTokenRepository;
    private JWTManager $jwtManager;
    private FirebaseNotificationService $notificationService;
    private \App\Repositories\JobseekerRepository $jobseekerRepository;
    private \App\Repositories\RecruiterRepository $recruiterRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->passwordResetTokenRepository = new PasswordResetTokenRepository();
        $this->jwtManager = new JWTManager();
        $this->notificationService = new FirebaseNotificationService();
        $this->jobseekerRepository = new \App\Repositories\JobseekerRepository();
        $this->recruiterRepository = new \App\Repositories\RecruiterRepository();
    }

    /**
     * Register a new user
     */
    public function register(array $userData): array
    {
        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($userData['email']);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Prepare user data for insertion
        $userData['password_hash'] = $hashedPassword;
        unset($userData['password']); // Remove plain password
        
        if (isset($userData['confirm_password'])) {
            unset($userData['confirm_password']);
        }

        // Insert user
        $userId = $this->userRepository->create($userData);

        if (!$userId) {
            return ['success' => false, 'message' => 'Registration failed'];
        }

        // Create profile based on user type (admins don't need profiles)
        if ($userData['user_type'] !== 'admin') {
            $profileRepo = $userData['user_type'] === 'jobseeker' ? 
                new \App\Repositories\JobseekerRepository() : 
                new \App\Repositories\RecruiterRepository();

            $profileData = [
                'user_id' => $userId,
                'name' => $userData['name'] ?? ($userData['company_name'] ?? ''),
                'phone' => $userData['phone'] ?? null,
                'location' => $userData['location'] ?? null,
            ];

            // Add specific fields based on user type
            if ($userData['user_type'] === 'jobseeker') {
                $profileData = array_merge($profileData, [
                    'qualification' => $userData['qualification'] ?? null,
                    'experience' => $userData['experience'] ?? 0,
                    'date_of_birth' => $userData['date_of_birth'] ?? null,
                    'resume_url' => $userData['resume_url'] ?? null,
                    'resume_filename' => $userData['resume_filename'] ?? null,
                    'profile_image_url' => $userData['profile_image_url'] ?? null,
                    'skills' => json_encode($userData['skills'] ?? []),
                    'bio' => $userData['bio'] ?? null
                ]);
            } else { // recruiter
                $profileData = array_merge($profileData, [
                    'company_name' => $userData['company_name'] ?? '',
                    'designation' => $userData['designation'] ?? null,
                    'photo_url' => $userData['photo_url'] ?? null,
                    'id_card_url' => $userData['id_card_url'] ?? null,
                    'approval_status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null
                ]);
            }

            $profileRepo->create($profileData);
        }

        // Generate tokens
        $user = $this->userRepository->findById($userId);
        $accessToken = $this->jwtManager->generateAccessToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type']
        ]);

        $refreshToken = $this->jwtManager->generateRefreshToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type']
        ]);

        // Send welcome notification
        try {
            $userName = $userData['name'] ?? $userData['company_name'] ?? 'User';
            $this->notificationService->sendWelcomeNotification($userId, $userName, $userData['user_type']);
        } catch (\Exception $e) {
            // Log error but don't fail registration
            error_log("Failed to send welcome notification: " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'status' => $user['status'],
            ],
            'tokens' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtManager->getTokenExpiry()
            ]
        ];
    }

    /**
     * Authenticate user credentials
     */
    public function login(string $identifier, string $password): array
    {
        // Try to find user by email or phone number
        $user = $this->userRepository->findByEmailOrPhone($identifier);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is inactive'];
        }

        // Generate tokens
        $accessToken = $this->jwtManager->generateAccessToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type']
        ]);

        $refreshToken = $this->jwtManager->generateRefreshToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type']
        ]);

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'status' => $user['status']
            ],
            'tokens' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtManager->getTokenExpiry()
            ]
        ];
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        if (!$this->jwtManager->validateRefreshToken($refreshToken)) {
            return ['success' => false, 'message' => 'Invalid or expired refresh token'];
        }

        $decodedToken = $this->jwtManager->decodeRefreshToken($refreshToken);
        $userId = $decodedToken->data->id;

        $user = $this->userRepository->findById($userId);

        if (!$user || $user['status'] !== 'active') {
            return ['success' => false, 'message' => 'User not found or inactive'];
        }

        // Generate new access token
        $newAccessToken = $this->jwtManager->generateAccessToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type']
        ]);

        return [
            'success' => true,
            'message' => 'Token refreshed successfully',
            'tokens' => [
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtManager->getTokenExpiry()
            ]
        ];
    }

    /**
     * Logout user (invalidate tokens)
     */
    public function logout(): array
    {
        // In a real implementation, you might store tokens in a blacklist
        // For now, just return success
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }

    /**
     * Get authenticated user from token
     */
    public function getUserFromToken(string $token): ?array
    {
        $decoded = $this->jwtManager->decodeAccessToken($token);
        
        if (!$decoded || !isset($decoded->data->id)) {
            return null;
        }

        return $this->userRepository->findById($decoded->data->id);
    }

    /**
     * Change user password
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $result = $this->userRepository->update($userId, [
            'password_hash' => $hashedNewPassword
        ]);

        if ($result) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        }

        return ['success' => false, 'message' => 'Failed to change password'];
    }

    /**
     * Forgot password
     */
    public function forgotPassword(string $identifier): array
    {
        // Try to find user by email or phone number
        $user = $this->userRepository->findByEmailOrPhone($identifier);

        if (!$user) {
            // Return success even if user doesn't exist to prevent enumeration
            return [
                'success' => true,
                'message' => 'If account exists, password reset instructions will be sent'
            ];
        }

        try {
            // Delete any existing tokens for this user
            $this->passwordResetTokenRepository->deleteByUserId($user['id']);
            
            // Generate reset token
            $resetToken = Uuid::uuid4()->toString();
            
            // Create token in database (expires in 24 hours)
            $tokenId = $this->passwordResetTokenRepository->createToken($user['id'], $resetToken, 24);
            
            if (!$tokenId) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate reset token'
                ];
            }

            // In a real implementation, send email/SMS with reset link
            // Send notification with reset token
            try {
                $userName = $user['user_type'] === 'jobseeker' ? 
                    ($this->jobseekerRepository->findByUserId($user['id'])['name'] ?? 'User') : 
                    ($this->recruiterRepository->findByUserId($user['id'])['company_name'] ?? 'User');
                
                $this->notificationService->sendPasswordResetNotification(
                    $user['id'], 
                    $userName, 
                    $resetToken, 
                    $identifier
                );
            } catch (\Exception $e) {
                // Log error but don't fail the process
                error_log("Failed to send password reset notification: " . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Password reset instructions sent successfully',
                'reset_token' => $resetToken  // For testing purposes only
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process password reset request'
            ];
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(string $resetToken, string $newPassword): array
    {
        // Validate password strength
        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long'
            ];
        }

        // Find valid reset token
        $tokenRecord = $this->passwordResetTokenRepository->findByToken($resetToken);
        
        if (!$tokenRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ];
        }

        try {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update user's password
            $result = $this->userRepository->update($tokenRecord['user_id'], [
                'password_hash' => $hashedPassword
            ]);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Failed to update password'
                ];
            }
            
            // Mark token as used
            $this->passwordResetTokenRepository->markAsUsed($tokenRecord['id']);
            
            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reset password'
            ];
        }
    }
}