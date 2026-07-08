<?php
// shared/bootstrap.php
// Centralised initialization for the CIS application.

// ---------------------------------------------------------------------------
// 1. Security Overrides
// ---------------------------------------------------------------------------
// Force errors to be hidden from the browser and stricly logged to a file.
ini_set('display_errors', '0');
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Enforce Strict, secure session parameters globally
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// ---------------------------------------------------------------------------
// environment & configuration
// ---------------------------------------------------------------------------
// load constants (DB credentials, SITE_URL, etc.)
// load environment variables from .env file if present
// Load Composer autoloader first
$projectRoot = dirname(__DIR__, 2);
$composerAutoload = $projectRoot . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    error_log("Composer autoload not found at: " . $composerAutoload);
}

$envFile = $projectRoot . '/.env';

if (class_exists('Dotenv\Dotenv') && file_exists($envFile)) {
    try {
        $dotenv = Dotenv\Dotenv::createUnsafeImmutable($projectRoot);
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        error_log("Dotenv load failed: " . $e->getMessage());
    }
}

require_once __DIR__ . '/config/constants.php';

// setup base paths/urls
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim(SITE_URL, '/') . '/');
}

// ensure log directory exists
if (!defined('LOG_PATH')) {
    define('LOG_PATH', dirname(__DIR__, 2) . '/logs');
    if (!is_dir(LOG_PATH)) {
        if (!@mkdir(LOG_PATH, 0755, true)) {
            error_log("Failed to create log directory: " . LOG_PATH);
        }
    }
}

// ---------------------------------------------------------------------------
// error/exception handling
// ---------------------------------------------------------------------------
set_error_handler(function ($severity, $message, $file, $line) {
    // Only log the error, Never echo it in production
    $msg = "[" . date('Y-m-d H:i:s') . "] [PHP ERROR] $message in $file:$line";
    error_log($msg . "\n", 3, LOG_PATH . '/errors.log');

    // Convert errors to exceptions for graceful handling
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    /*$msg = "[UNCAUGHT EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
    error_log($msg . "\n", 3, LOG_PATH . '/errors.log');
    if (ini_get('display_errors')) {
        echo "<b>Exception:</b> " . htmlspecialchars($e->getMessage());
    }*/
    $msg = "[" . date('Y-m-d H:i:s') . "] [EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    error_log($msg . "\n", 3, LOG_PATH . '/errors.log');

    // Display a generic, safe fail message to the user
    if (!headers_sent()) {
        header("HTTP/1.1 500 Internal Server Error");
    }
    die("A system error occurred. Please try again later.");
});

// ---------------------------------------------------------------------------
// database helper
// ---------------------------------------------------------------------------
require_once __DIR__ . '/config/database.php';
// set stricter mysqli reporting (exceptions will be thrown)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------------------------
// session management (wrapping existing Session class)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/includes/Session.php';
$session = new Session();   // starts session and handles regeneration

// ---------------------------------------------------------------------------
// common utilities
// ---------------------------------------------------------------------------
require_once __DIR__ . '/includes/Auth.php';
$riskFile = __DIR__ . '/../admin/utils/risk_score.php';
if (file_exists($riskFile)) {
    require_once $riskFile;
}

// ---------------------------------------------------------------------------
// CSRF helpers
// ---------------------------------------------------------------------------
if (!isset($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token()
{
    return $_SESSION['_csrf_token'];
}

function verify_csrf($token)
{
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

// ---------------------------------------------------------------------------
// autoloader (simple)
// ---------------------------------------------------------------------------
spl_autoload_register(function ($class) {
    // map class names to file locations if they follow a predictable pattern
    $paths = [
        __DIR__ . '/includes/' . $class . '.php',
        __DIR__ . '/../admin/utils/' . $class . '.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            return;
        }
    }
});

// ---------------------------------------------------------------------------
// utility shortcuts (optional)
// ---------------------------------------------------------------------------

// make $conn and $auth available without re-declaring each file
$conn = null;
$dbError = null;

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception(Database::getLastError() ?: 'Database connection unavailable.');
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
    error_log("DB Init Failed: " . $dbError);
    $_SESSION['db_error'] = $dbError;
}

$auth = null;
if ($conn) {
    try {
        $auth = new Auth();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
        error_log("AUTH Init Failed: " . $dbError);
        $_SESSION['db_error'] = $dbError;
    }
}

if ($dbError) {
    $_SESSION['db_error'] = $dbError;
}


// convenience wrappers for session data
function current_user()
{
    global $session;
    return $session;
}
