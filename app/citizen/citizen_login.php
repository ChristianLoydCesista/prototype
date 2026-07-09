<?php
// citizen_login.php
require_once '../shared/bootstrap.php';
$auth = new Auth(); // bootstrap already created session

// Check if already logged in
if ($session->isCitizenLoggedIn() && !empty($_SESSION['remember_login'])) {
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

    $_SESSION['old_login'] = [
        'username' => $_POST['username'] ?? '',
        'remember' => isset($_POST['remember'])
    ];

    // Validate inputs
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
        $_SESSION['remember_login'] = !empty($_POST['remember']);

        // Set remember me cookie if requested
        if (!empty($_POST['remember'])) {
            $rememberValue = $auth->createRememberToken($citizen['id']);

            setcookie(
                'remember_token',
                $rememberValue,
                [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'httponly' => true,
                    'secure' => !empty($_SERVER['HTTPS']),
                    'samesite' => 'Strict'
                ]
            );
        }

        // Redirect to dashboard
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
