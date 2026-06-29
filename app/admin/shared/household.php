<?php
// app/admin/shared/households.php
require_once __DIR__ . '/../../shared/bootstrap.php';

require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/config/constants.php';
$conn = getDB();

// Authentication check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';

// Handle barangay selection
$selected_barangay_id = null;
$selected_barangay = null;
$viewing_all_barangays = false;

if (!$is_super_admin) {
    $selected_barangay_id = $admin_barangay_id;
    $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $selected_barangay = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $viewing_all_barangays = false;
} else {
    if (isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '') {
        $selected_barangay_id = intval($_GET['barangay_id']);
        $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
        $stmt->bind_param("i", $selected_barangay_id);
        $stmt->execute();
        $selected_barangay = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $viewing_all_barangays = false;
    } else {
        $viewing_all_barangays = true;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$risk_filter = isset($_GET['risk']) ? $_GET['risk'] : '';
$four_ps_filter = isset($_GET['four_ps']) ? $_GET['four_ps'] : '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if (!$viewing_all_barangays && $selected_barangay_id) {
    $where_clauses[] = "h.barangay_id = ?";
    $params[] = $selected_barangay_id;
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(h.name LIKE ? OR h.household_identifier LIKE ? OR h.contact_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($risk_filter)) {
    switch ($risk_filter) {
        case 'low':
            $where_clauses[] = "h.risk_score <= " . RISK_LOW_MAX;
            break;
        case 'medium':
            $where_clauses[] = "h.risk_score > " . RISK_LOW_MAX . " AND h.risk_score <= " . RISK_MEDIUM_MAX;
            break;
        case 'high':
            $where_clauses[] = "h.risk_score >= " . RISK_HIGH_MIN;
            break;
    }
}

if ($four_ps_filter === 'yes' || $four_ps_filter === 'no') {
    $where_clauses[] = "h.four_ps = ?";
    $params[] = $four_ps_filter === 'yes' ? 'Yes' : 'No';
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM households h $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get households
$sql = "
    SELECT h.*, b.name as barangay_name 
    FROM households h
    LEFT JOIN barangays b ON h.barangay_id = b.id
    $where_sql
    ORDER BY h.id DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$households = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get barangays for filter (super admin only)
$barangays = [];
if ($is_super_admin) {
    $barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Management | Arteche CIS</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-bg: #f3f4f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--light-bg);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        /* Modern Navbar */
        .navbar-modern {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        /* Sidebar */
        .sidebar-modern {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 1rem;
        }

        .sidebar-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-bg);
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link-modern {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #4b5563;
            text-decoration: none;
            border-radius: 1rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link-modern i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .nav-link-modern:hover {
            background: var(--light-bg);
            color: var(--primary-color);
        }

        .nav-link-modern.active {
            background: #dbeafe;
            color: var(--primary-color);
        }

        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Table */
        .table-modern {
            background: white;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .table-modern thead {
            background: var(--light-bg);
        }

        .table-modern th {
            padding: 1rem;
            font-weight: 600;
            color: #4b5563;
            border: none;
        }

        .table-modern td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .table-modern tbody tr:hover {
            background: #f9fafb;
        }

        /* Risk Badges */
        .risk-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .risk-badge.low {
            background: #d1fae5;
            color: #065f46;
        }

        .risk-badge.medium {
            background: #fed7aa;
            color: #92400e;
        }

        .risk-badge.high {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.2s;
        }

        .action-btn.view {
            background: var(--primary-color);
        }

        .action-btn.edit {
            background: var(--warning-color);
        }

        .action-btn.delete {
            background: var(--danger-color);
        }

        .action-btn.document {
            background: var(--success-color);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            color: white;
        }

        /* Pagination */
        .pagination-modern .page-link {
            border: none;
            margin: 0 0.25rem;
            border-radius: 0.5rem;
            color: #4b5563;
        }

        .pagination-modern .page-item.active .page-link {
            background: var(--primary-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar-modern {
                position: static;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar-modern">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="d-flex align-items-center gap-4">
                    <a class="navbar-brand text-white" href="dashboard.php">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        Arteche CIS
                    </a>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-white">
                        <i class="bi bi-person-circle me-2"></i>
                        <?= htmlspecialchars($username) ?>
                    </span>
                    <a href="dashboard.php" class="btn btn-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Household Management</h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-house-door me-1"></i>
                    <?= $total_records ?> total households found
                    <?php if (!$viewing_all_barangays && $selected_barangay): ?>
                        in <?= htmlspecialchars($selected_barangay['name']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="survey.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Household
            </a>
        </div>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar-modern">
                    <h5 class="sidebar-title">
                        <i class="bi bi-funnel me-2"></i>
                        Filters
                    </h5>

                    <!-- Barangay Filter (Super Admin) -->
                    <?php if ($is_super_admin): ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Barangay</label>
                            <select class="form-select" id="barangayFilter" onchange="applyFilter('barangay', this.value)">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selected_barangay_id == $b['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Risk Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Risk Level</label>
                        <select class="form-select" id="riskFilter" onchange="applyFilter('risk', this.value)">
                            <option value="">All Risk Levels</option>
                            <option value="low" <?= $risk_filter === 'low' ? 'selected' : '' ?>>Low Risk (0-30)</option>
                            <option value="medium" <?= $risk_filter === 'medium' ? 'selected' : '' ?>>Medium Risk (31-60)
                            </option>
                            <option value="high" <?= $risk_filter === 'high' ? 'selected' : '' ?>>High Risk (61-100)
                            </option>
                        </select>
                    </div>

                    <!-- 4Ps Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">4Ps Beneficiary</label>
                        <select class="form-select" id="fourPsFilter" onchange="applyFilter('four_ps', this.value)">
                            <option value="">All</option>
                            <option value="yes" <?= $four_ps_filter === 'yes' ? 'selected' : '' ?>>Yes</option>
                            <option value="no" <?= $four_ps_filter === 'no' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Search</label>
                        <form method="GET" id="searchForm">
                            <?php if ($selected_barangay_id): ?>
                                <input type="hidden" name="barangay_id" value="<?= $selected_barangay_id ?>">
                            <?php endif; ?>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Name, ID, Contact..."
                                    value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Clear Filters -->
                    <a href="households.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                        class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </a>

                    <!-- Navigation Menu -->
                    <hr class="my-4">
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="households.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern active">
                                <i class="bi bi-people"></i>
                                Households
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="document_requests.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                class="nav-link-modern">
                                <i class="bi bi-file-text"></i>
                                Document Requests
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Households Table -->
                <div class="table-modern">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Household Head</th>
                                    <th>Barangay</th>
                                    <th>Size</th>
                                    <th>Income</th>
                                    <th>4Ps</th>
                                    <th>Risk</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($households)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                            <p class="text-muted mb-0">No households found</p>
                                            <a href="survey.php<?= $selected_barangay_id ? '?barangay_id=' . $selected_barangay_id : '' ?>"
                                                class="btn btn-primary btn-sm mt-3">
                                                <i class="bi bi-plus-circle"></i> Add First Household
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($households as $hh):
                                        $risk_class = $hh['risk_score'] <= RISK_LOW_MAX ? 'low' :
                                            ($hh['risk_score'] <= RISK_MEDIUM_MAX ? 'medium' : 'high');
                                        ?>
                                        <tr>
                                            <td>
                                                <code><?= htmlspecialchars($hh['household_identifier'] ?? 'N/A') ?></code>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($hh['name']) ?></strong>
                                                <?php if ($hh['age']): ?>
                                                    <br><small class="text-muted">Age: <?= $hh['age'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($hh['barangay_name'] ?? 'N/A') ?>
                                                </span>
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
                                                <span class="risk-badge <?= $risk_class ?>">
                                                    <?= $hh['risk_score'] ?? 'N/A' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($hh['latitude']) && !empty($hh['longitude'])): ?>
                                                    <small>
                                                        <i class="bi bi-geo-alt"></i>
                                                        <?= number_format($hh['latitude'], 4) ?>,
                                                        <?= number_format($hh['longitude'], 4) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">No coordinates</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="view_household.php?id=<?= $hh['id'] ?>" class="action-btn view"
                                                        title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="survey.php?edit=<?= $hh['id'] ?><?= $selected_barangay_id ? '&barangay_id=' . $selected_barangay_id : '' ?>"
                                                        class="action-btn edit" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="request_certificate.php?household_id=<?= $hh['id'] ?>"
                                                        class="action-btn document" title="Request Document">
                                                        <i class="bi bi-file-text"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="p-3 border-top">
                            <nav>
                                <ul class="pagination pagination-modern justify-content-center mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function applyFilter(type, value) {
            const url = new URL(window.location.href);

            if (value) {
                url.searchParams.set(type, value);
            } else {
                url.searchParams.delete(type);
            }

            // Reset to first page
            url.searchParams.set('page', '1');

            window.location.href = url.toString();
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>