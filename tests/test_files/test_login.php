<?php
// test_login.php
require_once '../app/shared/config/database.php';
require_once '../app/shared/config/constants.php';
require_once '../app/shared/includes/Auth.php';

// First, register a test user
$uniqueId = time() . rand(1000, 9999);
$testData = [
    'email' => 'testlogin' . $uniqueId . '@example.com',
    'phone' => '091234' . str_pad(rand(10000, 99999), 5, '0'),
    'password' => 'password123',
    'confirm_password' => 'password123',
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'middle_name' => 'Smith',
    'birth_date' => '1990-01-01',
    'address' => '123 Test Street',
    'barangay_id' => 1
];

$auth = new Auth();
$citizenId = $auth->register($testData);

if (!$citizenId) {
    echo "Failed to register test user: " . implode(', ', $auth->getErrors()) . "\n";
    exit;
}

echo "Test user registered. ID: $citizenId\n";

// Test login with correct credentials
$loginResult = $auth->login($testData['email'], $testData['password']);

if ($loginResult) {
    echo "Login successful. Citizen: " . $loginResult['first_name'] . " " . $loginResult['last_name'] . "\n";
} else {
    echo "Login failed: " . implode(', ', $auth->getErrors()) . "\n";
}

// Test login with wrong password
$wrongLogin = $auth->login($testData['email'], 'wrongpassword');
if (!$wrongLogin) {
    echo "Correctly rejected wrong password.\n";
} else {
    echo "Error: Wrong password was accepted.\n";
}

// Test login with non-existent email
$nonExistentLogin = $auth->login('nonexistent@example.com', 'password123');
if (!$nonExistentLogin) {
    echo "Correctly rejected non-existent email.\n";
} else {
    echo "Error: Non-existent email was accepted.\n";
}

// Clean up: delete test user
$db = getDB();
$stmt = $db->prepare("DELETE FROM citizens WHERE id = ?");
$stmt->bind_param("i", $citizenId);
$stmt->execute();
$stmt->close();
echo "Test user cleaned up.\n";
?>
