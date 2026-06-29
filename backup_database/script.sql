CREATE DATABASE IF NOT EXISTS barangay_ci_system;
USE barangay_ci_system;

CREATE TABLE IF NOT EXISTS households (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    sex ENUM('Male','Female') NOT NULL,
    civil_status ENUM('Single','Married','Widowed','Separated') NOT NULL,
    household_size INT NOT NULL,
    income_monthly DECIMAL(10,2) NOT NULL,
    income_per_capita DECIMAL(10,2) NOT NULL,
    income_source VARCHAR(100),
    four_ps ENUM('Yes','No') DEFAULT 'No',
    housing_type VARCHAR(100),
    water_source VARCHAR(100),
    toilet_type VARCHAR(100),
    employment VARCHAR(100),
    disability ENUM('Yes','No') DEFAULT 'No',
    senior_citizen ENUM('Yes','No') DEFAULT 'No',
    vulnerability_index INT DEFAULT 0,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    barangay VARCHAR(100) DEFAULT 'Tangbo',
    survey_date DATE DEFAULT CURRENT_DATE,
    date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','BarangayStaff') NOT NULL
);

ALTER TABLE households
ADD COLUMN risk_score INT DEFAULT 0;


INSERT INTO users (username, password, role)
VALUES ('admin', MD5('admin123'), 'Admin');
