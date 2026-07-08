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

//Store username to repopulate the form in case of error
$submittedUsername = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF validation ---
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $session->setFlash('error', 'Invalid request. Please try again.');
        header("Location: citizen_portal.php");
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Store the submitted username to repopulate the form in case of error
    $submittedUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    if (empty($username) || empty($password)) {
        $session->setFlash('error', 'Please enter both username and password');
        header("Location: citizen_portal.php");
        exit;
    }

    $citizen = $auth->login($username, $password);

    if ($citizen) {
        // Success - clear stored username
        unset($_SESSION['login_username']);
        $session->setCitizen($citizen);

        if ($remember) {
            $token = $auth->createRememberToken($citizen['id']);
            $isSecure = (ENVIRONMENT === 'production');
            setcookie(
                'remember_token',
                $token,
                time() + (30 * 24 * 60 * 60),
                '/',
                '',
                $isSecure,
                true
            );
        }

        $session->setFlash('success', 'Welcome back, ' . $citizen['first_name'] . '!');
        header("Location: citizen_dashboard.php");
        exit;
    } else {
       
        $submittedUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        // Failed login - store username for repopulation
        $_SESSION['login_username'] = $username;

        $errors = $auth->getErrors();
        $errorMsg = !empty($errors) ? $errors[0] : 'Login failed. Please try again.';
        $session->setFlash('error', $errorMsg);
        header("Location: citizen_portal.php");
        exit;
    }
}

// If not POST, redirect to portal
header("Location: citizen_portal.php");
exit;
?>