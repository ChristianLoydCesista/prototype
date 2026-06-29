<?php
require_once __DIR__ . '/../../shared/bootstrap.php';

// Super admin check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../admin_login.php');
    exit();
}

$db = getDB();
$message = '';

// Handle filters (default last 30 days)
$barangay_id = intval($_GET['barangay_id'] ?? 0);
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$doc_type = $_GET['doc_type'] ?? '';

// Where clause for filters
$where = 'WHERE 1=1';
$params = [];
$types = '';
if ($barangay_id) {
    $where .= ' AND r.barangay_id = ?';
    $params[] = $barangay_id;
    $types .= 'i';
}
if ($start_date && $end_date) {
    $where .= ' AND DATE(r.created_at) BETWEEN ? AND ?';
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}
if ($doc_type) {
    $where .= ' AND r.document_type = ?';
    $params[] = $doc_type;
    $types .= 's';
}

// KPI Stats
$kpi_sql = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN payment_status = 'paid' THEN fee ELSE 0 END) as revenue,
        (SELECT COUNT(*) FROM households h $where_h) as total_households,
        (SELECT AVG(risk_score) FROM households h $where_h) as avg_risk
    FROM citizen_requests r
";
$kpi = $db->query($kpi_sql)->fetch_assoc();

// Status data
$stmt = $db->prepare("SELECT status, COUNT(*) as total FROM citizen_requests r $where GROUP BY status");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$status_result = $stmt->get_result();
$status_labels = []; $status_data = [];
while ($row = $status_result->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_data[] = $row['total'];
}

// Payment
$stmt = $db->prepare("SELECT payment_status, COUNT(*) as total FROM citizen_requests r $where GROUP BY payment_status");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payment_result = $stmt->get_result();
$payment_labels = []; $payment_data = [];
while ($row = $payment_result->fetch_assoc()) {
    $payment_labels[] = $row['payment_status'];
    $payment_data[] = $row['total'];
}

// Trend (monthly requests)
$trend_sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total FROM citizen_requests r $where GROUP BY month ORDER BY month DESC LIMIT 12";
$trend_result = $db->query($trend_sql);
$trend_labels = []; $trend_data = [];
while ($row = $trend_result->fetch_assoc()) {
    $trend_labels[] = $row['month'];
    $trend_data[] = $row['total'];
}
$trend_labels = array_reverse($trend_labels); // Chronological
$trend_data = array_reverse($trend_data);

// Top Barangays
$top_sql = "SELECT b.name, COUNT(r.id) as count FROM citizen_requests r JOIN barangays b ON r.barangay_id = b.id $where GROUP BY b.id ORDER BY count DESC LIMIT 5";
$top_result = $db->query($top_sql);
$top_labels = []; $top_data = [];
while ($row = $top_result->fetch_assoc()) {
    $top_labels[] = $row['name'];
    $top_data[] = $row['count'];
}

// Risk buckets
$risk_sql = "
    SELECT
        SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) low,
        SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) medium,
        SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) high
    FROM households h
";
$risk = $db->query($risk_sql)->fetch_assoc();

// Get barangays for filter
$barangays = $db->query('SELECT id, name FROM barangays ORDER BY name')->fetch_all(MYSQLI_ASSOC);

