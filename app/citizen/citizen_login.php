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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Attempt login
    $citizen = $auth->login($username, $password);

    if ($citizen) {
        // Set session
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
