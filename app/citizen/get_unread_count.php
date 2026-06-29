<?php
require_once '../shared/bootstrap.php';

$session = new Session();

if (!$session->isCitizenLoggedIn()) {
    http_response_code(401);
    echo json_encode(['unread_count' => 0]);
    exit;
}

$citizen = $session->getCitizen();
$db = getDB();

$stmt = $db->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE citizen_id = ? AND is_read = 0
");
$stmt->bind_param("i", $citizen['id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['unread_count' => (int) $result['unread_count']]);
?>

