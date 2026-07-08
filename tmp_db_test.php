<?php
require 'app/shared/config/constants.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo 'CONNECTION_FAILED:' . $conn->connect_error;
    exit(1);
}
echo 'CONNECTED';
$conn->close();
