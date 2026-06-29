<?php
require_once __DIR__ . '/../shared/bootstrap.php';

$session = new Session();
if (!$session->isCitizenLoggedIn()) {
    http_response_code(403);
    exit('Login required');
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Missing request ID');
}

$citizen_id = $session->getCitizenId();
$conn = getDB();

$stmt = $conn->prepare("
    SELECT document_path 
    FROM citizen_requests 
    WHERE id = ? AND citizen_id = ? AND status = 'Ready for Pickup' AND document_path IS NOT NULL
");
$stmt->bind_param('ii', $id, $citizen_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row || !$row['document_path'] || !file_exists('../../public/' . $row['document_path'])) {
    http_response_code(404);
    exit('Document not found');
}

$path = $row['document_path'];

// MARK AS COMPLETED
$update_stmt = $conn->prepare("UPDATE citizen_requests SET status = 'Completed', completed_at = NOW() WHERE id = ? AND citizen_id = ?");
$update_stmt->bind_param("ii", $id, $citizen_id);
$update_stmt->execute();
$update_stmt->close();

error_log("Download completed: request $id by citizen $citizen_id");

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize('../../public/' . $path));
readfile('../../public/' . $path);
exit;
?>