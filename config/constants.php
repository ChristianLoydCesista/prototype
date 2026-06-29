<?php 
// config/constants.php

// Site Information
define('SITE_NAME', 'Arteche Citizen Portal');
define('SITE_URL', 'http://localhost/prototype/');
define('SITE_EMAIL', 'citizen@arteche.gov.ph');

// File paths
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/prototype/uploads/');
define('DOCUMENT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/prototype/documents/');
define('PROFILE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/prototype/profiles/');

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