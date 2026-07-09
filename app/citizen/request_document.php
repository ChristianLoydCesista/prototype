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
$citizen_id = 0;
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

function generateRequestNumber($prefix = 'REQ-')
{
    return $prefix . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
}

function generateUniqueRequestNumber($db, $prefix = 'REQ-', $table = 'citizen_requests')
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $request_number = generateRequestNumber($prefix);
        $checkStmt = $db->prepare("SELECT 1 FROM {$table} WHERE request_number = ?");
        if (!$checkStmt) {
            return $request_number;
        }

        $checkStmt->bind_param('s', $request_number);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$exists) {
            return $request_number;
        }
    }

    return generateRequestNumber($prefix) . '-' . mt_rand(100, 999);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    // Sanitize inputs
    $document_type_id = intval($_POST['document_type_id'] ?? 0);
    $purpose = trim($db->real_escape_string($_POST['purpose'] ?? ''));
    $additional_notes = trim($db->real_escape_string($_POST['additional_notes'] ?? ''));

    // Get document type info
    $document_type = null;
    try {
        $stmt = $db->prepare("SELECT * FROM document_types WHERE id = ?");
        $stmt->bind_param("i", $document_type_id);
        $stmt->execute();
        $doc_type_result = $stmt->get_result();
        $document_type = $doc_type_result->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("Document type lookup failed: " . $e->getMessage());
        $document_type = null;
    }

    $fallback_document_types = [
        1 => ['id' => 1, 'name' => 'Barangay Clearance', 'fee' => 50.00, 'processing_days' => 2, 'description' => 'Standard clearance for good moral character and residency verification'],
        2 => ['id' => 2, 'name' => 'Barangay Indigency', 'fee' => 0.00, 'processing_days' => 3, 'description' => 'Certificate for financial assistance programs and medical support']
    ];

    if (empty($document_type) && isset($fallback_document_types[$document_type_id])) {
        $document_type = $fallback_document_types[$document_type_id];
    }

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

            $request_number = generateUniqueRequestNumber($db, 'REQ-', 'citizen_requests');

            try {
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
            } catch (Throwable $e) {
                $error = "Failed to submit request. Please try again.";
                error_log("Citizen request insert prepare failed: " . $e->getMessage());
                $stmt = null;
            }
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
                $request_number = generateUniqueRequestNumber($db, 'PUB-', 'certificate_requests');

                try {
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
                } catch (Throwable $e) {
                    $error = "Failed to submit request. Please try again.";
                    error_log("Public request insert prepare failed: " . $e->getMessage());
                    $stmt = null;
                }
            }
        }

        if (empty($error) && isset($stmt)) {
            try {
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
                        $fee = $document_type['processing_fee'] ?? $document_type['fee'] ?? 0;
                        $message .= "Processing Fee: ₱" . number_format((float)$fee, 2) . "\n\n";
                        $message .= "You can track your request status in your dashboard.\n\n";
                        $message .= "Thank you,\nArteche Barangay Services";

                        // In production, use mail() or PHPMailer
                        error_log("DEMO: Would send email to " . $citizen_info['email'] . ": " . $subject);
                    }
                } else {
                    $error = "Failed to submit request. Please try again.";
                    error_log("Citizen request insert failed: " . $stmt->error);
                }
            } catch (Throwable $e) {
                $error = "Failed to submit request. Please try again.";
                error_log("Citizen request insert failed: " . $e->getMessage());
            }

            if (isset($stmt) && $stmt) {
                $stmt->close();
            }
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/request_document.css">

</head>

