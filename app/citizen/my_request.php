<?php
// app/citizen/my_request.php - Citizen track their document requests
require_once __DIR__ . '/../shared/bootstrap.php';

$session = new Session();
if (!$session->isCitizenLoggedIn()) {
    header('Location: citizen_login.php');
    exit;
}

$citizen_id = $session->getCitizenId();
$citizen_name = $_SESSION['citizen_name'] ?? 'Citizen';
$citizen = $session->getCitizen();

$conn = getDB();

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        cr.*,
        dt.name as document_name,
        c.barangay_id,
        b.name as barangay_name,
        CASE cr.status
            WHEN 'Submitted' THEN 1
            WHEN 'Under Review' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Ready for Pickup' THEN 4
            WHEN 'Completed' THEN 5
            WHEN 'Rejected' THEN 6
            ELSE 7
        END as status_order
    FROM citizen_requests cr
    JOIN citizens c ON cr.citizen_id = c.id
    JOIN document_types dt ON cr.document_type_id = dt.id
    LEFT JOIN barangays b ON c.barangay_id = b.id
    WHERE cr.citizen_id = ?
";

$params = [$citizen_id];
$types = 'i';

if ($status_filter !== 'all') {
    $query .= " AND cr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from) {
    $query .= " AND DATE(cr.submitted_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $query .= " AND DATE(cr.submitted_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($search) {
    $query .= " AND (cr.request_number LIKE ? OR dt.name LIKE ? OR cr.purpose LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$query .= " ORDER BY status_order ASC, cr.submitted_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Submitted', 'Under Review') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM citizen_requests WHERE citizen_id = ?
");
$stats_stmt->bind_param('i', $citizen_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Status configuration for consistent styling
$statusConfig = [
    'Submitted' => ['class' => 'bg-secondary', 'icon' => 'bi-send', 'text' => 'Submitted'],
    'Under Review' => ['class' => 'bg-warning', 'icon' => 'bi-hourglass-split', 'text' => 'Under Review'],
    'Approved' => ['class' => 'bg-success', 'icon' => 'bi-check-circle', 'text' => 'Approved'],
    'Ready for Pickup' => ['class' => 'bg-info', 'icon' => 'bi-box-seam', 'text' => 'Ready for Pickup'],
    'Completed' => ['class' => 'bg-dark', 'icon' => 'bi-check2-circle', 'text' => 'Completed'],
    'Rejected' => ['class' => 'bg-danger', 'icon' => 'bi-x-circle', 'text' => 'Rejected']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Requests - Arteche Citizen Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/my_request.css">
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4 class="mb-0"><i class="bi bi-shield-check"></i> Arteche Portal</h4>
            <small class="text-white-50">Eastern Samar</small>
        </div>

        <div class="user-profile-sidebar">
            <div class="avatar-circle">
                <i class="bi bi-person-fill"></i>
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($citizen_name) ?></h6>
            <small class="text-white-50 opacity-75"><?= htmlspecialchars($citizen['barangay_name'] ?? 'Resident') ?></small>
            <div class="mt-2">
                <span class="badge bg-success bg-opacity-75">
                    <i class="bi bi-check-circle-fill"></i> Verified
                </span>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="citizen_dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="request_document.php">
                        <i class="bi bi-file-earmark-plus"></i> New Request
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_request.php">
                        <i class="bi bi-files"></i> My Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="citizen_notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="citizen_profile.php">
                        <i class="bi bi-person"></i> Profile Settings
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
                <div class="dropdown">
                    <button class="btn btn-link text-dark p-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="text-end d-none d-sm-block">
                            <div class="fw-semibold small"><?= htmlspecialchars($citizen_name) ?></div>
                            <div class="text-muted small">Resident</div>
                        </div>
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="citizen_profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="citizen_notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="citizen_logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
                <div>
                    <h2 class="h3 mb-1 fw-bold">
                        <i class="bi bi-files text-primary me-2"></i>My Document Requests
                    </h2>
                    <p class="text-muted mb-0">Track and manage all your document requests in one place</p>
                </div>
                <a href="request_document.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Request
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" onclick="filterByStatus('all')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Total Requests</p>
                                    <div class="stat-value"><?= number_format($stats['total']) ?></div>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-files"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" onclick="filterByStatus('pending')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Pending</p>
                                    <div class="stat-value text-warning"><?= number_format($stats['pending']) ?></div>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" onclick="filterByStatus('Ready for Pickup')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Ready for Pickup</p>
                                    <div class="stat-value text-info"><?= number_format($stats['ready']) ?></div>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" onclick="filterByStatus('Completed')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted mb-1 small">Completed</p>
                                    <div class="stat-value text-success"><?= number_format($stats['completed']) ?></div>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check2-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="Submitted" <?= $status_filter === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="Under Review" <?= $status_filter === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Ready for Pickup" <?= $status_filter === 'Ready for Pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Request #, document, purpose..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Requests List -->
            <?php if (empty($requests)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h5>No requests found</h5>
                            <p class="text-muted mb-3">
                                <?php if ($status_filter !== 'all' || $date_from || $date_to || $search): ?>
                                        No requests match your filters. Try adjusting your search criteria.
                                <?php else: ?>
                                        You haven't made any document requests yet.
                                <?php endif; ?>
                            </p>
                            <a href="request_document.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Make Your First Request
                            </a>
                        </div>
                    </div>
            <?php else: ?>
                    <div class="row">
                        <div class="col-12">
                            <?php foreach ($requests as $req):
                                $status = $statusConfig[$req['status']] ?? ['class' => 'bg-secondary', 'icon' => 'bi-question-circle', 'text' => $req['status']];
                                ?>
                                    <div class="request-card p-4">
                                        <div class="request-status-badge <?= $status['class'] ?> text-white">
                                            <i class="bi <?= $status['icon'] ?> me-1"></i>
                                            <?= $status['text'] ?>
                                        </div>
                                
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <span class="request-number">
                                                        <i class="bi bi-hash"></i> <?= htmlspecialchars($req['request_number']) ?>
                                                    </span>
                                                    <h5 class="mt-2 mb-1">
                                                        <?= htmlspecialchars($req['document_name']) ?>
                                                    </h5>
                                                    <p class="text-muted small mb-2">
                                                        <i class="bi bi-calendar3"></i> Submitted: <?= date('F j, Y', strtotime($req['submitted_at'])) ?>
                                                        <?php if ($req['status'] === 'Completed' && !empty($req['completed_at'])): ?>
                                                                • Completed: <?= date('F j, Y', strtotime($req['completed_at'])) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Purpose:</strong> <?= htmlspecialchars(substr($req['purpose'], 0, 150)) ?>
                                                        <?php if (strlen($req['purpose']) > 150): ?>...<?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="bg-light rounded p-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted small">Processing Fee:</span>
                                                        <strong>₱<?= number_format($req['fee'] ?? 0, 2) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-3">
                                                        <span class="text-muted small">Last Updated:</span>
                                                        <small><?= date('M d, Y', strtotime($req['updated_at'] ?? $req['submitted_at'])) ?></small>
                                                    </div>
                                            
                                                    <?php if ($req['status'] == 'Ready for Pickup' && !empty($req['document_path'])): ?>
                                                            <div class="d-grid gap-2">
                                                                <a href="../../public/<?= $req['document_path'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                                    <i class="bi bi-eye"></i> View Document
                                                                </a>
                                                                <a href="download_request.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-success" 
                                                                   onclick="return confirm('Downloading will mark this request as completed. Continue?')">
                                                                    <i class="bi bi-download"></i> Download & Complete
                                                                </a>
                                                            </div>
                                                    <?php elseif ($req['status'] == 'Ready for Pickup'): ?>
                                                            <div class="alert alert-info mb-0 py-2">
                                                                <i class="bi bi-info-circle"></i> Document ready for pickup at barangay hall
                                                            </div>
                                                    <?php elseif ($req['status'] == 'Rejected' && !empty($req['remarks'])): ?>
                                                            <div class="alert alert-danger mb-0 py-2">
                                                                <i class="bi bi-exclamation-triangle"></i>
                                                                <small><?= htmlspecialchars(substr($req['remarks'], 0, 100)) ?></small>
                                                            </div>
                                                    <?php elseif ($req['status'] == 'Completed'): ?>
                                                            <div class="alert alert-success mb-0 py-2 text-center">
                                                                <i class="bi bi-check-circle"></i> Request Completed
                                                            </div>
                                                    <?php else: ?>
                                                            <div class="text-center text-muted small mt-2">
                                                                <i class="bi bi-clock"></i> Processing...
                                                            </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                
                                        <!-- Status Timeline - Fixed Version -->
                                        <?php if (in_array($req['status'], ['Submitted', 'Under Review', 'Approved', 'Ready for Pickup'])): ?>
                                                <div class="timeline-container">
                                                    <div class="timeline-steps">
                                                        <?php
                                                        $steps = [
                                                            ['label' => 'Submitted', 'date' => $req['submitted_at'], 'icon' => 'bi-send'],
                                                            ['label' => 'Under Review', 'date' => null, 'icon' => 'bi-hourglass-split'],
                                                            ['label' => 'Approved', 'date' => null, 'icon' => 'bi-check-circle'],
                                                            ['label' => 'Ready', 'date' => null, 'icon' => 'bi-box-seam']
                                                        ];

                                                        $currentStep = 0;
                                                        switch ($req['status']) {
                                                            case 'Under Review':
                                                                $currentStep = 1;
                                                                break;
                                                            case 'Approved':
                                                                $currentStep = 2;
                                                                break;
                                                            case 'Ready for Pickup':
                                                                $currentStep = 3;
                                                                break;
                                                            default:
                                                                $currentStep = 0;
                                                        }

                                                        foreach ($steps as $index => $step):
                                                            $stepStatus = '';
                                                            if ($index < $currentStep) {
                                                                $stepStatus = 'completed';
                                                            } elseif ($index == $currentStep) {
                                                                $stepStatus = 'active';
                                                            }
                                                            ?>
                                                                <div class="timeline-step <?= $stepStatus ?>">
                                                                    <div class="timeline-dot">
                                                                        <i class="bi <?= $step['icon'] ?>"></i>
                                                                    </div>
                                                                    <div class="timeline-label"><?= $step['label'] ?></div>
                                                                    <?php if ($index == 0 && !empty($step['date'])): ?>
                                                                            <div class="timeline-date"><?= date('M d', strtotime($step['date'])) ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                        <?php endif; ?>
                                    </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
            
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

        // Filter by status function
        function filterByStatus(status) {
            const currentUrl = new URL(window.location.href);
            if (status === 'pending') {
                currentUrl.searchParams.set('status', 'Under Review');
            } else if (status === 'all') {
                currentUrl.searchParams.delete('status');
            } else {
                currentUrl.searchParams.set('status', status);
            }
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.top-navbar button');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('open') &&
                !sidebar.contains(event.target) &&
                event.target !== toggleBtn &&
                !toggleBtn?.contains(event.target)) {
                toggleSidebar();
            }
        });

        // Highlight active filter on stats cards
        document.addEventListener('DOMContentLoaded', function() {
            const currentStatus = '<?= $status_filter ?>';
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach(card => {
                card.classList.remove('active');
            });
            
            if (currentStatus === 'all') {
                cards[0]?.classList.add('active');
            } else if (currentStatus === 'Under Review' || currentStatus === 'Submitted') {
                cards[1]?.classList.add('active');
            } else if (currentStatus === 'Ready for Pickup') {
                cards[2]?.classList.add('active');
            } else if (currentStatus === 'Completed') {
                cards[3]?.classList.add('active');
            }
        });
    </script>
</body>

</html>