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
            error_log("Firebase Storage: Starting upload process");
            error_log("Firebase Storage: Local file path: {$localFilePath}");
            error_log("Firebase Storage: Remote file name: {$remoteFileName}");
            
            // Check if local file exists
            if (!file_exists($localFilePath)) {
                error_log("Firebase Storage: Local file does not exist: {$localFilePath}");
                throw new \Exception("Local file does not exist: {$localFilePath}");
            }
            
            error_log("Firebase Storage: File size: " . filesize($localFilePath) . " bytes");

            // Determine content type if not provided
            if (!$contentType) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $contentType = finfo_file($finfo, $localFilePath);
                finfo_close($finfo);
            }
            
            error_log("Firebase Storage: Content type: {$contentType}");

            // Prepare remote file path
            $remotePath = "{$this->uploadDir}/{$remoteFileName}";
            error_log("Firebase Storage: Remote path: {$remotePath}");

            // Get access token for authentication
            $accessToken = $this->getAccessToken();
            
            if (!$accessToken) {
                error_log("Firebase Storage: Failed to get access token - upload aborted");
                return null;
            }
            
            error_log("Firebase Storage: Access token obtained successfully");

            // Upload file to Firebase Storage
            $encodedRemotePath = urlencode($remotePath);
            $url = "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$encodedRemotePath}";
            
            error_log("Firebase Storage: Uploading to URL: {$url}");
            error_log("Firebase Storage: Bucket name: {$this->bucketName}");

            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => $contentType,
                    'X-Upload-Content-Type' => $contentType
                ],
                'body' => fopen($localFilePath, 'rb')
            ]);
            
            error_log("Firebase Storage: HTTP response status: " . $response->getStatusCode());

            $responseData = json_decode($response->getBody(), true);
            
            error_log("Firebase Storage: Upload response: " . json_encode($responseData));

            // Return public URL - remotePath already includes uploadDir
            $fileUrl = $this->getFileUrl($remotePath, false);
            error_log("Firebase Storage: Generated URL: {$fileUrl}");
            error_log("Firebase Storage: Upload completed successfully");
            
            return $fileUrl;
        } catch (\Exception $e) {
            error_log("Firebase Storage upload error: " . $e->getMessage());
            error_log("Firebase Storage stack trace: " . $e->getTraceAsString());
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
    public function deleteFile(string $fileIdentifier): bool
    {
        try {
            // Extract file path from URL if it's a full URL
            $remotePath = $fileIdentifier;
            
            // If it's a URL, extract the file path
            if (strpos($fileIdentifier, 'http') === 0) {
                // Parse URL to get the file path
                $parsedUrl = parse_url($fileIdentifier);
                if (isset($parsedUrl['path'])) {
                    // URL decode and extract path after '/o/'
                    $decodedPath = urldecode($parsedUrl['path']);
                    // Extract path after '/b/{bucket}/o/'
                    if (preg_match('#/b/[^/]+/o/(.+)#', $decodedPath, $matches)) {
                        $remotePath = $matches[1];
                    }
                }
            }
            
            error_log("Firebase Storage: Deleting file: {$remotePath}");

            // Get access token for authentication
            $accessToken = $this->getAccessToken();
            
            if (!$accessToken) {
                error_log("Firebase Storage: Failed to get access token for deletion");
                return false;
            }

            // Delete file from Firebase Storage
            $encodedPath = urlencode($remotePath);
            $response = $this->httpClient->delete(
                "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o?name={$encodedPath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}"
                    ]
                ]
            );

            $statusCode = $response->getStatusCode();
            error_log("Firebase Storage: Delete response status: {$statusCode}");
            
            return $statusCode === 200 || $statusCode === 204;
        } catch (\Exception $e) {
            error_log("Firebase Storage delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public URL for file
     * @param string $remoteFileName The full remote path (may already include uploadDir)
     * @param bool $addUploadDir Whether to prepend uploadDir (default: true for backward compatibility)
     */
    public function getFileUrl(string $remoteFileName, bool $addUploadDir = true): string
    {
        // Only add uploadDir if the path doesn't already start with it
        $fullPath = $remoteFileName;
        if ($addUploadDir && strpos($remoteFileName, "{$this->uploadDir}/") !== 0) {
            $fullPath = "{$this->uploadDir}/{$remoteFileName}";
        }
        
        $encodedPath = urlencode($fullPath);
        return "https://firebasestorage.googleapis.com/v0/b/{$this->bucketName}/o/{$encodedPath}?alt=media";
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
     * Get access token for Firebase API using OAuth2
     */
    private function getAccessToken(): ?string
    {
        try {
            // Get service account credentials from config
            $privateKey = AppConfig::get('firebase.private_key');
            $clientEmail = AppConfig::get('firebase.client_email');
            $tokenUri = AppConfig::get('firebase.token_uri');
            
            if (!$privateKey || !$clientEmail || !$tokenUri) {
                error_log("Firebase Storage: Missing credentials configuration");
                return null;
            }
            
            // Decode private key if it contains \n
            $privateKey = str_replace('\\n', "\n", $privateKey);
            
            // Create JWT for OAuth2 token request
            $now = time();
            $expiry = $now + 3600; // Token valid for 1 hour
            
            $header = json_encode([
                'typ' => 'JWT',
                'alg' => 'RS256'
            ]);
            
            $payload = json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/devstorage.full_control',
                'aud' => $tokenUri,
                'exp' => $expiry,
                'iat' => $now
            ]);
            
            // Base64 encode header and payload
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            
            // Create signature
            $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;
            $signature = '';
            openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            // Create JWT
            $jwt = $signatureInput . '.' . $base64UrlSignature;
            
            // Exchange JWT for access token
            $response = $this->httpClient->post($tokenUri, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['access_token'])) {
                error_log("Firebase Storage: Successfully obtained access token");
                return $data['access_token'];
            }
            
            error_log("Firebase Storage: No access token in response: " . json_encode($data));
            return null;
            
        } catch (\Exception $e) {
            error_log("Firebase Storage: Error getting access token: " . $e->getMessage());
            error_log("Firebase Storage stack trace: " . $e->getTraceAsString());
            return null;
        }
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