<?php
// central bootstrap handles configuration, session, DB and common classes
require_once __DIR__ . '/../../shared/bootstrap.php';
// $conn and $auth are now available from bootstrap

$conn = getDB();
$auth = new Auth();

// ============================================
// AUTHENTICATION CHECK - SUPPORTS BOTH STAFF & CITIZENS
// ============================================
$is_staff = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$is_citizen = isset($_SESSION['citizen_id']) && $_SESSION['citizen_id'] > 0;
$is_logged_in = $is_staff || $is_citizen;

// Redirect if not logged in
if (!$is_logged_in) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: ../../public/on_boarding.html");
    exit;
}

// Include risk score calculator
require_once __DIR__ . '/../utils/risk_score.php';

// ============================================
// USER ROLE HANDLING
// ============================================
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';
$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$username = $_SESSION['username'] ?? 'Admin';
$user_id = $_SESSION['user_id'] ?? 0;

// For citizens, get their barangay from citizens table
if ($is_citizen) {
    $citizen_id = $_SESSION['citizen_id'];
    $stmt = $conn->prepare("SELECT barangay_id, first_name, last_name FROM citizens WHERE id = ?");
    $stmt->bind_param("i", $citizen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($citizen = $result->fetch_assoc()) {
        $admin_barangay_id = $citizen['barangay_id'];
        $username = $citizen['first_name'] . ' ' . $citizen['last_name'];
    }
    $stmt->close();
}

// Security: Non-super-admin must have assigned barangay
if (!$is_super_admin && !$admin_barangay_id) {
    session_destroy();
    header("Location: ../admin_login.php?error=no_barangay_assigned");
    exit;
}

// Determine if editing existing household
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$household = null;

// If editing, fetch household data with access control
if ($edit_id > 0) {
    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT * FROM households WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM households WHERE id = ? AND barangay_id = ?");
        $stmt->bind_param("ii", $edit_id, $admin_barangay_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $household = $result->fetch_assoc();
    
    if (!$household) {
        header("Location: dashboard.php?error=household_not_found");
        exit;
    }
}

// Get barangays for dropdown (with proper access control)
if ($is_super_admin) {
    $barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->bind_param("i", $admin_barangay_id);
    $stmt->execute();
    $barangays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
$success_message = $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
        $error_message = "Invalid form submission.";
    } else {

        $required_fields = ['name', 'age', 'sex', 'civil_status', 'household_size', 'income_monthly'];
        $missing = [];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing[] = ucfirst(str_replace('_', ' ', $field));
            }
        }

        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {

            $age = intval($_POST['age']);
            $household_size = intval($_POST['household_size']);
            $income = floatval($_POST['income_monthly']);

            if ($age < 0 || $age > 120) {
                $error_message = "Invalid age.";
            }

            if ($household_size < 1) {
                $error_message = "Household size must be at least 1.";
            }

            if ($income < 0) {
                $error_message = "Income cannot be negative.";
            }

            if (!in_array($_POST['sex'], ['Male', 'Female'])) {
                $error_message = "Invalid sex value.";
            }

            if (!in_array($_POST['civil_status'], ['Single', 'Married', 'Widowed', 'Separated'])) {
                $error_message = "Invalid civil status.";
            }

            if (!in_array($_POST['four_ps'] ?? 'No', ['Yes', 'No'])) {
                $error_message = "Invalid 4Ps status.";
            }

            if (!in_array($_POST['disability'] ?? 'No', ['Yes', 'No'])) {
                $error_message = "Invalid disability status.";
            }

            if (!in_array($_POST['senior_citizen'] ?? 'No', ['Yes', 'No'])) {
                $error_message = "Invalid senior citizen status.";
            }

            if (!empty($_POST['contact_number'])) {
                if (!preg_match('/^(09|\+639)\d{9}$/', $_POST['contact_number'])) {
                    $error_message = "Invalid contact number.";
                }
            }

            if (!empty($_POST['vulnerability_index'])) {
                $vi = intval($_POST['vulnerability_index']);
                if ($vi < 0 || $vi > 10) {
                    $error_message = "Vulnerability index must be between 0 and 10.";
                }
            }

            if (!empty($_POST['latitude'])) {
                $lat = floatval($_POST['latitude']);
                if ($lat < -90 || $lat > 90) {
                    $error_message = "Latitude must be between -90 and 90.";
                }
            }

            if (!empty($_POST['longitude'])) {
                $lng = floatval($_POST['longitude']);
                if ($lng < -180 || $lng > 180) {
                    $error_message = "Longitude must be between -180 and 180.";
                }
            }

            if (empty($error_message)) {

                $barangay_id = $is_super_admin ? intval($_POST['barangay_id']) : $admin_barangay_id;

                $barangay_stmt = $conn->prepare("SELECT name FROM barangays WHERE id=?");
                $barangay_stmt->bind_param("i", $barangay_id);
                $barangay_stmt->execute();
                $barangay_result = $barangay_stmt->get_result();
                $barangay_row = $barangay_result->fetch_assoc();

                $barangay_name = $barangay_row['name'] ?? 'Unknown';
                $identifier = trim($_POST['household_identifier'] ?? '');

                if ($identifier === '' || $identifier === 'Will be auto generated') {
                    $identifier = generateHouseholdIdentifier($barangay_id);
                }

                $data = [
                    'household_identifier' => $edit_id > 0
                        ? $_POST['household_identifier']
                        : generateHouseholdIdentifier($barangay_id),
                    'barangay_id' => $barangay_id,
                    'name' => trim($_POST['name']),
                    'contact_number' => trim($_POST['contact_number'] ?? ''),
                    'age' => $age,
                    'sex' => $_POST['sex'],
                    'civil_status' => $_POST['civil_status'],
                    'household_size' => $household_size,
                    'income_monthly' => $income,
                    'income_per_capita' => $income / max(1, $household_size),
                    'income_source' => trim($_POST['income_source'] ?? ''),
                    'four_ps' => $_POST['four_ps'] ?? 'No',
                    'housing_type' => trim($_POST['housing_type'] ?? ''),
                    'water_source' => trim($_POST['water_source'] ?? ''),
                    'toilet_type' => trim($_POST['toilet_type'] ?? ''),
                    'employment' => trim($_POST['employment'] ?? ''),
                    'disability' => $_POST['disability'] ?? 'No',
                    'senior_citizen' => $_POST['senior_citizen'] ?? 'No',
                    'vulnerability_index' => intval($_POST['vulnerability_index'] ?? 0),
                    'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
                    'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
                    'surveyed_by' => $username,
                    'surveyed_date' => $_POST['surveyed_date'] ?? date('Y-m-d'),
                    'notes' => trim($_POST['notes'] ?? ''),
                    'barangay' => $barangay_name,
                    'survey_date' => $_POST['surveyed_date'] ?? date('Y-m-d'),
                    'date_submitted' => date('Y-m-d H:i:s'),
                    'risk_score' => 0
                ];

                $conn->begin_transaction();

                try {

                    if ($edit_id > 0) {

                        $sql = "UPDATE households SET
                        household_identifier=?,
                        name=?,
                        contact_number=?,
                        age=?,
                        sex=?,
                        civil_status=?,
                        household_size=?,
                        income_monthly=?,
                        income_per_capita=?,
                        income_source=?,
                        four_ps=?,
                        housing_type=?,
                        water_source=?,
                        toilet_type=?,
                        employment=?,
                        disability=?,
                        senior_citizen=?,
                        vulnerability_index=?,
                        latitude=?,
                        longitude=?,
                        surveyed_by=?,
                        surveyed_date=?,
                        notes=?,
                        barangay=?,
                        survey_date=?";

                        if ($is_super_admin) {
                            $sql .= ", barangay_id=?";
                        }

                        $sql .= " WHERE id=?";

                        $stmt = $conn->prepare($sql);

                        if ($is_super_admin) {

                            $stmt->bind_param(
                                "sssissiddsssssssidddsssiiii",
                                $data['household_identifier'],
                                $data['name'],
                                $data['contact_number'],
                                $data['age'],
                                $data['sex'],
                                $data['civil_status'],
                                $data['household_size'],
                                $data['income_monthly'],
                                $data['income_per_capita'],
                                $data['income_source'],
                                $data['four_ps'],
                                $data['housing_type'],
                                $data['water_source'],
                                $data['toilet_type'],
                                $data['employment'],
                                $data['disability'],
                                $data['senior_citizen'],
                                $data['vulnerability_index'],
                                $data['latitude'],
                                $data['longitude'],
                                $data['surveyed_by'],
                                $data['surveyed_date'],
                                $data['notes'],
                                $data['barangay'],
                                $data['survey_date'],
                                $data['barangay_id'],
                                $edit_id
                            );

                        } else {

                            $stmt->bind_param(
                                "sssissiddsssssssidddsssii",
                                $data['household_identifier'],
                                $data['name'],
                                $data['contact_number'],
                                $data['age'],
                                $data['sex'],
                                $data['civil_status'],
                                $data['household_size'],
                                $data['income_monthly'],
                                $data['income_per_capita'],
                                $data['income_source'],
                                $data['four_ps'],
                                $data['housing_type'],
                                $data['water_source'],
                                $data['toilet_type'],
                                $data['employment'],
                                $data['disability'],
                                $data['senior_citizen'],
                                $data['vulnerability_index'],
                                $data['latitude'],
                                $data['longitude'],
                                $data['surveyed_by'],
                                $data['surveyed_date'],
                                $data['notes'],
                                $data['barangay'],
                                $data['survey_date'],
                                $edit_id
                            );
                        }

                        $stmt->execute();
                        $household_id = $edit_id;

                    } else {

                        $sql = "INSERT INTO households (
                        household_identifier,
                        barangay_id,
                        name,
                        contact_number,
                        age,
                        sex,
                        civil_status,
                        household_size,
                        income_monthly,
                        income_per_capita,
                        income_source,
                        four_ps,
                        housing_type,
                        water_source,
                        toilet_type,
                        employment,
                        disability,
                        senior_citizen,
                        vulnerability_index,
                        latitude,
                        longitude,
                        surveyed_by,
                        surveyed_date,
                        notes,
                        barangay,
                        survey_date,
                        date_submitted,
                        risk_score
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "sississiddsssssssidddsssssi",
                            $data['household_identifier'],
                            $data['barangay_id'],
                            $data['name'],
                            $data['contact_number'],
                            $data['age'],
                            $data['sex'],
                            $data['civil_status'],
                            $data['household_size'],
                            $data['income_monthly'],
                            $data['income_per_capita'],
                            $data['income_source'],
                            $data['four_ps'],
                            $data['housing_type'],
                            $data['water_source'],
                            $data['toilet_type'],
                            $data['employment'],
                            $data['disability'],
                            $data['senior_citizen'],
                            $data['vulnerability_index'],
                            $data['latitude'],
                            $data['longitude'],
                            $data['surveyed_by'],
                            $data['surveyed_date'],
                            $data['notes'],
                            $data['barangay'],
                            $data['survey_date'],
                            $data['risk_score']
                        );

                        $stmt->execute();
                        $household_id = $conn->insert_id;
                    }

                    $risk_calculator = new RiskScoreCalculator($conn);
                    $risk_score = $risk_calculator->calculateHouseholdRisk($household_id);

                    $update = $conn->prepare("UPDATE households SET risk_score=? WHERE id=?");
                    $update->bind_param("ii", $risk_score, $household_id);
                    $update->execute();

                    $conn->commit();

                    $success_message = "Household saved successfully. Risk Score: " . $risk_score;

                } catch (Exception $e) {

                    $conn->rollback();
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Function to generate household identifier
function generateHouseholdIdentifier($barangay_id) {
    global $conn;
    
    // Get barangay code (first 3 letters of barangay name)
    $stmt = $conn->prepare("SELECT UPPER(SUBSTRING(name, 1, 3)) as code FROM barangays WHERE id = ?");
    $stmt->bind_param("i", $barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $barangay = $result->fetch_assoc();
    $code = $barangay['code'] ?? 'BRG';
    
    // Generate random number
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    return $code . '-' . $date . '-' . $random;
}

// Predefined options for dropdowns
$civil_status_options = ['Single', 'Married', 'Widowed', 'Separated'];
$income_sources = ['Farming', 'Fishing', 'Private Employee', 'Government Employee', 'Self-Employed', 'Business', 'Pension', 'Others'];
$housing_types = ['Concrete', 'Wood', 'Makeshift', 'Semi-Concrete', 'Others'];
$water_sources = [
    'Level I (Point Source)',
    'Level II (Communal Faucet)',
    'Level III (Waterworks System)',
    'Deep Well',
    'Others'
];
$toilet_types = ['Water-sealed', 'Antipolo', 'None', 'Others'];
$employment_status = ['Employed', 'Unemployed', 'Self-Employed', 'Student', 'Retired', 'Others'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Household Survey - Arteche CI System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            border-left: 5px solid #0d6efd;
            padding-left: 15px;
            margin: 25px 0 20px;
            font-weight: 600;
            color: #333;
        }
        
        .required-field::after {
            content: " *";
            color: red;
            font-weight: bold;
        }
        
        #location-map {
            height: 300px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .risk-indicator {
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }
        
        .risk-low {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .risk-medium {
            background: #fff3cd;
            color: #856404;
            border-left: 5px solid #ffc107;
        }
        
        .risk-high {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .coordinate-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-geo-alt"></i> Arteche Community Intelligence System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-white">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($username) ?>
                    <?php if ($is_staff && $is_super_admin): ?>
                        <span class="badge bg-warning">Super Admin</span>
                    <?php elseif ($is_staff): ?>
                        <span class="badge bg-info">Barangay Admin</span>
                    <?php elseif ($is_citizen): ?>
                        <span class="badge bg-success">Citizen</span>
                    <?php endif; ?>
                </span>
                <a class="nav-item nav-link text-white" href="../../shared/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><?= $edit_id ? 'Edit' : 'Add' ?> Household Survey</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-house-add"></i> 
                <?= $edit_id ? 'Edit Household' : 'Add New Household' ?>
            </h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <div class="form-container">
            <form method="POST" id="surveyForm" onsubmit="return validateForm()">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <!-- Household Information -->
                <h4 class="section-title">
                    <i class="bi bi-info-circle"></i> Household Information
                </h4>
                
                <div class="row g-3">
                    <?php if ($is_super_admin): ?>
                    <!-- Barangay Selection (Super Admin only) -->
                    <div class="col-md-6">
                        <label for="barangay_id" class="form-label required-field">Barangay</label>
                        <select class="form-select" id="barangay_id" name="barangay_id" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($barangays as $b): ?>
                                <option value="<?= $b['id'] ?>" 
                                    <?= ($household['barangay_id'] ?? $admin_barangay_id) == $b['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label for="household_identifier" class="form-label">Household ID</label>
                        <input type="text" class="form-control" id="household_identifier" 
                               value="<?= htmlspecialchars($household['household_identifier'] ?? '') ?>" 
                               placeholder="Auto-generated if empty" readonly>
                        <small class="text-muted">Will be auto-generated if left empty</small>
                    </div>

                    <div class="col-md-6">
                        <label for="name" class="form-label required-field">Head of Household</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($household['name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                               value="<?= htmlspecialchars($household['contact_number'] ?? '') ?>"
                               placeholder="0912 345 6789">
                    </div>

                    <div class="col-md-3">
                        <label for="age" class="form-label required-field">Age</label>
                        <input type="number" class="form-control" id="age" name="age" 
                               value="<?= htmlspecialchars($household['age'] ?? '') ?>" 
                               min="0" max="120" required>
                    </div>

                    <div class="col-md-3">
                        <label for="sex" class="form-label required-field">Sex</label>
                        <select class="form-select" id="sex" name="sex" required>
                            <option value="">Select</option>
                            <option value="Male" <?= ($household['sex'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($household['sex'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="civil_status" class="form-label required-field">Civil Status</label>
                        <select class="form-select" id="civil_status" name="civil_status" required>
                            <option value="">Select</option>
                            <?php foreach ($civil_status_options as $status): ?>
                                <option value="<?= $status ?>" <?= ($household['civil_status'] ?? '') == $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="household_size" class="form-label required-field">Household Size</label>
                        <input type="number" class="form-control" id="household_size" name="household_size" 
                               value="<?= htmlspecialchars($household['household_size'] ?? '') ?>" 
                               min="1" required>
                    </div>
                </div>

                <!-- Economic Information -->
                <h4 class="section-title mt-4">
                    <i class="bi bi-cash"></i> Economic Information
                </h4>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="income_monthly" class="form-label required-field">Monthly Income (₱)</label>
                        <input type="number" class="form-control" id="income_monthly" name="income_monthly" 
                               value="<?= htmlspecialchars($household['income_monthly'] ?? '') ?>" 
                               min="0" step="0.01" required>
                    </div>

                    <div class="col-md-4">
                        <label for="income_source" class="form-label">Primary Income Source</label>
                        <select class="form-select" id="income_source" name="income_source">
                            <option value="">Select</option>
                            <?php foreach ($income_sources as $source): ?>
                                <option value="<?= $source ?>" <?= ($household['income_source'] ?? '') == $source ? 'selected' : '' ?>>
                                    <?= $source ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="four_ps" class="form-label">4Ps Beneficiary</label>
                        <select class="form-select" id="four_ps" name="four_ps">
                            <option value="No" <?= ($household['four_ps'] ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= ($household['four_ps'] ?? '') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="employment" class="form-label">Employment Status</label>
                        <select class="form-select" id="employment" name="employment">
                            <option value="">Select</option>
                            <?php foreach ($employment_status as $status): ?>
                                <option value="<?= $status ?>" <?= ($household['employment'] ?? '') == $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Living Conditions -->
                <h4 class="section-title mt-4">
                    <i class="bi bi-house"></i> Living Conditions
                </h4>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="housing_type" class="form-label">Housing Type</label>
                        <select class="form-select" id="housing_type" name="housing_type">
                            <option value="">Select</option>
                            <?php foreach ($housing_types as $type): ?>
                                <option value="<?= $type ?>" <?= ($household['housing_type'] ?? '') == $type ? 'selected' : '' ?>>
                                    <?= $type ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="water_source" class="form-label">Water Source</label>
                        <select class="form-select" id="water_source" name="water_source">
                            <option value="">Select</option>
                            <?php foreach ($water_sources as $source): ?>
                                <option value="<?= $source ?>" <?= ($household['water_source'] ?? '') == $source ? 'selected' : '' ?>>
                                    <?= $source ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="toilet_type" class="form-label">Toilet Type</label>
                        <select class="form-select" id="toilet_type" name="toilet_type">
                            <option value="">Select</option>
                            <?php foreach ($toilet_types as $type): ?>
                                <option value="<?= $type ?>" <?= ($household['toilet_type'] ?? '') == $type ? 'selected' : '' ?>>
                                    <?= $type ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Vulnerability Indicators -->
                <h4 class="section-title mt-4">
                    <i class="bi bi-exclamation-triangle"></i> Vulnerability Indicators
                </h4>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="disability" class="form-label">Has Disability</label>
                        <select class="form-select" id="disability" name="disability">
                            <option value="No" <?= ($household['disability'] ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= ($household['disability'] ?? '') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="senior_citizen" class="form-label">Has Senior Citizen</label>
                        <select class="form-select" id="senior_citizen" name="senior_citizen">
                            <option value="No" <?= ($household['senior_citizen'] ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= ($household['senior_citizen'] ?? '') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="vulnerability_index" class="form-label">Vulnerability Index</label>
                        <input type="number" class="form-control" id="vulnerability_index" name="vulnerability_index" 
                               value="<?= htmlspecialchars($household['vulnerability_index'] ?? '0') ?>" 
                               min="0" max="10">
                        <small class="text-muted">0-10 (higher = more vulnerable)</small>
                    </div>
                </div>

                <!-- Location Information -->
                <h4 class="section-title mt-4">
                    <i class="bi bi-geo-alt"></i> Location Information
                </h4>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="latitude" class="form-label">Latitude</label>
                        <input type="number" class="form-control" id="latitude" name="latitude" 
                               value="<?= htmlspecialchars($household['latitude'] ?? '') ?>" 
                               step="any" placeholder="e.g., 12.2692022">
                    </div>

                    <div class="col-md-3">
                        <label for="longitude" class="form-label">Longitude</label>
                        <input type="number" class="form-control" id="longitude" name="longitude" 
                               value="<?= htmlspecialchars($household['longitude'] ?? '') ?>" 
                               step="any" placeholder="e.g., 125.3714274">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary w-100" id="getLocationBtn">
                            <i class="bi bi-geo"></i> Use My Current Location
                        </button>
                    </div>
                </div>

                <!-- Map for location selection -->
                <div class="mt-3">
                    <div id="location-map"></div>
                    <p class="coordinate-hint">
                        <i class="bi bi-info-circle"></i> Click on the map to set coordinates, or enter them manually above.
                    </p>
                </div>

                <!-- Survey Information -->
                <h4 class="section-title mt-4">
                    <i class="bi bi-clipboard"></i> Survey Information
                </h4>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="surveyed_date" class="form-label">Survey Date</label>
                        <input type="date" class="form-control" id="surveyed_date" name="surveyed_date" 
                               value="<?= htmlspecialchars($household['surveyed_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($household['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Risk Score Preview -->
                <div id="riskIndicator" class="risk-indicator">
                    <h5><i class="bi bi-shield-check"></i> Estimated Risk Score: <span id="riskScore">0</span></h5>
                    <p id="riskDescription">Complete the form to see risk assessment</p>
                </div>

                <!-- Form Actions -->
                <hr class="my-4">

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="dashboard.php" class="btn btn-secondary me-md-2">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> <?= $edit_id ? 'Update' : 'Save' ?> Household
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Map Integration
        <?php if (!empty($household['latitude']) && !empty($household['longitude'])): ?>
            var defaultLat = <?= $household['latitude'] ?>;
            var defaultLng = <?= $household['longitude'] ?>;
            var defaultZoom = 18;
        <?php else: ?>
            // Default to Arteche center
            var defaultLat = 12.2660;
            var defaultLng = 125.3690;
            var defaultZoom = 12;
        <?php endif; ?>

        // Initialize map
        var map = L.map('location-map').setView([defaultLat, defaultLng], defaultZoom);

        // Add satellite imagery
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri',
            maxZoom: 20
        }).addTo(map);

        // Add marker
        var marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);

        // Update form fields when marker is dragged
        marker.on('dragend', function(e) {
            var position = marker.getLatLng();
            document.getElementById('latitude').value = position.lat.toFixed(7);
            document.getElementById('longitude').value = position.lng.toFixed(7);
        });

        // Update marker when clicking on map
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
        });

        // Update marker when coordinates are manually entered
        document.getElementById('latitude').addEventListener('change', updateMarkerFromInputs);
        document.getElementById('longitude').addEventListener('change', updateMarkerFromInputs);

        function updateMarkerFromInputs() {
            var lat = parseFloat(document.getElementById('latitude').value);
            var lng = parseFloat(document.getElementById('longitude').value);
            
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 18);
            }
        }

        // Geolocation function
        document.getElementById('getLocationBtn').addEventListener('click', getCurrentLocation);

        function getCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }

            const btn = document.getElementById('getLocationBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-geo"></i> Getting Location...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    document.getElementById('latitude').value = lat.toFixed(7);
                    document.getElementById('longitude').value = lng.toFixed(7);
                    
                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 18);
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo"></i> Use My Current Location';
                },
                (error) => {
                    let message = 'Unable to retrieve your location. ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message += 'Location access denied by user.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message += 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            message += 'Location request timed out.';
                            break;
                        default:
                            message += 'An unknown error occurred.';
                            break;
                    }
                    alert(message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo"></i> Use My Current Location';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                }
            );
        }

        // Form validation
        function validateForm() {
            var householdSize = parseInt(document.getElementById('household_size').value);
            var age = parseInt(document.getElementById('age').value);
            
            if (age < 0 || age > 120) {
                alert('Please enter a valid age (0-120)');
                return false;
            }
            
            if (householdSize < 1) {
                alert('Household size must be at least 1');
                return false;
            }
            
            return true;
        }

        // Real-time risk calculation (simplified preview)
        document.getElementById('income_monthly').addEventListener('input', updateRiskPreview);
        document.getElementById('household_size').addEventListener('input', updateRiskPreview);
        document.getElementById('four_ps').addEventListener('change', updateRiskPreview);
        document.getElementById('disability').addEventListener('change', updateRiskPreview);
        document.getElementById('senior_citizen').addEventListener('change', updateRiskPreview);

        function updateRiskPreview() {
            var income = parseFloat(document.getElementById('income_monthly').value) || 0;
            var size = parseInt(document.getElementById('household_size').value) || 1;
            var fourPs = document.getElementById('four_ps').value === 'Yes';
            var disability = document.getElementById('disability').value === 'Yes';
            var senior = document.getElementById('senior_citizen').value === 'Yes';
            
            var perCapita = income / size;
            var score = 0;
            
            // Income factor (0-40 points)
            if (perCapita < 1000) score += 40;
            else if (perCapita < 2000) score += 30;
            else if (perCapita < 3000) score += 20;
            else if (perCapita < 5000) score += 10;
            
            // Household size factor (0-20 points)
            if (size > 8) score += 20;
            else if (size > 6) score += 15;
            else if (size > 4) score += 10;
            else if (size > 2) score += 5;
            
            // Vulnerability factors (0-40 points)
            if (!fourPs) score += 15; // Not in 4Ps increases risk
            if (disability) score += 15;
            if (senior) score += 10;
            
            // Cap at 100
            score = Math.min(100, score);
            
            // Update display
            var indicator = document.getElementById('riskIndicator');
            var scoreSpan = document.getElementById('riskScore');
            var description = document.getElementById('riskDescription');
            
            scoreSpan.textContent = score;
            
            // Remove existing classes
            indicator.classList.remove('risk-low', 'risk-medium', 'risk-high');
            
            // Add appropriate class and description
            if (score <= 30) {
                indicator.classList.add('risk-low');
                description.textContent = 'Low Risk - Household shows good economic indicators';
            } else if (score <= 60) {
                indicator.classList.add('risk-medium');
                description.textContent = 'Medium Risk - Household shows moderate vulnerability';
            } else {
                indicator.classList.add('risk-high');
                description.textContent = 'High Risk - Household requires immediate assistance';
            }
            
            indicator.style.display = 'block';
        }

        // Auto-generate household identifier if empty
        document.getElementById('barangay_id')?.addEventListener('change', function() {
            var householdId = document.getElementById('household_identifier');
            if (!householdId.value) {
                householdId.value = 'Will be auto-generated';
            }
        });

        // Trigger risk preview on page load if editing
        <?php if ($edit_id): ?>
            window.onload = function() {
                updateRiskPreview();
            };
        <?php endif; ?>
    </script>
</body>

</html>
<?php $conn->close()?>