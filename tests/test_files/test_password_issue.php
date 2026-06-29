<?php
// test_password_issue.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Test Results:</h2>";
    
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];
    
    echo "Password 1: <code>" . htmlspecialchars($password1) . "</code><br>";
    echo "Password 2: <code>" . htmlspecialchars($password2) . "</code><br>";
    echo "Length 1: " . strlen($password1) . "<br>";
    echo "Length 2: " . strlen($password2) . "<br>";
    echo "Match: " . ($password1 === $password2 ? "✅ YES" : "❌ NO") . "<br>";
    
    echo "<hr><h3>Debug:</h3>";
    echo "Password 1 bytes: ";
    foreach (str_split($password1) as $char) {
        echo ord($char) . " ";
    }
    echo "<br>Password 2 bytes: ";
    foreach (str_split($password2) as $char) {
        echo ord($char) . " ";
    }
    
    exit;
}
?>
<form method="POST">
    <input type="password" name="password1" placeholder="Password 1" required><br>
    <input type="password" name="password2" placeholder="Password 2" required><br>
    <button type="submit">Test</button>
</form>