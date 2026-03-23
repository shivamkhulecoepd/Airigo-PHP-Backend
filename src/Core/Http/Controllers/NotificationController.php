<?php

namespace App\Core\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use Firebase\FirebaseNotificationService;
use App\Core\Utils\ResponseBuilder;
use Psr\Http\Message\ServerRequestInterface;

class NotificationController extends BaseController
{
    private FirebaseNotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new FirebaseNotificationService();
    }

    /**
     * Store FCM token for user
     */
    public function storeFcmToken(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['fcm_token'])) {
            return ResponseBuilder::badRequest([
                'message' => 'FCM token is required'
            ]);
        }

        $deviceType = $data['device_type'] ?? 'mobile';
        $deviceInfo = $data['device_info'] ?? null;

        $result = $this->notificationService->storeUserToken(
            $user['id'], 
            $data['fcm_token'], 
            $deviceType
        );

        if ($result) {
            return ResponseBuilder::ok([
                'message' => 'FCM token stored successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to store FCM token'
        ]);
    }

    /**
     * Remove FCM token (logout/unregister device)
     */
    public function removeFcmToken(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        if (empty($data['fcm_token'])) {
            return ResponseBuilder::badRequest([
                'message' => 'FCM token is required'
            ]);
        }

        $result = $this->notificationService->removeUserToken($data['fcm_token']);

        if ($result) {
            return ResponseBuilder::ok([
                'message' => 'FCM token removed successfully'
            ]);
        }

        return ResponseBuilder::serverError([
            'message' => 'Failed to remove FCM token'
        ]);
    }

    /**
     * Get user's FCM tokens
     */
    public function getUserTokens(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        try {
            // This would require a method to fetch tokens from database
            // For now returning placeholder
            return ResponseBuilder::ok([
                'tokens' => [],
                'message' => 'User tokens retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to retrieve user tokens',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);
        
        $notificationData = [
            'title' => $data['title'] ?? 'Test Notification',
            'body' => $data['body'] ?? 'This is a test notification from Airigo Jobs'
        ];

        $customData = $data['data'] ?? [];

        try {
            $result = $this->notificationService->sendToUser(
                $user['id'], 
                $notificationData, 
                $customData
            );

            if ($result) {
                return ResponseBuilder::ok([
                    'message' => 'Test notification sent successfully'
                ]);
            } else {
                return ResponseBuilder::ok([
                    'message' => 'Test attempted but no valid tokens found for user. This is expected in Postman testing.'
                ]);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup invalid FCM tokens (admin only)
     */
    public function cleanupInvalidTokens(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        try {
            $deletedCount = $this->notificationService->cleanupInvalidTokens();
            
            return ResponseBuilder::ok([
                'message' => 'Invalid tokens cleanup completed',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to cleanup invalid tokens',
                'error' => $e->getMessage()
            ]);
        }
    }
}