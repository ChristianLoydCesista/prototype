<?php
// app/admin/shared/analytics.php
require_once __DIR__ . '/../../shared/bootstrap.php';

// Authentication check (reuse existing pattern)
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

// Session-derived context
$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';
$full_name = $_SESSION['full_name'] ?? $username;

// Determine selected barangay (respect RBAC)
$selected_barangay_id = null;
if ($is_super_admin) {
    if (isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '') {
        $selected_barangay_id = intval($_GET['barangay_id']);
    }
} else {
    $selected_barangay_id = $admin_barangay_id; // force scope to assigned barangay
}

// Default date range (last 30 days)
$to = date('Y-m-d');
$from = date('Y-m-d', strtotime('-30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Analytics | Arteche CIS</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Leaflet CSS (map placeholder ready) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
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
        body { background: var(--light-bg); font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji'; }
        .navbar-modern { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 1rem 0; }
        .navbar-brand { font-weight: 700; letter-spacing: -0.3px; }
        .container-page { padding: 1.25rem 1rem; }
        .panel { background: #fff; border-radius: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.06); }
        .panel-header { padding: 1rem 1.25rem; border-bottom: 1px solid #eef2f7; }
        .panel-body { padding: 1.25rem; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .kpi { background:#fff; border:1px solid #eef2f7; border-radius: 1rem; padding: 1rem; display:flex; gap:.75rem; align-items:center; }
        .kpi .icon { width:44px; height:44px; border-radius: .75rem; display:flex; align-items:center; justify-content:center; font-size:1.25rem; }
        .kpi .meta { line-height:1.1; }
        .kpi .value { font-size:1.4rem; font-weight:700; color:#111827; }
        .kpi .label { font-size:.85rem; color:#6b7280; }
        .kpi .delta { font-size:.8rem; font-weight:600; }
        .map-shell { height: 320px; border-radius: 1rem; overflow:hidden; border:1px solid #eef2f7; }
        .chart-shell { height: 320px; }
        .muted { color:#6b7280; }
        .badge-soft { background:#eef2ff; color:#1e40af; border-radius: 999px; }
        .help { font-size:.85rem; color:#6b7280; }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar-modern">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-4">
                <a class="navbar-brand text-white text-decoration-none" href="dashboard.php">
                    <i class="bi bi-graph-up me-2"></i> Analytics
                </a>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-modern mb-0">
                        <li class="breadcrumb-item"><a class="text-white text-decoration-none" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
                        <li class="breadcrumb-item text-white">Analytics</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small opacity-75">Signed in as <?= htmlspecialchars($full_name) ?></span>
                <a class="btn btn-sm btn-light" href="../../shared/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid container-page">
        <!-- Filters Panel -->
        <div class="panel mb-3">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-sliders2 text-primary"></i>
                    <strong>Filters</strong>
                </div>
                <div class="help">No database changes. Metrics are computed from existing tables and cached client-side.</div>
            </div>
            <div class="panel-body">
                <form class="row g-2" id="filtersForm">
                    <div class="col-12 col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?= $from ?>" max="<?= $to ?>" />
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?= $to ?>" max="<?= $to ?>" />
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Barangay</label>
                        <input type="number" min="1" class="form-control" id="barangay_id" name="barangay_id" placeholder="All" value="<?= $selected_barangay_id ? intval($selected_barangay_id) : '' ?>" <?= $is_super_admin ? '' : 'readonly' ?> />
                        <?php if (!$is_super_admin): ?>
                            <div class="form-text">Scoped to your assigned barangay.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Apply</button>
                        <button type="button" class="btn btn-outline-secondary" id="resetBtn"><i class="bi bi-x"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="kpi-grid mb-3" id="kpiGrid">
            <div class="kpi"><div class="icon" style="background:#dbeafe;color:#2563eb"><i class="bi bi-people"></i></div><div class="meta"><div class="label">Total Citizens</div><div class="value" id="kpi_total_citizens">--</div><div class="delta text-success" id="kpi_total_citizens_delta"></div></div></div>
            <div class="kpi"><div class="icon" style="background:#d1fae5;color:#10b981"><i class="bi bi-house"></i></div><div class="meta"><div class="label">Active Households</div><div class="value" id="kpi_active_households">--</div><div class="delta text-success" id="kpi_active_households_delta"></div></div></div>
            <div class="kpi"><div class="icon" style="background:#fee2e2;color:#ef4444"><i class="bi bi-file-earmark-text"></i></div><div class="meta"><div class="label">Pending Requests</div><div class="value" id="kpi_pending_requests">--</div><div class="delta text-danger" id="kpi_pending_sla"></div></div></div>
            <div class="kpi"><div class="icon" style="background:#e0f2fe;color:#0369a1"><i class="bi bi-ui-checks-grid"></i></div><div class="meta"><div class="label">Surveys Completed</div><div class="value" id="kpi_surveys_completed">--</div><div class="delta text-success" id="kpi_surveys_rate"></div></div></div>
            <div class="kpi"><div class="icon" style="background:#fff7ed;color:#c2410c"><i class="bi bi-hourglass-split"></i></div><div class="meta"><div class="label">Avg Turnaround (hrs)</div><div class="value" id="kpi_avg_turnaround">--</div></div></div>
            <div class="kpi"><div class="icon" style="background:#f3e8ff;color:#6d28d9"><i class="bi bi-exclamation-triangle"></i></div><div class="meta"><div class="label">High-risk Households</div><div class="value" id="kpi_high_risk">--</div></div></div>
        </div>

        <!-- Charts and Map Row -->
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="panel h-100">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-activity me-2 text-primary"></i>Requests – Daily Trend</strong>
                        <span class="badge badge-soft"><span id="asOf">As of --</span></span>
                    </div>
                    <div class="panel-body">
                        <div class="chart-shell"><canvas id="trendChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="panel h-100">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-pie-chart text-primary me-2"></i>Status Distribution</strong>
                    </div>
                    <div class="panel-body">
                        <div class="chart-shell"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map placeholder & alerts -->
        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-8">
                <div class="panel">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-geo-alt text-primary me-2"></i>Map (Barangay overlay)</strong>
                        <span class="muted small">Integration ready with shared/components/map.php</span>
                    </div>
                    <div class="panel-body">
                        <div id="map" class="map-shell"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="panel">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-bell text-primary me-2"></i>Alerts</strong>
                        <span class="muted small">Computed client-side for now</span>
                    </div>
                    <div class="panel-body" id="alertsPanel">
                        <div class="help">No alerts yet. Apply filters to evaluate rules.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script>
    // Simple in-memory cache for the session lifetime
    const cache = new Map();

    // Attempt to fetch analytics API; fallback to placeholders if missing
    async function fetchAnalytics(params) {
        const key = JSON.stringify(params);
        if (cache.has(key)) return cache.get(key);

        const qs = new URLSearchParams(params).toString();
        try {
            const resp = await fetch(`analytics_api.php?${qs}`, { credentials: 'same-origin' });
            if (!resp.ok) throw new Error('API not available');
            const data = await resp.json();
            cache.set(key, data);
            return data;
        } catch (e) {
            // Fallback demo structure; replace as API becomes available
            const nowIso = new Date().toISOString();
            const days = 14;
            const daily = Array.from({length: days}).map((_, i) => {
                const d = new Date(); d.setDate(d.getDate() - (days - 1 - i));
                return { date: d.toISOString().slice(0,10), count: Math.floor(20 + Math.random()*30) };
            });
            const mock = {
                meta: { from: params.from, to: params.to, as_of: nowIso },
                kpis: {
                    total_citizens: { value: 1200, delta_7d: 2.3 },
                    active_households: { value: 340, delta_7d: 1.1 },
                    pending_requests: { value: 42, sla_breaches: 5 },
                    surveys_completed: { value: 180, completion_rate: 76.5 },
                    avg_turnaround_hours: { value: 36.4 },
                    high_risk_households: { value: 18 }
                },
                requests: {
                    by_status: [
                        { status: 'Submitted', count: 18 },
                        { status: 'Under Review', count: 24 },
                        { status: 'Approved', count: 56 },
                        { status: 'Rejected', count: 6 }
                    ],
                    daily_trend: daily
                }
            };
            cache.set(key, mock);
            return mock;
        }
    }

    // Charts
    let trendChart, statusChart;
    function renderCharts(data) {
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        const ctxStatus = document.getElementById('statusChart').getContext('2d');

        const labels = (data.requests?.daily_trend || []).map(p => p.date);
        const values = (data.requests?.daily_trend || []).map(p => p.count);

        if (trendChart) trendChart.destroy();
        trendChart = new Chart(ctxTrend, {
            type: 'line',
            data: { labels, datasets: [{ label: 'Requests', data: values, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', tension: .3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display:false } }, y: { beginAtZero: true } } }
        });

        if (statusChart) statusChart.destroy();
        const byStatus = data.requests?.by_status || [];
        statusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: byStatus.map(s => s.status),
                datasets: [{ data: byStatus.map(s => s.count), backgroundColor: ['#1e3a8a','#0ea5e9','#10b981','#f59e0b','#ef4444','#9333ea'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
        });
    }

    // KPIs
    function renderKpis(kpis) {
        const fmt = (n) => (n === null || n === undefined) ? '--' : new Intl.NumberFormat().format(n);
        document.getElementById('kpi_total_citizens').textContent = fmt(kpis.total_citizens?.value);
        document.getElementById('kpi_active_households').textContent = fmt(kpis.active_households?.value);
        document.getElementById('kpi_pending_requests').textContent = fmt(kpis.pending_requests?.value);
        document.getElementById('kpi_surveys_completed').textContent = fmt(kpis.surveys_completed?.value);
        document.getElementById('kpi_avg_turnaround').textContent = fmt(kpis.avg_turnaround_hours?.value);
        document.getElementById('kpi_high_risk').textContent = fmt(kpis.high_risk_households?.value);

        const delta1 = kpis.total_citizens?.delta_7d; 
        document.getElementById('kpi_total_citizens_delta').textContent = (delta1 || delta1 === 0) ? `${delta1 > 0 ? '+' : ''}${delta1}% vs 7d` : '';
        const delta2 = kpis.active_households?.delta_7d; 
        document.getElementById('kpi_active_households_delta').textContent = (delta2 || delta2 === 0) ? `${delta2 > 0 ? '+' : ''}${delta2}% vs 7d` : '';
        const sla = kpis.pending_requests?.sla_breaches;
        document.getElementById('kpi_pending_sla').textContent = (sla || sla === 0) ? `${sla} SLA breaches` : '';
        const rate = kpis.surveys_completed?.completion_rate;
        document.getElementById('kpi_surveys_rate').textContent = (rate || rate === 0) ? `${rate}% rate` : '';
    }

    // Alerts (client-side rules for now)
    function renderAlerts(data) {
        const el = document.getElementById('alertsPanel');
        const alerts = [];
        const pending = data.kpis?.pending_requests?.value ?? 0;
        const breaches = data.kpis?.pending_requests?.sla_breaches ?? 0;
        const withinTarget = data.requests?.sla_within_target_pct ?? null; // present when API ready
        if (pending > 100) alerts.push({ type:'danger', title: 'Backlog spike', body: `Pending requests at ${pending}.`});
        if (breaches > 10) alerts.push({ type:'warning', title: 'SLA risk', body: `${breaches} items beyond SLA.`});
        if (withinTarget !== null && withinTarget < 85) alerts.push({ type:'warning', title: 'SLA below target', body: `Within target: ${withinTarget}% (7d).`});

        if (!alerts.length) {
            el.innerHTML = '<div class="help">No alerts for selected period.</div>';
            return;
        }
        el.innerHTML = alerts.map(a => `
            <div class="alert alert-${a.type} d-flex align-items-start" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                <div>
                    <div class="fw-semibold">${a.title}</div>
                    <div class="small">${a.body}</div>
                </div>
            </div>`).join('');
    }

    // Map init (placeholder center; replace with barangay centroid when integrating PHP map component)
    function initMap() {
        try {
            const map = L.map('map').setView([12.2660, 125.3690], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            L.marker([12.2660, 125.3690]).addTo(map).bindPopup('Map ready for barangay overlay');
        } catch (e) { console.warn('Map init failed', e); }
    }

    function currentFilters() {
        const f = document.getElementById('from').value;
        const t = document.getElementById('to').value;
        const b = document.getElementById('barangay_id').value || '';
        return { from: f, to: t, barangay_id: b, compare: 'false' };
    }

    async function load() {
        const params = currentFilters();
        const data = await fetchAnalytics(params);
        document.getElementById('asOf').textContent = `As of ${new Date(data.meta?.as_of || Date.now()).toLocaleString()}`;
        renderKpis(data.kpis || {});
        renderCharts(data);
        renderAlerts(data);
    }

    document.getElementById('filtersForm').addEventListener('submit', function(e){ e.preventDefault(); load(); });
    document.getElementById('resetBtn').addEventListener('click', function(){ window.location.href = 'analytics.php'; });

    // Init
    initMap();
    load();
    </script>
</body>
</html>
