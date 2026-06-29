<?php
// config/constants.php

// Site Information
define('SITE_NAME', 'Arteche Citizen Portal');
define('SITE_URL', 'http://localhost/prototype/');
define('SITE_EMAIL', 'citizen@arteche.gov.ph');

// Email/SMTP Configuration
// Set SMTP_ENABLED to true and configure your SMTP settings to send real emails
// For Gmail: Use App Password (not your regular password)
// Get App Password: https://myaccount.google.com/apppasswords
define('SMTP_ENABLED', false); // Set to true to enable SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', ''); // Your Gmail address
define('SMTP_PASSWORD', ''); // Your App Password (not regular password)
define('SMTP_ENCRYPTION', 'tls');

// From email settings
define('FROM_EMAIL', 'noreply@arteche.gov.ph');
define('FROM_NAME', 'Arteche Citizen Portal');

// File paths - Use __DIR__ for reliable relative paths
define('UPLOAD_PATH', __DIR__ . '/../../public/uploads/');
define('DOCUMENT_PATH', __DIR__ . '/../../public/uploads/documents/');
define('PROFILE_PATH', __DIR__ . '/../../public/uploads/profiles/');

// URL paths for web access
define('UPLOAD_URL', 'uploads/');
define('ASSETS_URL', 'assets/');

// System Constants
define('SYSTEM_NAME', 'Arteche Community Intelligence System');
define('SYSTEM_VERSION', '1.0.0');
define('COMPANY_NAME', 'Municipality of Arteche');

// Session settings
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Security
define('PASSWORD_COST', 12); // Bcrypt cost factor

// Document settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx');

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barangay_ci_system');

// Risk score thresholds
define('RISK_LOW_MAX', 30);
define('RISK_MEDIUM_MAX', 60);
define('RISK_HIGH_MIN', 61);

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Date formats
define('DATE_FORMAT', 'M d, Y');
define('DATETIME_FORMAT', 'M d, Y h:i A');
define('SQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

// Session timeout settings
//define('SESSION_TIMEOUT', 3600); // 1 hour

// Status colors
$statusColors = [
    'Draft' => 'secondary',
    'Submitted' => 'info',
    'Under Review' => 'warning',
    'For Payment' => 'primary',
    'Approved' => 'success',
    'Rejected' => 'danger',
    'Ready for Pickup' => 'primary',
    'Completed' => 'success',
    'Cancelled' => 'dark'
];

// Payment status colors
$paymentColors = [
    'Pending' => 'warning',
    'Paid' => 'success',
    'Free' => 'info',
    'Waived' => 'secondary'
];
?>
