<?php
// citizen_profile.php
require_once '../shared/bootstrap.php';
// everything required by profile is handled in bootstrap

$session = new Session();

// Redirect if not logged in
if (!$session->isCitizenLoggedIn()) {
    $session->setFlash('error', 'Please login first');
    header("Location: citizen_portal.php");
    exit;
}

$citizen = $session->getCitizen();
$auth = new Auth();

// Get complete citizen data including profile
$citizenData = $auth->getCitizen($citizen['id']);

// Get additional statistics for profile view
$db = getDB();

// Get request statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_requests,
        SUM(CASE WHEN status = 'Pending' OR status = 'Submitted' OR status = 'Under Review' THEN 1 ELSE 0 END) as pending_requests
    FROM citizen_requests 
    WHERE citizen_id = ?
");
$stmt->bind_param("i", $citizen['id']);
$stmt->execute();
$requestStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get member since (first request date or account creation)
$stmt = $db->prepare("
    SELECT MIN(created_at) as first_request 
    FROM citizen_requests 
    WHERE citizen_id = ?
");
$stmt->bind_param("i", $citizen['id']);
$stmt->execute();
$memberSince = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Add this after your existing queries in citizen_profile.php

// Get announcements for citizens
$announcements = [];

try {
    // Check if announcements table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'announcements'");
    if ($tableCheck->num_rows > 0) {
        // Get announcements - show all barangay-wide announcements + specific barangay if citizen has barangay
        $citizenBarangayId = $citizenData['barangay_id'] ?? null;

        $sql = "
            SELECT 
                a.*,
                u.full_name as created_by_name,
                b.name as barangay_name,
                CASE 
                    WHEN ar.id IS NOT NULL THEN 1 
                    ELSE 0 
                END as is_read
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN barangays b ON a.barangay_id = b.id
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.citizen_id = ?
            WHERE a.is_active = 1 
                AND a.published_at <= NOW() 
                AND (a.expires_at IS NULL OR a.expires_at > NOW())
                AND (
                    a.barangay_id IS NULL 
                    OR a.barangay_id = ?
                )
            ORDER BY 
                CASE a.priority
                    WHEN 'Urgent' THEN 1
                    WHEN 'High' THEN 2
                    WHEN 'Normal' THEN 3
                    WHEN 'Low' THEN 4
                END,
                a.published_at DESC
            LIMIT 10
        ";

        $stmt = $db->prepare($sql);
        $stmt->bind_param("ii", $citizen['id'], $citizenBarangayId);
        $stmt->execute();
        $announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get unread announcements count
        $unreadStmt = $db->prepare("
            SELECT COUNT(*) as unread_count
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.citizen_id = ?
            WHERE a.is_active = 1 
                AND a.published_at <= NOW() 
                AND (a.expires_at IS NULL OR a.expires_at > NOW())
                AND (
                    a.barangay_id IS NULL 
                    OR a.barangay_id = ?
                )
                AND ar.id IS NULL
        ");
        $unreadStmt->bind_param("ii", $citizen['id'], $citizenBarangayId);
        $unreadStmt->execute();
        $unreadResult = $unreadStmt->get_result()->fetch_assoc();
        $unreadAnnouncements = $unreadResult['unread_count'] ?? 0;
        $unreadStmt->close();
    }
} catch (Exception $e) {
    error_log("Announcements query error: " . $e->getMessage());
    $announcements = [];
    $unreadAnnouncements = 0;
}

// Function to mark announcement as read (call this when citizen views an announcement)
function markAnnouncementAsRead($db, $announcementId, $citizenId)
{
    $checkStmt = $db->prepare("
        SELECT id FROM announcement_reads 
        WHERE announcement_id = ? AND citizen_id = ?
    ");
    $checkStmt->bind_param("ii", $announcementId, $citizenId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if (!$exists) {
        $insertStmt = $db->prepare("
            INSERT INTO announcement_reads (announcement_id, citizen_id, ip_address)
            VALUES (?, ?, ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $insertStmt->bind_param("iis", $announcementId, $citizenId, $ip);
        $insertStmt->execute();
        $insertStmt->close();

        // Increment views count
        $updateStmt = $db->prepare("
            UPDATE announcements SET views_count = views_count + 1 WHERE id = ?
        ");
        $updateStmt->bind_param("i", $announcementId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

// Handle profile update
$errors = [];
$success = false;

// Validation functions (built-in, no external dependencies)
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone)
{
    // Philippine phone number format: 09XXXXXXXXX or +639XXXXXXXXX
    return empty($phone) || preg_match('/^(09|\+639)\d{9}$/', $phone);
}

function validatePassword($password)
{
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// File upload handling (built-in)
function handleAvatarUpload($file, $citizenId)
{
    $targetDir = "../public/uploads/avatars/"; // Fixed path to match storage location

    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error. Code: ' . $file['error']];
    }

    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size must be less than 2MB'];
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and GIF files are allowed'];
    }

    // Get file extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    // Generate unique filename
    $filename = 'avatar_' . $citizenId . '_' . time() . '.' . $extension;
    $targetFile = $targetDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        // Validate inputs
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $middleName = sanitize($_POST['middle_name'] ?? '');
        $suffix = sanitize($_POST['suffix'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $birthDate = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $address = sanitize($_POST['address'] ?? '');
        $barangay = sanitize($_POST['barangay'] ?? '');
        $civilStatus = $_POST['civil_status'] ?? '';
        $occupation = sanitize($_POST['occupation'] ?? '');

        // Validate required fields
        if (empty($firstName)) $errors['first_name'] = 'First name is required';
        if (empty($lastName)) $errors['last_name'] = 'Last name is required';
        if (empty($email)) $errors['email'] = 'Email is required';
        elseif (!validateEmail($email)) $errors['email'] = 'Invalid email format';
        if (!empty($phone) && !validatePhone($phone)) $errors['phone'] = 'Invalid phone number (format: 09XXXXXXXXX)';
        if (empty($birthDate)) $errors['birth_date'] = 'Birth date is required';
        else {
            // Check if at least 18 years old
            $birth = new DateTime($birthDate);
            $now = new DateTime();
            $age = $birth->diff($now)->y;
            if ($age < 18) $errors['birth_date'] = 'You must be at least 18 years old';
        }
        if (empty($gender)) $errors['gender'] = 'Gender is required';
        if (empty($address)) $errors['address'] = 'Address is required';
        if (empty($barangay)) $errors['barangay'] = 'Barangay is required';

        // Check if email already exists (if changed)
        if ($email !== $citizen['email']) {
            $checkStmt = $db->prepare("SELECT id FROM citizens WHERE email = ? AND id != ?");
            $checkStmt->bind_param("si", $email, $citizen['id']);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors['email'] = 'Email already registered';
            }
            $checkStmt->close();
        }

        if (empty($errors)) {
            // Update profile in database
            $updateStmt = $db->prepare("
                UPDATE citizens SET 
                    first_name = ?,
                    last_name = ?,
                    middle_name = ?,
                    suffix = ?,
                    email = ?,
                    phone = ?,
                    birth_date = ?,
                    gender = ?,
                    address = ?,
                    barangay = ?,
                    civil_status = ?,
                    occupation = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $updateStmt->bind_param(
                "ssssssssssssi",
                $firstName,
                $lastName,
                $middleName,
                $suffix,
                $email,
                $phone,
                $birthDate,
                $gender,
                $address,
                $barangay,
                $civilStatus,
                $occupation,
                $citizen['id']
            );

            if ($updateStmt->execute()) {
                $success = true;

                // Update session data
                $_SESSION['citizen']['first_name'] = $firstName;
                $_SESSION['citizen']['last_name'] = $lastName;
                $_SESSION['citizen']['email'] = $email;

                // Refresh citizen data
                $citizenData = $auth->getCitizen($citizen['id']);

                // Log activity (if activity_logs table exists)
                if (tableExists('activity_logs')) {
                    $logStmt = $db->prepare("
                        INSERT INTO activity_logs (citizen_id, action, description, ip_address)
                        VALUES (?, 'profile_update', 'Updated profile information', ?)
                    ");
                    $logStmt->bind_param("is", $citizen['id'], $_SERVER['REMOTE_ADDR']);
                    $logStmt->execute();
                    $logStmt->close();
                }
            } else {
                $errors['general'] = 'Failed to update profile. Please try again.';
            }
            $updateStmt->close();
        }
    } elseif ($action === 'change_password') {
        // Handle password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate current password
        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required';
        } else {
            // Verify current password (you need to implement this in Auth class)
            if (!$auth->verifyCitizenPassword($citizen['id'], $currentPassword)) {
                $errors['current_password'] = 'Current password is incorrect';
            }
        }

        // Validate new password
        if (empty($newPassword)) {
            $errors['new_password'] = 'New password is required';
        } elseif (!validatePassword($newPassword)) {
            $errors['new_password'] = 'Password must be at least 8 characters with uppercase, lowercase, and numbers';
        }

        // Confirm password
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        if (empty($errors)) {
            // Update password (you need to implement this in Auth class)
            if ($auth->updateCitizenPassword($citizen['id'], $newPassword)) {
                $success = true;
                $passwordChanged = true;

                // Log activity
                if (tableExists('activity_logs')) {
                    $logStmt = $db->prepare("
                        INSERT INTO activity_logs (citizen_id, action, description, ip_address)
                        VALUES (?, 'password_change', 'Changed account password', ?)
                    ");
                    $logStmt->bind_param("is", $citizen['id'], $_SERVER['REMOTE_ADDR']);
                    $logStmt->execute();
                    $logStmt->close();
                }
            } else {
                $errors['general'] = 'Failed to change password. Please try again.';
            }
        }
    } elseif ($action === 'upload_avatar') {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleAvatarUpload($_FILES['avatar'], $citizen['id']);

            if ($uploadResult['success']) {
                // Update avatar in database
                $avatarStmt = $db->prepare("
                    UPDATE citizens SET avatar = ? WHERE id = ?
                ");
                $avatarStmt->bind_param("si", $uploadResult['filename'], $citizen['id']);
                $avatarStmt->execute();
                $avatarStmt->close();

                $success = true;
                $avatarUpdated = true;

                // Refresh citizen data
                $citizenData = $auth->getCitizen($citizen['id']);
            } else {
                $errors['avatar'] = $uploadResult['error'];
            }
        } else {
            $errors['avatar'] = 'Please select an image to upload';
        }
    }
}

// Helper function to check if table exists
function tableExists($tableName)
{
    $db = getDB();
    $result = $db->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Get barangays list
$barangays = [
    'Arteche Poblacion',
    'Aguinaldo',
    'Balud',
    'Bato',
    'Buenavista',
    'Catabaan',
    'Cogon',
    'Cruz',
    'Gabay',
    'Inarihan',
    'Mabini',
    'Macion',
    'Malobago',
    'Mabuhay',
    'Salvacion',
    'San Agustin',
    'San Antonio',
    'San Isidro',
    'San Juan',
    'San Miguel',
    'San Pedro',
    'Tandang Sora'
];
sort($barangays);

// Get unread notifications count
$unreadNotifications = 0;
try {
    if (tableExists('notifications')) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as unread_count
            FROM notifications
            WHERE citizen_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $citizen['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $unreadData = $result->fetch_assoc();
        $unreadNotifications = $unreadData['unread_count'] ?? 0;
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Notifications query error: " . $e->getMessage());
    $unreadNotifications = 0;
}

// Format dates for display
$birthDateFormatted = isset($citizenData['birth_date']) ? date('F j, Y', strtotime($citizenData['birth_date'])) : '';
$birthDateValue = isset($citizenData['birth_date']) ? date('Y-m-d', strtotime($citizenData['birth_date'])) : '';
$memberSinceDate = $memberSince['first_request'] ?? $citizenData['created_at'] ?? date('Y-m-d H:i:s');
$memberSinceFormatted = date('F j, Y', strtotime($memberSinceDate));

// Calculate age if birth date exists
$age = '';
if (!empty($citizenData['birth_date'])) {
    $birth = new DateTime($citizenData['birth_date']);
    $now = new DateTime();
    $age = $birth->diff($now)->y;
}

// Determine if profile is complete
$profileFields = [
    'first_name' => $citizenData['first_name'] ?? '',
    'last_name' => $citizenData['last_name'] ?? '',
    'email' => $citizenData['email'] ?? '',
    'phone' => $citizenData['phone'] ?? '',
    'birth_date' => $citizenData['birth_date'] ?? '',
    'gender' => $citizenData['gender'] ?? '',
    'address' => $citizenData['address'] ?? '',
    'barangay' => $citizenData['barangay'] ?? ''
];

$completedFields = 0;
foreach ($profileFields as $field) {
    if (!empty($field)) $completedFields++;
}
$profileCompletion = round(($completedFields / count($profileFields)) * 100);

// Function to verify citizen password (add this to Auth class if not exists)
// If not in Auth class, you can implement it here temporarily

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Citizen Portal | Arteche LGU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #1e40af;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }

        body {
            background-color: #f3f4f6;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar.active {
                transform: translateX(0);
            }
        }

        .sidebar-brand {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #06b6d4;
        }

        .nav-link i {
            width: 24px;
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 20px;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            z-index: 999;
            padding: 0.75rem 1.5rem;
        }

        /* Profile Styles */
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .avatar-container {
            position: relative;
            display: inline-block;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .avatar-upload-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
            color: #333;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .profile-card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: var(--primary-color);
        }

        .profile-card-header i {
            margin-right: 0.5rem;
        }

        .profile-card-body {
            padding: 1.5rem;
        }

        .info-group {
            margin-bottom: 1.25rem;
        }

        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #dee2e6;
        }

        .info-value i {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        .completion-progress {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
        }

        .completion-progress .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 5px;
        }

        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .activity-item {
            position: relative;
            padding-bottom: 1.5rem;
            border-left: 2px solid #dee2e6;
            padding-left: 1.5rem;
        }

        .activity-item:last-child {
            border-left: 2px solid transparent;
        }

        .activity-icon {
            position: absolute;
            left: -0.9rem;
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .activity-content {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #333;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }

        .form-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3);
        }

        .btn-outline-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-outline-custom:hover {
            background: var(--primary-color);
            color: white;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 0.5rem;
            transition: all 0.3s;
            background-color: #e9ecef;
        }

        .password-strength.weak {
            background: var(--danger-color);
            width: 25%;
        }

        .password-strength.medium {
            background: var(--warning-color);
            width: 50%;
        }

        .password-strength.strong {
            background: var(--success-color);
            width: 100%;
        }

        .field-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper select {
            appearance: none;
            padding-right: 30px;
        }

        .select-wrapper::after {
            content: '▼';
            font-size: 0.8rem;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4 class="mb-0">
                <i class="bi bi-person-badge"></i> Citizen Portal
            </h4>
            <small class="text-white-50">Arteche, Eastern Samar</small>
        </div>

        <!-- User Profile -->
        <div class="px-3 py-4 text-center">
            <div class="mb-3">
                <?php if (!empty($citizenData['avatar']) && file_exists('../public/uploads/avatars/' . $citizenData['avatar'])): ?>
                    <img src="../public/uploads/avatars/<?= htmlspecialchars($citizenData['avatar']) ?>"
                        class="rounded-circle bg-white"
                        style="width: 80px; height: 80px; object-fit: cover;"
                        alt="Avatar">
                <?php else: ?>
                    <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center"
                        style="width: 80px; height: 80px;">
                        <i class="bi bi-person-fill text-primary" style="font-size: 2rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($citizen['first_name'] . ' ' . $citizen['last_name']) ?></h6>
            <small class="text-white-50"><?= htmlspecialchars($citizenData['email'] ?? $citizen['email']) ?></small>
            <div class="mt-2">
                <span class="badge bg-success">
                    <i class="bi bi-check-circle"></i> Verified
                </span>
                <?php if ($profileCompletion < 100): ?>
                    <span class="badge bg-warning text-dark ms-1">
                        <?= $profileCompletion ?>% Complete
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Menu -->
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="citizen_dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="citizen_profile.php">
                        <i class="bi bi-person"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="request_document.php">
                        <i class="bi bi-file-earmark-plus"></i> Request Document
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_request.php">
                        <i class="bi bi-files"></i> My Requests
                        <?php if (($requestStats['pending_requests'] ?? 0) > 0): ?>
                            <span class="badge bg-danger notification-badge"><?= $requestStats['pending_requests'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="citizen_notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge bg-warning notification-badge"><?= $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="available_documents.php">
                        <i class="bi bi-file-text"></i> Available Documents
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="citizen_logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <div class="position-absolute bottom-0 start-0 end-0 p-3 text-center">
            <small class="text-white-50">© <?= date('Y') ?> Arteche LGU</small>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="navbar top-navbar">
            <div class="container-fluid">
                <button class="btn btn-outline-primary d-md-none" type="button" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>

                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                            data-bs-toggle="dropdown">
                            <div class="me-2">
                                <strong><?= htmlspecialchars($citizen['first_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($citizenData['barangay'] ?? 'Arteche') ?></small>
                            </div>
                            <?php if (!empty($citizenData['avatar']) && file_exists('../public/uploads/avatars/' . $citizenData['avatar'])): ?>
                                <img src="../public/uploads/avatars/<?= htmlspecialchars($citizenData['avatar']) ?>"
                                    style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;"
                                    alt="Avatar">
                            <?php else: ?>
                                <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item active" href="citizen_profile.php">
                                    <i class="bi bi-person me-2"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="citizen_notifications.php">
                                    <i class="bi bi-bell me-2"></i> Notifications
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span class="badge bg-warning float-end"><?= $unreadNotifications ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="citizen_logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Profile Content -->
        <div class="container-fluid p-4">
            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php if (isset($passwordChanged)): ?>
                        Password changed successfully!
                    <?php elseif (isset($avatarUpdated)): ?>
                        Profile picture updated successfully!
                    <?php else: ?>
                        Profile updated successfully!
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($errors['general']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['avatar'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($errors['avatar']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-6 fw-bold mb-2">
                            <i class="bi bi-person-circle me-2"></i>My Profile
                        </h1>
                        <p class="lead mb-0">
                            Manage your personal information and account settings
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-light text-dark me-2">
                                <i class="bi bi-calendar3"></i> Member since <?= $memberSinceFormatted ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-file-text"></i> <?= $requestStats['total_requests'] ?? 0 ?> total requests
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <!-- Avatar with upload -->
                        <div class="avatar-container">
                            <?php if (!empty($citizenData['avatar']) && file_exists('../public/uploads/avatars/' . $citizenData['avatar'])): ?>
                                <img src="../public/uploads/avatars/<?= htmlspecialchars($citizenData['avatar']) ?>"
                                    class="profile-avatar" id="profileAvatar"
                                    alt="Profile Avatar">
                            <?php else: ?>
                                <div class="profile-avatar d-inline-flex align-items-center justify-content-center bg-white" id="profileAvatar">
                                    <i class="bi bi-person-fill text-primary" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Upload button -->
                            <form id="avatarForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_avatar">
                                <button type="button" class="avatar-upload-btn" onclick="document.getElementById('avatarInput').click();">
                                    <i class="bi bi-camera-fill"></i>
                                </button>
                                <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;" onchange="document.getElementById('avatarForm').submit();">
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Completion Progress -->
            <?php if ($profileCompletion < 100): ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-info-circle-fill fs-3"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Complete your profile!</strong> You're <?= $profileCompletion ?>% done.
                            Completing your profile helps process your requests faster.
                            <div class="progress completion-progress mt-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $profileCompletion ?>%;" aria-valuenow="<?= $profileCompletion ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Row -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div class="stat-value"><?= $requestStats['total_requests'] ?? 0 ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-check-circle text-success"></i>
                        </div>
                        <div class="stat-value"><?= $requestStats['completed_requests'] ?? 0 ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-hourglass-split text-warning"></i>
                        </div>
                        <div class="stat-value"><?= $requestStats['pending_requests'] ?? 0 ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-star text-warning"></i>
                        </div>
                        <div class="stat-value"><?= $age ?: '--' ?></div>
                        <div class="stat-label">Age</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row">
                <!-- Left Column - Profile Information -->
                <div class="col-lg-8">
                    <!-- Personal Information Form -->
                    <form method="POST" action="" class="profile-card" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="profile-card-header">
                            <i class="bi bi-pencil-square"></i> Personal Information
                        </div>

                        <div class="profile-card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                                        name="first_name"
                                        value="<?= htmlspecialchars($citizenData['first_name'] ?? '') ?>"
                                        required>
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?= $errors['first_name'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text"
                                        class="form-control"
                                        name="middle_name"
                                        value="<?= htmlspecialchars($citizenData['middle_name'] ?? '') ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                                        name="last_name"
                                        value="<?= htmlspecialchars($citizenData['last_name'] ?? '') ?>"
                                        required>
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?= $errors['last_name'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Suffix (Jr., Sr., III, etc.)</label>
                                    <input type="text"
                                        class="form-control"
                                        name="suffix"
                                        value="<?= htmlspecialchars($citizenData['suffix'] ?? '') ?>"
                                        placeholder="e.g., Jr., Sr., III">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                                    <div class="select-wrapper">
                                        <select class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>"
                                            name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= ($citizenData['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($citizenData['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($citizenData['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                            <option value="Prefer not to say" <?= ($citizenData['gender'] ?? '') == 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                        </select>
                                    </div>
                                    <?php if (isset($errors['gender'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['gender'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Birth Date <span class="text-danger">*</span></label>
                                    <input type="date"
                                        class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>"
                                        name="birth_date"
                                        value="<?= htmlspecialchars($birthDateValue) ?>"
                                        max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                        required>
                                    <?php if (isset($errors['birth_date'])): ?>
                                        <div class="invalid-feedback"><?= $errors['birth_date'] ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">You must be at least 18 years old</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Civil Status</label>
                                    <div class="select-wrapper">
                                        <select class="form-select" name="civil_status">
                                            <option value="">Select Civil Status</option>
                                            <option value="Single" <?= ($citizenData['civil_status'] ?? '') == 'Single' ? 'selected' : '' ?>>Single</option>
                                            <option value="Married" <?= ($citizenData['civil_status'] ?? '') == 'Married' ? 'selected' : '' ?>>Married</option>
                                            <option value="Divorced" <?= ($citizenData['civil_status'] ?? '') == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                                            <option value="Separated" <?= ($citizenData['civil_status'] ?? '') == 'Separated' ? 'selected' : '' ?>>Separated</option>
                                            <option value="Widowed" <?= ($citizenData['civil_status'] ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email"
                                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                        name="email"
                                        value="<?= htmlspecialchars($citizenData['email'] ?? '') ?>"
                                        required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?= $errors['email'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel"
                                        class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                        name="phone"
                                        value="<?= htmlspecialchars($citizenData['phone'] ?? '') ?>"
                                        placeholder="09XXXXXXXXX">
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?= $errors['phone'] ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Format: 09XXXXXXXXX</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">House/Street Address <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                        name="address"
                                        value="<?= htmlspecialchars($citizenData['address'] ?? '') ?>"
                                        placeholder="House No., Street, Purok"
                                        required>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback"><?= $errors['address'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <div class="select-wrapper">
                                        <select class="form-select <?= isset($errors['barangay']) ? 'is-invalid' : '' ?>"
                                            name="barangay" required>
                                            <option value="">Select Barangay</option>
                                            <?php foreach ($barangays as $brgy): ?>
                                                <option value="<?= htmlspecialchars($brgy) ?>"
                                                    <?= ($citizenData['barangay'] ?? '') == $brgy ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($brgy) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if (isset($errors['barangay'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['barangay'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Occupation</label>
                                    <input type="text"
                                        class="form-control"
                                        name="occupation"
                                        value="<?= htmlspecialchars($citizenData['occupation'] ?? '') ?>"
                                        placeholder="e.g., Teacher, Government Employee, Self-employed">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-save me-2"></i>Update Profile
                                </button>
                                <button type="button" class="btn btn-outline-custom ms-2" onclick="resetForm()">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Change Password Form -->
                    <form method="POST" action="" class="profile-card mt-4" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">

                        <div class="profile-card-header">
                            <i class="bi bi-shield-lock"></i> Change Password
                        </div>

                        <div class="profile-card-body">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <div class="position-relative">
                                    <input type="password"
                                        class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                                        name="current_password"
                                        id="current_password"
                                        required>
                                    <i class="bi bi-eye field-icon" onclick="togglePassword('current_password', this)"></i>
                                </div>
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback d-block"><?= $errors['current_password'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <div class="position-relative">
                                    <input type="password"
                                        class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                        name="new_password"
                                        id="new_password"
                                        onkeyup="checkPasswordStrength()"
                                        required>
                                    <i class="bi bi-eye field-icon" onclick="togglePassword('new_password', this)"></i>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <small class="text-muted">
                                    Password must be at least 8 characters with uppercase, lowercase, and numbers
                                </small>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback d-block"><?= $errors['new_password'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <div class="position-relative">
                                    <input type="password"
                                        class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                        name="confirm_password"
                                        id="confirm_password"
                                        onkeyup="validatePasswordMatch()"
                                        required>
                                    <i class="bi bi-eye field-icon" onclick="togglePassword('confirm_password', this)"></i>
                                </div>
                                <div id="passwordMatchMessage" class="small mt-1"></div>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback d-block"><?= $errors['confirm_password'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right Column - Information Summary & Activity -->
                <div class="col-lg-4">
                    <!-- Profile Summary Card (keep existing) -->

                    <!-- Announcements Card (replaces Recent Activity) -->
                    <div class="profile-card mt-4">
                        <div class="profile-card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-megaphone"></i> Barangay Announcements
                            </div>
                            <?php if ($unreadAnnouncements > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $unreadAnnouncements ?> New</span>
                            <?php endif; ?>
                        </div>

                        <div class="profile-card-body p-0">
                            <?php if (empty($announcements)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-megaphone" style="font-size: 3rem; color: #6c757d;"></i>
                                    <p class="text-muted mt-3">No announcements at this time</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($announcements as $announcement):
                                        $priorityClass = [
                                            'Urgent' => 'danger',
                                            'High' => 'warning',
                                            'Normal' => 'info',
                                            'Low' => 'secondary'
                                        ][$announcement['priority']] ?? 'info';

                                        $categoryIcon = [
                                            'General' => 'bi-info-circle',
                                            'Emergency' => 'bi-exclamation-triangle',
                                            'Event' => 'bi-calendar-event',
                                            'Advisory' => 'bi-shield-exclamation',
                                            'Document' => 'bi-file-text',
                                            'Barangay' => 'bi-building'
                                        ][$announcement['category']] ?? 'bi-megaphone';
                                    ?>
                                        <div class="list-group-item list-group-item-action <?= !$announcement['is_read'] ? 'fw-bold' : '' ?>"
                                            style="cursor: pointer;"
                                            onclick="showAnnouncement(<?= $announcement['id'] ?>, <?= $citizen['id'] ?>)"
                                            data-bs-toggle="modal"
                                            data-bs-target="#announcementModal<?= $announcement['id'] ?>">

                                            <div class="d-flex w-100 justify-content-between align-items-start">
                                                <div class="flex-grow-1 me-2">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <i class="bi <?= $categoryIcon ?> me-2 text-<?= $priorityClass ?>"></i>
                                                        <span class="badge bg-<?= $priorityClass ?> me-2"><?= $announcement['priority'] ?></span>
                                                        <?php if (!$announcement['is_read']): ?>
                                                            <span class="badge bg-primary">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <h6 class="mb-1 <?= !$announcement['is_read'] ? 'fw-bold' : '' ?>">
                                                        <?= htmlspecialchars($announcement['title']) ?>
                                                    </h6>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?= date('M d, Y', strtotime($announcement['published_at'])) ?>
                                                        <?php if ($announcement['barangay_name']): ?>
                                                            <span class="ms-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($announcement['barangay_name']) ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php if ($announcement['image_path']): ?>
                                                    <i class="bi bi-image text-muted"></i>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Preview of content -->
                                            <p class="mb-0 small text-muted mt-2">
                                                <?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...
                                            </p>
                                        </div>

                                        <!-- Modal for each announcement -->
                                        <div class="modal fade" id="announcementModal<?= $announcement['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-<?= $priorityClass ?> text-white">
                                                        <h5 class="modal-title">
                                                            <i class="bi <?= $categoryIcon ?> me-2"></i>
                                                            <?= htmlspecialchars($announcement['title']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block">
                                                                    <i class="bi bi-calendar"></i> Published: <?= date('F d, Y h:i A', strtotime($announcement['published_at'])) ?>
                                                                </small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block">
                                                                    <i class="bi bi-tag"></i> Category: <?= $announcement['category'] ?>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <?php if ($announcement['image_path']): ?>
                                                            <img src="uploads/announcements/<?= htmlspecialchars($announcement['image_path']) ?>"
                                                                class="img-fluid mb-3 rounded" alt="Announcement image">
                                                        <?php endif; ?>

                                                        <div class="announcement-content">
                                                            <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                                                        </div>

                                                        <?php if ($announcement['attachment_path']): ?>
                                                            <hr>
                                                            <a href="uploads/announcements/<?= htmlspecialchars($announcement['attachment_path']) ?>"
                                                                class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="bi bi-paperclip"></i> Download Attachment
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <small class="text-muted">
                                                            Posted by: <?= htmlspecialchars($announcement['created_by_name'] ?? 'Barangay Admin') ?>
                                                            <?php if ($announcement['barangay_name']): ?>
                                                                | Barangay: <?= htmlspecialchars($announcement['barangay_name']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($announcements)): ?>
                            <div class="card-footer bg-transparent text-center">
                                <small class="text-muted">
                                    <i class="bi bi-eye"></i> Total Views: <?= array_sum(array_column($announcements, 'views_count')) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Links Card (keep existing) -->
                    <!-- Quick Links Card -->
                    <div class="profile-card mt-4">
                        <div class="profile-card-header">
                            <i class="bi bi-link"></i> Quick Links
                        </div>
                        <div class="profile-card-body">
                            <div class="list-group">
                                <a href="request_document.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-file-earmark-plus text-primary me-2"></i>
                                    Request New Document
                                </a>
                                <a href="my_request.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-files text-success me-2"></i>
                                    View My Requests
                                </a>
                                <a href="citizen_notifications.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-bell text-warning me-2"></i>
                                    View Notifications
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span class="badge bg-warning float-end"><?= $unreadNotifications ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="available_documents.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-file-text text-info me-2"></i>
                                    Available Documents
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');

            const mainContent = document.querySelector('.main-content');
            if (sidebar.classList.contains('active')) {
                mainContent.style.marginLeft = '0';
            } else {
                mainContent.style.marginLeft = '250px';
            }
        }

        // Auto-close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.navbar button');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                event.target !== toggleBtn &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').style.marginLeft = '0';
            }
        });

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('passwordStrength');

            // Reset classes
            strengthBar.className = 'password-strength';

            if (password.length === 0) {
                strengthBar.style.width = '0';
                return;
            }

            let strength = 0;

            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;

            // Character type checks
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;

            // Determine strength
            if (strength <= 3) {
                strengthBar.classList.add('weak');
            } else if (strength <= 5) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const messageEl = document.getElementById('passwordMatchMessage');

            if (confirm.length === 0) {
                messageEl.innerHTML = '';
                return;
            }

            if (password === confirm) {
                messageEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Passwords match</span>';
                document.getElementById('confirm_password').classList.remove('is-invalid');
            } else {
                messageEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Passwords do not match</span>';
                document.getElementById('confirm_password').classList.add('is-invalid');
            }
        }

        // Toggle password visibility
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Form reset function
        function resetForm() {
            if (confirm('Reset all changes to last saved values?')) {
                window.location.reload();
            }
        }

        // Avatar upload preview
        document.getElementById('avatarInput')?.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                // Show loading indicator
                const avatarContainer = document.querySelector('.avatar-container');
                const originalContent = avatarContainer.innerHTML;
                avatarContainer.innerHTML += '<div class="spinner-border text-light position-absolute top-50 start-50" role="status"></div>';

                // Form will auto-submit, so no need for preview
            }
        });

        // Confirm before leaving with unsaved changes
        let formChanged = false;

        document.querySelectorAll('#profileForm input, #profileForm select').forEach(element => {
            element.addEventListener('change', () => {
                formChanged = true;
            });

            element.addEventListener('input', () => {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        document.getElementById('profileForm').addEventListener('submit', function() {
            formChanged = false;
        });

        // Phone number formatting
        document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substr(0, 11);
            this.value = value;
        });

        // Auto-refresh dashboard every 60 seconds for real-time updates (optional)
        setTimeout(() => {
            // Only refresh if not on a form with unsaved changes
            if (!formChanged) {
                window.location.reload();
            }
        }, 60000);
    </script>
</body>

</html>