<body>
    <header class="cis-topbar">
        <div class="cis-shell cis-topbar-inner">
            <div>
                <div class="cis-brand">
                    <i class="bi bi-file-earmark-text"></i> Request Document
                </div>
                <small class="cis-subtitle">Barangay services</small>
            </div>

            <a href="<?= $is_citizen ? 'citizen_dashboard.php' : 'citizen_portal.php' ?>" class="cis-icon-btn">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </header>

    <main class="cis-shell">
        <section class="cis-hero">
            <div class="cis-hero-content">
                <span class="cis-eyebrow">Online Document Request</span>
                <h1>Request a Barangay Document</h1>
                <p>Select a document, provide your purpose, and submit your request securely.</p>
            </div>
        </section>

        <?php if (!empty($error)): ?>
            <div class="cis-alert cis-alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <section class="cis-card cis-success">
                <i class="bi bi-check-circle-fill cis-success-icon"></i>
                <h2>Request Submitted</h2>
                <p class="cis-help">Your request is now queued for processing.</p>

                <div>
                    <small class="cis-help">Reference Number</small><br>
                    <div class="cis-reference"><?= htmlspecialchars($request_number) ?></div>
                </div>

                <div class="cis-actions" style="margin-top:16px;">
                    <a href="<?= $is_citizen ? 'my_request.php' : 'request_document.php' ?>" class="cis-btn cis-btn-primary">
                        <?= $is_citizen ? 'Track Request' : 'New Request' ?>
                    </a>
                    <a href="<?= $is_citizen ? 'citizen_dashboard.php' : 'citizen_portal.php' ?>" class="cis-btn cis-btn-light">
                        Back
                    </a>
                </div>
            </section>
        <?php else: ?>
            <form method="POST" id="requestForm" class="cis-stack">
                <section class="cis-card cis-card-pad">
                    <h2 class="cis-section-title">
                        <i class="bi bi-file-earmark-richtext"></i> Select Document
                    </h2>

                    <div class="cis-grid cis-grid-2">
                        <?php foreach ($document_types as $doc): ?>
                            <label class="cis-doc-card">
                                <input type="radio" name="document_type_id" value="<?= (int)$doc['id'] ?>" hidden required>

                                <div class="cis-doc-header">
                                    <div>
                                        <div class="cis-doc-title"><?= htmlspecialchars($doc['name']) ?></div>
                                        <p class="cis-doc-desc">
                                            <?= htmlspecialchars($doc['description'] ?? 'Official barangay document') ?>
                                        </p>
                                        <div class="cis-meta">
                                            <i class="bi bi-clock"></i>
                                            <?= (int)$doc['processing_days'] ?> business days
                                        </div>
                                    </div>

                                    <span class="cis-badge">
                                        <?= ((float)$doc['fee'] > 0) ? '₱' . number_format((float)$doc['fee'], 2) : 'Free' ?>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($is_public): ?>
                    <section class="cis-card cis-card-pad">
                        <h2 class="cis-section-title">
                            <i class="bi bi-person-badge"></i> Your Information
                        </h2>

                        <div class="cis-form-grid">
                            <div class="cis-field">
                                <label class="cis-label cis-required">First Name</label>
                                <input type="text" name="first_name" class="cis-input" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                            </div>

                            <div class="cis-field">
                                <label class="cis-label cis-required">Last Name</label>
                                <input type="text" name="last_name" class="cis-input" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            </div>

                            <div class="cis-field">
                                <label class="cis-label cis-required">Mobile Number</label>
                                <input type="tel" name="phone" class="cis-input" required pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>

                            <div class="cis-field">
                                <label class="cis-label">Email</label>
                                <input type="email" name="email" class="cis-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="cis-field cis-field-full">
                                <label class="cis-label cis-required">Complete Address</label>
                                <textarea name="address" class="cis-textarea" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>

                            <div class="cis-field cis-field-full">
                                <label class="cis-label cis-required">Barangay</label>
                                <select name="barangay_id" class="cis-select" required>
                                    <option value="">Select barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?= (int)$barangay['id'] ?>" <?= ($_POST['barangay_id'] ?? '') == $barangay['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($barangay['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="cis-card cis-card-pad cis-verified-card">
                        <div class="cis-icon-soft">
                            <i class="bi bi-person-check"></i>
                        </div>

                        <div style="flex:1;">
                            <strong>
                                <?= htmlspecialchars(($citizen_info['first_name'] ?? '') . ' ' . ($citizen_info['last_name'] ?? '')) ?>
                            </strong>
                            <small class="cis-help" style="display:block;">
                                Your verified citizen information will be used for this request.
                            </small>
                        </div>

                        <a href="citizen_profile.php" class="cis-btn cis-btn-light">Update</a>
                    </section>
                <?php endif; ?>

                <section class="cis-card cis-card-pad">
                    <h2 class="cis-section-title">
                        <i class="bi bi-pencil-square"></i> Request Details
                    </h2>

                    <div class="cis-field">
                        <label class="cis-label cis-required">Purpose of Request</label>
                        <textarea name="purpose" class="cis-textarea" required placeholder="Example: For employment, scholarship, medical assistance..."><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                        <small class="cis-help">Clear purpose helps speed up validation.</small>
                    </div>

                    <div class="cis-field" style="margin-top:14px;">
                        <label class="cis-label">Additional Notes</label>
                        <textarea name="additional_notes" class="cis-textarea" placeholder="Optional instructions or details"><?= htmlspecialchars($_POST['additional_notes'] ?? '') ?></textarea>
                    </div>

                    <div class="cis-alert cis-alert-warning" style="margin-top:16px;">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>Please bring a valid ID when claiming your document. Fees may be paid at the barangay office.</div>
                    </div>

                    <label class="cis-check" style="margin-top:16px;">
                        <input type="checkbox" id="privacyAgreement" required>
                        <span>I consent to the processing of my personal data for this document request.</span>
                    </label>
                </section>

                <section class="cis-actions">
                    <a href="<?= $is_citizen ? 'citizen_dashboard.php' : 'citizen_portal.php' ?>" class="cis-btn cis-btn-light">
                        Cancel
                    </a>

                    <button type="submit" name="submit_request" class="cis-btn cis-btn-primary">
                        <i class="bi bi-send-check"></i> Submit
                    </button>
                </section>
            </form>
        <?php endif; ?>
    </main>

    <nav class="cis-bottom-nav">
        <a href="citizen_dashboard.php">
            <i class="bi bi-house"></i> Home
        </a>
        <a href="my_request.php">
            <i class="bi bi-files"></i> Requests
        </a>
        <a href="request_document.php" class="active">
            <i class="bi bi-plus-circle-fill"></i> New
        </a>
        <a href="citizen_notifications.php">
            <i class="bi bi-bell"></i> Alerts
        </a>
        <a href="citizen_profile.php">
            <i class="bi bi-person"></i> Profile
        </a>
    </nav>

    <script>
        document.querySelectorAll('.cis-doc-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.cis-doc-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');

                const input = card.querySelector('input[type="radio"]');
                if (input) input.checked = true;
            });
        });

        const form = document.getElementById('requestForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const privacy = document.getElementById('privacyAgreement');

                if (privacy && !privacy.checked) {
                    e.preventDefault();
                    alert('Please agree to the Data Privacy statement before submitting.');
                }
            });
        }
    </script>
</body>

</html>