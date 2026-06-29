<?php
// test_simple_register.php
session_start();

if ($_POST) {
    echo "<h2>Registration Test</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Password Match Test:</h3>";
    $match = $_POST['password'] === $_POST['confirm_password'];
    echo $match ? "✅ PASSWORDS MATCH" : "❌ PASSWORDS DON'T MATCH";
    
    echo "<hr><a href='citizen_portal.php'>Back</a>";
    exit;
}