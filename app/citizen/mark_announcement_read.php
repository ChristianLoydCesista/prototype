<?php
require_once '../shared/bootstrap.php';

$session = new Session();

if (!$session->isCitizenLoggedIn()) {
    http_response_code(401);
    exit;
}

$citizen = $session->getCitizen();
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
$announcementId = (int) $input['id'];

$stmt = $db->prepare("
    INSERT IGNORE INTO announcement_reads (announcement_id, citizen_id) 
    VALUES (?, ?)
");
$stmt->bind_param("ii", $announcementId, $citizen['id']);
$stmt->execute();
$stmt->close();

http_response_code(200);
echo json_encode(['success' => true]);
?>

