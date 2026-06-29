-- ============================================
-- CITIZEN PORTAL DATABASE SCHEMA
-- ============================================

-- 1. Citizens table (separate from households)
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

-- 2. Document types table
CREATE TABLE IF NOT EXISTS document_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE, -- e.g., "BRGY-CLEAR", "INDIGENCY"
    name VARCHAR(100) UNIQUE,
    description TEXT,
    requirements TEXT, -- JSON format for requirements checklist
    processing_days INT DEFAULT 3,
    processing_fee DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    icon_class VARCHAR(50) DEFAULT 'bi-file-text', -- Bootstrap icon class
    color VARCHAR(20) DEFAULT '#0d6efd', -- Color for UI
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Citizen document requests
CREATE TABLE IF NOT EXISTS citizen_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(20) UNIQUE,
    citizen_id INT,
    document_type_id INT,
    purpose TEXT,
    additional_info TEXT,
    status ENUM('Draft','Submitted','Under Review','For Payment','Approved','Rejected','Ready for Pickup','Completed','Cancelled') DEFAULT 'Draft',
    submitted_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    document_path VARCHAR(255),
    payment_status ENUM('Pending','Paid','Free','Waived') DEFAULT 'Pending',
    payment_reference VARCHAR(100),
    payment_date TIMESTAMP NULL,
    pickup_code VARCHAR(10), -- Code for document pickup
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_request_number (request_number),
    INDEX idx_citizen (citizen_id),
    INDEX idx_status (status),
    INDEX idx_document_type (document_type_id)
);

-- 4. Request attachments
CREATE TABLE IF NOT EXISTS request_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    file_size INT,
    is_verified BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES citizen_requests(id) ON DELETE CASCADE,
    INDEX idx_request (request_id)
);

-- 5. Notifications table
CREATE TABLE IF NOT EXISTS citizen_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT,
    title VARCHAR(100),
    message TEXT,
    type ENUM('Request Update','Payment','Reminder','System','Announcement'),
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255), -- URL for action
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE,
    INDEX idx_citizen (citizen_id),
    INDEX idx_read_status (is_read),
    INDEX idx_created (created_at DESC)
);

-- 6. Document requirements table (for dynamic requirements)
CREATE TABLE IF NOT EXISTS document_requirements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_type_id INT,
    requirement_text VARCHAR(255),
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE CASCADE,
    INDEX idx_document_type (document_type_id)
);

-- ============================================
-- INSERT INITIAL DATA
-- ============================================

-- Insert default document types
INSERT INTO document_types (code, name, description, requirements, processing_days, processing_fee, icon_class, color) VALUES
('BRGY-CLEAR', 'Barangay Clearance', 'Certificate for good moral character and residency', '["Valid ID", "Proof of Residency", "Recent 2x2 Photo"]', 2, 50.00, 'bi-file-check', '#0d6efd'),
('INDIGENCY', 'Certificate of Indigency', 'Certifies that an individual belongs to an indigent family', '["Valid ID", "Proof of Income", "Family Members Information"]', 3, 0.00, 'bi-people', '#198754'),
('RESIDENCY', 'Certificate of Residency', 'Certifies residency in the barangay', '["Valid ID", "Proof of Residency (Utility Bill)", "Recent 2x2 Photo"]', 1, 30.00, 'bi-house', '#6f42c1'),
('BUS-PERMIT', 'Business Permit Application', 'Application for barangay business permit', '["DTI/SEC Registration", "Business Location Sketch", "Proof of Ownership/Lease"]', 5, 200.00, 'bi-briefcase', '#fd7e14'),
('SOLO-PARENT', 'Solo Parent ID Application', 'Application for Solo Parent Identification Card', '["Birth Certificate of Children", "Proof of Solo Parenting", "Valid ID", "Recent 2x2 Photo"]', 7, 0.00, 'bi-person', '#e83e8c'),
('SENIOR-ID', 'Senior Citizen ID Application', 'Application for Senior Citizen Identification Card', '["Birth Certificate", "Valid ID", "Recent 2x2 Photo"]', 3, 0.00, 'bi-person-badge', '#20c997');

