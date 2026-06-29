-- Test Data for citizen_requests (run in phpMyAdmin)
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

INSERT INTO `citizen_requests` (`request_number`, `citizen_id`, `document_type_id`, `purpose`, `status`, `payment_status`, `submitted_at`, `reviewed_by`, `reviewed_at`) VALUES
('REQ-20241201-000001-123', 1, 1, 'Employment application at local company', 'Submitted', 'Pending', '2024-12-01 09:30:00', NULL, NULL),
('REQ-20241201-000002-456', 2, 2, 'Medical financial assistance', 'Under Review', 'Pending', '2024-12-01 10:15:00', 1, '2024-12-01 11:00:00'),
('REQ-20241201-000003-789', 3, 1, 'Business permit renewal', 'Approved', 'Paid', '2024-12-01 14:20:00', 1, '2024-12-01 15:30:00'),
('REQ-20241202-000004-101', 4, 3, 'School residency certificate', 'Ready for Pickup', 'Paid', '2024-12-02 08:45:00', 1, '2024-12-02 09:30:00'),
('REQ-20241202-000005-112', 5, 2, 'Scholarship indigency', 'Rejected', 'Waived', '2024-12-02 11:20:00', 1, '2024-12-02 14:00:00'),
('REQ-20241203-000006-334', 1, 1, 'Police clearance for employment', 'Completed', 'Paid', '2024-12-03 13:30:00', 1, '2024-12-03 15:45:00'),
('REQ-20241203-000007-556', 6, 4, 'Local business registration', 'Submitted', 'Pending', '2024-12-03 16:10:00', NULL, NULL);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

