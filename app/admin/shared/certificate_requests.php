<?php
require_once __DIR__ . '/../../shared/bootstrap.php';
// session, database and helpers already initialised

if (!isset($_SESSION['admin'])) {
    header("Location: ../admin_login.php");
    exit;
}

// Get session variables
$is_super_admin = ($_SESSION['role'] ?? '') == 'super_admin';
$barangay_id = $_SESSION['barangay_id'] ?? null;
$username = $_SESSION['username'] ?? 'Admin';

// Get current barangay info for display
$selected_barangay = null;
if ($barangay_id && !$is_super_admin) {
    $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->bind_param("i", $barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_barangay = $result->fetch_assoc();
    $stmt->close();
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $cert_id = intval($_GET['id']);
    $action = $_GET['action'];

    // Security: Check if barangay admin can process this request
    if (!$is_super_admin && $barangay_id) {
        // Verify the certificate belongs to their barangay
        $check_stmt = $conn->prepare("
            SELECT cr.id 
            FROM certificate_requests cr
            JOIN households h ON cr.household_id = h.id
            WHERE cr.id = ? AND h.barangay_id = ?
        ");
        $check_stmt->bind_param("ii", $cert_id, $barangay_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            // Not authorized
            header("Location: certificate_requests.php?error=unauthorized");
            exit;
        }
        $check_stmt->close();
    }

    if ($action == 'approve') {
        $stmt = $conn->prepare("UPDATE certificate_requests SET status='Approved', processed_date=NOW(), approved_by=? WHERE id=?");
        $stmt->bind_param("si", $_SESSION['username'], $cert_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE certificate_requests SET status='Rejected', processed_date=NOW(), approved_by=? WHERE id=?");
        $stmt->bind_param("si", $_SESSION['username'], $cert_id);
        $stmt->execute();
        $stmt->close();
    }

    // Refresh page to show updated status
    header("Location: certificate_requests.php");
    exit;
}

// Get certificates with RBAC
if ($is_super_admin) {
    // Super admin sees all
    $stmt = $conn->prepare("
        SELECT cr.*, h.name as resident_name, b.name as barangay_name 
        FROM certificate_requests cr
        LEFT JOIN households h ON cr.household_id = h.id
        LEFT JOIN barangays b ON h.barangay_id = b.id
        ORDER BY cr.requested_date DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $certificates = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($barangay_id) {
    // Barangay admin sees only their barangay
    $stmt = $conn->prepare("
        SELECT cr.*, h.name as resident_name, b.name as barangay_name 
        FROM certificate_requests cr
        LEFT JOIN households h ON cr.household_id = h.id
        LEFT JOIN barangays b ON h.barangay_id = b.id
        WHERE h.barangay_id = ?
        ORDER BY cr.requested_date DESC
    ");
    $stmt->bind_param("i", $barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $certificates = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $certificates = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Certificate Requests - Arteche CI System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
        }

        .table-actions {
            min-width: 180px;
        }

        .breadcrumb {
            background: transparent;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard_v2.php">
                <i class="bi bi-geo-alt"></i> Arteche CI System
            </a>
            <nav aria-label="breadcrumb" class="ms-3">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard_v2.php" class="text-white">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active text-white">Certificate Requests</li>
                    <?php if ($selected_barangay && !$is_super_admin): ?>
                        <li class="breadcrumb-item active text-white"><?= htmlspecialchars($selected_barangay['name']) ?>
                        </li>
                    <?php endif; ?>
                </ol>
            </nav>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-white">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($username) ?>
                    <?php if ($is_super_admin): ?>
                        <span class="badge bg-warning">Super Admin</span>
                    <?php else: ?>
                        <span
                            class="badge bg-info"><?= htmlspecialchars($selected_barangay['name'] ?? 'Barangay Admin') ?></span>
                    <?php endif; ?>
                </span>
                <a class="nav-item nav-link text-white" href="dashboard_v2.php"><i class="bi bi-speedometer2"></i>
                    Dashboard</a>
                <a class="nav-item nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i>
                    Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-file-text"></i> Certificate Requests
                <?php if ($selected_barangay && !$is_super_admin): ?>
                    <small class="text-muted">- <?= htmlspecialchars($selected_barangay['name']) ?></small>
                <?php elseif ($is_super_admin): ?>
                    <small class="text-muted">- All Barangays</small>
                <?php endif; ?>
            </h2>
            <a href="dashboard_v2.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Status Filters -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group" role="group">
                    <a href="certificate_requests.php" class="btn btn-outline-primary">All</a>
                    <a href="certificate_requests.php?status=Pending" class="btn btn-outline-warning">Pending</a>
                    <a href="certificate_requests.php?status=Approved" class="btn btn-outline-success">Approved</a>
                    <a href="certificate_requests.php?status=Rejected" class="btn btn-outline-danger">Rejected</a>
                </div>

                <?php if (isset($_GET['error']) && $_GET['error'] == 'unauthorized'): ?>
                    <div class="alert alert-danger mt-2">
                        <i class="bi bi-exclamation-triangle"></i> You are not authorized to process that certificate
                        request.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <?php
            // Get counts
            if ($is_super_admin) {
                $total = $conn->query("SELECT COUNT(*) as cnt FROM certificate_requests")->fetch_assoc()['cnt'];
                $pending = $conn->query("SELECT COUNT(*) as cnt FROM certificate_requests WHERE status='Pending'")->fetch_assoc()['cnt'];
                $approved = $conn->query("SELECT COUNT(*) as cnt FROM certificate_requests WHERE status='Approved'")->fetch_assoc()['cnt'];
                $rejected = $conn->query("SELECT COUNT(*) as cnt FROM certificate_requests WHERE status='Rejected'")->fetch_assoc()['cnt'];
            } elseif ($barangay_id) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN cr.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN cr.status = 'Approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN cr.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                    FROM certificate_requests cr
                    JOIN households h ON cr.household_id = h.id
                    WHERE h.barangay_id = ?
                ");
                $stmt->bind_param("i", $barangay_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $counts = $result->fetch_assoc();
                $stmt->close();

                $total = $counts['total'] ?? 0;
                $pending = $counts['pending'] ?? 0;
                $approved = $counts['approved'] ?? 0;
                $rejected = $counts['rejected'] ?? 0;
            } else {
                $total = $pending = $approved = $rejected = 0;
            }
            ?>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-6"><?= $total ?></h1>
                        <p class="text-muted">Total Requests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-6 text-warning"><?= $pending ?></h1>
                        <p class="text-muted">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-6 text-success"><?= $approved ?></h1>
                        <p class="text-muted">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-6 text-danger"><?= $rejected ?></h1>
                        <p class="text-muted">Rejected</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Certificate Requests</h5>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($certificates)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-text" style="font-size: 3rem; color: #6c757d;"></i>
                        <h5 class="mt-3">No certificate requests found</h5>
                        <p class="text-muted">There are no certificate requests at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($is_super_admin): ?>
                                        <th>Barangay</th>
                                    <?php endif; ?>
                                    <th>Request #</th>
                                    <th>Resident</th>
                                    <th>Certificate Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Date Requested</th>
                                    <th class="table-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificates as $cert):
                                    $status_class = [
                                        'Pending' => 'warning',
                                        'Approved' => 'success',
                                        'Rejected' => 'danger'
                                    ][$cert['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <?php if ($is_super_admin): ?>
                                            <td>
                                                <span class="badge bg-info barangay-badge">
                                                    <?= htmlspecialchars($cert['barangay_name'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td><code><?= htmlspecialchars($cert['request_number'] ?? 'N/A') ?></code></td>
                                        <td><strong><?= htmlspecialchars($cert['resident_name'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($cert['certificate_type'] ?? 'N/A') ?></td>
                                        <td><small><?= substr(htmlspecialchars($cert['purpose'] ?? ''), 0, 50) ?>...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?> status-badge">
                                                <?= htmlspecialchars($cert['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($cert['requested_date'] ?? 'now')) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view_certificate.php?id=<?= $cert['id'] ?>" class="btn btn-info"
                                                    title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($cert['status'] == 'Pending'): ?>
                                                    <a href="?action=approve&id=<?= $cert['id'] ?>" class="btn btn-success"
                                                        title="Approve"
                                                        onclick="return confirm('Approve this certificate request?')">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <a href="?action=reject&id=<?= $cert['id'] ?>" class="btn btn-danger"
                                                        title="Reject" onclick="return confirm('Reject this certificate request?')">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
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

        <!-- Back to Dashboard -->
        <div class="mt-3 text-center">
            <a href="dashboard_v2.php" class="btn btn-primary">
                <i class="bi bi-speedometer2"></i> Return to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Add confirmation for actions
        document.addEventListener('DOMContentLoaded', function () {
            const approveBtns = document.querySelectorAll('a[href*="action=approve"]');
            const rejectBtns = document.querySelectorAll('a[href*="action=reject"]');

            approveBtns.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    if (!confirm('Are you sure you want to approve this certificate?')) {
                        e.preventDefault();
                    }
                });
            });

            rejectBtns.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    if (!confirm('Are you sure you want to reject this certificate?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>

</html>