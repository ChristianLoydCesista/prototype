<?php
// app/admin/shared/view_household.php
require_once __DIR__ . '/../../shared/bootstrap.php';
// bootstrap already defined $conn, constants and started session

// Authentication check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$username = $_SESSION['username'] ?? 'Admin';
$full_name = $_SESSION['full_name'] ?? $username;

// Get household ID from URL
$household_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$household_id) {
    header("Location: dashboard.php?error=invalid_household");
    exit;
}

// Fetch household details with security check
if ($is_super_admin) {
    $stmt = $conn->prepare("
        SELECT h.*, b.name as barangay_name, b.id as barangay_id
        FROM households h
        LEFT JOIN barangays b ON h.barangay_id = b.id
        WHERE h.id = ?
    ");
    $stmt->bind_param("i", $household_id);
} else {
    $stmt = $conn->prepare("
        SELECT h.*, b.name as barangay_name, b.id as barangay_id
        FROM households h
        LEFT JOIN barangays b ON h.barangay_id = b.id
        WHERE h.id = ? AND h.barangay_id = ?
    ");
    $stmt->bind_param("ii", $household_id, $admin_barangay_id);
}

$stmt->execute();
$result = $stmt->get_result();
$household = $result->fetch_assoc();
$stmt->close();

if (!$household) {
    header("Location: dashboard.php?error=household_not_found");
    exit;
}

// Initialize empty requests array since citizen_requests links to citizens, not households
$requests = [];

// Calculate risk score if not set
require_once '../utils/risk_score.php';
if (!$household['risk_score'] && $household['income_monthly'] && $household['household_size']) {
    $risk_calculator = new RiskScoreCalculator($conn);
    $household['risk_score'] = $risk_calculator->calculateHouseholdRisk($household_id);
    // Update the database
    $update_stmt = $conn->prepare("UPDATE households SET risk_score = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $household['risk_score'], $household_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Get risk factors explanation
$risk_factors = [
    [
        'description' => 'Income Level',
        'detail' => 'Monthly income: ₱' . number_format($household['income_monthly'] ?? 0, 2),
        'type' => ($household['income_monthly'] ?? 0) < 3000 ? 'negative' : 'positive',
        'impact' => ($household['income_monthly'] ?? 0) < 3000 ? 'High' : 'Low'
    ],
    [
        'description' => 'Household Size',
        'detail' => $household['household_size'] ?? 1 . ' members',
        'type' => ($household['household_size'] ?? 1) > 4 ? 'negative' : 'positive',
        'impact' => ($household['household_size'] ?? 1) > 4 ? 'Medium' : 'Low'
    ],
    [
        'description' => '4Ps Status',
        'detail' => ($household['four_ps'] ?? 'No') === 'Yes' ? 'Beneficiary' : 'Not a beneficiary',
        'type' => ($household['four_ps'] ?? 'No') === 'Yes' ? 'positive' : 'negative',
        'impact' => ($household['four_ps'] ?? 'No') === 'Yes' ? 'Low' : 'Medium'
    ],
    [
        'description' => 'Vulnerability Indicators',
        'detail' => (($household['disability'] ?? 'No') === 'Yes' ? 'Has disability, ' : '') . (($household['senior_citizen'] ?? 'No') === 'Yes' ? 'Has senior citizen' : ''),
        'type' => (($household['disability'] ?? 'No') === 'Yes' || ($household['senior_citizen'] ?? 'No') === 'Yes') ? 'negative' : 'positive',
        'impact' => (($household['disability'] ?? 'No') === 'Yes' || ($household['senior_citizen'] ?? 'No') === 'Yes') ? 'High' : 'Low'
    ]
];

// Determine risk level and color
$risk_score = $household['risk_score'] ?? 0;
$risk_level = $risk_score <= RISK_LOW_MAX ? 'Low' : ($risk_score <= RISK_MEDIUM_MAX ? 'Medium' : 'High');
$risk_color = $risk_level === 'Low' ? 'success' : ($risk_level === 'Medium' ? 'warning' : 'danger');
$risk_bg = $risk_level === 'Low' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' :
    ($risk_level === 'Medium' ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' :
        'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Household - <?= htmlspecialchars($household['name']) ?> | Arteche CIS</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

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

        /* Profile Header */
        .profile-header {
            background:
                <?= $risk_bg ?>
            ;
            padding: 3rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 0 0 2rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><polygon points="0,0 100,0 100,100" fill="rgba(255,255,255,0.05)"/></svg>');
            background-size: cover;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 3px solid white;
            backdrop-filter: blur(10px);
        }

        .risk-score-large {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            text-align: center;
            min-width: 150px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .risk-score-large .score {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .risk-score-large .label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Cards */
        .info-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .info-card-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-bg);
            display: flex;
            align-items: center;
        }

        .info-card-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .info-item {
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 1rem;
            transition: all 0.2s;
        }

        .info-item:hover {
            background: #e5e7eb;
        }

        .info-item .label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-item .value {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        /* Risk Gauge */
        .risk-gauge {
            height: 1rem;
            background: #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
            margin: 1rem 0;
        }

        .risk-gauge-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--warning-color), var(--danger-color));
            border-radius: 0.5rem;
            width:
                <?= $risk_score ?>
                %;
            position: relative;
            animation: gaugeFill 1s ease-out;
        }

        @keyframes gaugeFill {
            from {
                width: 0;
            }

            to {
                width:
                    <?= $risk_score ?>
                    %;
            }
        }

        /* Factor Items */
        .factor-item {
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 1rem;
            background: var(--light-bg);
            border-left: 4px solid #dee2e6;
            transition: all 0.2s;
        }

        .factor-item:hover {
            transform: translateX(4px);
        }

        .factor-item.positive {
            border-left-color: var(--success-color);
            background: #f0fff4;
        }

        .factor-item.negative {
            border-left-color: var(--danger-color);
            background: #fff5f5;
        }

        .factor-item.warning {
            border-left-color: var(--warning-color);
            background: #fff9e6;
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

        /* Action Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            margin-right: 0.5rem;
        }

        .action-btn i {
            margin-right: 0.5rem;
        }

        .action-btn.primary {
            background: var(--primary-color);
            color: white;
        }

        .action-btn.success {
            background: var(--success-color);
            color: white;
        }

        .action-btn.warning {
            background: var(--warning-color);
            color: white;
        }

        .action-btn.danger {
            background: var(--danger-color);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            color: white;
        }

        /* Map */
        #mini-map {
            height: 250px;
            width: 100%;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            margin-top: 1rem;
        }

        /* Breadcrumb */
        .breadcrumb-modern {
            background: white;
            padding: 0.75rem 1.5rem;
            border-radius: 3rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .breadcrumb-modern .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-modern .breadcrumb-item.active {
            color: #6b7280;
        }

        /* Recommendations */
        .recommendation-item {
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: #f8fafc;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .recommendation-item i {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 0;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .risk-score-large {
                min-width: 120px;
                padding: 1rem;
            }

            .risk-score-large .score {
                font-size: 2.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .profile-header {
                background:
                    <?= $risk_bg ?>
                    !important;
                color: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .info-card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            .factor-item {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-card {
            animation: slideIn 0.5s ease-out forwards;
        }

        .info-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .info-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .info-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .info-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .info-card:nth-child(5) {
            animation-delay: 0.5s;
        }
    </style>
</head>

<body>
    <!-- Modern Navigation (no-print) -->
    <nav class="navbar-modern no-print">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="d-flex align-items-center gap-4">
                    <a class="navbar-brand text-white" href="dashboard.php">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        Arteche CIS
                    </a>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-link text-white text-decoration-none d-flex align-items-center gap-2"
                            type="button" data-bs-toggle="dropdown">
                            <div class="text-end">
                                <div class="fw-semibold"><?= htmlspecialchars($full_name) ?></div>
                                <small class="opacity-75">
                                    <?php if ($is_super_admin): ?>
                                        Super Administrator
                                    <?php else: ?>
                                        Barangay Admin
                                    <?php endif; ?>
                                </small>
                            </div>
                            <i class="bi bi-person-circle fs-3"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i
                                        class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container px-4 py-4">
        <!-- Modern Breadcrumb (no-print) -->
        <nav aria-label="breadcrumb" class="breadcrumb-modern no-print">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="dashboard.php">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Household Details</li>
            </ol>
        </nav>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-4">
                            <div class="profile-avatar">
                                <i class="bi bi-house-door"></i>
                            </div>
                            <div>
                                <h1 class="display-5 fw-bold mb-2"><?= htmlspecialchars($household['name']) ?></h1>
                                <div class="d-flex flex-wrap gap-3">
                                    <span>
                                        <i class="bi bi-geo-alt-fill me-1"></i>
                                        <?= htmlspecialchars($household['barangay_name']) ?>
                                    </span>
                                    <?php if (!empty($household['contact_number'])): ?>
                                        <span>
                                            <i class="bi bi-telephone me-1"></i>
                                            <?= htmlspecialchars($household['contact_number']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="bi bi-calendar me-1"></i>
                                        Surveyed:
                                        <?= date('M d, Y', strtotime($household['survey_date'] ?? $household['date_submitted'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-4 mt-md-0">
                        <div class="risk-score-large">
                            <div class="score"><?= $risk_score ?></div>
                            <div class="label">Risk Score - <?= $risk_level ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons (no-print) -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <a href="survey.php?edit=<?= $household['id'] ?>&barangay_id=<?= $household['barangay_id'] ?>"
                        class="action-btn warning">
                        <i class="bi bi-pencil-square"></i> Edit Household
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-6">
                <!-- Basic Information -->
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="bi bi-person-badge"></i>
                        Basic Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Head of Household</div>
                            <div class="value"><?= htmlspecialchars($household['name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Age</div>
                            <div class="value"><?= $household['age'] ?? 'N/A' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Gender</div>
                            <div class="value"><?= $household['sex'] ?? 'N/A' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Civil Status</div>
                            <div class="value"><?= $household['civil_status'] ?? 'N/A' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Employment Status</div>
                            <div class="value"><?= $household['employment'] ?? 'N/A' ?></div>
                        </div>
                    </div>
                </div>

                <!-- Household Composition -->
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="bi bi-people"></i>
                        Household Composition
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Family Size</div>
                            <div class="value"><?= $household['household_size'] ?? 'N/A' ?> members</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Has Disability</div>
                            <div class="value">
                                <?php if (($household['disability'] ?? 'No') == 'Yes'): ?>
                                    <span class="badge bg-warning">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-success">No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Has Senior Citizen</div>
                            <div class="value">
                                <?php if (($household['senior_citizen'] ?? 'No') == 'Yes'): ?>
                                    <span class="badge bg-info">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Vulnerability Index</div>
                            <div class="value"><?= $household['vulnerability_index'] ?? '0' ?>/10</div>
                        </div>
                    </div>
                </div>

                <!-- Economic Information -->
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="bi bi-cash-stack"></i>
                        Economic Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Monthly Income</div>
                            <div class="value">₱<?= number_format($household['income_monthly'] ?? 0, 2) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Income per Capita</div>
                            <div class="value">
                                ₱<?= number_format(($household['income_monthly'] ?? 0) / max(1, ($household['household_size'] ?? 1)), 2) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">4Ps Beneficiary</div>
                            <div class="value">
                                <?php if (($household['four_ps'] ?? 'No') == 'Yes'): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Income Source</div>
                            <div class="value"><?= htmlspecialchars($household['income_source'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-6">
                <!-- Risk Analysis -->
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="bi bi-graph-up"></i>
                        Risk Analysis
                    </div>

                    <!-- Risk Gauge -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success">Low Risk</span>
                            <span class="text-warning">Medium Risk</span>
                            <span class="text-danger">High Risk</span>
                        </div>
                        <div class="risk-gauge">
                            <div class="risk-gauge-fill"></div>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">Current Score: <?= $risk_score ?>/100 - <?= $risk_level ?>
                                Risk</small>
                        </div>
                    </div>

                    <!-- Contributing Factors -->
                    <h6 class="fw-semibold mb-3">Contributing Factors:</h6>
                    <?php foreach ($risk_factors as $factor): ?>
                        <div class="factor-item <?= $factor['type'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><?= htmlspecialchars($factor['description']) ?></span>
                                <span
                                    class="badge bg-<?= $factor['type'] === 'positive' ? 'success' : ($factor['type'] === 'negative' ? 'danger' : 'warning') ?>">
                                    <?= $factor['impact'] ?>
                                </span>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($factor['detail']) ?></small>
                        </div>
                    <?php endforeach; ?>

                    <!-- Recommendations -->
                    <div class="mt-4 p-3 bg-light rounded-3">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-lightbulb text-warning me-2"></i>
                            Recommendations
                        </h6>
                        <?php if ($risk_score > 60): ?>
                            <div class="recommendation-item">
                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                Prioritize for immediate assistance
                            </div>
                            <div class="recommendation-item">
                                <i class="bi bi-people text-primary"></i>
                                Consider for 4Ps or other social programs
                            </div>
                            <div class="recommendation-item">
                                <i class="bi bi-house-check text-success"></i>
                                Schedule home visit for needs assessment
                            </div>
                        <?php elseif ($risk_score > 30): ?>
                            <div class="recommendation-item">
                                <i class="bi bi-eye text-warning"></i>
                                Monitor economic situation
                            </div>
                            <div class="recommendation-item">
                                <i class="bi bi-check-circle text-primary"></i>
                                Check eligibility for existing programs
                            </div>
                            <div class="recommendation-item">
                                <i class="bi bi-book text-info"></i>
                                Provide skills training opportunities
                            </div>
                        <?php else: ?>
                            <div class="recommendation-item">
                                <i class="bi bi-activity text-success"></i>
                                Continue regular monitoring
                            </div>
                            <div class="recommendation-item">
                                <i class="bi bi-star text-warning"></i>
                                Document as potential resource family
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Location -->
                <?php if (!empty($household['latitude']) && !empty($household['longitude'])): ?>
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="bi bi-geo-alt"></i>
                            Location
                        </div>
                        <div id="mini-map"></div>
                        <div class="d-flex justify-content-between mt-3">
                            <small class="text-muted">
                                <i class="bi bi-crosshair"></i>
                                Lat: <?= number_format($household['latitude'], 6) ?>
                            </small>
                            <small class="text-muted">
                                <i class="bi bi-crosshair"></i>
                                Long: <?= number_format($household['longitude'], 6) ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Document Requests -->
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="bi bi-file-text"></i>
                        Recent Document Requests (<?= count($requests) ?>)
                    </div>

                    <?php if (empty($requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                            <p class="text-muted mb-3">No document requests found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($requests, 0, 5) as $req): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($req['document_name']) ?></strong>
                                                <br>
                                                <small
                                                    class="text-muted"><?= htmlspecialchars($req['document_code'] ?? '') ?></small>
                                            </td>
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
                                                        $status_class = 'submitted';
                                                }
                                                ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= $req['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($req['submitted_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notes -->
                <?php if (!empty($household['notes'])): ?>
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="bi bi-chat-text"></i>
                            Notes
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($household['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script>
        // Initialize map if coordinates exist
        <?php if (!empty($household['latitude']) && !empty($household['longitude'])): ?>
            document.addEventListener('DOMContentLoaded', function () {
                var map = L.map('mini-map').setView([<?= $household['latitude'] ?>, <?= $household['longitude'] ?>], 16);

                // Base layers
                var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                });

                var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: '&copy; Esri'
                });

                // Add satellite by default
                satelliteLayer.addTo(map);

                // Layer control
                L.control.layers({
                    "Satellite": satelliteLayer,
                    "Street": streetLayer
                }).addTo(map);

                // Custom marker
                var customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: <?= $risk_color === 'success' ? '#10b981' : ($risk_color === 'warning' ? '#f59e0b' : '#ef4444') ?>; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });

                // Add marker
                L.marker([<?= $household['latitude'] ?>, <?= $household['longitude'] ?>], {
                    icon: customIcon
                })
                    .addTo(map)
                    .bindPopup('<b><?= addslashes($household['name']) ?></b>')
                    .openPopup();
            });
        <?php endif; ?>

        // Print handling
        window.onbeforeprint = function () {
            var map = document.getElementById('mini-map');
            if (map && map._leaflet_map) {
                map._leaflet_map.invalidateSize();
            }
        };
    </script>
</body>

</html>
<?php $conn->close(); ?>