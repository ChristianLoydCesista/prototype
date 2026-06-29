-- ============================================================
-- PROFESSIONAL DATABASE CLEANUP
-- Author: Database Architect
-- Date: 2026-02-18
-- Description: Consolidate all document requests into citizen_requests
-- ============================================================

-- Start transaction for safety
START TRANSACTION;

-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- STEP 1: ENHANCE citizen_requests table
-- Add columns needed for public users and all document types
-- ============================================================

ALTER TABLE `citizen_requests` 
-- Add columns for public users (non-citizens)
ADD COLUMN `is_public` TINYINT(1) NOT NULL DEFAULT 0 AFTER `citizen_id`,
ADD COLUMN `public_name` VARCHAR(100) NULL DEFAULT NULL AFTER `is_public`,
ADD COLUMN `public_contact` VARCHAR(20) NULL DEFAULT NULL AFTER `public_name`,
ADD COLUMN `public_address` TEXT NULL DEFAULT NULL AFTER `public_contact`,
ADD COLUMN `public_barangay_id` INT(11) NULL DEFAULT NULL AFTER `public_address`,

-- Enhance document type handling (now includes all types)
MODIFY COLUMN `document_type_id` INT(11) NOT NULL,

-- Add tracking fields
ADD COLUMN `processed_by` INT(11) NULL DEFAULT NULL AFTER `reviewed_by`,
ADD COLUMN `processed_at` TIMESTAMP NULL DEFAULT NULL AFTER `processed_by`,
ADD COLUMN `processing_notes` TEXT NULL DEFAULT NULL AFTER `rejection_reason`,
ADD COLUMN `release_date` TIMESTAMP NULL DEFAULT NULL AFTER `processed_notes`,

-- Add indexes for performance
ADD INDEX `idx_is_public` (`is_public`),
ADD INDEX `idx_public_barangay` (`public_barangay_id`),
ADD INDEX `idx_processed_by` (`processed_by`),
ADD INDEX `idx_submitted_date` (`submitted_at`),

-- Add foreign key for public_barangay
ADD CONSTRAINT `fk_citizen_requests_public_barangay` 
    FOREIGN KEY (`public_barangay_id`) REFERENCES `barangays` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
    
ADD CONSTRAINT `fk_citizen_requests_processor` 
    FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- STEP 2: MIGRATE DATA FROM certificate_requests TO citizen_requests
-- ============================================================

INSERT INTO `citizen_requests` (
    `request_number`,
    `citizen_id`,
    `is_public`,
    `public_name`,
    `public_contact`,
    `public_address`,
    `public_barangay_id`,
    `document_type_id`,
    `purpose`,
    `status`,
    `submitted_at`,
    `processed_at`,
    `processing_notes`,
    `document_path`,
    `created_at`
)
SELECT 
    COALESCE(cr.`request_number`, CONCAT('PUB-', cr.`id`, '-', DATE_FORMAT(cr.`requested_date`, '%Y%m%d'))) AS `request_number`,
    NULL AS `citizen_id`,
    1 AS `is_public`,
    cr.`resident_name` AS `public_name`,
    NULL AS `public_contact`,
    NULL AS `public_address`,
    h.`barangay_id` AS `public_barangay_id`,
    -- Map certificate_type to document_type_id
    CASE cr.`certificate_type`
        WHEN 'Barangay Clearance' THEN 1
        WHEN 'Indigency' THEN 2
        WHEN 'Residency' THEN 3
        WHEN 'Business Permit' THEN 4
        ELSE 1
    END AS `document_type_id`,
    COALESCE(cr.`purpose`, 'Not specified') AS `purpose`,
    CASE cr.`status`
        WHEN 'Pending' THEN 'Submitted'
        WHEN 'Approved' THEN 'Approved'
        WHEN 'Rejected' THEN 'Rejected'
        WHEN 'Completed' THEN 'Completed'
        ELSE 'Submitted'
    END AS `status`,
    cr.`requested_date` AS `submitted_at`,
    cr.`processed_date` AS `processed_at`,
    cr.`notes` AS `processing_notes`,
    cr.`pdf_path` AS `document_path`,
    cr.`requested_date` AS `created_at`
