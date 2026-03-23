<?php

namespace Firebase;

use GuzzleHttp\Client;
use App\Config\AppConfig;
use App\Repositories\UserRepository;
use App\Core\Database\Connection;

class FirebaseNotificationService
{
    private Client $httpClient;
    private string $projectId;
    private string $privateKeyId;
    private string $privateKey;
    private string $clientEmail;
    private string $clientId;
    private string $authUri;
    private string $tokenUri;
    private UserRepository $userRepository;
    private \PDO $connection;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        
        // Load Firebase configuration
        $this->projectId = AppConfig::get('firebase.project_id');
        $this->privateKeyId = AppConfig::get('firebase.private_key_id');
        $this->privateKey = AppConfig::get('firebase.private_key');
        $this->clientEmail = AppConfig::get('firebase.client_email');
        $this->clientId = AppConfig::get('firebase.client_id');
        $this->authUri = AppConfig::get('firebase.auth_uri');
        $this->tokenUri = AppConfig::get('firebase.token_uri');
        
        $this->userRepository = new UserRepository();
        $this->connection = Connection::getInstance();
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(int $userId, array $notificationData, array $data = []): bool
    {
        try {
            // Get user's FCM tokens (would need to be stored in user table or separate tokens table)
            $fcmTokens = $this->getUserFcmTokens($userId);
            
            if (empty($fcmTokens)) {
                error_log("No FCM tokens found for user ID: {$userId}");
                return false;
            }

            $successCount = 0;
            foreach ($fcmTokens as $token) {
                if ($this->sendToDevice($token, $notificationData, $data)) {
                    $successCount++;
                }
            }

            return $successCount > 0;
        } catch (\Exception $e) {
            error_log("Failed to send notification to user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(array $userIds, array $notificationData, array $data = []): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendToUser($userId, $notificationData, $data);
        }
        return $results;
    }

    /**
     * Send notification to all users of a specific type
     */
    public function sendToUserType(string $userType, array $notificationData, array $data = []): array
    {
        try {
            $users = $this->userRepository->findByUserType($userType);
            $userIds = array_column($users, 'id');
            return $this->sendToUsers($userIds, $notificationData, $data);
        } catch (\Exception $e) {
            error_log("Failed to send notification to user type {$userType}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send notification to a specific device token
     */
    public function sendToDevice(string $deviceToken, array $notificationData, array $data = []): bool
    {
        try {
            // Validate token format (basic validation)
            if (empty($deviceToken) || strlen($deviceToken) < 10 || strlen($deviceToken) > 4096) {
                error_log("Invalid FCM token format: token length is invalid");
                return false;
            }

            $accessToken = $this->getAccessToken();
            
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $notificationData['title'] ?? 'Notification',
                        'body' => $notificationData['body'] ?? '',
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'default_channel'
                        ]
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10'
                        ]
                    ]
                ]
            ];

            $response = $this->httpClient->post(
                "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $payload
                ]
            );

            $responseData = json_decode($response->getBody(), true);
            
            // Check if the response indicates success
            if (isset($responseData['name'])) {
                return true;
            }
            
