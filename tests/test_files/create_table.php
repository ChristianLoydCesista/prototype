<?php
// create_table.php
require_once '../app/shared/config/database.php';

$db = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS citizens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    middle_name VARCHAR(50),
    birth_date DATE,
    address TEXT,
    barangay_id INT,
    verification_code VARCHAR(10),
    is_verified BOOLEAN DEFAULT FALSE,
    account_status ENUM('Active','Inactive','Suspended') DEFAULT 'Active',
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_barangay (barangay_id)
);
";

if ($db->query($sql)) {
    echo "Citizens table created successfully.\n";
} else {
    echo "Error creating table: " . $db->error . "\n";
}
