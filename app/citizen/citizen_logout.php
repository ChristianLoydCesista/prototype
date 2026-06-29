<?php
// citizen_logout.php
require_once '../shared/bootstrap.php'; // session will be ended below

$session = new Session();
$session->logout();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to portal with logout message
header("Location: citizen_portal.php?logout=success");
exit;