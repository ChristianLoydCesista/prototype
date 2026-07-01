<?php
// shared/bootstrap.php
// Centralised initialization for the CIS application.

// ---------------------------------------------------------------------------
// environment & configuration
// ---------------------------------------------------------------------------
// load constants (DB credentials, SITE_URL, etc.)
require_once __DIR__ . '/config/constants.php';

// setup base paths/urls
if (!defined('BASE_PATH')) {
    //define('BASE_PATH', realpath(__DIR__ . '/../..'));
    define('BASE_PATH', dirname(__DIR__, 2));
}

if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    //$script = dirname($_SERVER['PHP_SELF']);
    $script = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $scheme . '://' . $host . $script . '/');
}

// ensure log directory exists
if (!defined('LOG_PATH')) {
    //define('LOG_PATH', BASE_PATH . '/logs');
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
    $msg = "[PHP ERROR] $message in $file:$line";
    error_log($msg . "\n", 3, LOG_PATH . '/errors.log');
    if (ini_get('display_errors')) {
        echo "<b>Error:</b> $message in $file:$line";
    }
});

set_exception_handler(function ($e) {
    $msg = "[UNCAUGHT EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
    error_log($msg . "\n", 3, LOG_PATH . '/errors.log');
    if (ini_get('display_errors')) {
        echo "<b>Exception:</b> " . htmlspecialchars($e->getMessage());
    }
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
//require_once __DIR__ . '/../admin/utils/risk_score.php';
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
try {
    $conn = getDB();
} catch (Exception $e) {
    error_log("DB Init Failed: " . $e->getMessage());
    die("System temporarily unavailable.");
}

try {
   $auth = new Auth();
} catch (Exception $e) {
    error_log("AUTH Init Failed: " . $e->getMessage());
    die("System temporarily unavailable.");
}


// convenience wrappers for session data
function current_user()
{
    global $session;
    return $session;
}
