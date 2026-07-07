<?php
// config/constants.php

// Site Information
$projectRoot = dirname(__DIR__, 3);
$documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/')
    : '';

$projectRootReal = rtrim(str_replace('\\', '/', realpath($projectRoot) ?: $projectRoot), '/');

if (!defined('BASE_PATH')) {
    $relativePath = '';

    if ($documentRoot && $projectRootReal && strpos($projectRootReal, $documentRoot) === 0) {
        $relativePath = substr($projectRootReal, strlen($documentRoot));
    }

    $relativePath = '/' . trim($relativePath, '/');
    if ($relativePath === '/') {
        $relativePath = '/';
    } else {
        $relativePath .= '/';
    }

    define('BASE_PATH', $relativePath);
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https://'
    : 'http://';

define('SITE_URL', $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
define('SITE_NAME', 'Arteche Citizen Portal');
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

// File paths - resolve from the actual project root for hosting environments
$projectRootAbsolute = $projectRootReal ?: $projectRoot;

define('UPLOAD_PATH', $projectRootAbsolute . '/public/uploads/');
define('DOCUMENT_PATH', $projectRootAbsolute . '/public/uploads/documents/');
define('PROFILE_PATH', $projectRootAbsolute . '/public/uploads/profiles/');

// URL paths for web access
define('UPLOAD_URL', rtrim(BASE_PATH, '/') . '/public/uploads/');
define('ASSETS_URL', rtrim(BASE_PATH, '/') . '/public/assets/');

foreach ([UPLOAD_PATH, DOCUMENT_PATH, PROFILE_PATH] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

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

// ==========================
// DATABASE CONFIG (AUTO-LOCAL/PRODUCTION)
// ==========================
$isLocalhost = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
$isLocalFile = isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1';
$isLocalEnvironment = $isLocalhost || $isLocalFile || (php_sapi_name() === 'cli' && !getenv('DB_HOST'));

if ($isLocalEnvironment) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'barangay_ci_system');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'sql306.infinityfree.com');
    define('DB_USER', getenv('DB_USER') ?: 'if0_42353709');
    define('DB_PASS', getenv('DB_PASS') ?: '6GbzqbCGseL');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_42353709_barangay_ci_system');
}

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
