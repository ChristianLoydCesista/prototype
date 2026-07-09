<?php

/**
 * Mailer Class - Handles sending emails for verification and notifications
 * 
 * This class provides email functionality
 * For production, configure SMTP settings in config/constants.php
 */

require_once __DIR__ . '/../config/constants.php';

class Mailer
{
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $useSMTP;

    public function __construct()
    {
        // SMTP configuration from constants
        $this->smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $this->smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $this->smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $this->fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@arteche.gov.ph';
        $this->fromName = defined('FROM_NAME') ? FROM_NAME : 'Arteche Community System';
        $this->useSMTP = defined('SMTP_ENABLED') && SMTP_ENABLED;
    }

    /**
     * Send verification email to citizen
     */
    public function sendVerificationEmail($email, $name, $verificationCode)
    {
        $subject = "Verify Your Arteche Citizen Account";
        $body = $this->getVerificationEmailTemplate($name, $verificationCode);
        return $this->send($email, $subject, $body, true);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $name, $resetLink)
    {
        $subject = "Reset Your Arteche Citizen Password";
        $body = $this->getPasswordResetEmailTemplate($name, $resetLink);
        return $this->send($email, $subject, $body, true);
    }

    /**
     * Send welcome email after verification
     */
    public function sendWelcomeEmail($email, $name)
    {
        $subject = "Welcome to Arteche Citizen Portal!";
        $body = $this->getWelcomeEmailTemplate($name);
        return $this->send($email, $subject, $body, true);
    }

    /**
     * Send document request confirmation
     */
    public function sendDocumentRequestConfirmation($email, $name, $requestNumber, $documentType)
    {
        $subject = "Document Request Confirmed - " . $requestNumber;
        $body = $this->getDocumentRequestTemplate($name, $requestNumber, $documentType);
        return $this->send($email, $subject, $body, true);
    }

    /**
     * Main send function
     */
    public function send($to, $subject, $body, $isHTML = true)
    {
        if (!$this->useSMTP) {
            $this->logEmail($to, $subject, $body);
            return true;
        }

        if (empty($this->smtpUsername) || empty($this->smtpPassword)) {
            error_log("SMTP is enabled but username/password is missing.");
            $this->logEmail($to, $subject, $body);
            return false;
        }

        // Try to use PHPMailer if available
        if ($this->loadPHPMailer()) {
            return $this->sendWithPHPMailer($to, $subject, $body, $isHTML);
        }



        // Fallback to PHP mail()
        return $this->sendWithMail($to, $subject, $body, $isHTML);
    }

    /**
     * Try to load PHPMailer
     */
    private function loadPHPMailer()
    {
        $autoloadPaths = [
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../../../vendor/autoload.php',
        ];

        foreach ($autoloadPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
            }
        }

        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }

    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer($to, $subject, $body, $isHTML)
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUsername;
            $mail->Password   = $this->smtpPassword;
            $mail->Port       = $this->smtpPort;
            $mail->CharSet    = 'UTF-8';

            if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $fromEmail = !empty($this->fromEmail) ? $this->fromEmail : $this->smtpUsername;

            $mail->setFrom($fromEmail, $this->fromName);
            $mail->addAddress($to);

            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            if ($isHTML) {
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            }

            $mail->send();

            error_log("Email sent successfully to: " . $to);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            $this->logEmail($to, $subject, $body);
            return false;
        } catch (\Exception $e) {
            error_log("General Mailer Error: " . $e->getMessage());
            $this->logEmail($to, $subject, $body);
            return false;
        }
    }

    /**
     * Send email using PHP mail()
     */
    private function sendWithMail($to, $subject, $body, $isHTML)
    {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";

        if ($isHTML) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }

        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        $result = mail($to, $subject, $body, $headers);

        if (!$result) {
            error_log("mail() failed for: $to");
            $this->logEmail($to, $subject, $body);
        }

        return $result;
    }

    /**
     * Log email to file (for demo/development)
     */
    private function logEmail($to, $subject, $body)
    {
        $logDir = dirname(__DIR__) . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/emails.log';
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = "====================================================================\n";
        $logEntry .= "Timestamp: $timestamp\n";
        $logEntry .= "To: $to\n";
        $logEntry .= "Subject: $subject\n";
        $logEntry .= "--------------------------------------------------------------------\n";
        $logEntry .= "Body:\n$body\n\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        error_log("DEMO EMAIL logged for: $to - Subject: $subject");
    }

    /**
     * Get verification email HTML template
     */
    private function getVerificationEmailTemplate($name, $code)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0d6efd, #1f6aa5); color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0;">Arteche Citizen Portal</h1>
            <p>Email Verification</p>
        </div>
        <div style="padding: 30px;">
            <h2>Hello, ' . htmlspecialchars($name) . '!</h2>
            <p>Thank you for registering with the Arteche Citizen Portal. To complete your registration, please verify your email address by entering the code below:</p>
            
            <div style="background: #f8f9fa; border: 2px dashed #0d6efd; border-radius: 8px; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #0d6efd; margin: 20px 0;">
                ' . htmlspecialchars($code) . '
            </div>
            
            <p><strong>This code will expire in 24 hours.</strong></p>
            
            <p>If you did not create an account, please ignore this email.</p>
            
            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            
            <p style="font-size: 14px; color: #6c757d;">
                <strong>Note:</strong> This is an automated message. Please do not reply to this email.
            </p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d;">
            <p>&copy; ' . date('Y') . ' Municipality of Arteche - Community Intelligence System</p>
        </div>
