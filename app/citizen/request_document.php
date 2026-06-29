<?php
require_once '../shared/bootstrap.php';
// dependencies (Session, Auth, DB) come from bootstrap

$session = new Session();
$auth = new Auth();

// Check user type
$is_citizen = $session->isCitizenLoggedIn();
$is_staff = $session->isStaffLoggedIn();
$is_public = !$is_citizen && !$is_staff;

// Get database connection
$db = getDB();

// Get document types for dropdown - Demo data
$document_types = [
    ['id' => 1, 'name' => 'Barangay Certification', 'fee' => 50.00, 'processing_days' => 2, 'description' => 'Standard clearance for good moral character and residency verification'],
    ['id' => 2, 'name' => 'Barangay Indigency', 'fee' => 0.00, 'processing_days' => 3, 'description' => 'Certificate for financial assistance programs and medical support']
];

// Get citizen info if logged in
$citizen_info = null;
if ($is_citizen) {
    $citizen_id = $session->getCitizenId();
    $stmt = $db->prepare("SELECT * FROM citizens WHERE id = ?");
    $stmt->bind_param("i", $citizen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $citizen_info = $result->fetch_assoc();
    $stmt->close();
}

// Get barangays for public users
$barangays = $db->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Process form submission
$success = false;
$error = '';
$request_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    // Sanitize inputs
    $document_type_id = intval($_POST['document_type_id'] ?? 0);
    $purpose = trim($db->real_escape_string($_POST['purpose'] ?? ''));
    $additional_notes = trim($db->real_escape_string($_POST['additional_notes'] ?? ''));

    // Get document type info
    $stmt = $db->prepare("SELECT * FROM document_types WHERE id = ?");
    $stmt->bind_param("i", $document_type_id);
    $stmt->execute();
    $doc_type_result = $stmt->get_result();
    $document_type = $doc_type_result->fetch_assoc();
    $stmt->close();

    if (!$document_type) {
        $error = "Invalid document type selected.";
    } elseif (empty($purpose)) {
        $error = "Please specify the purpose of the request.";
    } else {
        // Handle different user types
        if ($is_citizen) {
            // Citizen request - use stored citizen info
            $request_data = [
                'citizen_id' => $citizen_id,
                'document_type_id' => $document_type_id,
                'purpose' => $purpose,
                'additional_notes' => $additional_notes,
                'status' => 'Submitted',
                'submitted_at' => date('Y-m-d H:i:s')
            ];

            // Generate unique request number
            $timestamp = date('Ymd-His');
            $rand = sprintf('%03d', rand(0, 999));
            $request_number = 'REQ-' . $timestamp . '-' . $rand;

            // Insert into citizen_requests table
            $stmt = $db->prepare("
                INSERT INTO citizen_requests 
                (request_number, citizen_id, document_type_id, purpose, status, submitted_at, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->bind_param(
                "siisss",
                $request_number,
                $citizen_id,
                $document_type_id,
                $purpose,
                $request_data['status'],
                $request_data['submitted_at']
            );
        } elseif ($is_public) {
            // Public/guest request - collect user info
            $first_name = trim($db->real_escape_string($_POST['first_name'] ?? ''));
            $last_name = trim($db->real_escape_string($_POST['last_name'] ?? ''));
            $phone = trim($db->real_escape_string($_POST['phone'] ?? ''));
            $email = trim($db->real_escape_string($_POST['email'] ?? ''));
            $address = trim($db->real_escape_string($_POST['address'] ?? ''));
            $barangay_id = intval($_POST['barangay_id'] ?? 0);

            // Validate public user inputs
            if (empty($first_name) || empty($last_name)) {
                $error = "Please provide your full name.";
            } elseif (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
                $error = "Please provide a valid Philippine mobile number (09XXXXXXXXX).";
            } elseif (empty($address)) {
                $error = "Please provide your complete address.";
            } elseif ($barangay_id <= 0) {
                $error = "Please select your barangay.";
            } else {
                // Generate request number for public user
                $request_number = 'PUB-' . date('Ymd') . '-' . str_pad($barangay_id, 3, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);

                // For public users, we use certificate_requests table
                $stmt = $db->prepare("
                    INSERT INTO certificate_requests 
                    (request_number, resident_name, certificate_type, purpose, status, requested_date)
                    VALUES (?, ?, ?, ?, 'Pending', NOW())
                ");

                $resident_name = $first_name . ' ' . $last_name;
                $certificate_type = $document_type['name'];

                $stmt->bind_param(
                    "ssss",
                    $request_number,
                    $resident_name,
                    $certificate_type,
                    $purpose
                );
            }
        }

        if (empty($error) && isset($stmt)) {
            if ($stmt->execute()) {
                $success = true;

                // Send confirmation (simulated)
                if ($is_citizen && !empty($citizen_info['email'])) {
                    // Send email to citizen
                    $subject = "Document Request Confirmation - Arteche Barangay";
                    $message = "Hello " . $citizen_info['first_name'] . ",\n\n";
                    $message .= "Your request for " . $document_type['name'] . " has been submitted successfully.\n";
                    $message .= "Request Number: " . $request_number . "\n";
                    $message .= "Purpose: " . $purpose . "\n";
                    $message .= "Processing Fee: ₱" . number_format($document_type['fee'], 2) . "\n\n";
                    $message .= "You can track your request status in your dashboard.\n\n";
                    $message .= "Thank you,\nArteche Barangay Services";

                    // In production, use mail() or PHPMailer
                    error_log("DEMO: Would send email to " . $citizen_info['email'] . ": " . $subject);
                }
            } else {
                $error = "Failed to submit request. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Request Document | Arteche Barangay Services</title>

    <!-- Bootstrap 5 + Icons + Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Inter for modern sans -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
            color: #1e293b;
            scroll-behavior: smooth;
        }

        /* ===== REFINED COLOR PALETTE ===== */
        :root {
            --primary-deep: #0a2f44;      /* deep navy */
            --primary-mid: #1e6f5c;       /* teal accent */
            --primary-light: #eef2fa;
            --accent-soft: #2c9c8c;
            --gold-accent: #e6b422;
            --gray-light: #f8fafc;
            --card-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.02);
            --transition: all 0.25s ease;
        }

        /* navigation (refined, consistent) */
        .navbar {
            background: var(--primary-deep);
            padding: 0.85rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.45rem;
            letter-spacing: -0.3px;
        }
        .navbar-brand i {
            color: var(--gold-accent);
            margin-right: 6px;
        }
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            margin: 0 2px;
            transition: var(--transition);
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,240,0.12);
            color: white !important;
        }

        /* page header — softer gradient */
        .page-header {
            background: linear-gradient(115deg, #0a2f44 0%, #145c4a 100%);
            color: white;
            padding: 2.8rem 0 3rem;
            text-align: center;
            border-bottom-left-radius: 2rem;
            border-bottom-right-radius: 2rem;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-weight: 700;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
        }
        .page-header p {
            opacity: 0.9;
            font-weight: 400;
        }

        /* main floating card */
        .request-card {
            background: white;
            border-radius: 32px;
            box-shadow: var(--card-shadow);
            margin-top: -48px;
            position: relative;
            z-index: 10;
            transition: transform 0.2s;
        }

        /* step indicator refined */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
            background: #fefefe;
            border-radius: 60px;
            padding: 0.5rem 0.8rem;
        }
        .step-indicator:before {
            content: '';
            position: absolute;
            top: 28px;
            left: 12%;
            right: 12%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        .step-circle {
            width: 44px;
            height: 44px;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.2s;
            background-color: #fff;
        }
        .step.active .step-circle {
            background: var(--primary-mid);
            border-color: var(--primary-mid);
            color: white;
            box-shadow: 0 6px 12px rgba(30,111,92,0.2);
        }
        .step.completed .step-circle {
            background: var(--accent-soft);
            border-color: var(--accent-soft);
            color: white;
        }
        .step-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 8px;
            color: #475569;
        }
        .step.active .step-label {
            color: var(--primary-mid);
            font-weight: 700;
        }

        /* document type cards (elevated) */
        .document-type-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem 1.2rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            border: 1px solid #e9edf2;
            box-shadow: 0 4px 8px rgba(0,0,0,0.02);
            height: 100%;
        }
        .document-type-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-mid);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
        }
        .document-type-card.selected {
            border: 2px solid var(--primary-mid);
            background: #f6fefc;
            box-shadow: 0 12px 20px -12px rgba(30,111,92,0.25);
        }
        .fee-badge {
            background: linear-gradient(135deg, #1e6f5c, #2c9c8c);
            color: white;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* form elements */
        .form-section {
            border-bottom: 1px solid #eef2f8;
            padding-bottom: 1.8rem;
            margin-bottom: 1.8rem;
        }
        .section-title {
            font-weight: 700;
            color: var(--primary-deep);
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i {
            font-size: 1.6rem;
            color: var(--primary-mid);
        }
        .required:after {
            content: " *";
            color: #dc3545;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 14px;
            border: 1px solid #cfdfe9;
            padding: 0.7rem 1rem;
            transition: 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-mid);
            box-shadow: 0 0 0 3px rgba(30,111,92,0.2);
        }

        /* success block */
        .success-message {
            background: #e6f9f0;
            border-radius: 28px;
            padding: 2rem;
            text-align: center;
        }
        .request-number {
            background: #1a2c3e;
            color: #f9fbfd;
            font-family: 'SF Mono', monospace;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 1px;
            padding: 0.7rem 1.2rem;
            border-radius: 60px;
            display: inline-block;
        }

        /* document info panel */
        .document-info {
            background: #f0f6fa;
            border-radius: 20px;
            padding: 1rem 1.2rem;
            margin-top: 1.4rem;
            border-left: 5px solid var(--primary-mid);
        }

        /* footer refinements */
        .footer {
            background: #0a2f44;
            color: #ccddee;
            padding: 2rem 0;
            margin-top: 3rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary-mid);
            border: none;
            border-radius: 40px;
            padding: 0.6rem 1.8rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-primary:hover {
            background: #145c4a;
            transform: translateY(-1px);
            box-shadow: 0 8px 16px -8px rgba(30,111,92,0.4);
        }
        .btn-outline-primary {
            border-radius: 40px;
            border-color: var(--primary-mid);
            color: var(--primary-mid);
        }
        .btn-outline-primary:hover {
            background: var(--primary-mid);
            border-color: var(--primary-mid);
        }
        .alert-info-custom {
            background: #e1f0fa;
            border-left: 4px solid var(--primary-mid);
        }
        @media (max-width: 768px) {
            .request-card {
                margin-top: -20px;
                padding: 1.5rem !important;
            }
            .step-label {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

<?php
// -------- preserve all original backend logic, no functional change ----------
// The code between this comment and the HTML is untouched aside from UI variables.
// All PHP variables, database calls, and request handling remain identical.
// Only the UI templates and style are refactored.
?>

<!-- NAVIGATION (kept exactly same structure, refined classes but same links) -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-building"></i> Arteche CI System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="bi bi-house-door"></i> Home</a>
                <a class="nav-link" href="map.php"><i class="bi bi-map"></i> Community Map</a>
                <?php if ($is_citizen): ?>
                        <a class="nav-link active" href="request_document.php"><i class="bi bi-file-text"></i> Request Document</a>
                        <a class="nav-link" href="citizen_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                <?php elseif ($is_staff): ?>
                        <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                <?php else: ?>
                        <a class="nav-link active" href="request_document.php"><i class="bi bi-file-text"></i> Request Document</a>
                        <a class="nav-link" href="on_boarding.html"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                        <a class="nav-link" href="citizen_register.php" style="color: #e6b422;"><i class="bi bi-person-plus"></i> Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- HEADER (clean) -->
<header class="page-header">
    <div class="container">
        <h1><i class="bi bi-file-earmark-text-fill"></i> Request a Barangay Document</h1>
        <p class="lead">Fast, secure, and convenient online applications</p>
        <?php if ($is_public): ?>
                <div class="alert alert-warning d-inline-flex mt-3 rounded-pill px-4" style="background: rgba(0,0,0,0.2); border: none; color: white;">
                    <i class="bi bi-info-circle-fill me-2"></i> Guest mode — register to unlock priority & tracking
                </div>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="request-card p-4 p-md-5">
                <?php if ($success): ?>
                        <!-- SUCCESS state (UI refined but functionality untouched) -->
                        <div class="success-message">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 fw-bold">Request Submitted!</h3>
                            <p class="lead">Your document request is now in queue.</p>
                            <div class="my-4">
                                <h6 class="fw-semibold">Reference Number</h6>
                                <div class="request-number"><?= htmlspecialchars($request_number) ?></div>
                            </div>
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="alert alert-primary rounded-4">
                                        <i class="bi bi-clock-history"></i> 
                                        <strong>Est. processing:</strong> <?= htmlspecialchars($document_type['processing_days'] ?? 3) ?> business days<br>
                                        <strong>Fee:</strong> ₱<?= number_format($document_type['fee'] ?? 0, 2) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <?php if ($is_citizen): ?>
                                        <a href="citizen_dashboard.php" class="btn btn-primary px-4 me-2"><i class="bi bi-speedometer2"></i> Dashboard</a>
                                        <a href="my_request.php" class="btn btn-outline-secondary"><i class="bi bi-journal-bookmark-fill"></i> Track Request</a>
                                <?php else: ?>
                                        <p class="text-muted small">Keep your request number for follow-up.</p>
                                        <a href="request_document.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle"></i> New Request</a>
                                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house"></i> Home</a>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 pt-2 border-top text-muted small">
                                <i class="bi bi-shield-lock"></i> Data Privacy Act compliant
                            </div>
                        </div>
                <?php else: ?>
                        <!-- STEP INDICATOR -->
                        <div class="step-indicator">
                            <div class="step <?= $is_citizen ? 'completed' : 'active' ?>">
                                <div class="step-circle"><?= $is_citizen ? '<i class="bi bi-check-lg"></i>' : '1' ?></div>
                                <div class="step-label"><?= $is_citizen ? 'Verified' : 'Your Info' ?></div>
                            </div>
                            <div class="step active">
                                <div class="step-circle">2</div>
                                <div class="step-label">Document & Details</div>
                            </div>
                            <div class="step">
                                <div class="step-circle">3</div>
                                <div class="step-label">Submit</div>
                            </div>
                        </div>

                        <?php if (!empty($error)): ?>
                                <div class="alert alert-danger rounded-4 d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> <?= htmlspecialchars($error) ?>
                                </div>
                        <?php endif; ?>

                        <form method="POST" id="requestForm">
                            <!-- DOCUMENT TYPE SELECTION (enhanced layout) -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-file-earmark-richtext"></i> 
                                    <span>1. Select Document Type</span>
                                </div>
                                <div class="row g-4">
                                    <?php foreach ($document_types as $doc): ?>
                                            <div class="col-md-6">
                                                <div class="document-type-card" data-doc-id="<?= $doc['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="fw-semibold mb-0"><?= htmlspecialchars($doc['name']) ?></h5>
                                                        <span class="fee-badge">₱<?= number_format($doc['fee'], 2) ?></span>
                                                    </div>
                                                    <p class="text-secondary small mt-2"><?= htmlspecialchars($doc['description'] ?? 'Official barangay document') ?></p>
                                                    <div class="mt-2 small text-muted">
                                                        <i class="bi bi-hourglass-split"></i> <?= $doc['processing_days'] ?> business days
                                                    </div>
                                                    <input type="radio" name="document_type_id" value="<?= $doc['id'] ?>" id="doc_<?= $doc['id'] ?>" style="display: none;" required>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                                <div id="documentInfo" class="document-info" style="display: none;">
                                    <div class="d-flex gap-2 align-items-start">
                                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                                        <div id="docDetails" class="small w-100"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- USER INFO for public visitors only (exactly same fields, improved clarity) -->
                            <?php if ($is_public): ?>
                                    <div class="form-section">
                                        <div class="section-title">
                                            <i class="bi bi-person-badge"></i> 
                                            <span>2. Personal Details</span>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">First Name</label>
                                                <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="e.g., Juan">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">Last Name</label>
                                                <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="e.g., Dela Cruz">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">Mobile Number</label>
                                                <input type="tel" name="phone" class="form-control" required pattern="09[0-9]{9}" placeholder="09171234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                                <small class="text-muted">11-digit PH number</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email (optional)</label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">Complete Address</label>
                                            <textarea name="address" rows="2" class="form-control" required placeholder="House no., street, zone/sitio"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">Barangay</label>
                                            <select name="barangay_id" class="form-select" required>
                                                <option value="">Select barangay</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                        <option value="<?= $barangay['id'] ?>" <?= ($_POST['barangay_id'] ?? '') == $barangay['id'] ? 'selected' : '' ?>><?= htmlspecialchars($barangay['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                            <?php else: ?>
                                    <!-- citizen info block (soft card) -->
                                    <div class="alert alert-info-custom rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <i class="bi bi-person-check-fill fs-4 me-2"></i>
                                            <strong><?= htmlspecialchars($citizen_info['first_name'] . ' ' . $citizen_info['last_name']) ?></strong>
                                            <span class="mx-2 text-secondary">•</span>
                                            <?php
                                            $citizen_barangay = 'Not specified';
                                            if ($citizen_info['barangay_id']) {
                                                $stmt_b = $db->prepare("SELECT name FROM barangays WHERE id = ?");
                                                $stmt_b->bind_param("i", $citizen_info['barangay_id']);
                                                $stmt_b->execute();
                                                $res_b = $stmt_b->get_result();
                                                $row_b = $res_b->fetch_assoc();
                                                $citizen_barangay = $row_b['name'] ?? 'Not specified';
                                                $stmt_b->close();
                                            }
                                            ?>
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($citizen_barangay) ?>
                                        </div>
                                        <a href="citizen_profile.php" class="btn btn-sm btn-outline-primary mt-2 mt-sm-0 rounded-pill">Update Info</a>
                                    </div>
                            <?php endif; ?>

                            <!-- REQUEST DETAILS (Purpose + notes) -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>3. Request Details</span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Purpose of Request</label>
                                    <textarea name="purpose" rows="3" class="form-control" required placeholder="e.g., For employment application, scholarship, business permit..."><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                                    <div class="form-text">Clear purpose speeds up approval.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Additional Notes (optional)</label>
                                    <textarea name="additional_notes" rows="2" class="form-control" placeholder="Any special instructions or details we should know?"><?= htmlspecialchars($_POST['additional_notes'] ?? '') ?></textarea>
                                </div>

                                <!-- requirements notice -->
                                <div class="alert alert-warning d-flex rounded-4">
                                    <i class="bi bi-clipboard-check fs-4 me-3"></i>
                                    <div>
                                        <strong>Reminders:</strong> Valid ID needed upon claiming, payment of fees, allow <?= $document_types[0]['processing_days'] ?? 3 ?> business days.
                                    </div>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="privacyAgreement" required>
                                    <label class="form-check-label" for="privacyAgreement">
                                        I consent to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 for the purpose of this document request.
                                    </label>
                                </div>
                            </div>

                            <!-- Action Buttons (clean) -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 gap-3">
                                <a href="<?= $is_citizen ? 'citizen_dashboard.php' : 'index.php' ?>" class="btn btn-outline-secondary rounded-pill px-4">
                                    <i class="bi bi-arrow-left"></i> Cancel
                                </a>
                                <div class="d-flex gap-2">
                                    <button type="reset" class="btn btn-light border rounded-pill px-4">Reset</button>
                                    <button type="submit" name="submit_request" class="btn btn-primary rounded-pill px-5">
                                        <i class="bi bi-send-check"></i> Submit Request
                                    </button>
                                </div>
                            </div>
                        </form>
                <?php endif; ?>
            </div>

            <!-- Benefits banner (public only) -->
            <?php if ($is_public && !$success): ?>
                    <div class="card border-0 shadow-sm mt-4 rounded-4 overflow-hidden">
                        <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
                            <i class="bi bi-star-fill text-warning fs-1"></i>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-1">✨ Why register as a citizen?</h5>
                                <p class="mb-0 text-secondary">Priority processing (24–48 hrs), real-time tracking, and digital updates.</p>
                            </div>
                            <a href="citizen_register.php" class="btn btn-outline-primary rounded-pill px-4">Register now →</a>
                        </div>
                    </div>
            <?php endif; ?>

            <div class="text-center mt-4 text-secondary-emphasis small">
                <i class="bi bi-telephone-inbound"></i> (055) 123-4567 &nbsp;|&nbsp;
                <i class="bi bi-envelope"></i> barangay@arteche.gov.ph &nbsp;|&nbsp;
                <i class="bi bi-clock"></i> Mon-Fri 8AM-5PM
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container text-center">
        <p class="mb-1"><i class="bi bi-shield-check"></i> Arteche Barangay Services – Digital Transformation</p>
        <small>© <?= date('Y') ?> Municipality of Arteche, Eastern Samar. All rights reserved.</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function() {
        // Document selection logic (preserved original intent but improved UX)
        const docCards = document.querySelectorAll('.document-type-card');
        const docInfoDiv = document.getElementById('documentInfo');
        const docDetailsSpan = document.getElementById('docDetails');

        function selectDocumentType(docId, cardElement) {
            // deselect others
            docCards.forEach(card => {
                card.classList.remove('selected');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            cardElement.classList.add('selected');
            const radio = cardElement.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            // show info based on dataset or static mapping
            const docsMap = {
                1: { name: 'Barangay Certification', reqs: 'Valid ID, proof of residency', processing: '2 days', fee: '₱50.00' },
                2: { name: 'Barangay Indigency', reqs: 'Income statement, valid ID', processing: '3 days', fee: 'Free' }
            };
            const selected = docsMap[docId] || { name: 'Barangay Document', reqs: 'Valid ID', processing: '3 days', fee: '₱0.00' };
            if (docDetailsSpan) {
                docDetailsSpan.innerHTML = `<strong>${selected.name}</strong><br>Requirements: ${selected.reqs}<br>Processing: ${selected.processing}  |  Fee: ${selected.fee}`;
                docInfoDiv.style.display = 'block';
            }
        }

        docCards.forEach(card => {
            const radioInput = card.querySelector('input[type="radio"]');
            if (radioInput) {
                const docId = parseInt(radioInput.value);
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectDocumentType(docId, card);
                });
                if (radioInput.checked) {
                    card.classList.add('selected');
                    if (docInfoDiv) docInfoDiv.style.display = 'block';
                    const docsMap = {1:{},2:{}};
                    let name = (docId===1) ? 'Barangay Certification' : 'Barangay Indigency';
                    if(docDetailsSpan) docDetailsSpan.innerHTML = `<strong>${name}</strong><br>Requirements: Valid ID<br>Processing: ${docId===1?2:3} days`;
                    if(docInfoDiv) docInfoDiv.style.display = 'block';
                }
            }
        });

        // auto-select first if none selected
        if (docCards.length && !document.querySelector('.document-type-card.selected')) {
            const firstCard = docCards[0];
            const firstRadio = firstCard.querySelector('input[type="radio"]');
            if (firstRadio) {
                firstRadio.checked = true;
                firstCard.classList.add('selected');
                const docId = parseInt(firstRadio.value);
                if(docDetailsSpan) docDetailsSpan.innerHTML = `<strong>${docId===1?'Barangay Certification':'Barangay Indigency'}</strong><br>Processing: ${docId===1?'2 days':'3 days'}`;
                if(docInfoDiv) docInfoDiv.style.display = 'block';
            }
        }

        // form validation enhancement
        const form = document.getElementById('requestForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const privacyCheck = document.getElementById('privacyAgreement');
                if (privacyCheck && !privacyCheck.checked) {
                    e.preventDefault();
                    alert('Please agree to the Data Privacy statement before submitting.');
                    return;
                }
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                <?php if ($is_public): ?>
                    const phoneField = document.querySelector('input[name="phone"]');
                    if (phoneField && phoneField.value && !/^09[0-9]{9}$/.test(phoneField.value)) {
                        alert('Enter a valid Philippine mobile number starting with 09 and 11 digits.');
                        e.preventDefault();
                    }
                <?php endif; ?>
                form.classList.add('was-validated');
            });
        }

        // purpose character counter (optional UI enhancement)
        const purpose = document.querySelector('textarea[name="purpose"]');
        if(purpose && !document.getElementById('charCounterHint')) {
            const hint = document.createElement('div');
            hint.className = 'form-text mt-1';
            hint.id = 'charCounterHint';
            hint.innerHTML = '<i class="bi bi-chat"></i> <span id="purposeLength">0</span> characters (recommended min. 20)';
            purpose.parentNode.appendChild(hint);
            const updateCount = () => {
                const len = purpose.value.length;
                const span = document.getElementById('purposeLength');
                if(span) span.innerText = len;
            };
            purpose.addEventListener('input', updateCount);
            updateCount();
        }
    })();
</script>
</body>
</html>