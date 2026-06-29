-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 07:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `barangay_ci_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', 'User logged in', '::1', '2026-02-03 17:26:31'),
(2, 2, 'Login', 'User logged in', '::1', '2026-02-03 17:30:38'),
(3, 1, 'Login', 'User logged in', '::1', '2026-02-03 17:33:18'),
(4, 2, 'Login', 'User logged in', '::1', '2026-02-03 17:51:52'),
(5, 1, 'Login', 'User logged in', '::1', '2026-02-03 17:52:58'),
(6, 1, 'Login', 'User logged in', '::1', '2026-02-03 17:53:41'),
(7, 1, 'Login', 'User logged in', '::1', '2026-02-03 18:02:35'),
(8, 1, 'Login', 'User logged in', '::1', '2026-02-03 18:08:53'),
(9, 1, 'Login', 'User logged in', '::1', '2026-02-03 18:14:57'),
(10, 2, 'Login', 'User logged in', '::1', '2026-02-03 18:42:53');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `municipality` varchar(100) DEFAULT 'Arteche',
  `province` varchar(100) DEFAULT 'Eastern Samar',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `population` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `municipality`, `province`, `latitude`, `longitude`, `population`, `created_at`) VALUES
(1, 'Tangbo', 'Arteche', 'Eastern Samar', 12.32145000, 125.54892000, 0, '2026-02-03 16:59:20'),
(2, 'Aguinaldo', 'Arteche', 'Eastern Samar', 12.26605838, 125.31139256, 0, '2026-02-03 17:55:37'),
(3, 'Balud', 'Arteche', 'Eastern Samar', 12.26907051, 125.36536349, 0, '2026-02-03 17:55:37'),
(4, 'Bato', 'Arteche', 'Eastern Samar', 12.25578222, 125.33632335, 0, '2026-02-03 17:55:37'),
(5, 'Batalay', 'Arteche', 'Eastern Samar', 12.29300000, 125.39800000, 0, '2026-02-03 17:55:37'),
(6, 'Beri', 'Arteche', 'Eastern Samar', 12.29402409, 125.35752715, 0, '2026-02-03 17:55:37'),
(7, 'Bigo', 'Arteche', 'Eastern Samar', 12.27500000, 125.43400000, 0, '2026-02-03 17:55:37'),
(8, 'Bonifacio', 'Arteche', 'Eastern Samar', 12.30400000, 125.38300000, 0, '2026-02-03 17:55:37'),
(9, 'Buenavista', 'Arteche', 'Eastern Samar', 12.31200000, 125.36700000, 0, '2026-02-03 17:55:37'),
(10, 'Buluan', 'Arteche', 'Eastern Samar', 12.32800000, 125.35200000, 0, '2026-02-03 17:55:37'),
(11, 'Campacion', 'Arteche', 'Eastern Samar', 12.29400000, 125.45300000, 0, '2026-02-03 17:55:37'),
(12, 'Carapdapan', 'Arteche', 'Eastern Samar', 12.33400000, 125.50500000, 0, '2026-02-03 17:55:37'),
(13, 'Casidman', 'Arteche', 'Eastern Samar', 12.32100000, 125.48900000, 0, '2026-02-03 17:55:37'),
(14, 'Catumsan', 'Arteche', 'Eastern Samar', 12.27566077, 125.31818694, 0, '2026-02-03 17:55:37'),
(15, 'Central', 'Arteche', 'Eastern Samar', 12.26500000, 125.52300000, 0, '2026-02-03 17:55:37'),
(16, 'Dantu', 'Arteche', 'Eastern Samar', 12.34700000, 125.43300000, 0, '2026-02-03 17:55:37'),
(17, 'Gamuton', 'Arteche', 'Eastern Samar', 12.33600000, 125.41800000, 0, '2026-02-03 17:55:37'),
(18, 'Inayawan', 'Arteche', 'Eastern Samar', 12.35800000, 125.38800000, 0, '2026-02-03 17:55:37'),
(19, 'Maca-anga', 'Arteche', 'Eastern Samar', 12.27900000, 125.54500000, 0, '2026-02-03 17:55:37'),
(20, 'Magsaysay', 'Arteche', 'Eastern Samar', 12.32400000, 125.53600000, 0, '2026-02-03 17:55:37'),
(21, 'Matin-ab', 'Arteche', 'Eastern Samar', 12.28900000, 125.55700000, 0, '2026-02-03 17:55:37'),
(22, 'Poblacion', 'Arteche', 'Eastern Samar', 12.26300000, 125.50200000, 0, '2026-02-03 17:55:37'),
(23, 'San Isidro', 'Arteche', 'Eastern Samar', 12.34800000, 125.45700000, 0, '2026-02-03 17:55:37'),
(24, 'Santa Cruz', 'Arteche', 'Eastern Samar', 12.27600000, 125.51800000, 0, '2026-02-03 17:55:37'),
(25, 'Tapican', 'Arteche', 'Eastern Samar', 12.31500000, 125.42600000, 0, '2026-02-03 17:55:37'),
(26, 'Tawagan', 'Arteche', 'Eastern Samar', 12.30200000, 125.44200000, 0, '2026-02-03 17:55:37'),
(27, 'Teguis', 'Arteche', 'Eastern Samar', 12.28700000, 125.47600000, 0, '2026-02-03 17:55:37'),
(28, 'Tudela', 'Arteche', 'Eastern Samar', 12.27200000, 125.49200000, 0, '2026-02-03 17:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_requests`
--

