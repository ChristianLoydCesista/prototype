<?php
// app/admin/shared/dashboard.php
require_once __DIR__ . '/../../shared/bootstrap.php';

// database connection available from bootstrap
$conn = getDB();

// Authentication check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

// Get session variables from login
$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';
$full_name = $_SESSION['full_name'] ?? $username;

// ============================================
// SECURITY: RBAC Logic
// ============================================

if (!$is_super_admin) {
    // BARANGAY ADMIN LOGIC
    $selected_barangay_id = $admin_barangay_id;

    if ($selected_barangay_id) {
        $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
        $stmt->bind_param("i", $selected_barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_barangay = $result->fetch_assoc();
        $stmt->close();
    } else {
        session_destroy();
        header("Location: ../admin_login.php?error=no_barangay_assigned");
        exit;
    }

    $viewing_all_barangays = false;

    // Get only their barangay
    $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $barangays = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Security check
    if (isset($_GET['barangay_id']) && $_GET['barangay_id'] != $selected_barangay_id) {
        error_log("SECURITY: Barangay admin " . $username . " attempted to access barangay_id=" . $_GET['barangay_id']);
        header("Location: dashboard.php");
        exit;
    }
} else {
    // SUPER ADMIN LOGIC
    $barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

    if (isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '') {
        $selected_barangay_id = intval($_GET['barangay_id']);
        $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
        $stmt->bind_param("i", $selected_barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_barangay = $result->fetch_assoc();
        $stmt->close();

        if (!$selected_barangay) {
            $selected_barangay_id = null;
            $selected_barangay = null;
        }
    } else {
        $selected_barangay_id = null;
        $selected_barangay = null;
    }

    $viewing_all_barangays = empty($selected_barangay_id);
}

// ============================================
// ERROR HANDLING & DATA FETCHING
// ============================================

// Initialize default values
$stats = [
    'total_households' => 0,
    'avg_income' => 0,
    'four_ps_count' => 0,
    'pending_requests' => 0,
    'low_risk' => 0,
    'medium_risk' => 0,
    'high_risk' => 0
];

$households = [];
$pending_requests = [];
$barangay_summary = [];

// ============================================
// PAGINATION SETUP
// ============================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate and sanitize pagination values
$limit = in_array($limit, [10, 25, 50, 100]) ? $limit : 25;
$offset = ($page - 1) * $limit;

// Allowed sort columns
$allowed_sorts = ['id', 'name', 'household_size', 'income_monthly', 'risk_score', 'survey_date'];
if (!in_array($sort, $allowed_sorts))
    $sort = 'id';
$order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';

// Build search condition
$search_condition = '';
$search_params = [];
if (!empty($search)) {
    $search_condition = " AND (h.name LIKE ? OR h.household_identifier LIKE ? OR h.contact_number LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%"];
}

// ============================================
// BARANGAY PAGINATION SETUP (for super admin)
// ============================================
$barangay_page = isset($_GET['barangay_page']) ? max(1, intval($_GET['barangay_page'])) : 1;
$barangay_limit = isset($_GET['barangay_limit']) ? intval($_GET['barangay_limit']) : 10;
$barangay_limit = in_array($barangay_limit, [5, 10, 20, 50]) ? $barangay_limit : 10;
$barangay_offset = ($barangay_page - 1) * $barangay_limit;

// Helper function for safe queries
function safeQuery($conn, $sql, $params = null, $types = null)
{
    try {
        if ($params) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                return $result;
            }
        } else {
            return $conn->query($sql);
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
    return null;
}

// Get statistics with error handling
if ($viewing_all_barangays) {
    // SUPER ADMIN: View ALL barangays statistics
    $result = safeQuery($conn, "SELECT COUNT(*) as cnt FROM households");
    $stats['total_households'] = ($result && $row = $result->fetch_assoc()) ? $row['cnt'] : 0;

    $result = safeQuery($conn, "SELECT AVG(income_monthly) as avg_inc FROM households");
    $stats['avg_income'] = ($result && $row = $result->fetch_assoc()) ? round($row['avg_inc'] ?? 0) : 0;

    $result = safeQuery($conn, "SELECT COUNT(*) as cnt FROM households WHERE four_ps='Yes'");
    $stats['four_ps_count'] = ($result && $row = $result->fetch_assoc()) ? $row['cnt'] : 0;

    // Get pending document requests
    $result = safeQuery($conn, "
        SELECT COUNT(*) as cnt 
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        WHERE cr.status IN ('Submitted', 'Under Review')
    ");
    $stats['pending_requests'] = ($result && $row = $result->fetch_assoc()) ? $row['cnt'] : 0;

    // Handle status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $action = $_POST['action'];
        if ($action === 'update_status') {
            $request_id = intval($_POST['request_id']);
            $new_status = $_POST['new_status'];
            $stmt = $conn->prepare("UPDATE citizen_requests SET status = ? WHERE id = ? AND (barangay_id = ? OR ?)");
            $stmt->bind_param("siii", $new_status, $request_id, $admin_barangay_id, $is_super_admin);
            $stmt->execute();
        } elseif ($action === 'bulk_update') {
            $ids = json_decode($_POST['ids'], true);
            $new_status = $_POST['new_status'];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE citizen_requests SET status = ? WHERE id IN ($placeholders) AND (barangay_id = ? OR ?)");
            $params = array_merge([$new_status], $ids, [$admin_barangay_id, $is_super_admin]);
            call_user_func_array([$stmt, 'bind_param'], array_merge(['s', str_repeat('i', count($ids)), 'ii'], array_fill(0, count($params), null)));
            $stmt->bind_param('s' . str_repeat('i', count($ids)) . 'ii', ...$params);
            $stmt->execute();
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
        exit;
    }
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    // Risk distribution
    $result = safeQuery($conn, "
        SELECT 
            SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) as high
        FROM households
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['low_risk'] = $row['low'] ?? 0;
        $stats['medium_risk'] = $row['medium'] ?? 0;
        $stats['high_risk'] = $row['high'] ?? 0;
    }
} elseif ($selected_barangay_id) {
    // View specific barangay statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM households WHERE barangay_id = ?");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $stats['total_households'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT AVG(income_monthly) as avg_inc FROM households WHERE barangay_id = ?");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $stats['avg_income'] = round($stmt->get_result()->fetch_assoc()['avg_inc'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM households WHERE four_ps='Yes' AND barangay_id = ?");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $stats['four_ps_count'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    // Get pending document requests for this barangay
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        WHERE cr.status IN ('Submitted', 'Under Review')
        AND c.barangay_id = ?
    ");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $stats['pending_requests'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    // Risk distribution
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) as high
        FROM households 
        WHERE barangay_id = ?
    ");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $risk_counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stats['low_risk'] = $risk_counts['low'] ?? 0;
    $stats['medium_risk'] = $risk_counts['medium'] ?? 0;
    $stats['high_risk'] = $risk_counts['high'] ?? 0;
}