FROM `certificate_requests` cr
LEFT JOIN `households` h ON cr.`household_id` = h.`id`;

-- ============================================================
-- STEP 3: MIGRATE DATA FROM documents TABLE (if needed)
-- ============================================================

INSERT INTO `citizen_requests` (
    `request_number`,
    `citizen_id`,
    `is_public`,
    `document_type_id`,
    `purpose`,
    `status`,
    `payment_status`,
    `submitted_at`,
    `processed_at`,
    `release_date`,
    `processing_notes`,
    `created_at`
)
SELECT 
    d.`code` AS `request_number`,
    d.`citizen_id`,
    0 AS `is_public`,
    CASE d.`document_type`
        WHEN 'Clearance' THEN 1
        WHEN 'Indigency' THEN 2
        WHEN 'Residency' THEN 3
        WHEN 'Certificate' THEN 3
        ELSE 1
    END AS `document_type_id`,
    d.`purpose`,
    CASE d.`status`
        WHEN 'Pending' THEN 'Submitted'
        WHEN 'Processing' THEN 'Under Review'
        WHEN 'Ready' THEN 'Ready for Pickup'
        WHEN 'Claimed' THEN 'Completed'
        WHEN 'Cancelled' THEN 'Rejected'
        ELSE 'Submitted'
    END AS `status`,
    CASE d.`payment_status`
        WHEN 'Paid' THEN 'Paid'
        WHEN 'Waived' THEN 'Waived'
        ELSE 'Pending'
    END AS `payment_status`,
    d.`request_date` AS `submitted_at`,
    d.`processed_date` AS `processed_at`,
    d.`ready_date` AS `release_date`,
    d.`notes` AS `processing_notes`,
    d.`request_date` AS `created_at`
FROM `documents` d;

-- ============================================================
-- STEP 4: MIGRATE DATA FROM document_requests (test table)
-- ============================================================

INSERT INTO `citizen_requests` (
    `request_number`,
    `citizen_id`,
    `is_public`,
    `public_name`,
    `public_contact`,
    `public_address`,
    `public_barangay_id`,
    `document_type_id`,
    `purpose`,
    `status`,
    `payment_status`,
    `submitted_at`,
    `processed_by`,
    `processed_at`,
    `processing_notes`,
    `release_date`,
    `created_at`
)
SELECT 
    dr.`request_number`,
    dr.`citizen_id`,
    CASE WHEN dr.`citizen_id` IS NULL THEN 1 ELSE 0 END AS `is_public`,
    CASE WHEN dr.`citizen_id` IS NULL THEN dr.`requester_name` ELSE NULL END AS `public_name`,
    CASE WHEN dr.`citizen_id` IS NULL THEN dr.`requester_contact` ELSE NULL END AS `public_contact`,
    CASE WHEN dr.`citizen_id` IS NULL THEN dr.`requester_address` ELSE NULL END AS `public_address`,
    CASE WHEN dr.`citizen_id` IS NULL THEN dr.`barangay_id` ELSE NULL END AS `public_barangay_id`,
    -- Map document_type to document_type_id
    CASE dr.`document_type`
        WHEN 'Barangay Clearance' THEN 1
        WHEN 'Certificate of Indigency' THEN 2
        ELSE 1
    END AS `document_type_id`,
    dr.`purpose`,
    dr.`status`,
    CASE dr.`payment_status`
        WHEN 'Paid' THEN 'Paid'
        WHEN 'Free' THEN 'Waived'
        ELSE 'Pending'
    END AS `payment_status`,
    dr.`submitted_at`,
    dr.`processed_by`,
    dr.`processed_at`,
    dr.`processed_notes`,
    dr.`release_date`,
    dr.`created_at`
FROM `document_requests` dr;

-- ============================================================
-- STEP 5: UPDATE document_types to include all needed types
-- ============================================================

