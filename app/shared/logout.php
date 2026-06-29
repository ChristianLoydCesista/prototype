<?php
require_once __DIR__ . '/bootstrap.php';
// clear all session variables

global $session;

// vendor code doesn't provide a logout method, so destroy manually
$_SESSION = [];
session_destroy();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../../public/index.html");
exit;
