<?php

namespace App\Core\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\Config\AppConfig;

class EmailService
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $fromEmail;
    private string $fromName;
    private bool $smtpAuth;
    private string $smtpSecurity; // 'tls', 'ssl', or 'none'

    public function __construct()
    {
        // Load SMTP configuration from environment variables
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? '';
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->fromEmail = $_ENV['FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'] ?? 'noreply@airigojobs.com';
        $this->fromName = $_ENV['FROM_NAME'] ?? 'Airigo Jobs';
        $this->smtpAuth = filter_var($_ENV['SMTP_AUTH'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->smtpSecurity = $_ENV['SMTP_ENCRYPTION'] ?? 'tls'; // tls, ssl, or none
    }

    /**
     * Send a password reset email
     */
    public function sendPasswordResetEmail(string $toEmail, string $userName, string $resetToken): bool
    {
        $subject = 'Password Reset Request - Airigo Jobs';
        $body = $this->buildPasswordResetEmailBody($userName, $resetToken);
        
        return $this->sendEmail($toEmail, $subject, $body);
    }

    /**
     * Send a welcome email
     */
    public function sendWelcomeEmail(string $toEmail, string $userName): bool
    {
        $subject = 'Welcome to Airigo Jobs!';
        $body = $this->buildWelcomeEmailBody($userName);
        
        return $this->sendEmail($toEmail, $subject, $body);
    }

    /**
     * Generic email sending method using SMTP
     */
    public function sendEmail(string $toEmail, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = $this->smtpAuth;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = $this->smtpSecurity === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body); // Plain text version

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build password reset email body
     */
    private function buildPasswordResetEmailBody(string $userName, string $resetToken): string
    {
        $appUrl = $_ENV['APP_URL'] ?? 'localhost:8000';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .content { padding: 20px 0; }
                .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 12px; }
                .button { display: inline-block; padding: 12px 30px; margin: 20px 0; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                .token-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; font-weight: bold; font-size: 18px; letter-spacing: 1px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Airigo Jobs</h1>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello {$userName},</p>
                    <p>We received a request to reset your password for your Airigo Jobs account.</p>
                    
                    <div class='token-box'>
                        {$resetToken}
                    </div>
                    
                    <p>You can use this token to reset your password. The token is valid for 24 hours.</p>
                    
                    <a href='http://{$appUrl}/reset-password?token={$resetToken}' class='button'>Reset Password</a>
                    
                    <p>If you didn't request this, please ignore this email.</p>
                    
                    <p>Best regards,<br>The Airigo Jobs Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>&copy; 2026 Airigo Jobs. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build welcome email body
     */
    private function buildWelcomeEmailBody(string $userName): string
    {
        $appUrl = $_ENV['APP_URL'] ?? 'localhost:8000';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .content { padding: 20px 0; }
                .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 12px; }
                .button { display: inline-block; padding: 12px 30px; margin: 20px 0; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Airigo Jobs!</h1>
                </div>
                <div class='content'>
                    <p>Hello {$userName},</p>
                    <p>Welcome to Airigo Jobs! Your account has been successfully created.</p>
                    <p>Thank you for joining our platform. You can now:</p>
                    <ul>
                        <li>Search for jobs that match your skills</li>
                        <li>Apply to positions that interest you</li>
                        <li>Create and manage your professional profile</li>
                        <li>Save jobs to your wishlist</li>
                    </ul>
                    
                    <a href='http://{$appUrl}' class='button'>Get Started</a>
                    
                    <p>If you have any questions, feel free to contact our support team.</p>
                    
                    <p>Best regards,<br>The Airigo Jobs Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>&copy; 2026 Airigo Jobs. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Check if email service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->smtpUsername) && !empty($this->smtpPassword);
    }
    
    /**
     * Getters for configuration values
     */
    public function getSmtpHost(): string
    {
        return $this->smtpHost;
    }
    
    public function getSmtpPort(): int
    {
        return $this->smtpPort;
    }
    
    public function getSmtpUsername(): string
    {
        return $this->smtpUsername;
    }
    
    public function getSmtpPassword(): string
    {
        return $this->smtpPassword;
    }
    
    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }
    
    public function getFromName(): string
    {
        return $this->fromName;
    }
    
    public function getSmtpAuth(): bool
    {
        return $this->smtpAuth;
    }
    
    public function getSmtpSecurity(): string
    {
        return $this->smtpSecurity;
    }
}