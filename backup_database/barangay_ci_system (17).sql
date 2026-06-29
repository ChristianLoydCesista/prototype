-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 04, 2026 at 10:32 PM
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
(10, 2, 'Login', 'User logged in', '::1', '2026-02-03 18:42:53'),
(11, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:33:44'),
(12, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:41:08'),
(13, 1, 'Login', 'User logged in', '::1', '2026-02-03 19:41:45'),
(14, 1, 'Login', 'User logged in', '::1', '2026-02-03 19:45:15'),
(15, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:45:50'),
(16, 1, 'Login', 'User logged in', '::1', '2026-02-03 19:56:31'),
(17, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:57:33'),
(18, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:58:00'),
(19, 1, 'Login', 'User logged in', '::1', '2026-02-03 19:58:39'),
(20, 2, 'Login', 'User logged in', '::1', '2026-02-03 19:59:14'),
(21, 1, 'Login', 'User logged in', '::1', '2026-02-03 20:03:18'),
(22, 2, 'Login', 'User logged in', '::1', '2026-02-03 20:04:51'),
(23, 2, 'Login', 'User logged in', '::1', '2026-02-03 20:09:31'),
(24, 1, 'Login', 'User logged in', '::1', '2026-02-03 20:10:38'),
(25, 2, 'Login', 'User logged in', '::1', '2026-02-03 20:16:58'),
(26, 1, 'Login', 'User logged in', '::1', '2026-02-03 20:17:25'),
(27, 2, 'Login', 'User logged in', '::1', '2026-02-03 20:19:32'),
(28, 1, 'Login', 'User logged in', '::1', '2026-02-03 20:54:57'),
(29, 1, 'Login', 'User logged in', '::1', '2026-02-03 21:28:53'),
(30, 2, 'Login', 'User logged in', '::1', '2026-02-03 21:31:11'),
(31, 2, 'Login', 'User logged in', '::1', '2026-02-03 21:42:23'),
(32, 2, 'Survey Submission', 'Added household survey: newUser', '::1', '2026-02-03 21:55:10'),
(33, 2, 'Survey Submission', 'Added household survey: testUser', '::1', '2026-02-03 21:58:02'),
(34, 2, 'Survey Submission', 'Added household survey: testUser12', '::1', '2026-02-03 22:12:56'),
(35, 2, 'Survey Submission', 'Added household survey: testUser12', '::1', '2026-02-03 22:15:49'),
(36, 1, 'Login', 'User logged in', '::1', '2026-02-03 22:17:56'),
(37, 1, 'Login', 'User logged in', '::1', '2026-02-03 22:19:14'),
(38, 1, 'Login', 'User logged in', '::1', '2026-02-03 22:19:54'),
(39, 2, 'Login', 'User logged in', '::1', '2026-02-03 22:44:14'),
(40, 2, 'Login', 'User logged in', '::1', '2026-02-04 02:42:25'),
(41, 1, 'Login', 'User logged in', '::1', '2026-02-04 02:43:59'),
(42, 1, 'Login', 'User logged in', '::1', '2026-02-04 02:48:47'),
(43, 1, 'Login', 'User logged in', '::1', '2026-02-04 02:50:10'),
(44, 1, 'Login', 'User logged in', '::1', '2026-02-04 02:59:45'),
(45, 1, 'Login', 'User logged in', '::1', '2026-02-04 02:59:58'),
(46, 1, 'Login', 'User logged in', '::1', '2026-02-04 03:07:31'),
(47, 1, 'Survey Submission', 'Added household survey: testUser2', '::1', '2026-02-04 03:23:07'),
(48, 2, 'Login', 'User logged in', '::1', '2026-02-04 03:28:41'),
(49, 1, 'Login', 'User logged in', '::1', '2026-02-04 03:33:14'),
(50, 1, 'Survey Submission', 'Added household survey: juan', '::1', '2026-02-04 03:43:31'),
(51, 2, 'Login', 'User logged in', '::1', '2026-02-04 03:44:10'),
(52, 2, 'Survey Submission', 'Added household survey: juana', '::1', '2026-02-04 03:45:07'),
(53, 1, 'Login', 'User logged in', '::1', '2026-02-04 04:06:01'),
(54, 1, 'Barangay Management', 'Deleted barangay: Tudela', '::1', '2026-02-04 04:07:09'),
(55, 1, 'Barangay Management', 'Deleted barangay: Teguis', '::1', '2026-02-04 04:07:16'),
(56, 1, 'Barangay Management', 'Deleted barangay: Tapican', '::1', '2026-02-04 04:07:24'),
(57, 1, 'Barangay Management', 'Deleted barangay: Santa Cruz', '::1', '2026-02-04 04:07:31'),
(58, 1, 'Barangay Management', 'Deleted barangay: San Isidro', '::1', '2026-02-04 04:07:44'),
(59, 1, 'Barangay Management', 'Deleted barangay: Dantu', '::1', '2026-02-04 04:08:05'),
(60, 1, 'Barangay Management', 'Deleted barangay: Buluan', '::1', '2026-02-04 04:08:14'),
(61, 1, 'Barangay Management', 'Deleted barangay: Maca-anga', '::1', '2026-02-04 04:08:22'),
(62, 1, 'Barangay Management', 'Updated barangay: Rawis (ID: 17)', '::1', '2026-02-04 04:09:16'),
(63, 1, 'Barangay Management', 'Updated barangay: Rawis (ID: 17)', '::1', '2026-02-04 04:13:07'),
(64, 1, 'Barangay Management', 'Updated barangay: Rawis (ID: 17)', '::1', '2026-02-04 04:14:44'),
(65, 1, 'Barangay Management', 'Updated barangay: Rawis (ID: 17)', '::1', '2026-02-04 04:15:15'),
(66, 2, 'Login', 'User logged in', '::1', '2026-02-04 04:19:34'),
(67, 1, 'Login', 'User logged in', '::1', '2026-02-04 04:38:47'),
(68, 1, 'Survey Submission', 'Added household survey: paul', '::1', '2026-02-04 04:43:19'),
(69, 1, 'Login', 'User logged in', '::1', '2026-02-04 05:48:04'),
(70, 1, 'Login', 'User logged in', '::1', '2026-02-04 05:49:38'),
(71, 1, 'Login', 'User logged in', '::1', '2026-02-04 05:52:45'),
(72, 1, 'Login', 'User logged in', '::1', '2026-02-04 05:53:28'),
(73, 1, 'Login', 'User logged in', '::1', '2026-02-04 05:55:24'),
(74, 1, 'Login', 'User logged in', '::1', '2026-02-04 07:52:26'),
(75, 1, 'Login', 'User logged in', '::1', '2026-02-04 07:58:57'),
(76, 1, 'Login', 'User logged in', '::1', '2026-02-04 13:22:13'),
(78, 1, 'Survey Submission', 'Added household survey: Rolly', '::1', '2026-02-04 13:53:16'),
(79, 1, 'Login', 'User logged in', '::1', '2026-02-04 22:49:20'),
(80, 1, 'Login', 'User logged in', '::1', '2026-02-04 23:01:15'),
(81, 1, 'Login', 'User logged in', '::1', '2026-02-04 23:04:13'),
(82, 1, 'Login', 'User logged in', '::1', '2026-02-05 00:21:16'),
(83, 1, 'Login', 'User logged in', '::1', '2026-02-05 00:24:12'),
(84, 1, 'Login', 'User logged in', '::1', '2026-02-05 09:00:49'),
(85, 2, 'Login', 'User logged in', '::1', '2026-02-05 09:04:31'),
(86, 2, 'Survey Submission', 'Added household survey: Stephanie', '::1', '2026-02-05 09:06:43'),
(87, 1, 'Login', 'User logged in', '127.0.0.1', '2026-02-05 11:57:30'),
(88, 1, 'Login', 'User logged in', '::1', '2026-02-05 12:43:47'),
(89, 2, 'Login', 'User logged in', '::1', '2026-02-05 12:45:19'),
(90, 1, 'Login', 'User logged in', '::1', '2026-02-05 13:00:24'),
(91, 2, 'Login', 'User logged in', '::1', '2026-02-05 13:07:14'),
(92, 1, 'Login', 'User logged in', '::1', '2026-02-05 13:23:24'),
(93, 1, 'Login', 'User logged in', '::1', '2026-02-05 20:56:51'),
(94, 1, 'Login', 'User logged in', '::1', '2026-02-05 20:57:36'),
(95, 1, 'Login', 'User logged in', '::1', '2026-02-06 11:33:25'),
(96, 1, 'Login', 'User logged in', '::1', '2026-02-06 11:41:34'),
(97, 1, 'Survey Submission', 'Added household survey: Restituta A. Camposano', '::1', '2026-02-06 11:57:10'),
(98, 1, 'Login', 'User logged in', '::1', '2026-02-06 15:55:00'),
(99, 1, 'Login', 'User logged in', '::1', '2026-02-06 15:59:06'),
(100, 1, 'Login', 'User logged in', '::1', '2026-02-09 08:09:59'),
(101, 1, 'Login', 'User logged in', '::1', '2026-02-09 08:10:19'),
(102, 1, 'Login', 'User logged in', '::1', '2026-02-09 09:07:08'),
(103, 1, 'Survey Submission', 'Added household survey: jason', '::1', '2026-02-09 09:09:03'),
(104, 1, 'Login', 'User logged in', '::1', '2026-02-09 11:18:26'),
(105, 1, 'Login', 'User logged in', '::1', '2026-02-09 15:48:48'),
(106, 1, 'Login', 'User logged in', '::1', '2026-02-10 03:37:48'),
(107, 2, 'Login', 'User logged in', '::1', '2026-02-10 03:38:33'),
(108, 1, 'Login', 'User logged in', '::1', '2026-02-10 03:57:21'),
(109, 1, 'Login', 'User logged in', '::1', '2026-02-10 09:13:57'),
(110, 1, 'Login', 'User logged in', '::1', '2026-02-10 11:26:02'),
(111, 1, 'Login', 'User logged in', '::1', '2026-02-10 13:13:35'),
(112, 2, 'Login', 'User logged in', '::1', '2026-02-10 14:22:11'),
(113, 2, 'Survey Submission', 'Added household survey: test', '::1', '2026-02-10 14:26:00'),
(114, 1, 'Login', 'User logged in', '::1', '2026-02-10 16:56:06'),
(115, 1, 'Login', 'User logged in', '::1', '2026-02-12 00:40:31'),
(116, 1, 'Login', 'User logged in', '::1', '2026-02-13 09:41:11'),
(117, 2, 'Login', 'User logged in', '::1', '2026-02-13 11:18:23'),
(118, 1, 'Login', 'User logged in', '::1', '2026-02-14 13:41:38'),
(119, 1, 'Login', 'User logged in', '::1', '2026-02-16 00:23:41'),
(120, 1, 'Login', 'User logged in', '::1', '2026-02-16 01:25:48'),
(121, 2, 'Login', 'User logged in', '::1', '2026-02-16 02:02:13'),
(122, 1, 'Login', 'User logged in', '::1', '2026-02-16 04:23:37'),
(123, 1, 'Login', 'User logged in', '::1', '2026-02-16 07:24:28'),
(124, 1, 'Survey Submission', 'Added household survey: Juan', '::1', '2026-02-16 08:11:09'),
(125, 1, 'Login', 'User logged in', '::1', '2026-02-16 23:22:15'),
(126, 1, 'Login', 'User logged in', '::1', '2026-02-17 08:03:18'),
(127, 1, 'Login', 'User logged in', '::1', '2026-02-17 12:11:11'),
(128, 1, 'Login', 'User logged in', '::1', '2026-02-17 13:50:56'),
(129, 2, 'Login', 'User logged in', '::1', '2026-02-18 08:46:50'),
(130, 2, 'Login', 'User logged in', '::1', '2026-02-18 09:30:22'),
(131, 1, 'Login', 'User logged in', '::1', '2026-02-19 16:19:04'),
(132, 1, 'Login', 'User logged in', '::1', '2026-02-22 06:07:34'),
(133, 1, 'Login', 'User logged in', '::1', '2026-02-22 07:03:41'),
(134, 1, 'Login', 'User logged in', '::1', '2026-02-23 03:48:42'),
(135, 1, 'Login', 'User logged in', '::1', '2026-03-03 12:52:43'),
(136, 2, 'Login', 'User logged in', '::1', '2026-03-03 13:44:45'),
(137, 1, 'Login', 'User logged in', '::1', '2026-03-03 13:49:46'),
(138, 2, 'Login', 'User logged in', '::1', '2026-03-03 13:57:02'),
(139, 1, 'Login', 'User logged in', '::1', '2026-03-04 12:09:09'),
(140, 1, 'Login', 'User logged in', '::1', '2026-03-04 12:23:53'),
(141, 1, 'Login', 'User logged in', '::1', '2026-03-04 21:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` enum('General','Emergency','Event','Advisory','Document','Barangay') DEFAULT 'General',
  `target_audience` enum('All','Citizens','Staff','Barangay') DEFAULT 'All',
  `barangay_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `image_path` varchar(255) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `views_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `category`, `target_audience`, `barangay_id`, `priority`, `image_path`, `attachment_path`, `created_by`, `published_at`, `expires_at`, `is_active`, `views_count`, `created_at`, `updated_at`) VALUES
(1, 'Barangay Cleanup Drive', 'Join us for the monthly barangay cleanup drive this Saturday at 7:00 AM at the Barangay Hall. Let\'s keep our community clean!', 'Event', 'All', NULL, 'Normal', NULL, NULL, 1, '2026-02-21 21:50:00', '2026-02-28 21:50:00', 1, 0, '2026-02-21 21:50:00', NULL),
(2, 'Schedule of Free Medical Mission', 'The Municipal Health Office will conduct a free medical mission at Barangay Hall on March 15, 2026 from 8:00 AM to 3:00 PM. Services include check-up, dental, and medicine distribution.', 'General', 'All', NULL, 'High', NULL, NULL, 1, '2026-02-21 21:50:00', '2026-03-23 21:50:00', 1, 0, '2026-02-21 21:50:00', NULL),
(3, 'Road Closure Advisory', 'Please be advised that the main road near the market will be closed from March 10-12 for repair. Use alternate routes.', 'Advisory', 'All', NULL, 'Urgent', NULL, NULL, 1, '2026-02-21 21:50:00', '2026-02-26 21:50:00', 1, 0, '2026-02-21 21:50:00', NULL),
(4, 'Document Processing Schedule', 'The barangay will process clearance and certificate requests every Monday, Wednesday, and Friday from 9:00 AM to 4:00 PM.', 'Document', 'Citizens', NULL, 'Normal', NULL, NULL, 1, '2026-02-21 21:50:00', '2026-04-22 21:50:00', 1, 0, '2026-02-21 21:50:00', NULL),
(5, 'Barangay Assembly', 'Quarterly Barangay Assembly on March 20, 2026 at 2:00 PM at the Barangay Covered Court. All residents are encouraged to attend.', 'Event', 'All', NULL, 'Normal', NULL, NULL, 1, '2026-02-21 21:50:00', '2026-03-07 21:50:00', 1, 0, '2026-02-21 21:50:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Tangbo', 'Arteche', 'Eastern Samar', 12.27117229, 125.34093847, 0, '2026-02-03 16:59:20'),
(2, 'Aguinaldo', 'Arteche', 'Eastern Samar', 12.26605838, 125.31139256, 0, '2026-02-03 17:55:37'),
(3, 'Balud', 'Arteche', 'Eastern Samar', 12.26907051, 125.36536349, 0, '2026-02-03 17:55:37'),
(4, 'Bato', 'Arteche', 'Eastern Samar', 12.25578222, 125.33632335, 0, '2026-02-03 17:55:37'),
(5, 'Batalay', 'Arteche', 'Eastern Samar', 12.29300000, 125.39800000, 0, '2026-02-03 17:55:37'),
(6, 'Beri', 'Arteche', 'Eastern Samar', 12.29402409, 125.35752715, 0, '2026-02-03 17:55:37'),
(7, 'Bigo', 'Arteche', 'Eastern Samar', 12.27500000, 125.43400000, 0, '2026-02-03 17:55:37'),
(8, 'Bonifacio', 'Arteche', 'Eastern Samar', 12.30400000, 125.38300000, 0, '2026-02-03 17:55:37'),
(9, 'Buenavista', 'Arteche', 'Eastern Samar', 12.31200000, 125.36700000, 0, '2026-02-03 17:55:37'),
(11, 'Campacion', 'Arteche', 'Eastern Samar', 12.29400000, 125.45300000, 0, '2026-02-03 17:55:37'),
(12, 'Carapdapan', 'Arteche', 'Eastern Samar', 12.33400000, 125.50500000, 0, '2026-02-03 17:55:37'),
(13, 'Casidman', 'Arteche', 'Eastern Samar', 12.32100000, 125.48900000, 0, '2026-02-03 17:55:37'),
(14, 'Catumsan', 'Arteche', 'Eastern Samar', 12.27566077, 125.31818694, 0, '2026-02-03 17:55:37'),
(15, 'Central', 'Arteche', 'Eastern Samar', 12.26500000, 125.52300000, 0, '2026-02-03 17:55:37'),
(17, 'Rawis', 'Arteche', 'Eastern Samar', 12.33600000, 125.41800000, 0, '2026-02-03 17:55:37'),
(18, 'Inayawan', 'Arteche', 'Eastern Samar', 12.35800000, 125.38800000, 0, '2026-02-03 17:55:37'),
(20, 'Magsaysay', 'Arteche', 'Eastern Samar', 12.32400000, 125.53600000, 0, '2026-02-03 17:55:37'),
(21, 'Matin-ab', 'Arteche', 'Eastern Samar', 12.28900000, 125.55700000, 0, '2026-02-03 17:55:37'),
(22, 'Poblacion', 'Arteche', 'Eastern Samar', 12.26300000, 125.50200000, 0, '2026-02-03 17:55:37'),
(26, 'Tawagan', 'Arteche', 'Eastern Samar', 12.30200000, 125.44200000, 0, '2026-02-03 17:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `citizens`
--

CREATE TABLE `citizens` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `middle_name` varchar(40) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT NULL,
  `civil_status` enum('Single','Married','Divorced','Separated','Widowed') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `account_status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citizens`
--

INSERT INTO `citizens` (`id`, `email`, `phone`, `password`, `first_name`, `last_name`, `suffix`, `middle_name`, `birth_date`, `gender`, `civil_status`, `occupation`, `address`, `barangay_id`, `verification_code`, `is_verified`, `account_status`, `created_at`, `updated_at`, `last_login`, `avatar`) VALUES
(1, 'loyd@gmail.com', '09123456789', '$2y$12$SBnn1CnYQMvHE7ZqYAS8ce/pM6dZ/UFlkplHWSJrV90glRK3B1ljK', 'loyd', 'cesista', NULL, 'G.', '2005-06-11', NULL, NULL, NULL, 'seaside street', 6, '7A0F67', 0, 'Inactive', '2026-02-05 22:24:21', NULL, NULL, NULL),
(2, 'tasha@gmail.com', '09533953075', '$2y$12$PN.u9H6Rmh47rrOe4/wEoOO4acIWkzuTHzPd14lPoUqrOzrKjN0Da', 'Atasha', 'Andrae', NULL, 'Guilleno', '0005-06-11', NULL, NULL, NULL, 'Arteche Eastern Samar, Philippines', 3, '49ECED', 0, 'Inactive', '2026-02-06 13:05:49', NULL, NULL, NULL),
(3, 'christiangcesista@gmail.com', '09103323380', '$2y$12$hr8JA3Dd1bovAS7tWJUdJeE1svQOeRgn6pPUZoBkWmHSQ5KpGhzNO', 'christian loyd', 'cesista', NULL, 'guilleno', '2005-11-06', NULL, NULL, NULL, 'Seaside', 6, NULL, 1, 'Active', '2026-02-06 21:03:59', NULL, NULL, NULL),
(4, 'user12@gmail.com', '09223456789', '$2y$12$Eq9V.yUzOWdIhajsDBF/z.c0cc1wBR7oy1XtK0K5.fEDisOfS7J6G', 'user12', 'test', NULL, 'g.', '2000-06-11', NULL, NULL, NULL, 'Seaside', 6, NULL, 1, 'Active', '2026-02-10 03:09:00', '2026-03-04 13:07:41', '2026-03-04 13:07:41', 'avatar_4_1771711736.png'),
(5, 'loyd12@gmail.com', '09122222221', '$2y$12$oIlmX6K7JO/SToF1mYsDd.l.JT6kUqnqyLUX85Z66HdR6HZcnsaqi', 'loyd', 'cesista', NULL, 'G.', '2005-06-11', NULL, NULL, NULL, 'Sea wall', 1, NULL, 1, 'Active', '2026-02-18 08:35:57', NULL, '2026-02-18 09:03:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `citizen_notifications`
--

CREATE TABLE `citizen_notifications` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('Request Update','Payment','Reminder','System') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `citizen_requests`
--

CREATE TABLE `citizen_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(20) DEFAULT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `document_type_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','Approved','Rejected','Ready for Pickup','Completed','Cancelled') DEFAULT 'Draft',
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Pending','Paid','Waived') DEFAULT 'Pending',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citizen_requests`
--

INSERT INTO `citizen_requests` (`id`, `request_number`, `citizen_id`, `document_type_id`, `purpose`, `status`, `fee`, `payment_status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `released_at`, `completed_at`, `rejection_reason`, `notes`, `document_path`, `created_at`) VALUES
(1, 'REQ-20260217-000004-', 4, 2, 'For scholarship', 'Draft', 0.00, 'Pending', '2026-02-17 06:41:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-17 13:41:29'),
(2, 'REQ-20260218-000005-', 5, 2, 'For demo purposes', 'Draft', 0.00, 'Pending', '2026-02-18 01:36:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 08:36:53');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `processing_days` int(11) DEFAULT 3,
  `fee` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `name`, `description`, `requirements`, `processing_days`, `fee`, `is_active`) VALUES
(1, 'Barangay Clearance', 'For employment, business permits, etc.', NULL, 2, 50.00, 1),
(2, 'Certificate of Indigency', 'For medical assistance, scholarships', NULL, 3, 0.00, 1),
(3, 'Certificate of Residency', 'Proof of barangay residency', NULL, 1, 30.00, 1),
(4, 'Business Permit', 'For local business operations', NULL, 5, 200.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(11) NOT NULL,
  `household_identifier` varchar(50) DEFAULT '',
  `barangay_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `contact_number` varchar(15) DEFAULT NULL,
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
  `surveyed_by` varchar(100) DEFAULT '',
  `surveyed_date` date DEFAULT curdate(),
  `notes` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT 'Tangbo',
  `survey_date` date DEFAULT curdate(),
  `date_submitted` timestamp NOT NULL DEFAULT current_timestamp(),
  `risk_score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `household_identifier`, `barangay_id`, `name`, `contact_number`, `age`, `sex`, `civil_status`, `household_size`, `income_monthly`, `income_per_capita`, `income_source`, `four_ps`, `housing_type`, `water_source`, `toilet_type`, `employment`, `disability`, `senior_citizen`, `vulnerability_index`, `latitude`, `longitude`, `surveyed_by`, `surveyed_date`, `notes`, `barangay`, `survey_date`, `date_submitted`, `risk_score`) VALUES
(1, '', 1, 'Christain', NULL, 21, 'Male', 'Single', 4, 5000.00, 1250.00, 'farming', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 5, 12.2692022, 125.3714274, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 15:54:54', 0),
(2, '', 1, 'Christain', NULL, 21, 'Male', 'Single', 4, 5000.00, 1250.00, 'farming', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 5, 12.2692022, 125.3714274, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 15:56:14', 80),
(3, '', 1, 'Loyd', NULL, 23, 'Male', 'Single', 6, 2000.00, 333.33, 'Lab\'as', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 8, 10.3307000, 123.9205000, '', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-03 16:03:41', 94),
(4, '', 1, 'Rolly', NULL, 56, 'Male', 'Married', 6, 6000.00, 1000.00, 'Farming', 'Yes', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Self-Employed', 'No', 'No', 7, 12.2692022, 125.3714274, '', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-03 17:37:22', 54),
(5, '', 2, 'Juan', NULL, 32, 'Male', 'Single', 10, 10000.00, 1000.00, 'Fishing', 'Yes', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Employed', 'Yes', 'Yes', 8, 12.2692022, 125.3714274, '', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-03 18:03:51', 73),
(6, '', 3, 'test', NULL, 20, 'Male', 'Single', 7, 6000.00, 857.14, 'Business', 'Yes', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Unemployed', 'No', 'No', 7, 12.2692022, 125.3714274, '', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-03 20:13:54', 69),
(7, '', NULL, 'Kikay Marteja', NULL, 23, 'Male', 'Single', 7, 5000.00, 714.29, 'Fishing', 'No', NULL, NULL, NULL, NULL, 'No', 'No', 5, 12.2691961, 125.3713871, '', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-03 20:40:03', 57),
(8, '', 1, 'newUser', '09533953075', 67, 'Male', 'Married', 8, 6000.00, 750.00, 'Pension', 'No', 'Makeshift', 'Level III (Waterworks System)', 'Antipolo', 'Self-Employed', 'Yes', 'Yes', 7, 10.3307000, 123.9205000, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 21:55:10', 74),
(9, '', 1, 'testUser', '09533953075', 67, 'Male', 'Widowed', 8, 6000.00, 750.00, 'Fishing', 'No', 'Wood', '', '', 'Self-Employed', 'No', 'No', 8, 10.3307000, 123.9205000, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 21:58:02', 60),
(10, '', 1, 'testUser12', '09533953075', 67, 'Male', 'Married', 8, 6000.00, 750.00, 'Private Employee', 'Yes', 'Makeshift', 'Deep Well', 'Antipolo', 'Self-Employed', 'Yes', 'Yes', 8, 10.3307000, 123.9205000, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 22:12:56', 93),
(11, '', 1, 'testUser12', '09533953075', 67, 'Male', 'Married', 8, 6000.00, 750.00, 'Private Employee', 'Yes', 'Makeshift', 'Deep Well', 'Antipolo', 'Self-Employed', 'Yes', 'Yes', 8, 10.3307000, 123.9205000, '', '2026-02-04', NULL, 'Tangbo', '2026-02-03', '2026-02-03 22:15:49', 93),
(12, 'AGU-20260204-0796', 2, 'testUser2', '09533953075', 30, 'Male', 'Single', 7, 5000.00, 714.29, 'Fishing', 'No', 'Concrete', 'Level III (Waterworks System)', 'Antipolo', 'Employed', 'Yes', 'No', 8, 12.2692020, 125.3714270, 'admin', '2026-02-04', NULL, 'Aguinaldo', '2026-02-04', '2026-02-04 03:23:07', 65),
(13, 'AGU-20260204-4558', 2, 'juan', '09533953075', 20, 'Male', 'Single', 40, 5000.00, 125.00, 'Fishing', 'No', 'Concrete', 'Level II (Communal Faucet)', 'Antipolo', 'Employed', 'No', 'No', 6, 12.2696250, 125.3711080, 'admin', '2026-02-04', NULL, 'Aguinaldo', '2026-02-04', '2026-02-04 03:43:31', 56),
(14, 'TAN-20260204-5012', 1, 'juana', '09533953075', 67, 'Female', 'Married', 10, 15000.00, 1500.00, 'Private Employee', 'No', 'Concrete', 'Level II (Communal Faucet)', 'Water-sealed', 'Employed', 'No', 'No', 8, 12.2696250, 125.3711080, 'tangbo_admin', '2026-02-04', NULL, 'Tangbo', '2026-02-04', '2026-02-04 03:45:07', 43),
(15, 'AGU-20260204-9876', 2, 'paul', '09123456789', 54, 'Male', '', 10, 10000.00, 1000.00, 'Farming', 'No', 'Concrete', 'Level II (Communal Faucet)', 'Water-sealed', 'Employed', 'No', 'No', 6, 12.2703890, 125.3707430, 'cesista_super_admin', '2026-02-04', NULL, 'Aguinaldo', '2026-02-04', '2026-02-04 04:43:19', 41),
(16, 'BER-20260204-2730', 6, 'Rolly', '09533953075', 56, 'Male', 'Married', 6, 6000.00, 1000.00, 'Farming', 'No', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Self-Employed', 'No', 'No', 7, 12.2694640, 125.3713070, 'admin', '2026-02-04', NULL, 'Beri', '2026-02-04', '2026-02-04 13:52:55', 39),
(17, 'BER-20260204-0147', 6, 'Rolly', '09533953075', 56, 'Male', 'Married', 6, 6000.00, 1000.00, 'Farming', 'No', 'Wood', 'Level II (Communal Faucet)', 'Water-sealed', 'Self-Employed', 'No', 'No', 7, 12.2694640, 125.3713070, 'admin', '2026-02-04', NULL, 'Beri', '2026-02-04', '2026-02-04 13:53:16', 39),
(18, 'TAN-20260205-2939', 1, 'Stephanie', '09123456789', 21, 'Female', 'Single', 8, 10000.00, 1250.00, 'Private Employee', 'No', '', '', '', 'Student', 'No', 'No', 5, 12.2701232, 125.3395333, 'Tangbo_BHWS', '2026-02-05', NULL, 'Tangbo', '2026-02-05', '2026-02-05 09:06:43', 42),
(19, 'CAR-20260206-2703', 12, 'Restituta A. Camposano', '09154691096', 85, 'Male', 'Married', 10, 15000.00, 1500.00, 'Pension', 'No', 'Concrete', 'Level II (Communal Faucet)', 'Water-sealed', 'Retired', 'No', 'Yes', 6, 12.2648910, 125.4040710, 'admin', '2026-02-06', NULL, 'Carapdapan', '2026-02-06', '2026-02-06 11:57:10', 46),
(20, 'AGU-20260209-5634', 2, 'jason', '09123456788', 20, 'Male', 'Single', 20, 7000.00, 350.00, 'Government Employee', 'Yes', 'Concrete', 'Level I (Point Source)', 'Water-sealed', 'Employed', 'No', 'No', 6, 10.3307000, 123.9205000, 'BHW', '2026-02-09', NULL, 'Aguinaldo', '2026-02-09', '2026-02-09 09:09:03', 71),
(21, 'TAN-20260210-7418', 1, 'test', '09123456676', 20, 'Male', 'Married', 6, 100000.00, 16666.67, 'Government Employee', 'No', 'Concrete', 'Level II (Communal Faucet)', 'Antipolo', 'Employed', 'No', 'No', 5, 12.2709323, 125.3408207, 'tangbo_admin', '2026-02-10', NULL, 'Tangbo', '2026-02-10', '2026-02-10 14:26:00', 17),
(22, 'CEN-20260216-1737', 15, 'Juan', '09234567898', 45, 'Male', 'Married', 8, 8000.00, 1000.00, 'Others', 'Yes', 'Concrete', 'Level II (Communal Faucet)', 'Water-sealed', 'Self-Employed', 'No', 'No', 0, 12.2693650, 125.3717000, 'admin', '2026-02-16', NULL, 'Central', '2026-02-16', '2026-02-16 08:11:09', 47);

-- --------------------------------------------------------

--
-- Table structure for table `request_attachments`
--

CREATE TABLE `request_attachments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'tangbo_admin', 'Tangbo Barangay Captain', '51457781324173df80488fc85370d37b', 1, 'barangay_admin', '2026-02-03 17:00:23');

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
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_announcement_active` (`is_active`),
  ADD KEY `idx_announcement_published` (`published_at`),
  ADD KEY `idx_announcement_expires` (`expires_at`),
  ADD KEY `idx_announcement_category` (`category`),
  ADD KEY `idx_announcement_priority` (`priority`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_announcement_citizen` (`announcement_id`,`citizen_id`),
  ADD UNIQUE KEY `unique_announcement_user` (`announcement_id`,`user_id`),
  ADD KEY `citizen_id` (`citizen_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_read_at` (`read_at`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `citizens`
--
ALTER TABLE `citizens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `idx_gender` (`gender`),
  ADD KEY `idx_civil_status` (`civil_status`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `citizen_notifications`
--
ALTER TABLE `citizen_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizen_id` (`citizen_id`);

--
-- Indexes for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `citizen_id` (`citizen_id`),
  ADD KEY `document_type_id` (`document_type_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_request_status` (`status`),
  ADD KEY `idx_request_payment` (`payment_status`),
  ADD KEY `idx_request_submitted` (`submitted_at`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- Indexes for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `citizens`
--
ALTER TABLE `citizens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `citizen_notifications`
--
ALTER TABLE `citizen_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `request_attachments`
--
ALTER TABLE `request_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `citizens`
--
ALTER TABLE `citizens`
  ADD CONSTRAINT `citizens_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `citizen_notifications`
--
ALTER TABLE `citizen_notifications`
  ADD CONSTRAINT `citizen_notifications_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`);

--
-- Constraints for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  ADD CONSTRAINT `fk_request_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_request_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  ADD CONSTRAINT `fk_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `households_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD CONSTRAINT `request_attachments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
