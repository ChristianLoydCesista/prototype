<?php
// update_existing_users.php
require_once 'config/database.php';

$db = getDB();

// Update existing users to have account_status = 'Active' if it's NULL
$sql = "UPDATE citizens SET account_status = 'Active' WHERE account_status IS NULL OR account_status = ''";

if ($db->query($sql)) {
    $affected = $db->affected_rows;
    echo "Updated $affected existing users to have account_status = 'Active'.\n";
} else {
    echo "Error updating users: " . $db->error . "\n";
}
?>
