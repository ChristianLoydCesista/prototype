<?php
// citizen_dashboard.php - IMPROVED UX VERSION
require_once '../shared/bootstrap.php';

$session = new Session();

// Redirect if not logged in
if (!$session->isCitizenLoggedIn()) {
    $session->setFlash('error', 'Please login first');
    header("Location: citizen_portal.php");
    exit;
}

$citizen = $session->getCitizen();
$auth = new Auth();
$citizenData = $auth->getCitizen($citizen['id']);
$db = getDB();

// Get request statistics with proper error handling
$requestStats = getRequestStats($db, $citizen['id']);
$recentRequests = getRecentRequests($db, $citizen['id']);
$documentTypes = getDocumentTypes($db);
$unreadNotifications = getUnreadNotificationsCount($db, $citizen['id']);
$announcements = getActiveAnnouncements($db, $citizen['id'], $citizenData['barangay_id'] ?? null);

// Helper functions (separate concerns)
function getRequestStats($db, $citizenId)
{
    try {
        $stmt = $db->prepare("
            SELECT
    COUNT(*) AS total,

    COALESCE(
        SUM(CASE
            WHEN status IN ('Pending','Submitted','Under Review')
            THEN 1 ELSE 0
        END),
    0) AS pending,

    COALESCE(
        SUM(CASE
            WHEN status='Approved'
            THEN 1 ELSE 0
        END),
    0) AS approved,

    COALESCE(
        SUM(CASE
            WHEN status='Rejected'
            THEN 1 ELSE 0
        END),
    0) AS rejected,

    COALESCE(
        SUM(CASE
            WHEN status='Completed'
            THEN 1 ELSE 0
        END),
    0) AS completed

FROM citizen_requests
WHERE citizen_id = ?
        ");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total'      => (int)($result['total'] ?? 0),
            'pending'    => (int)($result['pending'] ?? 0),
            'approved'   => (int)($result['approved'] ?? 0),
            'rejected'   => (int)($result['rejected'] ?? 0),
            'completed'  => (int)($result['completed'] ?? 0),
        ];
    } catch (Exception $e) {
        error_log("Error fetching request stats: " . $e->getMessage());
        return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
    }
}

