<?php
require_once __DIR__ . '/bootstrap.php';
// clear all session variables

global $session;

// User the custom, secure logout method
$session->logout();

// Redirect usign the dynamically generated Base URL
header("Location: " . SITE_URL . "public/index.html");
exit;