CREATE TABLE `certificate_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) DEFAULT NULL,
  `household_id` int(11) DEFAULT NULL,
  `resident_name` varchar(100) NOT NULL,
  `certificate_type` enum('Barangay Clearance','Indigency','Residency','Business Permit','Other') NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `requested_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_date` timestamp NULL DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(11) NOT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') NOT NULL,
  `household_size` int(11) NOT NULL,
  `income_monthly` decimal(10,2) NOT NULL,
  `income_per_capita` decimal(10,2) NOT NULL,
  `income_source` varchar(100) DEFAULT NULL,
  `four_ps` enum('Yes','No') DEFAULT 'No',
  `housing_type` varchar(100) DEFAULT NULL,
  `water_source` varchar(100) DEFAULT NULL,
  `toilet_type` varchar(100) DEFAULT NULL,
  `employment` varchar(100) DEFAULT NULL,
  `disability` enum('Yes','No') DEFAULT 'No',
  `senior_citizen` enum('Yes','No') DEFAULT 'No',
  `vulnerability_index` int(11) DEFAULT 0,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT 'Tangbo',
  `survey_date` date DEFAULT curdate(),
  `date_submitted` timestamp NOT NULL DEFAULT current_timestamp(),
  `risk_score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `barangay_id`, `name`, `age`, `sex`, `civil_status`, `household_size`, `income_monthly`, `income_per_capita`, `income_source`, `four_ps`, `housing_type`, `water_source`, `toilet_type`, `employment`, `disability`, `senior_citizen`, `vulnerability_index`, `latitude`, `longitude`, `barangay`, `survey_date`, `date_submitted`, `risk_score`) VALUES
(1, 1, 'Christain', 21, 'Male', 'Single', 4, 5000.00, 1250.00, 'farming', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 5, 12.2692022, 125.3714274, 'Tangbo', '2026-02-03', '2026-02-03 15:54:54', 0),
(2, 1, 'Christain', 21, 'Male', 'Single', 4, 5000.00, 1250.00, 'farming', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 5, 12.2692022, 125.3714274, 'Tangbo', '2026-02-03', '2026-02-03 15:56:14', 80),
(3, 1, 'Loyd', 23, 'Male', 'Single', 6, 2000.00, 333.33, 'Lab\'as', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 8, 10.3307000, 123.9205000, 'Tangbo', '2026-02-04', '2026-02-03 16:03:41', 94),
(4, 1, 'Rolly', 56, 'Male', 'Married', 6, 6000.00, 1000.00, 'Farming', 'Yes', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Self-Employed', 'No', 'No', 7, 12.2692022, 125.3714274, 'Tangbo', '2026-02-04', '2026-02-03 17:37:22', 54),
(5, 2, 'Juan', 32, 'Male', 'Single', 10, 10000.00, 1000.00, 'Fishing', 'Yes', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Employed', 'Yes', 'Yes', 8, 12.2692022, 125.3714274, 'Tangbo', '2026-02-04', '2026-02-03 18:03:51', 73);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `role` enum('Admin','BarangayStaff','super_admin','barangay_admin') DEFAULT 'barangay_admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `barangay_id`, `role`, `created_at`) VALUES
(1, 'admin', 'System Administrator', '0192023a7bbd73250516f069df18b500', NULL, 'super_admin', '2026-02-03 16:58:18'),
(2, 'tangbo_admin', 'Tangbo Barangay Captain', '51457781324173df80488fc85370d37b', NULL, 'barangay_admin', '2026-02-03 17:00:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `household_id` (`household_id`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  ADD CONSTRAINT `certificate_requests_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `households_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
