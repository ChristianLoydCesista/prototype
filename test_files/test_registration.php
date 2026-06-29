<?php
// test_registration.php
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/Auth.php';

// Test data with unique email/phone
$uniqueId = time() . rand(1000, 9999);
$testData = [
    'email' => 'test' . $uniqueId . '@example.com',
    'phone' => '091234' . str_pad(rand(10000, 99999), 5, '0'),
    'password' => 'password123',
    'confirm_password' => 'password123',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'middle_name' => 'Smith',
    'birth_date' => '1990-01-01',
    'address' => '123 Test Street',
    'barangay_id' => 1
];

$auth = new Auth();
$result = $auth->register($testData);

if ($result) {
    echo "Registration successful. Citizen ID: $result\n";

    // Check database
    $db = getDB();
    $stmt = $db->prepare("SELECT account_status FROM citizens WHERE id = ?");
    $stmt->bind_param("i", $result);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();

    if ($status === 'Active') {
        echo "Account status is correctly set to 'Active'.\n";
    } else {
        echo "Error: Account status is '$status', expected 'Active'.\n";
    }

    // Clean up: delete test user
    $stmt = $db->prepare("DELETE FROM citizens WHERE id = ?");
    $stmt->bind_param("i", $result);
    $stmt->execute();
    $stmt->close();
    echo "Test user cleaned up.\n";
} else {
    echo "Registration failed. Errors: " . implode(', ', $auth->getErrors()) . "\n";
}
?>