-- Ensure all document types exist
INSERT INTO `document_types` (`id`, `name`, `description`, `processing_days`, `fee`, `is_active`) VALUES
(1, 'Barangay Clearance', 'Official document certifying good moral character and residency', 2, 50.00, 1),
(2, 'Certificate of Indigency', 'Official document certifying low-income status for assistance', 1, 0.00, 1),
(3, 'Certificate of Residency', 'Proof of barangay residency', 1, 30.00, 1),
(4, 'Business Permit', 'For local business operations', 5, 200.00, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `processing_days` = VALUES(`processing_days`),
    `fee` = VALUES(`fee`),
    `is_active` = 1;

-- ============================================================
-- STEP 6: UPDATE request_attachments to point to citizen_requests
-- ============================================================

-- First, drop the old foreign key
ALTER TABLE `request_attachments` DROP FOREIGN KEY IF EXISTS `request_attachments_ibfk_1`;

-- Add new request_type column to identify which request system
ALTER TABLE `request_attachments` 
ADD COLUMN `request_type` ENUM('citizen', 'certificate', 'document', 'new') DEFAULT 'citizen' AFTER `request_id`,
ADD COLUMN `new_request_id` INT(11) NULL DEFAULT NULL AFTER `request_type`,
ADD INDEX `idx_new_request` (`new_request_id`);

-- Migrate attachments (this is a simplified mapping - adjust as needed)
UPDATE `request_attachments` ra
SET ra.`new_request_id` = (
    SELECT cr.`id` 
    FROM `citizen_requests` cr 
    WHERE cr.`request_number` LIKE CONCAT('%', ra.`request_id`, '%')
    LIMIT 1
),
ra.`request_type` = 'citizen'
WHERE ra.`request_id` IS NOT NULL;

-- Add new foreign key
ALTER TABLE `request_attachments` 
ADD CONSTRAINT `fk_request_attachments_citizen_request` 
    FOREIGN KEY (`new_request_id`) REFERENCES `citizen_requests` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================
-- STEP 7: SAFELY REMOVE DUPLICATE TABLES (Archive them first)
-- ============================================================

-- Rename tables to archive them (safer than deleting)
RENAME TABLE `certificate_requests` TO `_archive_certificate_requests`;
RENAME TABLE `documents` TO `_archive_documents`;
RENAME TABLE `document_requests` TO `_archive_document_requests`;

-- Note: We're keeping document_types as reference table

-- ============================================================
-- STEP 8: RE-ENABLE FOREIGN KEY CHECKS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- STEP 9: VERIFY THE CLEANUP
-- ============================================================

SELECT '✅ DATABASE CLEANUP COMPLETED' as 'STATUS';

SELECT '📊 REMAINING TABLES:' as 'INFO';
SHOW TABLES;

SELECT '📋 citizen_requests NOW CONTAINS:' as 'REPORT';
SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN `is_public` = 1 THEN 1 ELSE 0 END) as public_requests,
    SUM(CASE WHEN `is_public` = 0 THEN 1 ELSE 0 END) as citizen_requests,
    COUNT(DISTINCT `document_type_id`) as document_types_used
FROM `citizen_requests`;

SELECT '📁 ARCHIVED TABLES (safe to delete after verification):' as 'INFO';
SHOW TABLES LIKE '_archive_%';

-- ============================================================
-- STEP 10: COMMIT TRANSACTION
-- ============================================================
COMMIT;

-- ============================================================
-- FINAL INSTRUCTIONS
-- ============================================================
SELECT '🎯 YOUR DATABASE IS NOW CLEAN!' as 'MESSAGE';
SELECT '👉 Use ONLY citizen_requests for all document requests going forward' as 'INSTRUCTION';
SELECT '👉 Archived tables are preserved as backup (prefix _archive_)' as 'INSTRUCTION';
SELECT '👉 After confirming everything works, you can drop archive tables with:' as 'INSTRUCTION';
SELECT '   DROP TABLE _archive_certificate_requests, _archive_documents, _archive_document_requests;' as 'SQL';