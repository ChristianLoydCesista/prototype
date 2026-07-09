<?php
require_once '../shared/bootstrap.php';
$session = new Session();

// Clear remember me cookie if exists
if (!empty($_COOKIE['remember_token'])) {
    $auth->deleteRememberToken($_COOKIE['remember_token']);

    setcookie(
        'remember_token',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']),
            'samesite' => 'Strict'
        ]
    );
}

// Logout first
$session->logout();

// Restart clean session for flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['remember_login']);
$session->setFlash('success', 'You have been logged out successfully.');

// Redirect to portal with logout message
header("Location: citizen_portal.php");
exit;
