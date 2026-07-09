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
        dt.processing_fee,
        dt.processing_days,
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/citizen_my_request.css">
</head>

<body>
    <header class="cis-topbar">
        <div class="cis-shell cis-topbar-inner">
            <div>
                <div class="cis-brand">
                    <i class="bi bi-files"></i> My Requests
                </div>
                <small class="cis-subtitle">Track your barangay document requests</small>
            </div>

            <a href="request_document.php" class="cis-icon-btn" title="New Request">
                <i class="bi bi-plus-circle"></i>
            </a>
        </div>
    </header>

    <main class="cis-shell">
        <section class="cis-hero">
            <span class="cis-eyebrow">Document Tracking</span>
            <h1>My Document Requests</h1>
            <p>View request status, check pickup availability, and track your submitted documents.</p>
        </section>

        <section class="cis-stat-grid">
            <a href="my_request.php" class="cis-stat-card <?= $status_filter === 'all' ? 'active' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <div>
                        <small>Total</small>
                        <strong><?= number_format((int)($stats['total'] ?? 0)) ?></strong>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-files"></i></div>
                </div>
            </a>

            <a href="my_request.php?status=Under Review" class="cis-stat-card <?= in_array($status_filter, ['Submitted', 'Under Review']) ? 'active' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <div>
                        <small>Pending</small>
                        <strong><?= number_format((int)($stats['pending'] ?? 0)) ?></strong>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </a>

            <a href="my_request.php?status=Ready for Pickup" class="cis-stat-card <?= $status_filter === 'Ready for Pickup' ? 'active' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <div>
                        <small>Ready</small>
                        <strong><?= number_format((int)($stats['ready'] ?? 0)) ?></strong>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-box-seam"></i></div>
                </div>
            </a>

            <a href="my_request.php?status=Completed" class="cis-stat-card <?= $status_filter === 'Completed' ? 'active' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <div>
                        <small>Completed</small>
                        <strong><?= number_format((int)($stats['completed'] ?? 0)) ?></strong>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
            </a>
        </section>

        <section class="cis-card cis-card-pad" style="margin-bottom:18px;">
            <h2 class="cis-section-title">
                <i class="bi bi-funnel"></i> Filters
            </h2>

            <form method="GET" class="cis-filter-grid">
                <div class="cis-field">
                    <label class="cis-label">Status</label>
                    <select name="status" class="cis-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="Submitted" <?= $status_filter === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= $status_filter === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Ready for Pickup" <?= $status_filter === 'Ready for Pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="cis-field">
                    <label class="cis-label">From</label>
                    <input type="date" name="date_from" class="cis-input" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="cis-field">
                    <label class="cis-label">To</label>
                    <input type="date" name="date_to" class="cis-input" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="cis-field">
                    <label class="cis-label">Search</label>
                    <input type="text" name="search" class="cis-input" placeholder="Request #, document, purpose..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <button class="cis-btn cis-btn-primary" type="submit">
                    <i class="bi bi-search"></i> Apply
                </button>
            </form>
        </section>

        <section>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <h2 class="cis-section-title" style="margin:0;">
                    <i class="bi bi-list-check"></i> Request List
                </h2>

                <a href="request_document.php" class="cis-btn cis-btn-light" style="padding:10px 14px;">
                    <i class="bi bi-plus-circle"></i> New
                </a>
            </div>

            <?php if (empty($requests)): ?>
                <div class="cis-card cis-empty">
                    <i class="bi bi-inbox"></i>
                    <strong>No requests found</strong>
                    <p>
                        <?php if ($status_filter !== 'all' || $date_from || $date_to || $search): ?>
                            No requests match your filters. Try adjusting your search.
                        <?php else: ?>
                            You have not submitted any document requests yet.
                        <?php endif; ?>
                    </p>

                    <a href="request_document.php" class="cis-btn cis-btn-primary">
                        <i class="bi bi-plus-circle"></i> Make Request
                    </a>
                </div>
            <?php else: ?>
                <div class="cis-request-list">
                    <?php foreach ($requests as $req): ?>
                        <?php
                        $statusClass = [
                            'Submitted' => 'cis-badge-submitted',
                            'Under Review' => 'cis-badge-review',
                            'Approved' => 'cis-badge-approved',
                            'Ready for Pickup' => 'cis-badge-ready',
                            'Completed' => 'cis-badge-completed',
                            'Rejected' => 'cis-badge-rejected'
                        ][$req['status']] ?? 'cis-badge-submitted';

                        $statusIcon = [
                            'Submitted' => 'bi-send',
                            'Under Review' => 'bi-hourglass-split',
                            'Approved' => 'bi-check-circle',
                            'Ready for Pickup' => 'bi-box-seam',
                            'Completed' => 'bi-check2-circle',
                            'Rejected' => 'bi-x-circle'
                        ][$req['status']] ?? 'bi-question-circle';
                        ?>

                        <article class="cis-request-card">
                            <div>
                                <div class="cis-request-head">
                                    <div>
                                        <span class="cis-request-number">
                                            <i class="bi bi-hash"></i>
                                            <?= htmlspecialchars($req['request_number']) ?>
                                        </span>

                                        <div class="cis-request-title">
                                            <?= htmlspecialchars($req['document_name']) ?>
                                        </div>

                                        <div class="cis-small cis-muted">
                                            <i class="bi bi-calendar3"></i>
                                            Submitted: <?= !empty($req['submitted_at']) ? date('F j, Y', strtotime($req['submitted_at'])) : 'No date' ?>
                                        </div>
                                    </div>

                                    <span class="cis-badge <?= $statusClass ?>">
                                        <i class="bi <?= $statusIcon ?>"></i>
                                        <?= htmlspecialchars($req['status']) ?>
                                    </span>
                                </div>

                                <p class="cis-small" style="margin:12px 0 0;">
                                    <strong>Purpose:</strong>
                                    <?= htmlspecialchars(mb_strimwidth($req['purpose'] ?? '', 0, 150, '...')) ?>
                                </p>
                            </div>

                            <div class="cis-request-meta">
                                <div class="cis-meta-row">
                                    <span class="cis-muted">Processing Fee</span>
                                    <strong>₱<?= number_format((float)($req['processing_fee'] ?? 0), 2) ?></strong>
                                </div>

                                <div class="cis-meta-row">
                                    <span class="cis-muted">Last Updated</span>
                                    <span><?= date('M d, Y', strtotime($req['updated_at'] ?? $req['submitted_at'])) ?></span>
                                </div>

                                <?php if ($req['status'] === 'Ready for Pickup' && !empty($req['document_path'])): ?>
                                    <a href="../../public/<?= htmlspecialchars($req['document_path']) ?>" target="_blank" class="cis-btn cis-btn-light">
                                        <i class="bi bi-eye"></i> View
                                    </a>

                                    <a href="download_request.php?id=<?= (int)$req['id'] ?>" class="cis-btn cis-btn-primary" onclick="return confirm('Downloading will mark this request as completed. Continue?')">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                <?php elseif ($req['status'] === 'Ready for Pickup'): ?>
                                    <div class="cis-alert cis-alert-info">
                                        <i class="bi bi-info-circle"></i> Ready for pickup at barangay hall.
                                    </div>
                                <?php elseif ($req['status'] === 'Rejected' && !empty($req['remarks'])): ?>
                                    <div class="cis-alert cis-alert-danger">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <?= htmlspecialchars(mb_strimwidth($req['remarks'], 0, 100, '...')) ?>
                                    </div>
                                <?php elseif ($req['status'] === 'Completed'): ?>
                                    <div class="cis-alert cis-alert-success">
                                        <i class="bi bi-check-circle"></i> Request completed.
                                    </div>
                                <?php else: ?>
                                    <div class="cis-small cis-muted">
                                        <i class="bi bi-clock"></i> Processing...
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <nav class="cis-bottom-nav">
        <a href="citizen_dashboard.php">
            <i class="bi bi-house"></i>
            Home
        </a>

        <a href="my_request.php" class="active">
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
</body>

</html>