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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/citizen_profile.css">
</head>

<body>
    <header class="cis-topbar">
        <div class="cis-shell cis-topbar-inner">
            <div>
                <div class="cis-brand">
                    <i class="bi bi-person-circle"></i> My Profile
                </div>
                <small class="cis-subtitle">Manage your citizen account</small>
            </div>

            <a href="citizen_notifications.php" class="cis-icon-btn" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="cis-dot"><?= (int)$unreadNotifications ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="cis-shell">
        <?php if ($success): ?>
            <div class="cis-alert cis-alert-success">
                <i class="bi bi-check-circle"></i>
                <?php if (isset($passwordChanged)): ?>
                    Password changed successfully.
                <?php elseif (isset($avatarUpdated)): ?>
                    Profile picture updated successfully.
                <?php else: ?>
                    Profile updated successfully.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="cis-alert cis-alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['avatar'])): ?>
            <div class="cis-alert cis-alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?= htmlspecialchars($errors['avatar']) ?>
            </div>
        <?php endif; ?>

        <section class="cis-profile-hero">
            <div class="cis-avatar-wrap">
                <?php if (!empty($citizenData['avatar']) && file_exists('../public/uploads/avatars/' . $citizenData['avatar'])): ?>
                    <img src="../public/uploads/avatars/<?= htmlspecialchars($citizenData['avatar']) ?>" class="cis-avatar" alt="Profile photo">
                <?php else: ?>
                    <div class="cis-avatar cis-avatar-placeholder">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>

                <form id="avatarForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <button type="button" class="cis-avatar-btn" onclick="document.getElementById('avatarInput').click();">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                    <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/jpg,image/png,image/gif" hidden onchange="document.getElementById('avatarForm').submit();">
                </form>
            </div>

            <div class="cis-profile-hero-content">
                <span class="cis-eyebrow">Citizen Account</span>
                <h1><?= htmlspecialchars(($citizenData['first_name'] ?? '') . ' ' . ($citizenData['last_name'] ?? '')) ?></h1>
                <p><?= htmlspecialchars($citizenData['email'] ?? $citizen['email']) ?></p>

                <div class="cis-hero-pills">
                    <span><i class="bi bi-check-circle"></i> Verified</span>
                    <span><i class="bi bi-calendar3"></i> Member since <?= htmlspecialchars($memberSinceFormatted) ?></span>
                    <span><i class="bi bi-person-check"></i> <?= (int)$profileCompletion ?>% Complete</span>
                </div>
            </div>
        </section>

        <?php if ($profileCompletion < 100): ?>
            <section class="cis-card cis-card-pad cis-progress-card">
                <div class="cis-progress-head">
                    <div>
                        <h2 class="cis-section-title">
                            <i class="bi bi-info-circle"></i> Complete your profile
                        </h2>
                        <p>Completing your profile helps process document requests faster.</p>
                    </div>
                    <strong><?= (int)$profileCompletion ?>%</strong>
                </div>

                <div class="cis-progress">
                    <span style="width: <?= (int)$profileCompletion ?>%;"></span>
                </div>
            </section>
        <?php endif; ?>

        <section class="cis-stat-grid">
            <div class="cis-stat-card">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Total Requests</small>
                        <h2><?= (int)($requestStats['total_requests'] ?? 0) ?></h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-file-text"></i></div>
                </div>
            </div>

            <div class="cis-stat-card">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Completed</small>
                        <h2><?= (int)($requestStats['completed_requests'] ?? 0) ?></h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>

            <div class="cis-stat-card">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Pending</small>
                        <h2><?= (int)($requestStats['pending_requests'] ?? 0) ?></h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>

            <div class="cis-stat-card">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Age</small>
                        <h2><?= $age ? (int)$age : '--' ?></h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-person-badge"></i></div>
                </div>
            </div>
        </section>

        <section class="cis-profile-grid">
            <div class="cis-main-column">
                <form method="POST" action="" class="cis-card cis-card-pad" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="cis-section-head">
                        <h2 class="cis-section-title">
                            <i class="bi bi-pencil-square"></i> Personal Information
                        </h2>
                    </div>

                    <div class="cis-form-grid">
                        <div class="cis-field">
                            <label>First Name <span>*</span></label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($citizenData['first_name'] ?? '') ?>" required>
                            <?php if (isset($errors['first_name'])): ?><small class="cis-error"><?= $errors['first_name'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" value="<?= htmlspecialchars($citizenData['middle_name'] ?? '') ?>">
                        </div>

                        <div class="cis-field">
                            <label>Last Name <span>*</span></label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($citizenData['last_name'] ?? '') ?>" required>
                            <?php if (isset($errors['last_name'])): ?><small class="cis-error"><?= $errors['last_name'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Suffix</label>
                            <input type="text" name="suffix" value="<?= htmlspecialchars($citizenData['suffix'] ?? '') ?>" placeholder="Jr., Sr., III">
                        </div>

                        <div class="cis-field">
                            <label>Gender <span>*</span></label>
                            <select name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?= ($citizenData['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($citizenData['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($citizenData['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                <option value="Prefer not to say" <?= ($citizenData['gender'] ?? '') == 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                            </select>
                            <?php if (isset($errors['gender'])): ?><small class="cis-error"><?= $errors['gender'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Birth Date <span>*</span></label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($birthDateValue) ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                            <?php if (isset($errors['birth_date'])): ?><small class="cis-error"><?= $errors['birth_date'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Civil Status</label>
                            <select name="civil_status">
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?= ($citizenData['civil_status'] ?? '') == 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($citizenData['civil_status'] ?? '') == 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Separated" <?= ($citizenData['civil_status'] ?? '') == 'Separated' ? 'selected' : '' ?>>Separated</option>
                                <option value="Widowed" <?= ($citizenData['civil_status'] ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                            </select>
                        </div>

                        <div class="cis-field">
                            <label>Email Address <span>*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($citizenData['email'] ?? '') ?>" required>
                            <?php if (isset($errors['email'])): ?><small class="cis-error"><?= $errors['email'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($citizenData['phone'] ?? '') ?>" placeholder="09XXXXXXXXX">
                            <?php if (isset($errors['phone'])): ?><small class="cis-error"><?= $errors['phone'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field cis-field-wide">
                            <label>House/Street Address <span>*</span></label>
                            <input type="text" name="address" value="<?= htmlspecialchars($citizenData['address'] ?? '') ?>" placeholder="House No., Street, Purok" required>
                            <?php if (isset($errors['address'])): ?><small class="cis-error"><?= $errors['address'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Barangay <span>*</span></label>
                            <select name="barangay" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $brgy): ?>
                                    <option value="<?= htmlspecialchars($brgy) ?>" <?= ($citizenData['barangay'] ?? '') == $brgy ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brgy) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['barangay'])): ?><small class="cis-error"><?= $errors['barangay'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= htmlspecialchars($citizenData['occupation'] ?? '') ?>" placeholder="e.g., Teacher, Self-employed">
                        </div>
                    </div>

                    <div class="cis-actions">
                        <button type="submit" class="cis-btn cis-btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>

                        <button type="button" class="cis-btn cis-btn-light" onclick="resetForm()">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                </form>

                <form method="POST" action="" class="cis-card cis-card-pad" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">

                    <div class="cis-section-head">
                        <h2 class="cis-section-title">
                            <i class="bi bi-shield-lock"></i> Change Password
                        </h2>
                    </div>

                    <div class="cis-form-grid cis-form-grid-password">
                        <div class="cis-field cis-password-field">
                            <label>Current Password</label>
                            <input type="password" name="current_password" id="current_password" required>
                            <button type="button" onclick="togglePassword('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (isset($errors['current_password'])): ?><small class="cis-error"><?= $errors['current_password'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field cis-password-field">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="new_password" onkeyup="checkPasswordStrength()" required>
                            <button type="button" onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="cis-password-strength" id="passwordStrength"></div>
                            <?php if (isset($errors['new_password'])): ?><small class="cis-error"><?= $errors['new_password'] ?></small><?php endif; ?>
                        </div>

                        <div class="cis-field cis-password-field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" onkeyup="validatePasswordMatch()" required>
                            <button type="button" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div id="passwordMatchMessage" class="cis-help"></div>
                            <?php if (isset($errors['confirm_password'])): ?><small class="cis-error"><?= $errors['confirm_password'] ?></small><?php endif; ?>
                        </div>
                    </div>

                    <div class="cis-actions">
                        <button type="submit" class="cis-btn cis-btn-primary">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <aside class="cis-side-column">
                <div class="cis-card cis-card-pad">
                    <h2 class="cis-section-title">
                        <i class="bi bi-person-lines-fill"></i> Profile Summary
                    </h2>

                    <div class="cis-info-list">
                        <div>
                            <span>Barangay</span>
                            <strong><?= htmlspecialchars($citizenData['barangay'] ?? 'Not set') ?></strong>
                        </div>
                        <div>
                            <span>Birth Date</span>
                            <strong><?= htmlspecialchars($birthDateFormatted ?: 'Not set') ?></strong>
                        </div>
                        <div>
                            <span>Phone</span>
                            <strong><?= htmlspecialchars($citizenData['phone'] ?? 'Not set') ?></strong>
                        </div>
                        <div>
                            <span>Address</span>
                            <strong><?= htmlspecialchars($citizenData['address'] ?? 'Not set') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="cis-card cis-card-pad">
                    <div class="cis-section-head">
                        <h2 class="cis-section-title">
                            <i class="bi bi-megaphone"></i> Announcements
                        </h2>

                        <?php if ($unreadAnnouncements > 0): ?>
                            <span class="cis-mini-badge"><?= (int)$unreadAnnouncements ?> New</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="cis-empty">
                            <i class="bi bi-megaphone"></i>
                            <p>No announcements at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="cis-announcement-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <article class="cis-announcement <?= !$announcement['is_read'] ? 'unread' : '' ?>">
                                    <div>
                                        <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                        <p><?= htmlspecialchars(mb_strimwidth($announcement['content'], 0, 95, '...')) ?></p>
                                        <small>
                                            <i class="bi bi-calendar3"></i>
                                            <?= date('M d, Y', strtotime($announcement['published_at'])) ?>
                                        </small>
                                    </div>

                                    <?php if (!$announcement['is_read']): ?>
                                        <span class="cis-mini-badge">New</span>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cis-card cis-card-pad">
                    <h2 class="cis-section-title">
                        <i class="bi bi-link-45deg"></i> Quick Links
                    </h2>

                    <div class="cis-link-list">
                        <a href="request_document.php">
                            <i class="bi bi-file-earmark-plus"></i> Request New Document
                        </a>
                        <a href="my_request.php">
                            <i class="bi bi-files"></i> View My Requests
                        </a>
                        <a href="citizen_notifications.php">
                            <i class="bi bi-bell"></i> View Notifications
                        </a>
                        <a href="available_documents.php">
                            <i class="bi bi-file-text"></i> Available Documents
                        </a>
                        <!--logout-->
                        <a href="citizen_logout.php" class="cis-link-logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </aside>
        </section>
    </main>

    <nav class="cis-bottom-nav">
        <a href="citizen_dashboard.php">
            <i class="bi bi-house"></i> Home
        </a>
        <a href="my_request.php">
            <i class="bi bi-files"></i> Requests
        </a>
        <a href="request_document.php">
            <i class="bi bi-plus-circle-fill"></i> New
        </a>
        <a href="citizen_notifications.php">
            <i class="bi bi-bell"></i> Alerts
        </a>
        <a href="citizen_profile.php" class="active">
            <i class="bi bi-person"></i> Profile
        </a>
    </nav>

    <script>
        function resetForm() {
            if (confirm('Reset all changes to last saved values?')) {
                window.location.reload();
            }
        }

        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');

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

        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('passwordStrength');

            strengthBar.className = 'cis-password-strength';

            if (!password.length) return;

            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength <= 3) {
                strengthBar.classList.add('weak');
            } else if (strength <= 5) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        function validatePasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const messageEl = document.getElementById('passwordMatchMessage');

            if (!confirm.length) {
                messageEl.innerHTML = '';
                return;
            }

            if (password === confirm) {
                messageEl.innerHTML = '<span class="cis-good"><i class="bi bi-check-circle"></i> Passwords match</span>';
            } else {
                messageEl.innerHTML = '<span class="cis-bad"><i class="bi bi-exclamation-circle"></i> Passwords do not match</span>';
            }
        }

        document.querySelector('input[name="phone"]')?.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            this.value = value;
        });
    </script>
</body>

</html>