<?php
// citizen_register_view.php - Registration Page
require_once '../shared/bootstrap.php';
$auth = new Auth();

// Redirect if already logged in
if ($session->isCitizenLoggedIn()) {
    header("Location: citizen_dashboard.php");
    exit;
}

// Get barangays for registration form
$db = getDB();
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Register - Arteche Citizen Portal";

?>

<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0f2a44;
            --secondary: #1f6aa5;
            --accent: #2dd4bf;
            --light-bg: #f4f7fb;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background-color: var(--light-bg);
            color: #1e2f4e;
            min-height: 100vh;
        }

        /* Glassmorphism Navbar */
        .navbar {
            background: rgba(11, 31, 51, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .navbar-brand i {
            color: #ff6b6b;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(11, 31, 51, 0.8) 0%, rgba(31, 106, 165, 0.75) 100%),
                url("../../public/assets/img/bungto_han_arteche.png") center center/cover no-repeat;
            padding: 4rem 0 3rem;
            border-bottom: 6px solid #c92a2a;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Auth Card */
        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-top: -2rem;
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border: 1.5px solid #e9edf2;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(31, 106, 165, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: #0f2a44;
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(31, 106, 165, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--secondary);
            color: var(--secondary);
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-outline-primary:hover {
            background: var(--secondary);
            color: white;
        }

        /* Validation */
        .is-valid {
            border-color: #198754 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        /* Footer */
        .auth-footer {
            background: #0a1e2c;
            color: #b6ccda;
            padding: 2rem 0;
            margin-top: auto;
        }

        .auth-footer a {
            color: var(--accent);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.8rem;
            }

            .auth-card {
                margin: 1rem;
                margin-top: -1rem;
            }
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../../public/index.html">
                <i class="bi bi-flag-fill me-1" style="color: #ffb4b4"></i>
                AR<span style="color: #ffd966">TECHE</span> · CIS
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../../public/index.html">
                    <i class="bi bi-house me-1"></i> Back to Home
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="hero-title mb-3">
                <i class="bi bi-person-plus me-2"></i> Create Account
            </h1>
            <p class="hero-subtitle">
                Register to access barangay services and request documents online
            </p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-grow-1">
        <div class="container pb-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="auth-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h4 class="mb-1">Join the Citizen Portal</h4>
                            <p class="text-muted mb-0">Fill in your information to create an account</p>
                        </div>

                        <?php if ($session->hasFlash('error')): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= $session->getFlash('error') ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form action="citizen_register.php" method="POST" id="registerForm">
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input
                                        type="text"
                                        name="first_name"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars($_SESSION['old']['first_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input
                                        type="text"
                                        name="last_name"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars($_SESSION['old']['last_name'] ?? '') ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input
                                        type="text"
                                        name="middle_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($_SESSION['old']['middle_name'] ?? '') ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Birth Date *</label>
                                    <input
                                        type="date"
                                        name="birth_date"
                                        class="form-control"
                                        required
                                        max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
                                        value="<?= htmlspecialchars($_SESSION['old']['birth_date'] ?? '') ?>">
                                </div>

                                <!-- Contact Information -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars($_SESSION['old']['email'] ?? '') ?>">
                                    <small class="text-muted">
                                        Verification code will be sent to this email.
                                    </small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mobile Number *</label>
                                    <input
                                        type="tel"
                                        name="phone"
                                        class="form-control"
                                        required
                                        pattern="09[0-9]{9}"
                                        placeholder="09XXXXXXXXX"
                                        value="<?= htmlspecialchars($_SESSION['old']['phone'] ?? '') ?>">
                                </div>

                                <!-- Address -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Barangay *</label>
                                    <select
                                        name="barangay_id"
                                        class="form-select"
                                        required>

                                        <option value="">Select Barangay</option>

                                        <?php foreach ($barangays as $b): ?>
                                            <option
                                                value="<?= $b['id'] ?>"
                                                <?= (($_SESSION['old']['barangay_id'] ?? '') == $b['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['name']) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Complete Address *</label>
                                    <textarea
                                        name="address"
                                        class="form-control"
                                        rows="2"
                                        required
                                        placeholder="House No., Street, etc."><?= htmlspecialchars($_SESSION['old']['address'] ?? '') ?></textarea>
                                </div>

                                <!-- Password -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password *</label>
                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control"
                                        required
                                        minlength="8">
                                    <small class="text-muted">
                                        Minimum 8 characters.
                                    </small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input
                                        type="password"
                                        name="confirm_password"
                                        class="form-control"
                                        required>
                                </div>

                                <!-- Terms -->
                                <div class="col-12 mb-4">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="terms"
                                            id="terms"
                                            required
                                            <?= !empty($_SESSION['old']['terms']) ? 'checked' : '' ?>>

                                        <label class="form-check-label" for="terms">
                                            I agree to the
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a>
                                            and
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="bi bi-person-plus me-2"></i>Create Account
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2">Already have an account?</p>
                            <a href="citizen_portal.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="auth-footer">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Your data is protected under the Data Privacy Act of 2012
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        <i class="bi bi-question-circle me-1"></i> Need help?
                        <a href="mailto:cis@arteche.gov.ph">Contact Support</a>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Terms content here...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Privacy policy content here...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            let hasError = false;

            // Check password match
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');

            if (password.value !== confirmPassword.value) {
                alert('Passwords do not match!');
                confirmPassword.focus();
                hasError = true;
            }

            // Validate age (minimum 13 years old)
            const birthDateInput = document.querySelector('input[name="birth_date"]');
            if (birthDateInput.value) {
                const birthDate = new Date(birthDateInput.value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age < 13) {
                    alert('You must be at least 13 years old to register.');
                    birthDateInput.focus();
                    hasError = true;
                }
            }

            if (hasError) {
                e.preventDefault();
                return false;
            }

            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating Account...';
        });

        // Phone number formatting
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0 && !value.startsWith('09')) {
                value = '09' + value.substring(2);
            }
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });

        // Real-time password match validation
        document.querySelectorAll('input[name="password"], input[name="confirm_password"]').forEach(input => {
            input.addEventListener('input', function() {
                const password = document.querySelector('input[name="password"]').value;
                const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                const confirmField = document.querySelector('input[name="confirm_password"]');

                if (password && confirmPassword) {
                    if (password === confirmPassword) {
                        confirmField.classList.remove('is-invalid');
                        confirmField.classList.add('is-valid');
                    } else {
                        confirmField.classList.remove('is-valid');
                        confirmField.classList.add('is-invalid');
                    }
                } else {
                    confirmField.classList.remove('is-valid', 'is-invalid');
                }
            });
        });
    </script>
</body>
<?php
unset($_SESSION['old']);
?>

</html>