// Get households with coordinates (with pagination)
$total_households = 0;
if ($viewing_all_barangays) {
    // Count total for pagination
    $count_result = $conn->query("SELECT COUNT(*) as total FROM households h WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL");
    $total_households = $count_result ? ($count_result->fetch_assoc()['total'] ?? 0) : 0;

    // Data query with pagination
    $stmt = $conn->prepare("
        SELECT h.*, b.name as barangay_name 
        FROM households h 
        LEFT JOIN barangays b ON h.barangay_id = b.id 
        WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL
        ORDER BY h." . $sort . " " . $order . " 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $households = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($selected_barangay_id) {
    // Count total for pagination
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM households h WHERE h.barangay_id = ? AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL");
    $count_stmt->bind_param("i", $selected_barangay_id);
    $count_stmt->execute();
    $total_households = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $count_stmt->close();

    // Data query with pagination
    $stmt = $conn->prepare("
        SELECT h.*, b.name as barangay_name 
        FROM households h 
        LEFT JOIN barangays b ON h.barangay_id = b.id 
        WHERE h.barangay_id = ? 
        AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
        ORDER BY h." . $sort . " " . $order . "
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $selected_barangay_id, $limit, $offset);
    $stmt->execute();
    $households = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Calculate pagination info
$total_pages = ceil($total_households / $limit);
$current_page = $page;
$showing_from = ($offset + 1);
$showing_to = min($offset + $limit, $total_households);

// Get pending document requests
if ($viewing_all_barangays) {
    $result = safeQuery($conn, "
        SELECT 
            cr.id,
            cr.request_number,
            cr.status,
            cr.submitted_at,
            cr.purpose,
            CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
            dt.name as document_name,
            b.name as barangay_name
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        JOIN document_types dt ON cr.document_type_id = dt.id
        LEFT JOIN barangays b ON c.barangay_id = b.id
        WHERE cr.status IN ('Submitted', 'Under Review')
        ORDER BY cr.submitted_at DESC 
        LIMIT 20
    ");
    $pending_requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} elseif ($selected_barangay_id) {
    $stmt = $conn->prepare("
        SELECT 
            cr.id,
            cr.request_number,
            cr.status,
            cr.submitted_at,
            cr.purpose,
            CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
            dt.name as document_name
        FROM citizen_requests cr
        JOIN citizens c ON cr.citizen_id = c.id
        JOIN document_types dt ON cr.document_type_id = dt.id
        WHERE cr.status IN ('Submitted', 'Under Review')
        AND c.barangay_id = ?
        ORDER BY cr.submitted_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $selected_barangay_id);
    $stmt->execute();
    $pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get barangay summary for super admin
$total_barangays = 0;
$household_summary = [];
if ($is_super_admin && $viewing_all_barangays) {
    // Count total barangays for pagination
    $count_result = safeQuery($conn, "SELECT COUNT(*) as total FROM barangays");
    $total_barangays = $count_result ? ($count_result->fetch_assoc()['total'] ?? 0) : 0;

    // Paginated query
    $stmt = $conn->prepare("
        SELECT 
            b.id, 
            b.name, 
            b.latitude,
            b.longitude,
            COUNT(DISTINCT h.id) as household_count,
            COALESCE(AVG(h.income_monthly), 0) as avg_income,
            SUM(CASE WHEN h.four_ps = 'Yes' THEN 1 ELSE 0 END) as four_ps_count,
            COALESCE(AVG(h.risk_score), 0) as avg_risk_score,
            (
                SELECT COUNT(*) 
                FROM citizen_requests cr
                JOIN citizens c ON cr.citizen_id = c.id
                WHERE cr.status IN ('Submitted', 'Under Review')
                AND c.barangay_id = b.id
            ) as pending_requests
        FROM barangays b
        LEFT JOIN households h ON b.id = h.barangay_id
        GROUP BY b.id
        ORDER BY b.name
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $barangay_limit, $barangay_offset);
    $stmt->execute();
    $barangay_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // NEW: Household summary for map aggregation (Option B)
    $stmt = $conn->prepare("
        SELECT 
            b.name, b.id, b.latitude as b_lat, b.longitude as b_lng,
            COUNT(h.id) as hh_count,
            AVG(h.risk_score) as avg_risk,
            AVG(h.income_monthly) as avg_income
        FROM barangays b
        LEFT JOIN households h ON b.id = h.barangay_id AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
        GROUP BY b.id
        HAVING hh_count > 0
        ORDER BY b.name
    ");
    $stmt->execute();
    $household_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Calculate barangay pagination info
$total_barangay_pages = ceil($total_barangays / $barangay_limit);
$barangay_showing_from = ($barangay_offset + 1);
$barangay_showing_to = min($barangay_offset + $barangay_limit, $total_barangays);

// Calculate percentages for risk distribution
$total_risk = $stats['low_risk'] + $stats['medium_risk'] + $stats['high_risk'];
$low_percent = $total_risk > 0 ? round(($stats['low_risk'] / $total_risk) * 100) : 0;
$medium_percent = $total_risk > 0 ? round(($stats['medium_risk'] / $total_risk) * 100) : 0;
$high_percent = $total_risk > 0 ? round(($stats['high_risk'] / $total_risk) * 100) : 0;

// Format currency
$avg_income_formatted = '₱' . number_format($stats['avg_income'], 0);
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

        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            backdrop-filter: blur(10px);
        }

        .breadcrumb-modern .breadcrumb-item {
            color: rgba(255, 255, 255, 0.8);
        }

        .breadcrumb-modern .breadcrumb-item.active {
            color: white;
        }

        .breadcrumb-modern .breadcrumb-item a {
            color: white;
            text-decoration: none;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #60a5fa);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: #dbeafe;
            color: var(--primary-color);
        }

        .stat-icon.success {
            background: #d1fae5;
            color: var(--success-color);
        }

        .stat-icon.warning {
            background: #fed7aa;
            color: var(--warning-color);
        }

        .stat-icon.danger {
            background: #fee2e2;
            color: var(--danger-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
            font-size: 0.875rem;
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

        .badge-pill {
            margin-left: auto;
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Map Container */
        .map-container {
            position: relative;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        #map {
            height: 500px;
            width: 100%;
        }

        .map-controls {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 12px 10px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .map-control-btn {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: white;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .map-control-btn:hover {
            transform: translateY(-2px) scale(1.05);
            background: var(--primary-color);
            color: white;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .map-control-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* Risk Pulse Animation */
        /* Removed pulse animations */

        /* Risk Distribution */
        .risk-distribution {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .risk-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .risk-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
        }

        .risk-color.low {
            background: var(--success-color);
        }

        .risk-color.medium {
            background: var(--warning-color);
        }

        .risk-color.high {
            background: var(--danger-color);
        }

        .risk-label {
            flex: 1;
            font-weight: 500;
        }

        .risk-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .risk-percent {
            width: 60px;
            text-align: right;
            color: #6b7280;
        }

        .progress-risk {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar-risk {
            height: 100%;
            transition: width 0.3s ease;
        }

        /* Tables */
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
        }

        .table-modern tbody tr:hover {
            background: #f9fafb;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.review {
            background: #fed7aa;
            color: #92400e;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.ready {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            #map {
                height: 350px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.5;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 2rem;
            height: 2rem;
            border: 3px solid #e5e7eb;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Print Styles */
        @media print {

            .sidebar-modern,
            .map-controls,
            .navbar-modern,
            .btn {
                display: none !important;
            }

            .col-md-3 {
                display: none;
            }

            .col-md-9 {
                width: 100%;
            }
        }
    </style>
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
    document.addEventListener('DOMContentLoaded', function () {
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
                        householdsData.forEach(function (hh) {
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
            window.toggleHeatmap = function () {
                if (typeof map === 'undefined' || !map || !heatLayer) return;

                if (map.hasLayer(heatLayer)) {
                    map.removeLayer(heatLayer);
                    document.getElementById('heatmapBtn')?.classList.remove('active');
                } else {
                    map.addLayer(heatLayer);
                    document.getElementById('heatmapBtn')?.classList.add('active');
                }
            };

            window.toggleMarkers = function () {
                if (typeof map === 'undefined' || !map || !markersLayer) return;

                if (map.hasLayer(markersLayer)) {
                    map.removeLayer(markersLayer);
                    document.getElementById('markersBtn')?.classList.remove('active');
                } else {
                    map.addLayer(markersLayer);
                    document.getElementById('markersBtn')?.classList.add('active');
                }
            };

            window.toggleBarangayMarkers = function () {
                if (!window.miniMap || !window.barangayMarkers) return;

                if (window.miniMap.hasLayer(window.barangayMarkers)) {
                    window.miniMap.removeLayer(window.barangayMarkers);
                    document.getElementById('barangayMarkersBtn')?.classList.remove('active');
                } else {
                    window.miniMap.addLayer(window.barangayMarkers);
                    document.getElementById('barangayMarkersBtn')?.classList.add('active');
                }
            };

            window.fitAllBarangays = function () {
                if (!window.miniMap) return;

                const group = new L.featureGroup([window.barangayMarkers, window.householdLayer]);
                if (group.getLayers().length > 0) {
                    window.miniMap.fitBounds(group.getBounds().pad(0.2));
                }
            };

            window.resetMiniMap = function () {
                if (window.miniMap) {
                    window.miniMap.setView([12.2660, 125.3690], 11);
                }
            };

            window.focusOnBarangay = function () {
                if (!map) return;
                <?php if ($selected_barangay && isset($selected_barangay['latitude']) && isset($selected_barangay['longitude'])): ?>
                    const lat = <?= $selected_barangay['latitude'] ?>;
                    const lng = <?= $selected_barangay['longitude'] ?>;
                    if (lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                        map.setView([lat, lng], 16, { animate: true });
                    }
                <?php endif; ?>
            };

            window.resetMap = function () {
                if (!map) return;

                const lat = <?= json_encode($selected_barangay['latitude'] ?? null) ?>;
                const lng = <?= json_encode($selected_barangay['longitude'] ?? null) ?>;

                if (lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 14, { animate: true });
                } else {
                    map.setView([12.266, 125.369], 13);
                }
            };

            window.changeBarangay = function (barangayId) {
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
            setTimeout(function () {
                window.location.reload();
            }, 300000);
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>