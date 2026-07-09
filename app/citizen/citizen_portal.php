<?php
// citizen_portal.php - Login Page
require_once '../shared/bootstrap.php';
$auth = new Auth();

// Auto-login via remember-me cookie
if (!$session->isCitizenLoggedIn() && isset($_COOKIE['remember_token'])) {
    $citizen = $auth->loginWithRememberToken($_COOKIE['remember_token']);
    if ($citizen) {
        $session->setCitizen($citizen);
        // Rotate token for security
        $newToken = $auth->rotateRememberToken($citizen['id']);
        $isSecure = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
        setcookie(
            'remember_token',
            $newToken,
            time() + (30 * 24 * 60 * 60),
            '/',
            '',
            $isSecure,
            true
        );
        header("Location: citizen_dashboard.php");
        exit;
    } else {
        // Invalid token – delete cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Redirect if already logged in
if ($session->isCitizenLoggedIn() && !empty($_SESSION['remember_login'])) {
    header("Location: citizen_dashboard.php");
    exit;
}

// =============================================
// ✅ RETRIEVE FLASH MESSAGES
// =============================================
$errorMessage = $session->getFlash('error');      // Gets and removes error flash
$successMessage = $session->getFlash('success');  // Gets and removes success flash

// =============================================
// ✅ RETRIEVE SUBMITTED USERNAME (if any)
// =============================================
$loginUsername = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_username']); // Clear after use

// =============================================
// ✅ REMEMBER ME PREFERENCE
// =============================================
$rememberChecked = $_COOKIE['remember_me_preference'] ?? false;
$pageTitle = "Login - Arteche Citizen Portal";
$oldLogin = $_SESSION['old_login'] ?? [];
unset($_SESSION['old_login']);
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
                <i class="bi bi-person-badge me-2"></i> Citizen Portal
            </h1>
            <p class="hero-subtitle">
                Request documents, track applications, and access barangay services online
            </p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-grow-1">
        <div class="container pb-5">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h4 class="mb-1"><i class="bi bi-box-arrow-in-right me-2"></i>Welcome Back</h4>
                            <p class="text-muted mb-0">Sign in to your account</p>
                        </div>

                        <!-- Flash Messages -->
                        <?php if ($errorMessage): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($errorMessage) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($successMessage): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($successMessage) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form action="citizen_login.php" method="POST" id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <div class="mb-4">
                                <label class="form-label">Email or Phone Number</label>
                                <input type="text" name="username" class="form-control"
                                    required placeholder="your@email.com or 09XXXXXXXXX"
                                    value="<?= htmlspecialchars($oldLogin['username'] ?? '') ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control <?= $errorMessage ? 'is-invalid' : '' ?>"
                                    required
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    <?= !empty($loginUsername) ? 'autofocus' : '' ?>>
                            </div>

                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" name="remember" id="remember"
                                    <?= !empty($oldLogin['remember']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2">Don't have an account?</p>
                            <a href="citizen_register_view.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus me-2"></i>Register Now
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>