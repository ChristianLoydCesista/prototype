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

                COALESCE(SUM(CASE
                    WHEN status IN ('Pending', 'Submitted', 'Under Review', 'Approved')
                    THEN 1 ELSE 0
                END), 0) AS pending,

                COALESCE(SUM(CASE
                    WHEN status = 'Ready for Pickup'
                    THEN 1 ELSE 0
                END), 0) AS ready,

                COALESCE(SUM(CASE
                    WHEN status = 'Rejected'
                    THEN 1 ELSE 0
                END), 0) AS rejected,

                COALESCE(SUM(CASE
                    WHEN status = 'Completed'
                    THEN 1 ELSE 0
                END), 0) AS completed

            FROM citizen_requests
            WHERE citizen_id = ?
        ");

        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total'     => (int)($result['total'] ?? 0),
            'pending'   => (int)($result['pending'] ?? 0),
            'ready'     => (int)($result['ready'] ?? 0),
            'rejected'  => (int)($result['rejected'] ?? 0),
            'completed' => (int)($result['completed'] ?? 0),
        ];
    } catch (Exception $e) {
        error_log("Error fetching request stats: " . $e->getMessage());

        return [
            'total' => 0,
            'pending' => 0,
            'ready' => 0,
            'rejected' => 0,
            'completed' => 0
        ];
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
    <header class="cis-topbar">
        <div class="cis-shell d-flex align-items-center justify-content-between py-3">
            <div>
                <div class="cis-brand">
                    <i class="bi bi-shield-check me-1"></i> Arteche Portal
                </div>
                <small class="text-muted">Citizen Dashboard</small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="citizen_notification.php" class="cis-icon-btn position-relative">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="citizen_profile.php" class="cis-icon-btn">
                    <i class="bi bi-person-circle"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="cis-shell">
        <!-- HERO -->
        <section class="cis-hero mb-4">
            <div class="position-relative">
                <small class="opacity-75 d-block mb-2">
                    <?= date('l, F d, Y') ?>
                </small>

                <h1 class="mb-2">
                    Hi, <?= htmlspecialchars($citizen['first_name'] ?? 'Citizen') ?> 👋
                </h1>

                <p class="mb-4 opacity-75">
                    Track your requests, view updates, and access barangay services.
                </p>

                <a href="request_document.php" class="btn btn-light fw-bold rounded-pill px-4 py-2">
                    <i class="bi bi-plus-circle me-1"></i> New Request
                </a>
            </div>
        </section>

        <!-- PROFILE COMPLETION -->
        <?php if ($profileCompletion < 100): ?>
            <section class="cis-card p-3 mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="cis-stat-icon">
                        <i class="bi bi-person-check"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Complete your profile</strong>
                            <span class="badge bg-primary"><?= $profileCompletion ?>%</span>
                        </div>

                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= $profileCompletion ?>%"></div>
                        </div>

                        <small class="text-muted d-block mb-2">
                            Add missing details to make document requests easier.
                        </small>

                        <a href="citizen_profile.php" class="small fw-bold text-decoration-none">
                            Update profile →
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- STATS -->
        <section class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="cis-section-title">Request Overview</h2>
                <a href="my_request.php" class="small fw-bold text-decoration-none">View all</a>
            </div>

            <div class="cis-stat-grid">
                <a href="my_request.php" class="cis-stat-card text-decoration-none text-dark">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small>Total</small>
                            <h2><?= number_format((int)($requestStats['total'] ?? 0)) ?></h2>
                        </div>
                        <div class="cis-stat-icon">
                            <i class="bi bi-files"></i>
                        </div>
                    </div>
                </a>

                <a href="my_request.php?status=Under Review" class="cis-stat-card text-decoration-none text-dark">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small>In Progress</small>
                            <h2><?= number_format((int)($requestStats['pending'] ?? 0)) ?></h2>
                        </div>
                        <div class="cis-stat-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </a>

                <a href="my_request.php?status=Ready for Pickup" class="cis-stat-card text-decoration-none text-dark">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small>Ready</small>
                            <h2><?= number_format((int)($requestStats['ready'] ?? 0)) ?></h2>
                        </div>
                        <div class="cis-stat-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </a>

                <a href="my_request.php?status=completed" class="cis-stat-card text-decoration-none text-dark">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small>Completed</small>
                            <h2><?= number_format((int)($requestStats['completed'] ?? 0)) ?></h2>
                        </div>
                        <div class="cis-stat-icon">
                            <i class="bi bi-trophy"></i>
                        </div>
                    </div>
                </a>
            </div>
        </section>

        <div class="cis-dashboard-grid">
            <div>
                <!-- Recent Requests here -->
                <!-- RECENT REQUESTS -->
                <section class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="cis-section-title">Recent Requests</h2>
                        <a href="my_request.php" class="small fw-bold text-decoration-none">View all</a>
                    </div>

                    <?php if (empty($recentRequests)): ?>
                        <div class="cis-card cis-empty">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <strong>No requests yet</strong>
                            <p class="small mb-3">Start by requesting your first barangay document.</p>
                            <a href="request_document.php" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-plus-circle me-1"></i> Make a Request
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($recentRequests as $request): ?>
                                <?php
                                $statusConfig = [
                                    'Pending' => ['class' => 'bg-warning text-dark', 'icon' => 'hourglass-split'],
                                    'Submitted' => ['class' => 'bg-info text-dark', 'icon' => 'send'],
                                    'Under Review' => ['class' => 'bg-info text-dark', 'icon' => 'search'],
                                    'Approved' => ['class' => 'bg-success text-white', 'icon' => 'check-circle'],
                                    'Rejected' => ['class' => 'bg-danger text-white', 'icon' => 'x-circle'],
                                    'Ready for Pickup' => ['class' => 'bg-primary text-white', 'icon' => 'box-seam'],
                                    'Completed' => ['class' => 'bg-secondary text-white', 'icon' => 'check2-circle'],
                                    'Cancelled' => ['class' => 'bg-dark text-white', 'icon' => 'slash-circle'],
                                ];

                                $config = $statusConfig[$request['status']] ?? [
                                    'class' => 'bg-secondary text-white',
                                    'icon' => 'file-text'
                                ];
                                ?>

                                <a href="request_details.php?id=<?= (int)$request['id'] ?>" class="cis-request-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                <strong>
                                                    <?= htmlspecialchars($request['document_name'] ?? 'Document Request') ?>
                                                </strong>

                                                <span class="cis-badge <?= $config['class'] ?>">
                                                    <i class="bi bi-<?= $config['icon'] ?>"></i>
                                                    <?= htmlspecialchars($request['status']) ?>
                                                </span>
                                            </div>

                                            <div class="small text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= !empty($request['created_at']) ? date('M d, Y h:i A', strtotime($request['created_at'])) : 'No date' ?>
                                            </div>

                                            <?php if (!empty($request['request_number'])): ?>
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-hash me-1"></i>
                                                    <?= htmlspecialchars($request['request_number']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-muted">
                                            <i class="bi bi-chevron-right"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Announcements here -->
                <!-- ANNOUNCEMENTS -->
                <section class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="cis-section-title">Announcements</h2>
                        <a href="announcements.php" class="small fw-bold text-decoration-none">View all</a>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="cis-card cis-empty">
                            <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
                            <strong>No announcements</strong>
                            <p class="small mb-0">Barangay updates will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                                <a href="announcements.php?id=<?= (int)$announcement['id'] ?>"
                                    class="cis-request-item announcement-item"
                                    data-id="<?= (int)$announcement['id'] ?>">

                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                                <strong><?= htmlspecialchars($announcement['title']) ?></strong>

                                                <?php if (empty($announcement['is_read'])): ?>
                                                    <span class="cis-badge bg-primary text-white">
                                                        New
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($announcement['priority']) && in_array($announcement['priority'], ['Urgent', 'High'])): ?>
                                                    <span class="cis-badge bg-danger text-white">
                                                        <?= htmlspecialchars($announcement['priority']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="small text-muted mb-2">
                                                <?= htmlspecialchars(mb_strimwidth(strip_tags($announcement['content'] ?? ''), 0, 95, '...')) ?>
                                            </p>

                                            <div class="small text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= !empty($announcement['published_at']) ? date('M d, Y', strtotime($announcement['published_at'])) : 'No date' ?>
                                            </div>
                                        </div>

                                        <div class="text-muted">
                                            <i class="bi bi-chevron-right"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="cis-desktop-sticky">
                <section class="mb-4">
                    <h2 class="cis-section-title mb-3">Quick Actions</h2>

                    <div class="row g-3">
                        <div class="col-6 col-lg-3">
                            <a href="request_document.php" class="cis-card p-3 d-block text-decoration-none text-dark h-100">
                                <div class="cis-stat-icon mb-3">
                                    <i class="bi bi-file-earmark-plus"></i>
                                </div>
                                <strong>Request</strong>
                                <small class="d-block text-muted">New document</small>
                            </a>
                        </div>

                        <div class="col-6 col-lg-3">
                            <a href="my_request.php" class="cis-card p-3 d-block text-decoration-none text-dark h-100">
                                <div class="cis-stat-icon mb-3">
                                    <i class="bi bi-folder2-open"></i>
                                </div>
                                <strong>History</strong>
                                <small class="d-block text-muted">Track requests</small>
                            </a>
                        </div>

                        <div class="col-6 col-lg-3">
                            <a href="citizen_notification.php" class="cis-card p-3 d-block text-decoration-none text-dark h-100 position-relative">
                                <div class="cis-stat-icon mb-3">
                                    <i class="bi bi-bell"></i>
                                </div>
                                <strong>Alerts</strong>
                                <small class="d-block text-muted">Notifications</small>

                                <?php if ($unreadNotifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?= $unreadNotifications ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <div class="col-6 col-lg-3">
                            <a href="citizen_profile.php" class="cis-card p-3 d-block text-decoration-none text-dark h-100">
                                <div class="cis-stat-icon mb-3">
                                    <i class="bi bi-person-gear"></i>
                                </div>
                                <strong>Profile</strong>
                                <small class="d-block text-muted">Update details</small>
                            </a>
                        </div>
                    </div>
                </section>

                <!-- DESKTOP FOOTER ACTION -->
                <section class="d-none d-lg-block mb-4">
                    <div class="cis-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <strong>Need another document?</strong>
                            <p class="small text-muted mb-0">Start a new barangay request anytime.</p>
                        </div>

                        <a href="request_document.php" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-plus-circle me-1"></i> New Request
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <!-- MOBILE BOTTOM NAV -->
    <nav class="cis-bottom-nav">
        <a href="citizen_dashboard.php" class="active">
            <i class="bi bi-house-fill"></i>
            Home
        </a>

        <a href="my_request.php">
            <i class="bi bi-files"></i>
            Requests
        </a>

        <a href="request_document.php">
            <i class="bi bi-plus-circle-fill"></i>
            New
        </a>

        <a href="citizen_notification.php">
            <i class="bi bi-bell"></i>
            Alerts
        </a>

        <a href="citizen_profile.php">
            <i class="bi bi-person"></i>
            Profile
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.announcement-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                const announcementId = item.dataset.id;
                if (!announcementId) return;

                try {
                    await fetch('mark_announcement_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: announcementId
                        })
                    });
                } catch (error) {
                    console.error('Announcement read error:', error);
                }
            });
        });

        window.addEventListener('online', () => {
            console.log('Back online');
        });

        window.addEventListener('offline', () => {
            console.log('Offline mode');
        });
    </script>
</body>

</html>