</body>
</html>';
    }

    /**
     * Get password reset email template
     */
    private function getPasswordResetEmailTemplate($name, $resetLink)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #0d6efd, #1f6aa5); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1>Password Reset Request</h1>
        </div>
        <div style="background: #fff; padding: 30px; border: 1px solid #ddd;">
            <h2>Hello, ' . htmlspecialchars($name) . '!</h2>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($resetLink) . '" style="display: inline-block; background: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">Reset Password</a>
            </p>
            <p><strong>This link will expire in 1 hour.</strong></p>
            <p>If you did not request a password reset, please ignore this email.</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px;">
            <p>&copy; ' . date('Y') . ' Municipality of Arteche</p>
        </div>
</body>
</html>';
    }

    /**
     * Get welcome email template
     */
    private function getWelcomeEmailTemplate($name)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #198754, #20c997); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1>Welcome to Arteche Citizen Portal!</h1>
        </div>
        <div style="background: #fff; padding: 30px; border: 1px solid #ddd;">
            <h2>Hello, ' . htmlspecialchars($name) . '!</h2>
            <p>Your account has been verified successfully. You can now access all features of the Arteche Citizen Portal:</p>
            <ul>
                <li>Request barangay documents online</li>
                <li>Track your document requests in real-time</li>
                <li>Access your household information</li>
                <li>Receive notifications from the barangay</li>
                <li>Update your profile information</li>
            </ul>
            <p>Get started by logging in to your account!</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px;">
            <p>&copy; ' . date('Y') . ' Municipality of Arteche</p>
        </div>
</body>
</html>';
    }

    /**
     * Get document request confirmation template
     */
    private function getDocumentRequestTemplate($name, $requestNumber, $documentType)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Document Request Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #0d6efd, #1f6aa5); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1>Document Request Confirmed</h1>
        </div>
        <div style="background: #fff; padding: 30px; border: 1px solid #ddd;">
            <h2>Hello, ' . htmlspecialchars($name) . '!</h2>
            <p>Your document request has been submitted successfully. Here are your request details:</p>
            
            <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; border-radius: 5px; margin: 20px 0;">
                Request #: ' . htmlspecialchars($requestNumber) . '
            </div>
            
            <p><strong>Document Type:</strong> ' . htmlspecialchars($documentType) . '</p>
            <p><strong>Status:</strong> Pending Processing</p>
            <p><strong>Estimated Processing Time:</strong> 2-3 business days</p>
            
            <hr>
            
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Wait for notification when your document is ready</li>
                <li>Visit the barangay hall for payment (if applicable)</li>
                <li>Bring valid ID when claiming your document</li>
            </ol>
            
            <p>You can track your request status anytime in your citizen dashboard.</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px;">
            <p>&copy; ' . date('Y') . ' Municipality of Arteche</p>
        </div>
</body>
</html>';
    }
}
