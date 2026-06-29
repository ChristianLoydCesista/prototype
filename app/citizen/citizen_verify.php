<?php
// citizen_verify.php - VERIFICATION PAGE WITH HTML
require_once '../shared/bootstrap.php';

$session = new Session();

// Check if verification data exists in session
if (!isset($_SESSION['verification_email'])) {
    $session->setFlash('error', 'Please register first.');
    header("Location: citizen_portal.php");
    exit;
}

$email = $_SESSION['verification_email'];
$phone = $_SESSION['verification_phone'] ?? '';

// Get demo code if available (from registration)
$demo_code = $_SESSION['demo_verification_code'] ?? '';

// Handle verification code submission
$verified = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    require_once '../shared/includes/Auth.php';
    $auth = new Auth();

    $code = strtoupper(trim($_POST['verification_code']));

    if ($auth->verifyAccount($email, $code)) {
        $verified = true;
        unset($_SESSION['verification_email']);
        unset($_SESSION['verification_phone']);
        unset($_SESSION['demo_verification_code']);

        // Get citizen and log them in
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM citizens WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $citizen = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $session->setCitizen($citizen);
        $session->setFlash('success', 'Account verified successfully!');

        header("Location: citizen_dashboard.php");
        exit;
    } else {
        $session->setFlash('error', 'Invalid verification code');
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - Arteche Citizen Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }

        .verification-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .verification-header .display-1 {
            font-size: 4rem;
        }

        .verification-header h2 {
            margin-top: 15px;
            font-weight: 600;
        }

        .verification-body {
            padding: 30px;
        }

        .demo-alert {
            background: #fff8e1;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .demo-alert .d-flex {
            align-items: flex-start;
        }

        .demo-alert .fs-3 {
            font-size: 1.75rem !important;
        }

        .verification-code-display {
            font-family: 'Courier New', monospace;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 8px;
            text-align: center;
            color: #198754;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border: 2px dashed #198754;
        }

        .info-alert {
            background: #e7f3ff;
            border: 2px solid #0d6efd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .input-code {
            width: 50px;
            height: 55px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .input-code:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
            outline: none;
        }

        .input-code.is-valid {
            border-color: #198754;
            background-color: #d1e7dd;
        }

        .btn-verified {
            background: linear-gradient(135deg, #198754, #20c997);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .btn-verified:hover {
            background: linear-gradient(135deg, #146c43, #20c997);
        }

        .code-container {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .input-code {
            width: 48px;
            height: 56px;
            font-size: 1.5rem;
            text-align: center;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            font-weight: bold;
            transition: all .2s;
        }

        .input-code:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
            outline: none;
        }

        .input-code.is-valid {
            border-color: #198754;
            background: #d1e7dd;
        }

        .verification-header i {
            font-size: 40px;
        }

        .demo-alert {
            background: #fff8e1;
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 18px;
        }

        .info-alert {
            background: #e7f3ff;
            border: 1px solid #0d6efd;
            border-radius: 12px;
            padding: 18px;
        }

        .verification-code-display {
            font-family: monospace;
            font-size: 2rem;
            letter-spacing: 8px;
            font-weight: bold;
            color: #198754;
        }
    </style>
</head>

<body>

    <div class="verification-card">

        <!-- Header -->
        <div class="verification-header">
            <i class="bi bi-shield-check display-5"></i>
            <h3 class="mt-2">Verify Your Account</h3>
            <p class="mb-0 small opacity-75">
                Enter the 6-digit verification code
            </p>
        </div>

        <div class="verification-body">

            <!-- Flash Messages -->
            <?php if ($session->hasFlash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $session->getFlash('error') ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($session->hasFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $session->getFlash('success') ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <!-- Email Info -->
            <div class="info-alert text-center">
                <i class="bi bi-envelope-check text-primary fs-4"></i>
                <p class="mb-1 mt-2">
                    Verification code sent to
                </p>
                <strong><?= htmlspecialchars($email) ?></strong>
            </div>


            <!-- Demo Mode -->
            <?php if ($demo_code): ?>
                <div class="demo-alert text-center">
                    <h6 class="fw-bold text-warning mb-2">
                        <i class="bi bi-lightbulb-fill"></i> Demo Mode
                    </h6>

                    <p class="small text-muted mb-2">
                        Use the code below for testing:
                    </p>

                    <div class="verification-code-display">
                        <?= htmlspecialchars($demo_code) ?>
                    </div>

                    <button class="btn btn-sm btn-outline-warning mt-2" onclick="autoFillCode()">
                        <i class="bi bi-magic"></i> Auto Fill
                    </button>
                </div>
            <?php endif; ?>


            <!-- Verification Form -->
            <form method="POST" id="verificationForm">

                <label class="form-label text-center w-100 mb-3">
                    Enter verification code
                </label>

                <div class="code-container">

                    <?php for ($i = 1; $i <= 6; $i++): ?>

                        <input type="text" class="input-code" maxlength="1" data-index="<?= $i ?>"
                            oninput="handleInput(this)" onkeydown="handleKeydown(event,this)" autocomplete="off">

                    <?php endfor; ?>

                </div>

                <input type="hidden" name="verification_code" id="fullCode">

                <button class="btn btn-primary w-100 btn-lg mt-4">
                    <i class="bi bi-check-circle me-2"></i>
                    Verify Account
                </button>

            </form>


            <!-- Resend -->
            <div class="text-center mt-4">

                <small class="text-muted">
                    Didn't receive the code?
                </small>

                <br>

                <button class="btn btn-link p-0" onclick="resendCode()" id="resendBtn">

                    Resend Code
                </button>

                <span id="countdown" class="text-muted small ms-2"></span>

            </div>


            <!-- Back -->
            <div class="text-center mt-3">
                <a href="citizen_portal.php" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Portal
                </a>
            </div>

        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill demo code if available
        document.addEventListener('DOMContentLoaded', function () {
            const firstInput = document.querySelector('.input-code');
            if (firstInput) {
                firstInput.focus();
            }

            <?php if ($demo_code): ?>
                setTimeout(() => {
                    autoFillCode();
                }, 500);
            <?php endif; ?>
        });

        function autoFillCode() {
            <?php if ($demo_code): ?>
                const demoCode = "<?= $demo_code ?>";
                const inputs = document.querySelectorAll('.input-code');

                if (demoCode.length === 6) {
                    inputs.forEach((input, index) => {
                        if (index < demoCode.length) {
                            input.value = demoCode[index];
                            input.classList.add('is-valid');
                        }
                    });
                    document.getElementById('fullCode').value = demoCode;
                }
            <?php endif; ?>
        }

        function handleInput(input) {
            const value = input.value;

            // Only allow numbers
            if (!/^\d*$/.test(value)) {
                input.value = '';
                return;
            }

            // Move to next input
            if (value.length === 1) {
                const nextInput = input.nextElementSibling;
                if (nextInput && nextInput.classList.contains('input-code')) {
                    nextInput.focus();
                }
            }

            updateFullCode();
        }

        function handleKeydown(event, input) {
            // Handle backspace - move to previous input
            if (event.key === 'Backspace' && input.value.length === 0) {
                const prevInput = input.previousElementSibling;
                if (prevInput && prevInput.classList.contains('input-code')) {
                    prevInput.focus();
                }
            }

            // Handle left arrow
            if (event.key === 'ArrowLeft') {
                const prevInput = input.previousElementSibling;
                if (prevInput && prevInput.classList.contains('input-code')) {
                    prevInput.focus();
                }
            }

            // Handle right arrow
            if (event.key === 'ArrowRight') {
                const nextInput = input.nextElementSibling;
                if (nextInput && nextInput.classList.contains('input-code')) {
                    nextInput.focus();
                }
            }

            updateFullCode();
        }

        function updateFullCode() {
            let fullCode = '';
            document.querySelectorAll('.input-code').forEach(input => {
                fullCode += input.value;
            });
            document.getElementById('fullCode').value = fullCode;
        }

        // Form submission
        document.getElementById('verificationForm').addEventListener('submit', function (e) {
            const fullCode = document.getElementById('fullCode').value;
            if (fullCode.length !== 6) {
                e.preventDefault();
                document.querySelector('.input-code').focus();
            }
        });

        // Resend code
        function resendCode() {
            if (confirm('A new verification code will be sent to your email.\n\nClick OK to continue.')) {
                location.reload();
            }
        }
    </script>
</body>

</html>