            error_log("FCM response did not contain success indicator: " . json_encode($responseData));
            return false;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle specific FCM errors
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody, true);
            
            // Log the specific error
            error_log("FCM Client Error: " . $responseBody);
            
            // If it's an invalid token error, remove the token from DB
            if (isset($errorData['error']['message']) && 
                (strpos($errorData['error']['message'], 'not a valid FCM registration token') !== false ||
                 strpos($errorData['error']['message'], 'InvalidRegistration') !== false ||
                 strpos($errorData['error']['message'], 'MismatchSenderId') !== false ||
                 strpos($errorData['error']['message'], 'NotRegistered') !== false)) {
                
                // Remove invalid token from database
                $this->removeUserToken($deviceToken);
                error_log("Removed invalid FCM token from database: {$deviceToken}");
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Failed to send notification to device: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple device tokens
     */
    public function sendToDevices(array $deviceTokens, array $notificationData, array $data = []): array
    {
        $results = [];
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendToDevice($token, $notificationData, $data);
        }
        return $results;
    }

    /**
     * Get access token for Firebase API
     */
    private function getAccessToken(): string
    {
        try {
            $jwt = $this->generateJWT();
            
            $response = $this->httpClient->post($this->tokenUri, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]
            ]);

            $tokenData = json_decode($response->getBody(), true);
            return $tokenData['access_token'];
        } catch (\Exception $e) {
            error_log("Failed to get access token: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate JWT token for Firebase authentication
     */
    private function generateJWT(): string
    {
        $now = time();
        $payload = [
            'iss' => $this->clientEmail,
            'sub' => $this->clientEmail,
            'aud' => $this->tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
        ];

        // This is a simplified JWT implementation
        // In production, use a proper JWT library
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $payloadJson = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));
        
        $signature = '';
        openssl_sign("{$base64Header}.{$base64Payload}", $signature, $this->privateKey, 'SHA256');
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Get FCM tokens for a user (placeholder - would need database implementation)
     */
    private function getUserFcmTokens(int $userId): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT token FROM user_fcm_tokens WHERE user_id = ? AND is_active = TRUE LIMIT 1"
            );
            $stmt->execute([$userId]);
            $tokens = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Filter out obviously invalid tokens (for testing purposes)
            $validTokens = [];
            foreach ($tokens as $token) {
                // Skip test tokens that start with 'test_' or are too obviously fake
                if (strpos($token, 'test_') === 0 || 
                    strpos($token, 'invalid_') === 0 || 
                    strlen($token) < 50) {
                    continue;
                }
                $validTokens[] = $token;
            }
            
            return $validTokens;
        } catch (\Exception $e) {
            error_log("Failed to get FCM tokens for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Store FCM token for a user
     */
    public function storeUserToken(int $userId, string $deviceToken, string $deviceType = 'mobile'): bool
    {
        try {
            // First, deactivate any existing tokens for this user
            $deactivateStmt = $this->connection->prepare(
                "UPDATE user_fcm_tokens SET is_active = FALSE, updated_at = NOW() WHERE user_id = ?"
            );
            $deactivateStmt->execute([$userId]);
            
            // Check if token already exists (inactive)
            $stmt = $this->connection->prepare(
                "SELECT id FROM user_fcm_tokens WHERE token = ?"
            );
            $stmt->execute([$deviceToken]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing token with new user and activate it
                $stmt = $this->connection->prepare(
                    "UPDATE user_fcm_tokens SET user_id = ?, device_type = ?, is_active = TRUE, updated_at = NOW() WHERE token = ?"
                );
                return $stmt->execute([$userId, $deviceType, $deviceToken]);
            } else {
                // Insert new token
                $stmt = $this->connection->prepare(
                    "INSERT INTO user_fcm_tokens (user_id, token, device_type, is_active, created_at) VALUES (?, ?, ?, TRUE, NOW())"
                );
                return $stmt->execute([$userId, $deviceToken, $deviceType]);
            }
        } catch (\Exception $e) {
            error_log("Failed to store user token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove FCM token (when user logs out or uninstalls)
     */
    public function removeUserToken(string $deviceToken): bool
    {
        try {
            $stmt = $this->connection->prepare(
                "DELETE FROM user_fcm_tokens WHERE token = ?"
            );
            return $stmt->execute([$deviceToken]);
        } catch (\Exception $e) {
            error_log("Failed to remove user token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove invalid FCM tokens from database (cleanup method)
     */
    public function cleanupInvalidTokens(): int
    {
        try {
            // Remove obviously invalid test tokens
            $stmt = $this->connection->prepare(
                "DELETE FROM user_fcm_tokens WHERE token LIKE 'test_%' OR token LIKE 'invalid_%' OR LENGTH(token) < 50"
            );
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("Failed to cleanup invalid tokens: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all active FCM tokens for a user (with validation)
     */
    public function getUserActiveTokens(int $userId): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT token, device_type FROM user_fcm_tokens WHERE user_id = ? AND is_active = TRUE"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Failed to get active tokens for user {$userId}: " . $e->getMessage());
            return [];
        }
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

        return $this->sendToUser($userId, $notificationData, $data);
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

        return $this->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send job application status update notification
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
            'pending' => "Your application for '{$jobTitle}' at {$companyName} is now under review."
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

        return $this->sendToUser($userId, $notificationData, $data);
    }

    /**
     * Send new job application notification to recruiter
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

        return $this->sendToUser($recruiterId, $notificationData, $data);
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

        return $this->sendToUser($userId, $notificationData, $data);
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

        return $this->sendToUser($userId, $notificationData, $data);
    }
}