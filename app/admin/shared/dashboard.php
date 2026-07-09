<?php
// app/admin/shared/dashboard.php
require_once __DIR__ . '/../../shared/bootstrap.php';

$conn = getDB();

/*
|--------------------------------------------------------------------------
| 1. AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

$adminBarangayId = $_SESSION['barangay_id'] ?? null;
$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';
$fullName = $_SESSION['full_name'] ?? $username;

/*
|--------------------------------------------------------------------------
| 2. HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("SQL prepare failed: " . $conn->error);
        return null;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function fetchAllRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("SQL prepare failed: " . $conn->error);
        return [];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function getBarangayById(mysqli $conn, int $barangayId): ?array
{
    return fetchOne(
        $conn,
        "SELECT * FROM barangays WHERE id = ?",
        "i",
        [$barangayId]
    );
}

function getDashboardStats(mysqli $conn, ?int $barangayId = null): array
{
    $where = '';
    $types = '';
    $params = [];

    if ($barangayId !== null) {
        $where = "WHERE h.barangay_id = ?";
        $types = "i";
        $params[] = $barangayId;
    }

    $householdStats = fetchOne(
        $conn,
        "
        SELECT
            COUNT(*) AS total_households,
            COALESCE(AVG(income_monthly), 0) AS avg_income,
            COALESCE(SUM(CASE WHEN four_ps = 'Yes' THEN 1 ELSE 0 END), 0) AS four_ps_count,
            COALESCE(SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END), 0) AS low_risk,
            COALESCE(SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END), 0) AS medium_risk,
            COALESCE(SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END), 0) AS high_risk
        FROM households h
        $where
        ",
        $types,
        $params
    ) ?? [];

    $requestWhere = "";
    $requestTypes = "";
    $requestParams = [];

    if ($barangayId !== null) {
        $requestWhere = "AND c.barangay_id = ?";
        $requestTypes = "i";
        $requestParams[] = $barangayId;
    }

    $requestStats = fetchOne(
        $conn,
        "
        SELECT COUNT(*) AS pending_requests
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        WHERE cr.status IN ('Submitted', 'Under Review')
        $requestWhere
        ",
        $requestTypes,
        $requestParams
    ) ?? [];

    return [
        'total_households' => (int)($householdStats['total_households'] ?? 0),
        'avg_income' => round((float)($householdStats['avg_income'] ?? 0)),
        'four_ps_count' => (int)($householdStats['four_ps_count'] ?? 0),
        'pending_requests' => (int)($requestStats['pending_requests'] ?? 0),
        'low_risk' => (int)($householdStats['low_risk'] ?? 0),
        'medium_risk' => (int)($householdStats['medium_risk'] ?? 0),
        'high_risk' => (int)($householdStats['high_risk'] ?? 0),
    ];
}

function getHouseholdCountWithCoordinates(mysqli $conn, ?int $barangayId = null): int
{
    if ($barangayId !== null) {
        $row = fetchOne(
            $conn,
            "
            SELECT COUNT(*) AS total
            FROM households
            WHERE barangay_id = ?
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL
            ",
            "i",
            [$barangayId]
        );
    } else {
        $row = fetchOne(
            $conn,
            "
            SELECT COUNT(*) AS total
            FROM households
            WHERE latitude IS NOT NULL
            AND longitude IS NOT NULL
            "
        );
    }

    return (int)($row['total'] ?? 0);
}

function getHouseholdsWithCoordinates(
    mysqli $conn,
    ?int $barangayId,
    int $limit,
    int $offset,
    string $sort,
    string $order
): array {
    if ($barangayId !== null) {
        return fetchAllRows(
            $conn,
            "
            SELECT h.*, b.name AS barangay_name
            FROM households h
            LEFT JOIN barangays b ON h.barangay_id = b.id
            WHERE h.barangay_id = ?
            AND h.latitude IS NOT NULL
            AND h.longitude IS NOT NULL
            ORDER BY h.$sort $order
            LIMIT ? OFFSET ?
            ",
            "iii",
            [$barangayId, $limit, $offset]
        );
    }

    return fetchAllRows(
        $conn,
        "
        SELECT h.*, b.name AS barangay_name
        FROM households h
        LEFT JOIN barangays b ON h.barangay_id = b.id
        WHERE h.latitude IS NOT NULL
        AND h.longitude IS NOT NULL
        ORDER BY h.$sort $order
        LIMIT ? OFFSET ?
        ",
        "ii",
        [$limit, $offset]
    );
}

function getPendingRequests(mysqli $conn, ?int $barangayId = null, int $limit = 20): array
{
    if ($barangayId !== null) {
        return fetchAllRows(
            $conn,
            "
            SELECT
                cr.id,
                cr.request_number,
                cr.status,
                cr.submitted_at,
                cr.purpose,
                CONCAT(c.first_name, ' ', c.last_name) AS citizen_name,
                dt.name AS document_name
            FROM citizen_requests cr
            JOIN citizens c ON cr.citizen_id = c.id
            JOIN document_types dt ON cr.document_type_id = dt.id
            WHERE cr.status IN ('Submitted', 'Under Review')
            AND c.barangay_id = ?
            ORDER BY cr.submitted_at DESC
            LIMIT ?
            ",
            "ii",
            [$barangayId, $limit]
        );
    }

    return fetchAllRows(
        $conn,
        "
        SELECT
            cr.id,
            cr.request_number,
            cr.status,
            cr.submitted_at,
            cr.purpose,
            CONCAT(c.first_name, ' ', c.last_name) AS citizen_name,
            dt.name AS document_name,
            b.name AS barangay_name
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        JOIN document_types dt ON cr.document_type_id = dt.id
        LEFT JOIN barangays b ON c.barangay_id = b.id
        WHERE cr.status IN ('Submitted', 'Under Review')
        ORDER BY cr.submitted_at DESC
        LIMIT ?
        ",
        "i",
        [$limit]
    );
}

function getBarangaySummary(mysqli $conn, int $limit, int $offset): array
{
    return fetchAllRows(
        $conn,
        "
        SELECT
            b.id,
            b.name,
            b.latitude,
            b.longitude,
            COUNT(DISTINCT h.id) AS household_count,
            COALESCE(AVG(h.income_monthly), 0) AS avg_income,
            COALESCE(SUM(CASE WHEN h.four_ps = 'Yes' THEN 1 ELSE 0 END), 0) AS four_ps_count,
            COALESCE(AVG(h.risk_score), 0) AS avg_risk_score,
            (
                SELECT COUNT(*)
                FROM citizen_requests cr
                JOIN citizens c ON cr.citizen_id = c.id
                WHERE cr.status IN ('Submitted', 'Under Review')
                AND c.barangay_id = b.id
            ) AS pending_requests
        FROM barangays b
        LEFT JOIN households h ON b.id = h.barangay_id
        GROUP BY b.id
        ORDER BY b.name
        LIMIT ? OFFSET ?
        ",
        "ii",
        [$limit, $offset]
    );
}

function getBarangayCount(mysqli $conn): int
{
    $row = fetchOne($conn, "SELECT COUNT(*) AS total FROM barangays");
    return (int)($row['total'] ?? 0);
}

function getHouseholdSummaryForMap(mysqli $conn): array
{
    return fetchAllRows(
        $conn,
        "
        SELECT
            b.name,
            b.id,
            b.latitude AS b_lat,
            b.longitude AS b_lng,
            COUNT(h.id) AS hh_count,
            AVG(h.risk_score) AS avg_risk,
            AVG(h.income_monthly) AS avg_income
        FROM barangays b
        LEFT JOIN households h
            ON b.id = h.barangay_id
            AND h.latitude IS NOT NULL
            AND h.longitude IS NOT NULL
        GROUP BY b.id
        HAVING hh_count > 0
        ORDER BY b.name
        "
    );
}

/*
|--------------------------------------------------------------------------
| 3. ROLE SCOPE
|--------------------------------------------------------------------------
*/

