<?php
// citizen_register.php - THIS SHOULD BE PHP ONLY, NO HTML
require_once '../shared/bootstrap.php';
// Auth/Session available via bootstrap

$session = new Session();
$auth = new Auth();

function saveOldInput()
{
    $old = $_POST;
    unset($old['password'], $old['confirm_password']);
    $_SESSION['old'] = $old;
}

// Redirect if already logged in
if ($session->isCitizenLoggedIn() && !empty($_SESSION['remember_login'])) {
    header("Location: citizen_dashboard.php");
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $data = [
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'birth_date' => $_POST['birth_date'] ?? '',
        'address' => $_POST['address'] ?? '',
        'barangay_id' => $_POST['barangay_id'] ?? ''
    ];

    // Validate required fields
    $required = ['email', 'phone', 'password', 'confirm_password', 'first_name', 'last_name', 'birth_date', 'address', 'barangay_id'];
    $missing = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        saveOldInput();
        $session->setFlash('error', 'All required fields must be filled. Missing: ' . implode(', ', $missing));
        header("Location: citizen_register_view.php");
        exit;
    }

    // Validate password match
    if ($data['password'] !== $data['confirm_password']) {
        saveOldInput();
        $session->setFlash('error', 'Passwords do not match.');
        header("Location: citizen_register_view.php");
        exit;
    }

    // Register citizen
    $citizenId = $auth->register($data);

    if ($citizenId) {
        unset($_SESSION['old']);
        // Store email and phone in session for verification page
        $_SESSION['verification_email'] = $data['email'];
        $_SESSION['verification_phone'] = $data['phone'];

        // Store name for email template
        $_SESSION['registration_first_name'] = $data['first_name'];

        // The verification code should already be in $_SESSION['demo_verification_code']
        // from the Auth::register() method (which sends email via Mailer)

        $session->setFlash('success', 'Registration successful! Please verify your account. Check your email for the verification code.');
        header("Location: citizen_verify.php");
        exit;
    } else {
        saveOldInput();
        $errors = $auth->getErrors();
        $session->setFlash('error', implode('<br>', $errors));
        header("Location: citizen_register_view.php");
        exit;
    }
} else {
    // If someone tries to access this page directly without POST
    $session->setFlash('error', 'Invalid request method.');
    header("Location: citizen_portal.php");
    exit;
}
// NO HTML AFTER THIS - FILE ENDS HERE
