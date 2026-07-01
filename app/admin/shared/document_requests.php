<?php
require_once __DIR__ . '/../../shared/bootstrap.php';

// Database configuration
require_once __DIR__ . '/../../shared/config/database.php';
$conn = getDB();

// Authentication check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

// Get session variables
$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';
$user_id = $_SESSION['user_id'] ?? 0;

// ============================================
// Handle POST Actions (Process, Approve, Reject, etc.)
// ============================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason'] ?? '');

    if ($request_id > 0) {
        // Verify permission for barangay admin
        if (!$is_super_admin) {
            $check_stmt = $conn->prepare("
                SELECT cr.id 
                FROM citizen_requests cr
                JOIN citizens c ON cr.citizen_id = c.id
                WHERE cr.id = ? AND c.barangay_id = ?
            ");
            $check_stmt->bind_param("ii", $request_id, $admin_barangay_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                $message = "You don't have permission to access this request.";
                $message_type = 'danger';
            }
            $check_stmt->close();
        }

        if (empty($message)) {
            switch ($action) {
                case 'process':
                    // Move from Submitted to Under Review
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET status = 'Under Review', 
                            reviewed_by = ?,
                            reviewed_at = NOW(),
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Processing started by " . $username . ($notes ? " - " . $notes : "");
                    $stmt->bind_param("isi", $user_id, $notes_text, $request_id);
                    break;

                case 'approve':
                    // Approve request
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET status = 'Approved', 
                            reviewed_by = ?,
                            reviewed_at = NOW(),
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Approved by " . $username . ($notes ? " - " . $notes : "");
                    $stmt->bind_param("isi", $user_id, $notes_text, $request_id);
                    break;

                case 'reject':
                    // Reject request with reason
                    if (empty($rejection_reason)) {
                        $message = "Rejection reason is required.";
                        $message_type = 'danger';
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE citizen_requests 
                            SET status = 'Rejected', 
                                reviewed_by = ?,
                                reviewed_at = NOW(),
                                rejection_reason = ?,
                                notes = CONCAT(IFNULL(notes, ''), ?)
                            WHERE id = ?
                        ");
                        $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Rejected by " . $username;
                        $stmt->bind_param("issi", $user_id, $rejection_reason, $notes_text, $request_id);
                    }
                    break;

                case 'ready':
                    // Mark as Ready for Pickup
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET status = 'Ready for Pickup', 
                            released_at = NOW(),
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Document ready for pickup by " . $username . ($notes ? " - " . $notes : "");
                    $stmt->bind_param("si", $notes_text, $request_id);
                    break;

                case 'complete':
                    // Mark as Completed (claimed)
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET status = 'Completed', 
                            completed_at = NOW(),
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Document claimed by citizen" . ($notes ? " - " . $notes : "");
                    $stmt->bind_param("si", $notes_text, $request_id);
                    break;

                case 'payment_paid':
                    // Update payment status to Paid
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET payment_status = 'Paid',
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Payment marked as Paid by " . $username;
                    $stmt->bind_param("si", $notes_text, $request_id);
                    break;

                case 'payment_waived':
                    // Update payment status to Waived
                    $stmt = $conn->prepare("
                        UPDATE citizen_requests 
                        SET payment_status = 'Waived',
                            notes = CONCAT(IFNULL(notes, ''), ?)
                        WHERE id = ?
                    ");
                    $notes_text = "\n[" . date('Y-m-d H:i:s') . "] Payment waived by " . $username;
                    $stmt->bind_param("si", $notes_text, $request_id);
                    break;

                default:
                    $message = "Invalid action.";
                    $message_type = 'danger';
            }

            if (isset($stmt) && empty($message)) {
                if ($stmt->execute()) {
                    $message = "Request updated successfully.";
                    $message_type = 'success';

                    // Log activity
                    $log_stmt = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $log_action = ucfirst($action) . " Request";
                    $log_details = $action . " request ID: " . $request_id . ($rejection_reason ? " - Reason: " . $rejection_reason : "");
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $log_stmt->bind_param("isss", $user_id, $log_action, $log_details, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                } else {
                    $message = "Error updating request: " . $conn->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}

// ============================================
// Get Filter Parameters
// ============================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$barangay_filter = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;
$document_filter = isset($_GET['document_type']) ? intval($_GET['document_type']) : null;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// For barangay admin, force their barangay
if (!$is_super_admin) {
    $barangay_filter = $admin_barangay_id;
}

// ============================================
// Build the Main Query
// ============================================
$query = "
    SELECT 
        cr.id,
        cr.request_number,
        cr.status,
        cr.payment_status,
        cr.fee,
        cr.submitted_at,
        cr.reviewed_at,
        cr.released_at,
        cr.completed_at,
        cr.rejection_reason,
        cr.notes,
        cr.purpose,
        CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
        c.email,
        c.phone,
        c.address,
        c.barangay_id,
        b.name as barangay_name,
        dt.id as document_type_id,
        dt.name as document_name,
        dt.processing_days,
        reviewer.full_name as reviewed_by_name,
        DATEDIFF(NOW(), cr.submitted_at) as days_pending,
        CASE 
            WHEN cr.status = 'Submitted' THEN 1
            WHEN cr.status = 'Under Review' THEN 2
            WHEN cr.status = 'Approved' THEN 3
            WHEN cr.status = 'Ready for Pickup' THEN 4
            WHEN cr.status = 'Completed' THEN 5
            WHEN cr.status = 'Rejected' THEN 6
            WHEN cr.status = 'Cancelled' THEN 7
            ELSE 8
        END as status_order
    FROM citizen_requests cr
    JOIN citizens c ON cr.citizen_id = c.id
    JOIN document_types dt ON cr.document_type_id = dt.id
    LEFT JOIN barangays b ON c.barangay_id = b.id
    LEFT JOIN users reviewer ON cr.reviewed_by = reviewer.id
    WHERE 1=1
";

$params = [];
$types = "";

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND cr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($barangay_filter) {
    $query .= " AND c.barangay_id = ?";
    $params[] = $barangay_filter;
    $types .= "i";
}

if ($document_filter) {
    $query .= " AND cr.document_type_id = ?";
    $params[] = $document_filter;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (cr.request_number LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($date_from)) {
    $query .= " AND DATE(cr.submitted_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(cr.submitted_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Order by status priority then most recent
$query .= " ORDER BY status_order ASC, cr.submitted_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================
// Get Summary Statistics
// ============================================
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) as payment_pending,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as payment_paid,
        SUM(CASE WHEN payment_status = 'Waived' THEN 1 ELSE 0 END) as payment_waived,
        SUM(fee) as total_fees,
        SUM(CASE WHEN payment_status = 'Paid' THEN fee ELSE 0 END) as collected_fees
    FROM citizen_requests cr
    JOIN citizens c ON cr.citizen_id = c.id
    WHERE 1=1
";

$stats_params = [];
$stats_types = "";

if (!$is_super_admin && $admin_barangay_id) {
    $stats_query .= " AND c.barangay_id = ?";
    $stats_params[] = $admin_barangay_id;
    $stats_types .= "i";
}

$stats_stmt = $conn->prepare($stats_query);
if (!empty($stats_params)) {
    $stats_stmt->bind_param($stats_types, ...$stats_params);
}
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// ============================================
// Get Barangays for Filter (Super Admin Only)
// ============================================
if ($is_super_admin) {
    $barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

// Get Document Types for Filter
$document_types = $conn->query("SELECT id, name, fee FROM document_types WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get current barangay name for display
$current_barangay_name = '';
if ($barangay_filter) {
    $barangay_stmt = $conn->prepare("SELECT name FROM barangays WHERE id = ?");
    $barangay_stmt->bind_param("i", $barangay_filter);
    $barangay_stmt->execute();
    $barangay_result = $barangay_stmt->get_result();
    if ($barangay_row = $barangay_result->fetch_assoc()) {
        $current_barangay_name = $barangay_row['name'];
    }
    $barangay_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Document Requests - Arteche CI System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0f2a44;
            --secondary: #1f6aa5;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #0dcaf0;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border-left: 5px solid;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stats-card p {
            margin-bottom: 0;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .stats-card small {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .stats-card .stats-icon {
            position: absolute;
            right: 10px;
            bottom: 10px;
            font-size: 3rem;
            opacity: 0.1;
        }

        .card-submitted {
            border-left-color: #0d6efd;
        }

        .card-submitted h3 {
            color: #0d6efd;
        }

        .card-review {
            border-left-color: #ffc107;
        }

        .card-review h3 {
            color: #ffc107;
        }

        .card-approved {
            border-left-color: #198754;
        }

        .card-approved h3 {
            color: #198754;
        }

        .card-ready {
            border-left-color: #0dcaf0;
        }

        .card-ready h3 {
            color: #0dcaf0;
        }

        .card-rejected {
            border-left-color: #dc3545;
        }

        .card-rejected h3 {
            color: #dc3545;
        }

        .card-completed {
            border-left-color: #6c757d;
        }

        .card-completed h3 {
            color: #6c757d;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            min-width: 110px;
        }

        .status-Draft {
            background: #e2e3e5;
            color: #41464b;
        }

        .status-Submitted {
            background: #cfe2ff;
            color: #084298;
        }

        .status-Under\ Review {
            background: #fff3cd;
            color: #856404;
        }

        .status-Approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-Ready\ for\ Pickup {
            background: #cff4fc;
            color: #087990;
        }

        .status-Rejected {
            background: #f8d7da;
            color: #842029;
        }

        .status-Completed {
            background: #e2e3e5;
            color: #41464b;
        }

        .status-Cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .payment-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .payment-Pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-Paid {
            background: #d1e7dd;
            color: #0f5132;
        }

        .payment-Waived {
            background: #cff4fc;
            color: #087990;
        }

        .action-buttons .btn {
            margin: 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .request-detail-modal .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .timeline {
            position: relative;
            padding: 20px 0 20px 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            bottom: -10px;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item:last-child:before {
            display: none;
        }

        .timeline-icon {
            position: absolute;
            left: -27px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: white;
            border: 3px solid;
            z-index: 2;
        }

        .timeline-icon.completed {
            background: var(--success);
            border-color: var(--success);
        }

        .timeline-icon.pending {
            background: var(--warning);
            border-color: var(--warning);
        }

        .timeline-icon.future {
            background: white;
            border-color: #6c757d;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .filter-active {
            background-color: var(--primary);
            color: white;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            width: 120px;
        }

        .priority-high {
            background-color: #f8d7da;
            font-weight: bold;
        }

        .priority-medium {
            background-color: #fff3cd;
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .action-buttons .btn {
                padding: 0.2rem 0.3rem;
                font-size: 0.7rem;
            }

            .status-badge {
                min-width: 90px;
                font-size: 0.7rem;
                padding: 4px 8px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="dashboard_v2.php">
                <i class="bi bi-geo-alt-fill"></i> Arteche CI System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <nav aria-label="breadcrumb" class="ms-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Document Requests</li>
                    </ol>
                </nav>
                <div class="navbar-nav ms-auto">
                    <span class="nav-item nav-link text-white">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($username) ?>
                        <?php if ($is_super_admin): ?>
                            <span class="badge bg-warning ms-1">Super Admin</span>
                        <?php else: ?>
                            <span
                                class="badge bg-info ms-1"><?= htmlspecialchars($current_barangay_name ?: 'Barangay Admin') ?></span>
                        <?php endif; ?>
                    </span>
                    <a class="nav-item nav-link text-white"
                        href="../../shared/logout.php">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4 px-4">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-file-text"></i> Document Request Management</h2>
                <p class="text-muted mb-0">
                    View, filter, and process citizen document requests
                    <?php if (!$is_super_admin && $current_barangay_name): ?>
                        - <span class="badge bg-info"><?= htmlspecialchars($current_barangay_name) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- PDF Success & Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-start border-success border-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Document Generated Successfully!</strong><br>
                <?= htmlspecialchars(urldecode($_GET['success'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i
                    class="bi bi-<?= $message_type == 'success' ? 'check-circle-fill' : ($message_type == 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4 g-3">
            <div class="col-md-2 col-6">
                <div class="stats-card card-submitted">
                    <div class="stats-icon"><i class="bi bi-envelope"></i></div>
                    <h3><?= number_format($stats['submitted'] ?? 0) ?></h3>
                    <p>Submitted</p>
                    <small>Awaiting review</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card card-review">
                    <div class="stats-icon"><i class="bi bi-eye"></i></div>
                    <h3><?= number_format($stats['under_review'] ?? 0) ?></h3>
                    <p>Under Review</p>
                    <small>Being processed</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card card-approved">
                    <div class="stats-icon"><i class="bi bi-check-circle"></i></div>
                    <h3><?= number_format($stats['approved'] ?? 0) ?></h3>
                    <p>Approved</p>
                    <small>Ready to prepare</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card card-ready">
                    <div class="stats-icon"><i class="bi bi-box"></i></div>
                    <h3><?= number_format($stats['ready'] ?? 0) ?></h3>
                    <p>Ready for Pickup</p>
                    <small>Awaiting claim</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card card-completed">
                    <div class="stats-icon"><i class="bi bi-check-all"></i></div>
                    <h3><?= number_format($stats['completed'] ?? 0) ?></h3>
                    <p>Completed</p>
                    <small>Claimed</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card card-rejected">
                    <div class="stats-icon"><i class="bi bi-x-circle"></i></div>
                    <h3><?= number_format(($stats['rejected'] ?? 0) + ($stats['cancelled'] ?? 0)) ?></h3>
                    <p>Rejected/Cancelled</p>
                    <small>Not approved</small>
                </div>
            </div>
        </div>

        <!-- Payment Summary Row -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-light border-0">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <span class="me-3"><i class="bi bi-cash-stack"></i> <strong>Payment Summary:</strong></span>
                            <span class="badge payment-Pending me-2">Pending:
                                <?= number_format($stats['payment_pending'] ?? 0) ?></span>
                            <span class="badge payment-Paid me-2">Paid:
                                <?= number_format($stats['payment_paid'] ?? 0) ?></span>
                            <span class="badge payment-Waived me-2">Waived:
                                <?= number_format($stats['payment_waived'] ?? 0) ?></span>
                            <span class="ms-auto"><strong>Total Fees:</strong>
                                ₱<?= number_format($stats['total_fees'] ?? 0, 2) ?></span>
                            <span class="ms-3"><strong>Collected:</strong>
                                ₱<?= number_format($stats['collected_fees'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="Submitted" <?= $status_filter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= $status_filter == 'Under Review' ? 'selected' : '' ?>>Under Review
                        </option>
                        <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Ready for Pickup" <?= $status_filter == 'Ready for Pickup' ? 'selected' : '' ?>>
                            Ready for Pickup</option>
                        <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <?php if ($is_super_admin): ?>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Barangay</label>
                        <select name="barangay_id" class="form-select form-select-sm">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $barangay_filter == $b['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Document Type</label>
                    <select name="document_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <?php foreach ($document_types as $dt): ?>
                            <option value="<?= $dt['id'] ?>" <?= $document_filter == $dt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Request #, Name, Email" value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date Range</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="date_from" class="form-control"
                            value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>"
                            placeholder="To">
                    </div>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <a href="document_requests.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="table-container">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-list-check"></i> Document Requests
                        <span class="badge bg-secondary ms-2"><?= count($requests) ?> records</span>
                    </h5>
                    <?php
                    $submitted_count = 0;
                    if ($admin_barangay_id) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id WHERE cr.status = 'Submitted' AND c.barangay_id = ?");
                        $stmt->bind_param("i", $admin_barangay_id);
                        $stmt->execute();
                        $result = $stmt->get_result()->fetch_assoc();
                        $submitted_count = $result['cnt'];
                        $stmt->close();
                    }
                    if ($submitted_count > 0):
                    ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_viewed">
                            <button type="submit" class="btn btn-warning btn-sm ms-3" onclick="return confirm('Mark all Submitted requests as Under Review? This clears navbar badge.');">
                                <i class="bi bi-eye-check"></i> Mark All Viewed (<?= $submitted_count ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="mt-2 mt-sm-0">
                    <button class="btn btn-sm btn-outline-primary" onclick="exportTableToCSV()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>

            <script>
                function markAllViewed() {
                    if (confirm('Mark all new Submitted requests as Under Review / Viewed? This will clear the navbar badge.')) {
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=mark_all_viewed&csrf=<?= $_SESSION['csrf'] ?? '' ?>'
                        }).then(r => r.text()).then(() => location.reload());
                    }
                }
            </script>

            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h5 class="mt-3 text-muted">No document requests found</h5>
                    <p class="text-muted">Try adjusting your filters or check back later.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="requestsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Request #</th>
                                <th>Citizen</th>
                                <th>Document</th>
                                <th>Barangay</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Days</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req):
                                $days_pending = $req['days_pending'] ?? 0;
                                $row_class = '';
                                if ($days_pending > 7 && $req['status'] == 'Submitted') {
                                    $row_class = 'priority-high';
                                } elseif ($days_pending > 3 && $req['status'] == 'Submitted') {
                                    $row_class = 'priority-medium';
                                }
                            ?>
                                <tr class="<?= $row_class ?>">
                                    <td>
                                        <code><?= htmlspecialchars($req['request_number'] ?? 'N/A') ?></code>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['citizen_name'] ?? 'N/A') ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($req['email'] ?? '') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($req['document_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span
                                            class="badge bg-secondary"><?= htmlspecialchars($req['barangay_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <?= date('M d, Y', strtotime($req['submitted_at'])) ?><br>
                                        <small class="text-muted"><?= date('h:i A', strtotime($req['submitted_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= str_replace(' ', '-', $req['status']) ?>">
                                            <?= $req['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="payment-badge payment-<?= $req['payment_status'] ?>">
                                            <?= $req['payment_status'] ?>
                                        </span>
                                        <?php if ($req['fee'] > 0): ?>
                                            <br><small>₱<?= number_format($req['fee'], 2) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="<?= $days_pending > 7 ? 'text-danger fw-bold' : ($days_pending > 3 ? 'text-warning' : '') ?>">
                                            <?= $days_pending ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm action-buttons" role="group">
                                            <button type="button" class="btn btn-info" onclick="viewRequest(<?= $req['id'] ?>)"
                                                title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php if ($req['status'] == 'Submitted'): ?>
                                                <button type="button" class="btn btn-primary"
                                                    onclick="processRequest(<?= $req['id'] ?>)" title="Start Processing">
                                                    <i class="bi bi-play"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if (in_array($req['status'], ['Submitted', 'Under Review'])): ?>
                                                <button type="button" class="btn btn-success"
                                                    onclick="approveRequest(<?= $req['id'] ?>)" title="Approve">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger"
                                                    onclick="rejectRequest(<?= $req['id'] ?>)" title="Reject">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($req['status'] == 'Approved'): ?>
                                                <a href="../generate_pdf.php?request_id=<?= $req['id'] ?>" class="btn btn-success btn-sm" title="Generate & Mark Ready for Pickup">
                                                    <i class="bi bi-file-earmark-pdf"></i> Generate Document
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($req['status'] == 'Ready for Pickup'): ?>
                                                <button type="button" class="btn btn-secondary"
                                                    onclick="markCompleted(<?= $req['id'] ?>)" title="Mark Completed">
                                                    <i class="bi bi-check-all"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($req['payment_status'] == 'Pending' && $req['fee'] > 0): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-success dropdown-toggle"
                                                        data-bs-toggle="dropdown" title="Update Payment">
                                                        <i class="bi bi-cash"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                                <input type="hidden" name="action" value="payment_paid">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="bi bi-check-circle"></i> Mark as Paid
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                                <input type="hidden" name="action" value="payment_waived">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="bi bi-gift"></i> Waive Fee
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Request Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-text"></i> Request Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetailContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading request details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Reject Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="rejectRequestId">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason for Rejection <span
                                    class="text-danger">*</span></label>
                            <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="4"
                                required placeholder="Please provide detailed reason for rejection..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Process Form (hidden, used for simple POST actions) -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="request_id" id="actionRequestId">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="notes" id="actionNotes">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize modals
        var viewModal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
        var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

        // View Request Details
        function viewRequest(id) {
            fetch('get_request_details.php?id=' + id)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('requestDetailContent').innerHTML = html;
                    viewModal.show();
                })
                .catch(error => {
                    document.getElementById('requestDetailContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> Error loading request details.
                        </div>
                    `;
                    viewModal.show();
                });
        }

        // Process Request
        function processRequest(id) {
            if (confirm('Start processing this request?')) {
                document.getElementById('actionRequestId').value = id;
                document.getElementById('actionType').value = 'process';
                document.getElementById('actionNotes').value = 'Processing started';
                document.getElementById('actionForm').submit();
            }
        }

        // Approve Request
        function approveRequest(id) {
            let notes = prompt('Enter any notes (optional):');
            if (notes !== null) {
                document.getElementById('actionRequestId').value = id;
                document.getElementById('actionType').value = 'approve';
                document.getElementById('actionNotes').value = notes || 'Approved';
                document.getElementById('actionForm').submit();
            }
        }

        // Reject Request
        function rejectRequest(id) {
            document.getElementById('rejectRequestId').value = id;
            document.getElementById('rejectionReason').value = '';
            rejectModal.show();
        }

        // Mark as Ready for Pickup
        function markReady(id) {
            if (confirm('Mark this document as ready for pickup?')) {
                document.getElementById('actionRequestId').value = id;
                document.getElementById('actionType').value = 'ready';
                document.getElementById('actionNotes').value = 'Ready for pickup';
                document.getElementById('actionForm').submit();
            }
        }

        // Mark as Completed
        function markCompleted(id) {
            if (confirm('Mark this request as completed? The document has been claimed.')) {
                document.getElementById('actionRequestId').value = id;
                document.getElementById('actionType').value = 'complete';
                document.getElementById('actionNotes').value = 'Completed';
                document.getElementById('actionForm').submit();
            }
        }

        // Export table to CSV
        function exportTableToCSV() {
            const rows = document.querySelectorAll('#requestsTable tr');
            let csv = [];

            for (let row of rows) {
                const cells = row.querySelectorAll('th, td');
                // Skip action column (last column)
                const rowData = [];
                for (let i = 0; i < cells.length - 1; i++) {
                    let cellText = cells[i].innerText.replace(/,/g, ' ').replace(/\n/g, ' ');
                    rowData.push(cellText);
                }
                csv.push(rowData.join(','));
            }

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'document_requests_<?= date('Y-m-d') ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                let bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>
<?php $conn->close(); ?>