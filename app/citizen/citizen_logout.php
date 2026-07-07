<?php
require_once '../shared/bootstrap.php';
$session = new Session();
$session->logout();

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Set flash message after logout
$session->setFlash('success', 'You have been logged out successfully.');
header("Location: citizen_portal.php");
exit;