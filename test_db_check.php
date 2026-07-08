<?php
require 'app/shared/bootstrap.php';
$db = getDB();
if (!$db) {
    echo "DB_NULL\n";
    exit(1);
}
$res = $db->query("SHOW TABLES LIKE 'citizen_requests'");
if ($res && $res->num_rows > 0) {
    echo "citizen_requests_exists\n";
} else {
    echo "citizen_requests_missing\n";
}
$res = $db->query("SHOW COLUMNS FROM citizens");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo 'citizens:' . $row['Field'] . "\n";
    }
}
$res = $db->query("SHOW COLUMNS FROM citizen_requests");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo 'citizen_requests:' . $row['Field'] . "\n";
    }
}
$res = $db->query("SHOW COLUMNS FROM document_types");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo 'document_types:' . $row['Field'] . "\n";
    }
}
