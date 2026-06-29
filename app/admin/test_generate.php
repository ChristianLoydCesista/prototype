<?php
// Test generate_pdf.php without session/DB
require_once '../shared/bootstrap.php';
$conn = getDB();

// Test request ID (use existing or insert test)
$test_id = 3; // REQ-20241201-000003-789 from insert script

$url = "generate_pdf.php?request_id=" . $test_id;
echo "Testing: <a href='$url'>Generate for ID $test_id</a><br>";
echo "Check after: public/uploads/documents/ and DB citizen_requests.document_path<br>";
echo "<hr>";
// Direct call
$_GET['request_id'] = $test_id;
include 'generate_pdf.php';
?>