-- Insert requirements for each document type
INSERT INTO document_requirements (document_type_id, requirement_text, is_required, sort_order) VALUES
(1, 'Valid Government ID (Original and Photocopy)', TRUE, 1),
(1, 'Proof of Residency (Utility Bill, Lease Contract)', TRUE, 2),
(1, 'Recent 2x2 ID Picture (2 copies)', TRUE, 3),
(1, 'Fully Accomplished Application Form', TRUE, 4),
(2, 'Valid Government ID', TRUE, 1),
(2, 'Latest 3 Months Income Statement', TRUE, 2),
(2, 'Family Members Information', TRUE, 3),
(2, 'Barangay Certificate of Residency', TRUE, 4),
(3, 'Valid Government ID', TRUE, 1),
(3, 'Proof of Residency (at least 6 months)', TRUE, 2),
(3, 'Recent 2x2 ID Picture', TRUE, 3),
(4, 'DTI/SEC Registration', TRUE, 1),
(4, 'Sketch of Business Location', TRUE, 2),
(4, 'Proof of Ownership/Lease Contract', TRUE, 3),
(4, 'Barangay Clearance', TRUE, 4),
(5, 'Birth Certificate of Children', TRUE, 1),
(5, 'Proof of Solo Parenting', TRUE, 2),
(5, 'Valid Government ID', TRUE, 3),
(5, 'Recent 2x2 ID Picture', TRUE, 4),
(6, 'Birth Certificate (Proof of Age 60+)', TRUE, 1),
(6, 'Valid Government ID', TRUE, 2),
(6, 'Recent 1x1 ID Picture', TRUE, 3);

-- ============================================
-- CREATE STORED PROCEDURES
-- ============================================

DELIMITER $$

-- Procedure to generate unique request number
CREATE PROCEDURE GenerateRequestNumber(IN doc_code VARCHAR(20), OUT request_num VARCHAR(20))
BEGIN
    DECLARE date_part VARCHAR(8);
    DECLARE seq_num INT;
    
    SET date_part = DATE_FORMAT(NOW(), '%Y%m%d');
    
    -- Get next sequence number for today
    SELECT COALESCE(MAX(SUBSTRING(request_number, 12)), 0) + 1 INTO seq_num
    FROM citizen_requests 
    WHERE request_number LIKE CONCAT(doc_code, '-', date_part, '-%');
    
    -- If no requests today, start from 1
    IF seq_num IS NULL THEN
        SET seq_num = 1;
    END IF;
    
    -- Format: DOCCODE-YYYYMMDD-00001
    SET request_num = CONCAT(doc_code, '-', date_part, '-', LPAD(seq_num, 5, '0'));
END$$

-- Procedure to update request status with notification
CREATE PROCEDURE UpdateRequestStatus(
    IN p_request_id INT,
    IN p_status VARCHAR(20),
    IN p_reviewer_id INT,
    IN p_reason TEXT
)
BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_request_number VARCHAR(20);
    DECLARE v_notification_title VARCHAR(100);
    DECLARE v_notification_message TEXT;
    
    -- Get citizen ID and request number
    SELECT citizen_id, request_number INTO v_citizen_id, v_request_number
    FROM citizen_requests WHERE id = p_request_id;
    
    -- Update request
    UPDATE citizen_requests 
    SET status = p_status,
        reviewed_by = p_reviewer_id,
        reviewed_at = NOW(),
        rejection_reason = CASE WHEN p_status = 'Rejected' THEN p_reason ELSE rejection_reason END,
        approved_at = CASE WHEN p_status = 'Approved' THEN NOW() ELSE approved_at END
    WHERE id = p_request_id;
    
    -- Create notification based on status
    CASE p_status
        WHEN 'Under Review' THEN
            SET v_notification_title = 'Request Under Review';
            SET v_notification_message = CONCAT('Your request ', v_request_number, ' is now under review.');
        WHEN 'Approved' THEN
            SET v_notification_title = 'Request Approved!';
            SET v_notification_message = CONCAT('Your request ', v_request_number, ' has been approved. You can now download your document.');
        WHEN 'Rejected' THEN
            SET v_notification_title = 'Request Rejected';
            SET v_notification_message = CONCAT('Your request ', v_request_number, ' has been rejected. Reason: ', p_reason);
        WHEN 'Ready for Pickup' THEN
            SET v_notification_title = 'Document Ready for Pickup';
            SET v_notification_message = CONCAT('Your document for request ', v_request_number, ' is ready for pickup at the barangay hall.');
        ELSE
            SET v_notification_title = 'Request Status Updated';
            SET v_notification_message = CONCAT('Status updated for request ', v_request_number, ' to: ', p_status);
    END CASE;
    
    -- Insert notification
    INSERT INTO citizen_notifications (citizen_id, title, message, type, link)
    VALUES (v_citizen_id, v_notification_title, v_notification_message, 'Request Update', 
            CONCAT('/citizen/request_details.php?id=', p_request_id));
END$$

DELIMITER ;