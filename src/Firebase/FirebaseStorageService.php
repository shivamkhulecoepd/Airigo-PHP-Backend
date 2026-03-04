<?php

namespace Firebase;

use GuzzleHttp\Client;
use App\Config\AppConfig;

class FirebaseStorageService
{
    private Client $httpClient;
    private string $projectId;
    private string $bucketName;
    private string $uploadDir;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        
        $this->projectId = AppConfig::get('firebase.project_id');
        $this->bucketName = AppConfig::get('firebase.storage_bucket');
        $this->uploadDir = 'uploads'; // Default upload directory
    }

    /**
     * Upload file to Firebase Storage
     */
    public function uploadFile(string $localFilePath, string $remoteFileName, string $contentType = null): ?string
    {
        try {
            // Check if local file exists
            if (!file_exists($localFilePath)) {
                throw new \Exception("Local file does not exist: {$localFilePath}");
            }

            // Determine content type if not provided
            if (!$contentType) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $contentType = finfo_file($finfo, $localFilePath);
                finfo_close($finfo);
            }

            // Prepare remote file path
            $remotePath = "{$this->uploadDir}/{$remoteFileName}";

            // Get access token for authentication
            $accessToken = $this->getAccessToken();

            // Upload file to Firebase Storage
            $response = $this->httpClient->post(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$remotePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => $contentType,
                        'X-Upload-Content-Type' => $contentType
                    ],
                    'body' => fopen($localFilePath, 'rb')
                ]
            );

            $responseData = json_decode($response->getBody(), true);

            // Return public URL
            return $this->getFileUrl($remotePath);
        } catch (\Exception $e) {
            error_log("Firebase Storage upload error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload file content directly
     */
    public function uploadFileContent(string $fileContent, string $remoteFileName, string $contentType = null): ?string
    {
        try {
            // Determine content type if not provided
            if (!$contentType) {
                // Default to application/octet-stream if content type is not provided
                $contentType = 'application/octet-stream';
            }

            // Prepare remote file path
            $remotePath = "{$this->uploadDir}/{$remoteFileName}";

            // Get access token for authentication
            $accessToken = $this->getAccessToken();

            // Upload file content to Firebase Storage
            $response = $this->httpClient->post(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$remotePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => $contentType,
                        'X-Upload-Content-Type' => $contentType
                    ],
                    'body' => $fileContent
                ]
            );

            $responseData = json_decode($response->getBody(), true);

            // Return public URL
            return $this->getFileUrl($remotePath);
        } catch (\Exception $e) {
            error_log("Firebase Storage upload error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete file from Firebase Storage
     */
    public function deleteFile(string $remoteFileName): bool
    {
        try {
            // Prepare remote file path
            $remotePath = "{$this->uploadDir}/{$remoteFileName}";

            // Get access token for authentication
            $accessToken = $this->getAccessToken();

            // Delete file from Firebase Storage
            $response = $this->httpClient->delete(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$remotePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}"
                    ]
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("Firebase Storage delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public URL for file
     */
    public function getFileUrl(string $remoteFileName): string
    {
        $remotePath = urlencode("{$this->uploadDir}/{$remoteFileName}");
        return "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o/{$remotePath}?alt=media";
    }

    /**
     * Get download URL with expiration
     */
    public function getDownloadUrl(string $remoteFileName, int $expirationSeconds = 3600): string
    {
        // For Firebase Storage, we typically use the public URL directly
        // In a real implementation, you might generate signed URLs
        return $this->getFileUrl($remoteFileName);
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $remoteFileName): bool
    {
        try {
            $remotePath = urlencode("{$this->uploadDir}/{$remoteFileName}");
            
            $accessToken = $this->getAccessToken();
            
            $response = $this->httpClient->get(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$remotePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}"
                    ]
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(string $remoteFileName): ?array
    {
        try {
            $remotePath = urlencode("{$this->uploadDir}/{$remoteFileName}");
            
            $accessToken = $this->getAccessToken();
            
            $response = $this->httpClient->get(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$remotePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}"
                    ]
                ]
            );

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }

            return null;
        } catch (\Exception $e) {
            error_log("Firebase Storage metadata error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get access token for Firebase API
     */
    private function getAccessToken(): string
    {
        // In a real implementation, you would use Google's service account authentication
        // For now, returning a placeholder - in production, implement proper OAuth2 flow
        return $this->generateAccessToken();
    }

    /**
     * Generate access token using service account
     */
    private function generateAccessToken(): string
    {
        // This is a simplified version - in production, use Google's official library
        // For demonstration purposes only
        return 'fake-access-token-for-demo';
    }

    /**
     * Set upload directory
     */
    public function setUploadDirectory(string $directory): void
    {
        $this->uploadDir = trim($directory, '/');
    }

    /**
     * Get upload directory
     */
    public function getUploadDirectory(): string
    {
        return $this->uploadDir;
    }
}