<?php
// run_schema.php
require_once 'config/database.php';

$db = getDB();

// Read the SQL file
$sql = file_get_contents('citizen_portal_schema.sql');

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement) && !preg_match('/^--/', $statement)) {
        try {
            $db->query($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "Error executing: " . $e->getMessage() . "\n";
        }
    }
}

echo "Schema execution completed.\n";
?>
