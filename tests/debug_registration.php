<?php
// debug_registration.php
require_once '../app/shared/includes/Auth.php';
require_once '../app/shared/includes/Session.php';

$session = new Session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>DEBUG: Registration Data Received</h3>";
    echo "<pre>";
    echo "First Name: " . htmlspecialchars($_POST['first_name'] ?? '') . "\n";
    echo "Last Name: " . htmlspecialchars($_POST['last_name'] ?? '') . "\n";
    echo "Email: " . htmlspecialchars($_POST['email'] ?? '') . "\n";
    echo "Phone: " . htmlspecialchars($_POST['phone'] ?? '') . "\n";
    echo "Password: " . htmlspecialchars($_POST['password'] ?? '') . "\n";
    echo "Confirm Password: " . htmlspecialchars($_POST['confirm_password'] ?? '') . "\n";
    echo "Password Length: " . strlen($_POST['password'] ?? '') . "\n";
    echo "Confirm Password Length: " . strlen($_POST['confirm_password'] ?? '') . "\n";
    
    // Check password match
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $match = $password === $confirm;
    
    echo "Passwords Match: " . ($match ? 'YES' : 'NO') . "\n";
    
    // Show character codes for debugging
    if (!$match) {
        echo "\n--- CHARACTER DEBUG ---\n";
        echo "Password characters: ";
        for ($i = 0; $i < strlen($password); $i++) {
            echo ord($password[$i]) . " ";
        }
        echo "\nConfirm characters: ";
        for ($i = 0; $i < strlen($confirm); $i++) {
            echo ord($confirm[$i]) . " ";
        }
    }
    echo "</pre>";
    
    // Test the Auth class directly - FIXED: Don't call private method
    $auth = new Auth();
    $data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'middle_name' => trim($_POST['middle_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'address' => trim($_POST['address'] ?? ''),
        'barangay_id' => intval($_POST['barangay_id'] ?? 0),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    echo "<h3>DEBUG: Manual Validation</h3>";
    
    $errors = [];
    
    // Manual validation (copy of Auth::validateRegistration logic)
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($data['phone']) || !preg_match('/^09[0-9]{9}$/', $data['phone'])) {
        $errors[] = 'Valid Philippine mobile number is required (09XXXXXXXXX)';
    }
    
    if (empty($data['password']) || strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($data['first_name']) || empty($data['last_name'])) {
        $errors[] = 'First name and last name are required';
    }
    
    if (empty($data['birth_date']) || strtotime($data['birth_date']) > strtotime('-13 years')) {
        $errors[] = 'You must be at least 13 years old';
    }
    
    if (empty($data['barangay_id'])) {
        $errors[] = 'Please select your barangay';
    }
    
    if (empty($errors)) {
        echo "<p style='color: green;'>✅ Validation passed!</p>";
        
        // Try to register
        echo "<h3>DEBUG: Attempting Registration</h3>";
        $citizenId = $auth->register($data);
        
        if ($citizenId) {
            echo "<p style='color: green;'>✅ Registration successful! Citizen ID: $citizenId</p>";
            
            // Get errors from Auth class if any
            $authErrors = $auth->getErrors();
            if (!empty($authErrors)) {
                echo "<p style='color: orange;'>⚠️ Auth class reported errors:</p>";
                echo "<ul>";
                foreach ($authErrors as $error) {
                    echo "<li>" . htmlspecialchars($error) . "</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>❌ Registration failed in Auth class</p>";
            echo "<ul>";
            foreach ($auth->getErrors() as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>❌ Validation failed:</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
    echo '<p><a href="citizen_portal.php">Back to Registration</a></p>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Registration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        input { margin: 5px 0; padding: 8px; width: 300px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>Test Registration Form</h2>
    <form method="POST">
        <input type="text" name="first_name" placeholder="First Name" required><br>
        <input type="text" name="last_name" placeholder="Last Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="tel" name="phone" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required><br>
        <input type="date" name="birth_date" required><br>
        <button type="submit">Test Registration</button>
    </form>
    
    <hr>
    
    <h3>Quick Tests:</h3>
    <form method="POST">
        <input type="hidden" name="first_name" value="John">
        <input type="hidden" name="last_name" value="Doe">
        <input type="hidden" name="email" value="test@example.com">
        <input type="hidden" name="phone" value="09123456789">
        <input type="hidden" name="birth_date" value="1990-01-01">
        <input type="hidden" name="barangay_id" value="1">
        
        <strong>Test 1: Matching Passwords</strong><br>
        Password: <input type="password" name="password" value="Test1234" required><br>
        Confirm: <input type="password" name="confirm_password" value="Test1234" required><br>
        <button type="submit">Test Matching</button>
    </form>
    
    <br>
    
    <form method="POST">
        <input type="hidden" name="first_name" value="Jane">
        <input type="hidden" name="last_name" value="Smith">
        <input type="hidden" name="email" value="jane@example.com">
        <input type="hidden" name="phone" value="09123456788">
        <input type="hidden" name="birth_date" value="1995-01-01">
        <input type="hidden" name="barangay_id" value="2">
        
        <strong>Test 2: Non-Matching Passwords</strong><br>
        Password: <input type="password" name="password" value="Test1234" required><br>
        Confirm: <input type="password" name="confirm_password" value="Test12345" required><br>
        <button type="submit">Test Non-Matching</button>
    </form>
</body>
</html>