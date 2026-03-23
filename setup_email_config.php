<?php

/**
 * Email Configuration Setup Script
 * 
 * This script helps configure email settings for password reset functionality
 */

echo "=== Airigo Job Portal - Email Configuration Setup ===\n\n";

echo "Current email configuration in .env file:\n";

// Check if .env file exists
if (!file_exists('.env')) {
    echo "❌ .env file not found!\n";
    echo "Please copy .env.example to .env first.\n";
    exit(1);
}

$envContent = file_get_contents('.env');
$lines = explode("\n", $envContent);

$emailConfigs = [
    'SMTP_HOST',
    'SMTP_PORT', 
    'SMTP_USERNAME',
    'SMTP_PASSWORD',
    'FROM_EMAIL',
    'FROM_NAME'
];

echo "\n--- Current Email Settings ---\n";
foreach ($lines as $line) {
    foreach ($emailConfigs as $config) {
        if (strpos($line, $config . '=') === 0) {
            $value = substr($line, strlen($config) + 1);
            $displayValue = empty(trim($value)) ? '(not set)' : $value;
            echo sprintf("%-15s: %s\n", $config, $displayValue);
        }
    }
}

echo "\n--- Email Configuration Guide ---\n";
echo "To enable password reset emails, configure these settings in your .env file:\n\n";

echo "For Gmail:\n";
echo "SMTP_HOST=smtp.gmail.com\n";
echo "SMTP_PORT=587\n";
echo "SMTP_USERNAME=your-email@gmail.com\n";
echo "SMTP_PASSWORD=your-app-password  # Use App Password, not regular password\n";
echo "FROM_EMAIL=your-email@gmail.com\n";
echo "FROM_NAME=\"Airigo Jobs\"\n\n";

echo "For Outlook/Hotmail:\n";
echo "SMTP_HOST=smtp-mail.outlook.com\n";
echo "SMTP_PORT=587\n";
echo "SMTP_USERNAME=your-email@outlook.com\n";
echo "SMTP_PASSWORD=your-password\n";
echo "FROM_EMAIL=your-email@outlook.com\n";
echo "FROM_NAME=\"Airigo Jobs\"\n\n";

echo "For Yahoo Mail:\n";
echo "SMTP_HOST=smtp.mail.yahoo.com\n";
echo "SMTP_PORT=587\n";
echo "SMTP_USERNAME=your-email@yahoo.com\n";
echo "SMTP_PASSWORD=your-app-password\n";
echo "FROM_EMAIL=your-email@yahoo.com\n";
echo "FROM_NAME=\"Airigo Jobs\"\n\n";

echo "Important Notes:\n";
echo "1. For Gmail, you need to use an App Password, not your regular password\n";
echo "2. To generate a Gmail App Password:\n";
echo "   - Go to Google Account settings\n";
echo "   - Security > 2-Step Verification > App passwords\n";
echo "   - Generate a password for 'Mail'\n";
echo "3. Make sure less secure app access is enabled if using other providers\n\n";

echo "After updating your .env file, the password reset functionality will send emails.\n";
echo "For testing purposes, the reset token will also be returned in the API response.\n\n";

echo "--- Testing Password Reset ---\n";
echo "1. Make sure your database has the password_reset_tokens table (should exist if you ran db_manager.php)\n";
echo "2. Register a user if you haven't already\n";
echo "3. Call POST /api/auth/forgot-password with email or phone\n";
echo "4. Check your email for the reset token\n";
echo "5. Use the token in POST /api/auth/reset-password\n\n";

echo "=== Setup Complete ===\n";