<?php
require_once __DIR__ . '/bootstrap.php';
// clear all session variables

global $session;

// User the custom, secure logout method
$session->logout();

$session->setFlash('success', 'You have been logged out successfully.');

// Redirect to admin login page using the dynamically generated Base URL
header("Location: " . SITE_URL . "app/admin/admin_login.php");

exit;