// Get document types
$doc_types = $db->query('SELECT DISTINCT document_type FROM citizen_requests WHERE document_type IS NOT NULL ORDER BY document_type')->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .kpi-card { transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); }
        .chart-container { position: relative; height: 400px; }
        .filter-bar { background: #f8f9fa; border-radius: 10px; padding: 15px; }
        .navbar-brand { font-weight: 700; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../shared/components/navbar.php'; ?>

    <div class="container-fluid mt-4 mb-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-5 fw-bold mb-2">
                    <i class="bi bi-bar-chart-line text-primary"></i> Analytics Dashboard
                </h1>
                <p class="lead text-muted">Super Admin Reports & Insights - <?= date('F Y') ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar mb-4 shadow-sm">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Barangay</label>
                    <select name="barangay_id" class="form-select">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $barangay_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>" class="form-control" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="form-control" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Document Type</label>
                    <select name="doc_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($doc_types as $type): ?>
                            <option value="<?= htmlspecialchars($type['document_type']) ?>" <?= $doc_type == $type['document_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['document_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="card kpi-card shadow border-0 h-100 text-center bg-primary text-white">
                    <div class="card-body">
                        <i class="bi bi-file-earmark-text fs-1 opacity-75 mb-3"></i>
                        <h3><?= number_format($kpi['total_requests'] ?? 0) ?></h3>
                        <h6>Total Requests</h6>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card kpi-card shadow border-0 h-100 text-center bg-success text-white">
                    <div class="card-body">
                        <i class="bi bi-cash-coin fs-1 opacity-75 mb-3"></i>
                        <h3>₱<?= number_format($kpi['revenue'] ?? 0) ?></h3>
                        <h6>Revenue</h6>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card kpi-card shadow border-0 h-100 text-center bg-info text-white">
                    <div class="card-body">
                        <i class="bi bi-house-door fs-1 opacity-75 mb-3"></i>
                        <h3><?= number_format($kpi['total_households'] ?? 0) ?></h3>
                        <h6>Households</h6>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card kpi-card shadow border-0 h-100 text-center bg-warning text-dark">
                    <div class="card-body">
                        <i class="bi bi-graph-down fs-1 opacity-75 mb-3"></i>
                        <h3><?= round($kpi['avg_risk'] ?? 0, 1) ?>%</h3>
                        <h6>Avg Risk</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="row g-4 mb-5">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Requests Over Time</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-check-circle"></i> Request Status</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Payment Status</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Top Barangays (Requests)</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="topBarangayChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Chart (standalone) -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header" style="background: var(--primary-gradient); color: white;">
                        <h5 class="mb-0"><i class="bi bi-graph-down"></i> Risk Distribution</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="riskChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Requests <small class="text-muted">(Last 30 days)</small></h5>
                    </div>
                    <div class="card-body">
                        <table id="requestsTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Barangay</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Fee</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> High Risk Households <small>(Risk > 50)</small></h5>
                    </div>
                    <div class="card-body">
                        <table id="riskTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Barangay</th>
                                    <th>Risk %</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Bar -->
        <div class="text-center mt-5">
            <button class="btn btn-success btn-lg me-3" onclick="exportPDF()">
                <i class="bi bi-file-earmark-pdf"></i> Export Full PDF
            </button>
            <button class="btn btn-outline-secondary btn-lg" onclick="exportCSV()">
                <i class="bi bi-filetype-csv"></i> Export CSV Data
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        // Chart configs
        const trendCtx = document.getElementById('trendChart')?.getContext('2d');
        if (trendCtx) new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{ label: 'Requests', data: <?= json_encode($trend_data) ?>, borderColor: '#667eea', fill: false }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) new Chart(statusCtx, {
            type: 'pie',
            data: { labels: <?= json_encode($status_labels) ?>, datasets: [{ data: <?= json_encode($status_data) ?> }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
        if (paymentCtx) new Chart(paymentCtx, {
            type: 'doughnut',
            data: { labels: <?= json_encode($payment_labels) ?>, datasets: [{ data: <?= json_encode($payment_data) ?> }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const topCtx = document.getElementById('topBarangayChart')?.getContext('2d');
        if (topCtx) new Chart(topCtx, {
            type: 'bar',
            data: { labels: <?= json_encode($top_labels) ?>, datasets: [{ label: 'Requests', data: <?= json_encode($top_data) ?>, backgroundColor: '#43e97b' }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const riskCtx = document.getElementById('riskChart')?.getContext('2d');
        if (riskCtx) new Chart(riskCtx, {
            type: 'bar',
            data: {
                labels: ['Low', 'Medium', 'High'],
                datasets: [{ label: 'Households', data: [<?= $risk['low'] ?? 0 ?>, <?= $risk['medium'] ?? 0 ?>, <?= $risk['high'] ?? 0 ?>], backgroundColor: ['#28a745', '#ffc107', '#dc3545'] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // DataTables
        $('#requestsTable').DataTable({
            ajax: { url: '?ajax=requests&<?= http_build_query($_GET) ?>', type: 'GET' },
            columns: [{ data: 'id' }, { data: 'barangay_name' }, { data: 'document_type' }, { data: 'status' }, { data: 'fee' }, { data: 'created_at' }],
            pageLength: 25, order: [[5, 'desc']]
        });

        // Simplified - full AJAX endpoint needed in prod
        // Mock high risk table
        $('#riskTable').DataTable({ data: [], columns: [{ data: 'name' }, { data: 'barangay' }, { data: 'risk' }] });

        // Filter submit
        $('#filterForm').submit(function(e) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(this)).toString();
            window.location = '?'+params;
        });

        // PDF Export (simplified)
        function exportPDF() {
            window.print(); // Full page print as PDF
        }

        function exportCSV() {
            alert('CSV export - implement server-side fputcsv');
        }
    </script>
</body>
</html>
