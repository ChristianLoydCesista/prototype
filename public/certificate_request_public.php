<?php
// certificate_request_public.php
// bootstrap provides configuration and database
require_once __DIR__ . '/../app/shared/bootstrap.php';

$db = getDB();

// Get barangays for dropdown
$barangays = $db->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $resident_name = htmlspecialchars($_POST['resident_name'] ?? '');
    $certificate_type = htmlspecialchars($_POST['certificate_type'] ?? '');
    $purpose = htmlspecialchars($_POST['purpose'] ?? '');
    $contact_number = htmlspecialchars($_POST['contact_number'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $barangay_id = intval($_POST['barangay_id'] ?? 0);
    
    // Generate unique request number
    $request_number = 'CR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Use prepared statements to prevent SQL injection
    $stmt = $db->prepare("INSERT INTO certificate_requests 
            (request_number, resident_name, certificate_type, purpose, barangay_id, contact_number, email, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    
    $stmt->bind_param("ssssiss", 
        $request_number, 
        $resident_name, 
        $certificate_type, 
        $purpose, 
        $barangay_id, 
        $contact_number, 
        $email
    );
    
    if ($stmt->execute()) {
        $message = "Certificate request submitted successfully!<br>Your Request Number: <strong>$request_number</strong><br>Please keep this number for tracking.";
        $success = true;
        // Clear form
        $_POST = array();
    } else {
        $message = "Error submitting request: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Certificate - Arteche CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .certificate-card {
            max-width: 800px;
            margin: 2rem auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 15px;
            overflow: hidden;
        }
        .certificate-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }
        .step.active .step-number {
            background: #0d6efd;
        }
        .step.completed .step-number {
            background: #198754;
        }
    </style>
</head>
<body>
    <?php include '../app/shared/components/navbar.php'; ?>

    <div class="container py-4">
        <div class="certificate-card">
            <div class="certificate-header text-center">
                <h1><i class="bi bi-file-text"></i> Certificate Request</h1>
                <p class="mb-0">Municipality of Arteche, Eastern Samar</p>
            </div>
            
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php if ($success): ?>
                            <hr>
                            <p class="mb-0">
                                <small>
                                    <i class="bi bi-info-circle"></i> 
                                    You can track your request status by contacting your barangay hall.
                                </small>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Steps -->
                <div class="step-indicator">
                    <div class="step active">
                        <div class="step-number">1</div>
                        <div class="step-label">Information</div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-label">Review</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Submit</div>
                    </div>
                </div>
                
                <form method="POST" id="certificateForm">
                    <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="resident_name" class="form-control" required 
                                   value="<?= $_POST['resident_name'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Barangay</label>
                            <select name="barangay_id" class="form-select" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= (($_POST['barangay_id'] ?? '') == $b['id']) ? 'selected' : '' ?>>
                                        <?= $b['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" class="form-control" 
                                   value="<?= $_POST['contact_number'] ?? '' ?>" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= $_POST['email'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> Certificate Details</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Certificate Type</label>
                            <select name="certificate_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Barangay Clearance" <?= (($_POST['certificate_type'] ?? '') == 'Barangay Clearance') ? 'selected' : '' ?>>Barangay Clearance</option>
                                <option value="Indigency" <?= (($_POST['certificate_type'] ?? '') == 'Indigency') ? 'selected' : '' ?>>Certificate of Indigency</option>
                                <option value="Residency" <?= (($_POST['certificate_type'] ?? '') == 'Residency') ? 'selected' : '' ?>>Certificate of Residency</option>
                                <option value="Business Permit" <?= (($_POST['certificate_type'] ?? '') == 'Business Permit') ? 'selected' : '' ?>>Business Permit Application</option>
                                <option value="Other" <?= (($_POST['certificate_type'] ?? '') == 'Other') ? 'selected' : '' ?>>Other Certificate</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label required">Purpose of Certificate</label>
                        <textarea name="purpose" class="form-control" rows="4" required 
                                  placeholder="Please specify the purpose of this certificate..."><?= $_POST['purpose'] ?? '' ?></textarea>
                        <small class="text-muted">Be specific about why you need this certificate (e.g., for employment, scholarship, business registration, etc.)</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-clock-history"></i> 
                        <strong>Processing Time:</strong> Certificate requests are typically processed within 3-5 working days.
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.html" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Home
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card-footer text-muted">
                <small>
                    <i class="bi bi-shield-lock"></i> 
                    Your information is protected under Data Privacy Act of 2012.
                    For inquiries, contact your barangay hall.
                </small>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('certificateForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    valid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Update progress steps
        const steps = document.querySelectorAll('.step');
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('input', function() {
                let allFilled = true;
                const formData = new FormData(document.getElementById('certificateForm'));
                
                // Check required fields in step 1
                if (!formData.get('resident_name') || !formData.get('barangay_id') || 
                    !formData.get('certificate_type') || !formData.get('purpose')) {
                    allFilled = false;
                }
                
                if (allFilled) {
                    steps[0].classList.add('completed');
                    steps[1].classList.add('active');
                } else {
                    steps[0].classList.remove('completed');
                    steps[1].classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>