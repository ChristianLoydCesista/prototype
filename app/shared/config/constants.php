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
define('SMTP_ENABLED', filter_var(getenv('SMTP_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN));
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', intval(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');

define('FROM_EMAIL', getenv('FROM_EMAIL') ?: SMTP_USERNAME);
define('FROM_NAME', getenv('FROM_NAME') ?: 'Arteche Citizen Portal');

error_log("SMTP_ENABLED=" . (SMTP_ENABLED ? 'true' : 'false'));
error_log("SMTP_HOST=" . SMTP_HOST);
error_log("SMTP_USERNAME=" . SMTP_USERNAME);
error_log("SMTP_PASSWORD_EMPTY=" . (empty(SMTP_PASSWORD) ? 'yes' : 'no'));

// From email settings

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
// DATABASE CONFIG
// ==========================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'barangay_ci_system');

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
