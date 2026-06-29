<?php
// citizen_login.php
require_once '../shared/bootstrap.php';
$auth = new Auth(); // bootstrap already created session

// Check if already logged in
if ($session->isCitizenLoggedIn()) {
    header("Location: citizen_dashboard.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($username) || empty($password)) {
        $session->setFlash('error', 'Please enter both username and password');
        header("Location: citizen_portal.php");
        exit;
    }

    // Attempt login
    $citizen = $auth->login($username, $password);

    if ($citizen) {
        // Set session
        $session->setCitizen($citizen);

        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');

            // Store token in database (you'd need a remember_tokens table)
            // For simplicity, we'll skip this for now
        }

        // Redirect to dashboard
        $session->setFlash('success', 'Welcome back, ' . $citizen['first_name'] . '!');
        header("Location: citizen_dashboard.php");
        exit;
    } else {
        $errors = $auth->getErrors();
        foreach ($errors as $error) {
            $session->setFlash('error', $error);
        }
        header("Location: citizen_portal.php");
        exit;
    }
}

// If not POST, redirect to portal
header("Location: citizen_portal.php");
exit;
?>