function getRecentRequests($db, $citizenId, $limit = 5)
{
    try {
        $stmt = $db->prepare("
            SELECT cr.*, dt.name as document_name, dt.icon_class, dt.color
            FROM citizen_requests cr
            LEFT JOIN document_types dt ON cr.document_type_id = dt.id
            WHERE cr.citizen_id = ?
            ORDER BY cr.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $citizenId, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error fetching recent requests: " . $e->getMessage());
        return [];
    }
}

function getDocumentTypes($db)
{
    try {
        $result = $db->query("
            SELECT id, name, description, processing_fee, processing_days, 
                   icon_class, color, is_active
            FROM document_types 
            WHERE is_active = 1
            ORDER BY sort_order, name
        ");
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching document types: " . $e->getMessage());
        return [];
    }
}

function getUnreadNotificationsCount($db, $citizenId)
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE citizen_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getActiveAnnouncements($db, $citizenId, $barangayId)
{
    try {
        $sql = "
            SELECT a.*, u.full_name as created_by_name,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.citizen_id = ?
            WHERE a.is_active = 1 
                AND a.published_at <= NOW() 
                AND (a.expires_at IS NULL OR a.expires_at > NOW())
                AND (a.barangay_id IS NULL OR a.barangay_id = ?)
            ORDER BY 
                FIELD(a.priority, 'Urgent', 'High', 'Normal', 'Low'),
                a.published_at DESC
            LIMIT 5
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ii", $citizenId, $barangayId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

// Calculate profile completion
$profileCompletion = calculateProfileCompletion($citizenData);
function calculateProfileCompletion($data)
{
    $fields = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'barangay'];
    $filled = 0;
    foreach ($fields as $field) {
        if (!empty($data[$field] ?? ''))
            $filled++;
    }
    return round(($filled / count($fields)) * 100);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - Arteche Citizen Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/citizen_dashboard.css">
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4 class="mb-0">
                <i class="bi bi-shield-check"></i> Arteche Portal
            </h4>
            <small class="text-white-50">Eastern Samar</small>
        </div>

        <div class="user-profile-sidebar">
            <div class="avatar-circle">
                <?php if (!empty($citizenData['avatar'])): ?>
                    <img src="../public/uploads/avatars/<?= htmlspecialchars($citizenData['avatar']) ?>" alt="Profile"
                        class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                <?php endif; ?>
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($citizen['first_name'] . ' ' . $citizen['last_name']) ?></h6>
            <small
                class="text-white-50 opacity-75"><?= htmlspecialchars($citizenData['barangay_name'] ?? 'Arteche') ?></small>
            <div class="mt-2">
                <span class="badge bg-success bg-opacity-75">
                    <i class="bi bi-check-circle-fill"></i> Verified
                </span>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="citizen_dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="request_document.php">
                        <i class="bi bi-file-earmark-plus"></i> New Request
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_request.php">
                        <i class="bi bi-files"></i> My Requests
                        <?php if ($requestStats['pending'] > 0): ?>
                            <span class="badge bg-warning float-end"><?= $requestStats['pending'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="citizen_profile.php">
                        <i class="bi bi-person"></i> Profile Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="citizen_notification.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge bg-danger float-end"><?= $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <hr class="border-light opacity-25">
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="citizen_logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <div class="position-absolute bottom-0 start-0 end-0 p-3 text-center">
            <small class="text-white-50 opacity-50">© <?= date('Y') ?> LGU Arteche</small>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar px-4 d-flex align-items-center justify-content-between">
            <div>
                <button class="btn btn-link d-md-none text-dark p-0" type="button" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-3"></i>
                </button>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Announcements Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link position-relative text-dark p-0" type="button"
                        data-bs-toggle="dropdown">
                        <i class="bi bi-megaphone fs-5"></i>
                        <?php if (count($announcements) > 0 && !empty(array_filter($announcements, fn($a) => !$a['is_read']))): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                style="font-size: 0.6rem;">
                                <?= count(array_filter($announcements, fn($a) => !$a['is_read'])) ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-0" style="width: 320px;">
                        <li class="px-3 py-2 bg-light">
                            <strong>Announcements</strong>
                        </li>
                        <?php if (empty($announcements)): ?>
                            <li class="px-3 py-3 text-center text-muted">
                                <i class="bi bi-info-circle"></i> No new announcements
                            </li>
                        <?php else: ?>
                            <?php foreach (array_slice($announcements, 0, 5) as $announcement): ?>
                                <li>
                                    <a class="dropdown-item announcement-item" href="#" data-id="<?= $announcement['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong class="d-block"><?= htmlspecialchars($announcement['title']) ?></strong>
                                                <small
                                                    class="text-muted"><?= date('M d, Y', strtotime($announcement['published_at'])) ?></small>
                                            </div>
                                            <?php if (!$announcement['is_read']): ?>
                                                <span class="badge bg-primary">New</span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li class="text-center p-2">
                                <a href="announcements.php" class="text-decoration-none small">View all announcements</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link text-dark p-0 d-flex align-items-center gap-2" type="button"
                        data-bs-toggle="dropdown">

                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="citizen_profile.php"><i class="bi bi-person me-2"></i>My
                                Profile</a></li>
                        <li><a class="dropdown-item" href="citizen_notifications.php"><i
                                    class="bi bi-bell me-2"></i>Notifications</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="citizen_logout.php"><i
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Welcome Banner -->
            <div class="welcome-card p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8 text-light">
                        <h1 class="display-6 fw-bold mb-2">
                            Welcome back, <?= htmlspecialchars($citizen['first_name']) ?>! 👋
                        </h1>
                        <p class="mb-0 opacity-90">
                            Track your document requests and stay updated with barangay announcements.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="request_document.php" class="btn btn-light btn-lg shadow-sm">
                            <i class="bi bi-plus-circle"></i> New Request
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile Completion Alert -->
            <?php if ($profileCompletion < 100): ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Complete your profile!</strong> Your profile is <?= $profileCompletion ?>% complete.
                    <a href="citizen_profile.php" class="alert-link">Update now</a> to unlock all features.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card" onclick="location.href='my_request.php'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Total Requests</p>
                                    <h2 class="mb-0 fw-bold">
                                        <?= number_format((int)($requestStats['total'] ?? 0)) ?>
                                    </h2>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-files"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card" onclick="location.href='my_request.php?status=pending'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">In Progress</p>
                                    <h2 class="mb-0 fw-bold">
                                        <?= number_format((int)($requestStats['pending'] ?? 0)) ?>
                                    </h2>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card" onclick="location.href='my_request.php?status=approved'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Approved</p>
                                    <h2 class="mb-0 fw-bold"><?= number_format($requestStats['approved']) ?></h2>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card" onclick="location.href='my_request.php?status=completed'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Completed</p>
                                    <h2 class="mb-0 fw-bold"><?= number_format($requestStats['completed']) ?></h2>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-trophy"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Requests & Quick Actions -->
            <div class="row g-4">
                <!-- Recent Requests Section -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div
                            class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-4 px-4">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-clock-history me-2 text-primary"></i>Recent Requests
                            </h5>
                            <a href="my_request.php" class="btn btn-sm btn-link text-decoration-none">View All →</a>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <?php if (empty($recentRequests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <h6 class="mt-3 text-muted">No requests yet</h6>
                                    <p class="small text-muted mb-3">Start by requesting your first document</p>
                                    <a href="request_document.php" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-circle"></i> Make a Request
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentRequests as $request):
                                        $statusConfig = [
                                            'Pending' => ['class' => 'bg-warning text-dark', 'icon' => 'hourglass-split'],
                                            'Processing' => ['class' => 'bg-info text-white', 'icon' => 'gear'],
                                            'Approved' => ['class' => 'bg-success text-white', 'icon' => 'check-circle'],
                                            'Rejected' => ['class' => 'bg-danger text-white', 'icon' => 'x-circle'],
                                            'Completed' => ['class' => 'bg-secondary text-white', 'icon' => 'check2-circle'],
                                            'Ready for Pickup' => ['class' => 'bg-primary text-white', 'icon' => 'box-seam']
                                        ];
                                        $config = $statusConfig[$request['status']] ?? ['class' => 'bg-secondary text-white', 'icon' => 'file-text'];
                                    ?>
                                        <div class="request-item list-group-item list-group-item-action border-0 px-0 py-3"
                                            onclick="location.href='request_details.php?id=<?= $request['id'] ?>'">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 mb-2">
                                                        <i class="bi bi-file-text text-primary"></i>
                                                        <strong><?= htmlspecialchars($request['document_name'] ?? $request['document_type'] ?? 'Document Request') ?></strong>
                                                        <span class="status-badge <?= $config['class'] ?>">
                                                            <i class="bi bi-<?= $config['icon'] ?> me-1"></i>
                                                            <?= $request['status'] ?>
                                                        </span>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?= date('M d, Y h:i A', strtotime($request['created_at'])) ?>
                                                        <?php if (!empty($request['request_number'])): ?>
                                                            • <i class="bi bi-hash"></i>
                                                            <?= htmlspecialchars($request['request_number']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <i class="bi bi-chevron-right text-muted"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="col-lg-4">
                    <!-- Profile Completion Card -->
                    <div class="profile-completion mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold">Profile Completion</h6>
                            <span class="badge bg-primary"><?= $profileCompletion ?>%</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar" style="width: <?= $profileCompletion ?>%"></div>
                        </div>
                        <?php if ($profileCompletion < 100): ?>
                            <a href="citizen_profile.php" class="btn btn-sm btn-outline-primary w-100 mt-2">
                                <i class="bi bi-pencil"></i> Complete Profile
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="d-grid gap-2">
                                <a href="request_document.php" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-plus"></i> Request Document
                                </a>
                                <a href="citizen_profile.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-person-gear"></i> Update Profile
                                </a>
                                <a href="citizen_notifications.php" class="btn btn-outline-secondary position-relative">
                                    <i class="bi bi-bell"></i> Notifications
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                            <?= $unreadNotifications ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Available Documents -->
                    <?php if (!empty($documentTypes)): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 pt-4 px-4">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-file-text me-2 text-primary"></i>Available Documents
                                </h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($documentTypes, 0, 4) as $doc): ?>
                                        <a href="request_document.php?type=<?= $doc['id'] ?>"
                                            class="list-group-item list-group-item-action border-0 px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi <?= $doc['icon_class'] ?? 'bi-file-text' ?> me-2"
                                                        style="color: <?= $doc['color'] ?? '#1e40af' ?>"></i>
                                                    <?= htmlspecialchars($doc['name']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= (($doc['processing_fee'] ?? 0) > 0)
                                                        ? '₱' . number_format((float)($doc['processing_fee'] ?? 0), 2)
                                                        : 'Free' ?>
                                                </small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($documentTypes) > 4): ?>
                                    <div class="text-center mt-3">
                                        <a href="request_document.php" class="btn btn-sm btn-link">View all documents →</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');

            // Add overlay for mobile
            if (sidebar.classList.contains('open')) {
                let overlay = document.querySelector('.sidebar-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.right = '0';
                    overlay.style.bottom = '0';
                    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
                    overlay.style.zIndex = '1029';
                    overlay.onclick = toggleSidebar;
                    document.body.appendChild(overlay);
                }
            } else {
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) overlay.remove();
            }
        }

        // Close sidebar when clicking a link on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Handle announcement click to mark as read
        document.querySelectorAll('.announcement-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.preventDefault();
                const announcementId = item.dataset.id;

                if (announcementId) {
                    try {
                        await fetch('mark_announcement_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: announcementId
                            })
                        });

                        // Update UI
                        const badge = item.querySelector('.badge');
                        if (badge) badge.remove();

                        // Show toast notification
                        showToast('Announcement marked as read', 'success');
                    } catch (error) {
                        console.error('Error marking announcement:', error);
                    }
                }
            });
        });

        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();

            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();

            // Remove toast after it's hidden
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // Auto-refresh data every 60 seconds (optional)
        let autoRefresh = true;
        if (autoRefresh) {
            setInterval(() => {
                // Only refresh if page is visible
                if (!document.hidden) {
                    fetch(window.location.href, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => {
                        if (response.ok) {
                            // Update specific elements without full page reload
                            // This is a simplified version - you'd want to update only dynamic content
                            console.log('Auto-refresh check');
                        }
                    }).catch(console.error);
                }
            }, 60000);
        }

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Handle offline mode detection
        window.addEventListener('online', () => {
            showToast('You are back online!', 'success');
        });

        window.addEventListener('offline', () => {
            showToast('You are offline. Some features may be unavailable.', 'warning');
        });
    </script>
</body>

</html>