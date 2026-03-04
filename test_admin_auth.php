<?php
/**
 * Test Admin Registration and Login
 * 
 * This script demonstrates how to register and login as an admin
 */

require_once __DIR__ . '/src/bootstrap.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$baseUrl = 'http://localhost:8000'; // Change if needed
$client = new Client(['base_uri' => $baseUrl]);

echo "=== Admin Registration and Login Test ===\n\n";

try {
    // 1. Register Admin User
    echo "1. Registering Admin User...\n";
    $registerResponse = $client->post('/api/auth/register', [
        'json' => [
            'email' => 'admin@airigo.com',
            'password' => 'Admin@12345',
            'user_type' => 'admin',
            'phone' => '+1234567890'
        ]
    ]);
    
    $registerData = json_decode($registerResponse->getBody(), true);
    echo "✅ Registration successful!\n";
    echo "User ID: " . $registerData['user']['id'] . "\n";
    echo "Email: " . $registerData['user']['email'] . "\n";
    echo "User Type: " . $registerData['user']['user_type'] . "\n\n";
    
    // 2. Login as Admin
    echo "2. Logging in as Admin...\n";
    $loginResponse = $client->post('/api/auth/login', [
        'json' => [
            'email' => 'admin@airigo.com',
            'password' => 'Admin@12345'
        ]
    ]);
    
    $loginData = json_decode($loginResponse->getBody(), true);
    $accessToken = $loginData['tokens']['access_token'];
    
    echo "✅ Login successful!\n";
    echo "Access Token: " . substr($accessToken, 0, 50) . "...\n\n";
    
    // 3. Access Admin Dashboard
    echo "3. Accessing Admin Dashboard...\n";
    $dashboardResponse = $client->get('/api/admin/dashboard/full-stats', [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken
        ]
    ]);
    
    $dashboardData = json_decode($dashboardResponse->getBody(), true);
    echo "✅ Admin Dashboard Access Successful!\n";
    echo "Total Users: " . $dashboardData['stats']['users']['total'] . "\n";
    echo "Total Jobs: " . $dashboardData['stats']['jobs']['total'] . "\n";
    echo "Total Applications: " . $dashboardData['stats']['applications']['total'] . "\n\n";
    
    echo "=== Test Completed Successfully! ===\n";
    echo "Admin user can now access all admin endpoints.\n";
    
} catch (RequestException $e) {
    if ($e->getResponse()) {
        $errorData = json_decode($e->getResponse()->getBody(), true);
        echo "❌ Error: " . $errorData['message'] . "\n";
        if (isset($errorData['errors'])) {
            foreach ($errorData['errors'] as $field => $error) {
                echo "   - $field: $error\n";
            }
        }
    } else {
        echo "❌ Request failed: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
}