$selected_barangay_id = null;
$selected_barangay = null;
$viewing_all_barangays = false;

if ($isSuperAdmin) {
    $barangays = fetchAllRows($conn, "SELECT * FROM barangays ORDER BY name");

    $requestedBarangayId = isset($_GET['barangay_id']) && $_GET['barangay_id'] !== ''
        ? (int)$_GET['barangay_id']
        : null;

    if ($requestedBarangayId) {
        $selected_barangay = getBarangayById($conn, $requestedBarangayId);

        if ($selected_barangay) {
            $selected_barangay_id = $requestedBarangayId;
        }
    }

    $viewing_all_barangays = empty($selected_barangay_id);
} else {
    if (empty($adminBarangayId)) {
        session_destroy();
        header("Location: ../admin_login.php?error=no_barangay_assigned");
        exit;
    }

    $selected_barangay_id = (int)$adminBarangayId;
    $selected_barangay = getBarangayById($conn, $selected_barangay_id);
    $barangays = $selected_barangay ? [$selected_barangay] : [];
    $viewing_all_barangays = false;

    if (isset($_GET['barangay_id']) && (int)$_GET['barangay_id'] !== $selected_barangay_id) {
        error_log("SECURITY: Barangay admin {$username} attempted to access barangay_id=" . $_GET['barangay_id']);
        header("Location: dashboard.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| 4. REQUEST INPUT
|--------------------------------------------------------------------------
*/

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'DESC');

$allowedLimits = [10, 25, 50, 100];
$allowedSorts = ['id', 'name', 'household_size', 'income_monthly', 'risk_score', 'survey_date'];
$allowedOrders = ['ASC', 'DESC'];

$limit = in_array($limit, $allowedLimits, true) ? $limit : 25;
$sort = in_array($sort, $allowedSorts, true) ? $sort : 'id';
$order = in_array($order, $allowedOrders, true) ? $order : 'DESC';

$offset = ($page - 1) * $limit;

$barangay_page = isset($_GET['barangay_page']) ? max(1, (int)$_GET['barangay_page']) : 1;
$barangay_limit = isset($_GET['barangay_limit']) ? (int)$_GET['barangay_limit'] : 10;

$allowedBarangayLimits = [5, 10, 20, 50];
$barangay_limit = in_array($barangay_limit, $allowedBarangayLimits, true) ? $barangay_limit : 10;
$barangay_offset = ($barangay_page - 1) * $barangay_limit;

/*
|--------------------------------------------------------------------------
| 5. DATA FETCHING
|--------------------------------------------------------------------------
*/

$scopeBarangayId = $viewing_all_barangays ? null : $selected_barangay_id;

$stats = getDashboardStats($conn, $scopeBarangayId);

$total_households = getHouseholdCountWithCoordinates($conn, $scopeBarangayId);

$households = getHouseholdsWithCoordinates(
    $conn,
    $scopeBarangayId,
    $limit,
    $offset,
    $sort,
    $order
);

$pendingLimit = $viewing_all_barangays ? 20 : 10;
$pending_requests = getPendingRequests($conn, $scopeBarangayId, $pendingLimit);

$total_barangays = 0;
$barangay_summary = [];
$household_summary = [];

if ($isSuperAdmin && $viewing_all_barangays) {
    $total_barangays = getBarangayCount($conn);
    $barangay_summary = getBarangaySummary($conn, $barangay_limit, $barangay_offset);
    $household_summary = getHouseholdSummaryForMap($conn);
}

/*
|--------------------------------------------------------------------------
| 6. COMPUTED VALUES
|--------------------------------------------------------------------------
*/

$total_pages = (int)ceil($total_households / $limit);
$current_page = $page;
$showing_from = $total_households > 0 ? ($offset + 1) : 0;
$showing_to = min($offset + $limit, $total_households);

$total_barangay_pages = (int)ceil($total_barangays / $barangay_limit);
$barangay_showing_from = $total_barangays > 0 ? ($barangay_offset + 1) : 0;
$barangay_showing_to = min($barangay_offset + $barangay_limit, $total_barangays);

$total_risk = $stats['low_risk'] + $stats['medium_risk'] + $stats['high_risk'];

$low_percent = $total_risk > 0 ? round(($stats['low_risk'] / $total_risk) * 100) : 0;
$medium_percent = $total_risk > 0 ? round(($stats['medium_risk'] / $total_risk) * 100) : 0;
$high_percent = $total_risk > 0 ? round(($stats['high_risk'] / $total_risk) * 100) : 0;

$avg_income_formatted = '₱' . number_format($stats['avg_income'], 0);

/*
|--------------------------------------------------------------------------
| 7. UI COMPATIBILITY VARIABLES
|--------------------------------------------------------------------------
| These keep your existing HTML working without rewriting the UI yet.
|--------------------------------------------------------------------------
*/

$is_super_admin = $isSuperAdmin;
$admin_barangay_id = $adminBarangayId;
$full_name = $fullName;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php if ($viewing_all_barangays): ?>
            Municipality Dashboard
        <?php elseif ($selected_barangay): ?>
            <?= htmlspecialchars($selected_barangay['name']) ?> Dashboard
        <?php else: ?>
            Dashboard
        <?php endif; ?> | Arteche CIS
    </title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
</head>

<body>
    <!-- Modern Navigation -->
    <nav class="navbar-modern">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="d-flex align-items-center gap-4">
                    <a class="navbar-brand text-white" href="dashboard.php">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        Arteche CIS
                    </a>

                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-modern mb-0">
                            <li class="breadcrumb-item">
                                <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
                            </li>
                            <?php if ($viewing_all_barangays): ?>
                                <li class="breadcrumb-item active">All Barangays</li>
                            <?php elseif ($selected_barangay): ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($selected_barangay['name']) ?></li>
                            <?php endif; ?>
                        </ol>
                    </nav>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-link text-white p-0 position-relative" type="button"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ($stats['pending_requests'] > 0): ?>
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $stats['pending_requests'] ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-link text-white text-decoration-none d-flex align-items-center gap-2"
                            type="button" data-bs-toggle="dropdown">
                            <div class="text-end">
                                <div class="fw-semibold"><?= htmlspecialchars($full_name) ?></div>
                                <small class="opacity-75">
                                    <?php if ($is_super_admin): ?>
                                        Super Administrator
                                    <?php else: ?>
                                        <?= htmlspecialchars($selected_barangay['name'] ?? 'Barangay Admin') ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <i class="bi bi-person-circle fs-3"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="../../shared/logout.php"><i
                                        class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <?php if ($viewing_all_barangays): ?>
                        Municipality Overview
                    <?php elseif ($selected_barangay): ?>
                        Barangay <?= htmlspecialchars($selected_barangay['name']) ?>
                    <?php endif; ?>
                </h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-calendar3 me-1"></i>
                    Last updated: <?= date('F d, Y h:i A') ?>
                </p>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <a href="../super_admin/reports.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                    class="btn btn-primary">
                    <i class="bi bi-file-earmark-text"></i> Generate Report
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar-modern">
                    <h5 class="sidebar-title">
                        <i class="bi bi-sliders2 me-2"></i>
                        Controls & Filters
                    </h5>

                    <!-- Barangay Selector -->
                    <?php if ($is_super_admin): ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Barangay</label>
                            <select class="form-select form-select-lg" id="barangaySelect"
                                onchange="changeBarangay(this.value)">
                                <option value="" <?= empty($selected_barangay_id) ? 'selected' : '' ?>>
                                    🌐 All Barangays
                                </option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= ($selected_barangay_id == $b['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                            <i class="bi bi-info-circle-fill fs-4"></i>
                            <div>
                                <strong><?= htmlspecialchars($selected_barangay['name'] ?? 'N/A') ?></strong><br>
                                <small>You are viewing your assigned barangay</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Navigation Menu -->
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link-modern active">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="document_requests.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-file-text"></i>
                                Document Requests
                                <?php if ($stats['pending_requests'] > 0): ?>
                                    <span class="badge-pill"><?= $stats['pending_requests'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="survey.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-house-add"></i>
                                Add Household
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="household.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-people"></i>
                                View Households
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="analytics.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-graph-up"></i>
                                Reports & Analytics
                            </a>
                        </li>
                        <?php if ($is_super_admin): ?>
                            <li class="nav-item">
                                <a href="../super_admin/manage_barangays.php" class="nav-link-modern">
                                    <i class="bi bi-building"></i>
                                    Manage Barangays
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../super_admin/users.php" class="nav-link-modern">
                                    <i class="bi bi-people-fill"></i>
                                    Manage Users
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Risk Legend -->
                    <div class="mt-4">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-palette me-2"></i>
                            Risk Legend
                        </h6>
                        <div class="risk-item">
                            <div class="risk-color low"></div>
                            <span class="risk-label">Low Risk</span>
                            <span class="risk-value">0-30</span>
                        </div>
                        <div class="risk-item">
                            <div class="risk-color medium"></div>
                            <span class="risk-label">Medium Risk</span>
                            <span class="risk-value">31-60</span>
                        </div>
                        <div class="risk-item">
                            <div class="risk-color high"></div>
                            <span class="risk-label">High Risk</span>
                            <span class="risk-value">61-100</span>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            System Info
                        </h6>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Barangays:</span>
                                <span class="fw-semibold"><?= count($barangays) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Database Size:</span>
                                <span class="fw-semibold"><?= round($stats['total_households'] * 2.5) ?> KB</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Last Backup:</span>
                                <span class="fw-semibold"><?= date('M d') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="bi bi-house"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['total_households']) ?></div>
                        <div class="stat-label">Total Households</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="stat-value"><?= $avg_income_formatted ?></div>
                        <div class="stat-label">Average Income</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['four_ps_count']) ?></div>
                        <div class="stat-label">4Ps Beneficiaries</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['pending_requests']) ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>

                <!-- Risk Distribution Chart -->
                <div class="risk-distribution">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-pie-chart me-2"></i>
                            Risk Distribution
                        </h5>
                        <span class="badge bg-light text-dark">
                            Total: <?= number_format($total_risk) ?> households
                        </span>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="risk-item">
                                <div class="risk-color low"></div>
                                <span class="risk-label">Low Risk (0-30)</span>
                                <span class="risk-value"><?= number_format($stats['low_risk']) ?></span>
                                <span class="risk-percent"><?= $low_percent ?>%</span>
                            </div>
                            <div class="progress-risk mb-3">
                                <div class="progress-bar-risk bg-success" style="width: <?= $low_percent ?>%"></div>
                            </div>

                            <div class="risk-item">
                                <div class="risk-color medium"></div>
                                <span class="risk-label">Medium Risk (31-60)</span>
                                <span class="risk-value"><?= number_format($stats['medium_risk']) ?></span>
                                <span class="risk-percent"><?= $medium_percent ?>%</span>
                            </div>
                            <div class="progress-risk mb-3">
                                <div class="progress-bar-risk bg-warning" style="width: <?= $medium_percent ?>%"></div>
                            </div>

                            <div class="risk-item">
                                <div class="risk-color high"></div>
                                <span class="risk-label">High Risk (61-100)</span>
                                <span class="risk-value"><?= number_format($stats['high_risk']) ?></span>
                                <span class="risk-percent"><?= $high_percent ?>%</span>
                            </div>
                            <div class="progress-risk">
                                <div class="progress-bar-risk bg-danger" style="width: <?= $high_percent ?>%"></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <canvas id="riskChart" style="max-height: 200px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Map Section -->
                <?php if (!$viewing_all_barangays && $selected_barangay && !empty($selected_barangay['latitude'])): ?>
                    <div class="map-container">
                        <div id="map"></div>
                        <div class="map-controls">
                            <button class="map-control-btn" onclick="toggleHeatmap()" title="Toggle Heatmap"
                                id="heatmapBtn">
                                <i class="bi bi-fire"></i>
                            </button>
                            <button class="map-control-btn" onclick="toggleMarkers()" title="Toggle Markers"
                                id="markersBtn">
                                <i class="bi bi-pin-map"></i>
                            </button>
                            <button class="map-control-btn" onclick="focusOnBarangay()" title="Focus on Barangay">
                                <i class="bi bi-zoom-in"></i>
                            </button>
                            <button class="map-control-btn" onclick="resetMap()" title="Reset View">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>
                <?php elseif ($viewing_all_barangays): ?>
                    <div class="map-container">
                        <div id="mini-map" style="height: 300px;"></div>
                        <div class="map-controls">
                            <button class="map-control-btn" onclick="toggleBarangayMarkers()"
                                title="Toggle Barangay Markers" id="barangayMarkersBtn">
                                <i class="bi bi-pin-map"></i>
                            </button>
                            <button class="map-control-btn" onclick="fitAllBarangays()" title="Fit All Barangays">
                                <i class="bi bi-zoom-in"></i>
                            </button>
                            <button class="map-control-btn" onclick="resetMiniMap()" title="Reset View">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Barangay Summary Table (Super Admin) -->
                <?php if ($is_super_admin && $viewing_all_barangays && !empty($barangay_summary)): ?>
                    <div class="table-modern mt-4">
                        <div class="p-3 border-bottom bg-light">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-building me-2"></i>
                                Barangay Summary
                            </h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Barangay</th>
                                        <th>Households</th>
                                        <th>Avg Income</th>
                                        <th>4Ps</th>
                                        <th>Avg Risk</th>
                                        <th>Pending</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($barangay_summary as $summary): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($summary['name']) ?></strong>
                                            </td>
                                            <td><?= number_format($summary['household_count'] ?: 0) ?></td>
                                            <td>₱<?= number_format($summary['avg_income'] ?: 0, 0) ?></td>
                                            <td><?= number_format($summary['four_ps_count'] ?: 0) ?></td>
                                            <td>
                                                <?php
                                                $avg_risk = round($summary['avg_risk_score'] ?: 0);
                                                $risk_class = $avg_risk <= 30 ? 'success' : ($avg_risk <= 60 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?= $risk_class ?>"><?= $avg_risk ?></span>
                                            </td>
                                            <td>
                                                <?php if ($summary['pending_requests'] > 0): ?>
                                                    <span class="badge bg-danger"><?= $summary['pending_requests'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?barangay_id=<?= $summary['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Barangay Pagination -->
                        <?php if ($total_barangay_pages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-3 px-3 pb-3">
                                <div class="text-muted small">
                                    Showing <?= $barangay_showing_from ?> to <?= $barangay_showing_to ?> of
                                    <?= $total_barangays ?> barangays
                                </div>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= $barangay_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link"
                                                href="?barangay_page=1&barangay_limit=<?= $barangay_limit ?>">First</a>
                                        </li>
                                        <li class="page-item <?= $barangay_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link"
                                                href="?barangay_page=<?= $barangay_page - 1 ?>&barangay_limit=<?= $barangay_limit ?>">Prev</a>
                                        </li>
                                        <?php
                                        $start_page = max(1, $barangay_page - 2);
                                        $end_page = min($total_barangay_pages, $barangay_page + 2);
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?= $i == $barangay_page ? 'active' : '' ?>">
                                                <a class="page-link"
                                                    href="?barangay_page=<?= $i ?>&barangay_limit=<?= $barangay_limit ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $barangay_page >= $total_barangay_pages ? 'disabled' : '' ?>">
                                            <a class="page-link"
                                                href="?barangay_page=<?= $barangay_page + 1 ?>&barangay_limit=<?= $barangay_limit ?>">Next</a>
                                        </li>
                                        <li class="page-item <?= $barangay_page >= $total_barangay_pages ? 'disabled' : '' ?>">
                                            <a class="page-link"
                                                href="?barangay_page=<?= $total_barangay_pages ?>&barangay_limit=<?= $barangay_limit ?>">Last</a>
                                        </li>
                                    </ul>
                                </nav>
                                <div>
                                    <select class="form-select form-select-sm"
                                        onchange="location.href='?barangay_page=1&barangay_limit=' + this.value">
                                        <option value="5" <?= $barangay_limit == 5 ? 'selected' : '' ?>>5 per page</option>
                                        <option value="10" <?= $barangay_limit == 10 ? 'selected' : '' ?>>10 per page</option>
                                        <option value="20" <?= $barangay_limit == 20 ? 'selected' : '' ?>>20 per page</option>
                                        <option value="50" <?= $barangay_limit == 50 ? 'selected' : '' ?>>50 per page</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <!-- Pending Requests -->
                <?php if (!empty($pending_requests)): ?>
                    <div class="table-modern mt-4">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Pending Document Requests
                            </h5>
                            <a href="document_requests.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Citizen</th>
                                        <?php if ($viewing_all_barangays): ?>
                                            <th>Barangay</th>
                                        <?php endif; ?>
                                        <th>Document</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $req): ?>
                                        <tr>
                                            <td>
                                                <code><?= htmlspecialchars($req['request_number'] ?? 'N/A') ?></code>
                                            </td>
                                            <td><?= htmlspecialchars($req['citizen_name'] ?? 'N/A') ?></td>
                                            <?php if ($viewing_all_barangays): ?>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($req['barangay_name'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($req['document_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($req['status']) {
                                                    case 'Submitted':
                                                        $status_class = 'submitted';
                                                        break;
                                                    case 'Under Review':
                                                        $status_class = 'review';
                                                        break;
                                                    case 'Approved':
                                                        $status_class = 'approved';
                                                        break;
                                                    case 'Ready for Pickup':
                                                        $status_class = 'ready';
                                                        break;
                                                    case 'Rejected':
                                                        $status_class = 'rejected';
                                                        break;
                                                    default:
                                                        $status_class = 'secondary';
                                                }
                                                ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= $req['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($req['submitted_at'])) ?></td>
                                            <td>
                                                <a href="view_request.php?id=<?= $req['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Households Table -->
                <?php if (!empty($households)): ?>
                    <div class="table-modern mt-4">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-house-door me-2"></i>
                                Recent Households
                                <?php if (!$viewing_all_barangays && $selected_barangay): ?>
                                    <small class="text-muted ms-2">(<?= count($households) ?> with coordinates)</small>
                                <?php endif; ?>
                            </h5>
                            <a href="households.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <?php if ($viewing_all_barangays): ?>
                                            <th>Barangay</th>
                                        <?php endif; ?>
                                        <th>Household Head</th>
                                        <th>Size</th>
                                        <th>Income</th>
                                        <th>4Ps</th>
                                        <th>Risk</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($households, 0, 10) as $hh):
                                        $risk_class = $hh['risk_score'] <= 30 ? 'success' : ($hh['risk_score'] <= 60 ? 'warning' : 'danger');
                                    ?>
                                        <tr>
                                            <?php if ($viewing_all_barangays): ?>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($hh['barangay_name'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <strong><?= htmlspecialchars($hh['name'] ?? 'N/A') ?></strong>
                                            </td>
                                            <td><?= $hh['household_size'] ?? 'N/A' ?></td>
                                            <td>₱<?= number_format($hh['income_monthly'] ?? 0, 0) ?></td>
                                            <td>
                                                <?php if (($hh['four_ps'] ?? 'No') == 'Yes'): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $risk_class ?>">
                                                    <?= $hh['risk_score'] ?? 'N/A' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= $hh['latitude'] ? number_format($hh['latitude'], 4) : 'N/A' ?>,
                                                    <?= $hh['longitude'] ? number_format($hh['longitude'], 4) : 'N/A' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_household.php?id=<?= $hh['id'] ?>"
                                                        class="btn btn-outline-primary" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="request_certificate.php?household_id=<?= $hh['id'] ?>"
                                                        class="btn btn-outline-success" title="Request Document">
                                                        <i class="bi bi-file-text"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <script>
        // ============================================
        // Risk Chart Initialization
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Risk Distribution Chart
            const ctx = document.getElementById('riskChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Low Risk', 'Medium Risk', 'High Risk'],
                        datasets: [{
                            data: [<?= $stats['low_risk'] ?>, <?= $stats['medium_risk'] ?>, <?= $stats['high_risk'] ?>],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        maintainAspectRatio: false
                    }
                });
            }

            // ============================================
            // Map Variables
            // ============================================
            let map;
            let markersLayer;
            let heatLayer;
            let heatmapVisible = false;
            let markersVisible = true;

            // ============================================
            // Initialize Main Map (for specific barangay)
            // ============================================
            <?php if (!$viewing_all_barangays && $selected_barangay && !empty($selected_barangay['latitude'])): ?>

                if (document.getElementById('map')) {
                    initMainMap();
                }

                function initMainMap() {
                    try {
                        const centerLat = <?= json_encode($selected_barangay['latitude'] ?? 12.266) ?>;
                        const centerLon = <?= json_encode($selected_barangay['longitude'] ?? 125.369) ?>;
                        const barangayName = "<?= addslashes($selected_barangay['name'] ?? '') ?>";

                        // Initialize map
                        map = L.map('map').setView([centerLat, centerLon], 14);

                        // Base layers
                        const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        });

                        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            attribution: '&copy; Esri'
                        });

                        // Add default layer
                        satelliteLayer.addTo(map);

                        // Add layer control
                        const baseMaps = {
                            "Street": streetLayer,
                            "Satellite": satelliteLayer
                        };

                        L.control.layers(baseMaps).addTo(map);

                        // Add barangay center marker
                        L.marker([centerLat, centerLon], {
                            icon: L.divIcon({
                                className: 'barangay-center',
                                html: '<i class="bi bi-building" style="font-size: 24px; color: #2563eb;"></i>',
                                iconSize: [24, 24],
                                iconAnchor: [12, 24]
                            })
                        }).addTo(map).bindPopup("<b>Barangay " + barangayName + "</b>");

                        // Initialize layers
                        markersLayer = L.layerGroup().addTo(map);
                        const heatData = [];

                        // Safe household data
                        const householdsData = <?= json_encode(array_filter($households ?? [], function ($h) {
                                                    return !empty($h['latitude']) && !empty($h['longitude']);
                                                })) ?> || [];

                        // Add household markers
                        householdsData.forEach(function(hh) {
                            const score = hh.risk_score || 0;
                            const color = score <= 30 ? '#10b981' : (score <= 60 ? '#f59e0b' : '#ef4444');

                            // Add to heatmap
                            heatData.push([parseFloat(hh.latitude), parseFloat(hh.longitude), score / 100]);

                            // Create marker
                            const marker = L.marker([parseFloat(hh.latitude), parseFloat(hh.longitude)], {
                                icon: L.divIcon({
                                    className: 'custom-marker',
                                    html: `<div style="background-color: ${color}; width: 16px; height: 16px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                                    iconSize: [20, 20],
                                    iconAnchor: [10, 10]
                                })
                            });

                            marker.bindPopup(`
                                <div style="min-width: 200px;">
                                    <h6 style="margin-bottom: 8px;"><strong>${hh.name || 'N/A'}</strong></h6>
                                    <table style="width: 100%; font-size: 12px;">
                                        <tr><td>Household Size:</td><td>${hh.household_size || 'N/A'}</td></tr>
                                        <tr><td>Monthly Income:</td><td>₱${hh.income_monthly ? Number(hh.income_monthly).toLocaleString() : '0'}</td></tr>
                                        <tr><td>Risk Score:</td><td><span style="background: ${color}; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${score}</span></td></tr>
                                    </table>
                                    <hr style="margin: 8px 0;">
                                    <div style="display: flex; gap: 8px;">
                                        <a href="view_household.php?id=${hh.id || ''}" style="text-decoration: none; background: #2563eb; color: white; padding: 4px 12px; border-radius: 6px; font-size: 12px;">View</a>
                                        <a href="request_certificate.php?household_id=${hh.id || ''}" style="text-decoration: none; background: #10b981; color: white; padding: 4px 12px; border-radius: 6px; font-size: 12px;">Request</a>
                                    </div>
                                </div>
                            `);

                            markersLayer.addLayer(marker);
                        });

                        // Create heat layer
                        if (heatData.length > 0) {
                            heatLayer = L.heatLayer(heatData, {
                                radius: 25,
                                blur: 15,
                                maxZoom: 17,
                                gradient: {
                                    0.0: '#10b981',
                                    0.3: '#f59e0b',
                                    0.6: '#ef4444'
                                }
                            });
                        }
                    } catch (error) {
                        console.error("Main map error:", error);
                    }
                }
            <?php endif; ?>

            // ============================================
            // Initialize mini map (always initialize if element exists)
            // ============================================
            if (document.getElementById('mini-map')) {
                initMiniMap();
            }

            function initMiniMap() {
                try {
                    const miniMap = L.map('mini-map').setView([12.2660, 125.3690], 11);
                    const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap'
                    });
                    tileLayer.addTo(miniMap);

                    // Barangay centers layer
                    const barangayMarkers = L.layerGroup();
                    const householdLayer = L.layerGroup();

                    <?php if ($is_super_admin && $viewing_all_barangays): ?>
                        <?php foreach ($barangay_summary as $b):
                            if (!empty($b['latitude']) && !empty($b['longitude'])):
                                $avg_risk = round($b['avg_risk_score'] ?? 0);
                                $color = $avg_risk <= 30 ? '#10b981' : ($avg_risk <= 60 ? '#f59e0b' : '#ef4444');
                        ?>
                                const riskLevel<?= $b['id'] ?> = '<?= $avg_risk <= 30 ? 'low' : ($avg_risk <= 60 ? 'medium' : 'high') ?>';
                                const markerSize<?= $b['id'] ?> = Math.max(20, Math.min(40, (<?= $b['household_count'] ?: 0 ?> / 10) + 20));

                                L.marker([<?= $b['latitude'] ?>, <?= $b['longitude'] ?>], {
                                    icon: L.divIcon({
                                        className: `barangay-marker risk-${riskLevel<?= $b['id'] ?>}`,
                                        html: `<i class="bi bi-building" style="font-size: ${markerSize<?= $b['id'] ?>}px; color: <?= $color ?>; text-shadow: 0 0 5px <?= $color ?>;"></i>`,
                                        iconSize: [markerSize<?= $b['id'] ?>, markerSize<?= $b['id'] ?>],
                                        iconAnchor: [markerSize<?= $b['id'] ?> / 2, markerSize<?= $b['id'] ?>]
                                    })
                                }).addTo(barangayMarkers).bindPopup(`
                                                <div style="min-width: 220px;">
                                                    <h6><i class="bi bi-building text-primary me-2"></i><strong><?= addslashes($b['name']) ?></strong></h6>
                                                    <p><strong>Households:</strong> <?= $b['household_count'] ?: 0 ?></p>
                                                    <p><strong>Avg Risk:</strong> <span class="badge bg-${riskLevel<?= $b['id'] ?>}"><?= $avg_risk ?>%</span></p>
                                                    <p><small class="text-muted">Lat: <?= number_format($b['latitude'], 6) ?>, Lng: <?= number_format($b['longitude'], 6) ?></small></p>
                                                    <hr>
                                                    <a href="?barangay_id=<?= $b['id'] ?>" class="btn btn-sm btn-primary w-100">View Dashboard →</a>
                                                </div>
                                            `);
                        <?php endif;
                        endforeach; ?>
                    <?php endif; ?>

                    // Add layers to map
                    barangayMarkers.addTo(miniMap);
                    householdLayer.addTo(miniMap);

                    // Store in window object for toolbar functions
                    window.miniMap = miniMap;
                    window.barangayMarkers = barangayMarkers;
                    window.householdLayer = householdLayer;

                } catch (error) {
                    console.error("Mini map error:", error);
                }
            }

            // ============================================
            // Toolbar Functions
            // ============================================
            window.toggleHeatmap = function() {
                if (typeof map === 'undefined' || !map || !heatLayer) return;

                if (map.hasLayer(heatLayer)) {
                    map.removeLayer(heatLayer);
                    document.getElementById('heatmapBtn')?.classList.remove('active');
                } else {
                    map.addLayer(heatLayer);
                    document.getElementById('heatmapBtn')?.classList.add('active');
                }
            };

            window.toggleMarkers = function() {
                if (typeof map === 'undefined' || !map || !markersLayer) return;

                if (map.hasLayer(markersLayer)) {
                    map.removeLayer(markersLayer);
                    document.getElementById('markersBtn')?.classList.remove('active');
                } else {
                    map.addLayer(markersLayer);
                    document.getElementById('markersBtn')?.classList.add('active');
                }
            };

            window.toggleBarangayMarkers = function() {
                if (!window.miniMap || !window.barangayMarkers) return;

                if (window.miniMap.hasLayer(window.barangayMarkers)) {
                    window.miniMap.removeLayer(window.barangayMarkers);
                    document.getElementById('barangayMarkersBtn')?.classList.remove('active');
                } else {
                    window.miniMap.addLayer(window.barangayMarkers);
                    document.getElementById('barangayMarkersBtn')?.classList.add('active');
                }
            };

            window.fitAllBarangays = function() {
                if (!window.miniMap) return;

                const group = new L.featureGroup([window.barangayMarkers, window.householdLayer]);
                if (group.getLayers().length > 0) {
                    window.miniMap.fitBounds(group.getBounds().pad(0.2));
                }
            };

            window.resetMiniMap = function() {
                if (window.miniMap) {
                    window.miniMap.setView([12.2660, 125.3690], 11);
                }
            };

            window.focusOnBarangay = function() {
                if (!map) return;
                <?php if ($selected_barangay && isset($selected_barangay['latitude']) && isset($selected_barangay['longitude'])): ?>
                    const lat = <?= $selected_barangay['latitude'] ?>;
                    const lng = <?= $selected_barangay['longitude'] ?>;
                    if (lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                        map.setView([lat, lng], 16, {
                            animate: true
                        });
                    }
                <?php endif; ?>
            };

            window.resetMap = function() {
                if (!map) return;

                const lat = <?= json_encode($selected_barangay['latitude'] ?? null) ?>;
                const lng = <?= json_encode($selected_barangay['longitude'] ?? null) ?>;

                if (lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 14, {
                        animate: true
                    });
                } else {
                    map.setView([12.266, 125.369], 13);
                }
            };

            window.changeBarangay = function(barangayId) {
                <?php if ($is_super_admin): ?>
                    if (!barangayId) {
                        window.location.href = "dashboard.php";
                    } else {
                        window.location.href = "dashboard.php?barangay_id=" + barangayId;
                    }
                <?php else: ?>
                    alert("You can only view data for your assigned barangay.");
                <?php endif; ?>
            };

            // Auto Refresh
            setTimeout(function() {
                window.location.reload();
            }, 300000);
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>