<?php

/**
 * Test Email Setup Script
 * 
 * This script tests if the email configuration is working properly
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Utils\EmailService;

echo "=== Email Service Configuration Test ===\n\n";

// Initialize email service
$emailService = new EmailService();

echo "Email Service Configuration:\n";
echo "SMTP Host: " . $emailService->getSmtpHost() . "\n";
echo "SMTP Port: " . $emailService->getSmtpPort() . "\n";
echo "SMTP Username: " . (!empty($emailService->getSmtpUsername()) ? 'SET' : 'NOT SET') . "\n";
echo "From Email: " . $emailService->getFromEmail() . "\n";
echo "From Name: " . $emailService->getFromName() . "\n";
echo "SMTP Auth: " . ($emailService->getSmtpAuth() ? 'TRUE' : 'FALSE') . "\n";
echo "SMTP Security: " . $emailService->getSmtpSecurity() . "\n";

echo "\nIs email service configured properly? " . ($emailService->isConfigured() ? 'YES' : 'NO') . "\n";

if (!$emailService->isConfigured()) {
    echo "\n❌ Email service is not properly configured.\n";
    echo "Please set SMTP_USERNAME and SMTP_PASSWORD in your .env file.\n";
    echo "For Gmail users, make sure to use an App Password, not your regular password.\n";
    exit(1);
}

echo "\n✅ Email service is configured.\n";

// Test email sending (use a test email address)
$testEmail = $emailService->getFromEmail();  // Use the configured from email as a test
if (empty($testEmail) || $testEmail === 'noreply@airigojobs.com') {
    echo "\n⚠️  Using default email address. Please configure FROM_EMAIL in your .env file.\n";
    echo "For testing, enter an email address to send test message to (or press Enter to skip): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (!empty($input) && filter_var($input, FILTER_VALIDATE_EMAIL)) {
        $testEmail = $input;
    } else {
        echo "Skipping test email...\n";
        exit(0);
    }
}

echo "\nSending test email to: $testEmail\n";

// Create a simple test message
$subject = 'Airigo Jobs - Email Configuration Test';
$body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .content { padding: 20px 0; }
        .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Airigo Jobs</h1>
            <h2>Email Configuration Test</h2>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>This is a test message to verify that your email configuration is working properly.</p>
            <p>Your Airigo Jobs backend is correctly configured to send emails.</p>
            <p>Best regards,<br>The Airigo Jobs Team</p>
        </div>
        <div class="footer">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>&copy; 2026 Airigo Jobs. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

$result = $emailService->sendEmail($testEmail, 'Test User', $subject, $body);

if ($result) {
    echo "✅ Test email sent successfully!\n";
    echo "Check your inbox (and spam folder) for the test email.\n";
} else {
    echo "❌ Failed to send test email.\n";
    echo "Common issues:\n";
    echo "  - Incorrect SMTP credentials\n";
    echo "  - Firewall blocking SMTP ports\n";
    echo "  - Email provider security settings\n";
    echo "  - Invalid email address\n";
}

echo "\n=== Test Complete ===\n";