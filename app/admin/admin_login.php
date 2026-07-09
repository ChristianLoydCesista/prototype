<?php
require_once __DIR__ . '/../shared/bootstrap.php';

if (isset($_SESSION['admin'])) {
    header("Location: shared/dashboard.php");
    exit;
}

// Generate CSRF Token if it doesn't exist
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

$conn = getDB();
$error = $_SESSION['db_error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$conn) {
        $error = $_SESSION['db_error'] ?? 'Unable to connect to the database. Please try again later.';
    } else {
        // 2. Validate CSRF Token
        $posted_token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['_csrf_token'], $posted_token)) {
            // Log this attempt as it could be malicious
            error_log("CSRF token mismatch detected from IP: " . $_SERVER['REMOTE_ADDR']);
            die("Invalid request signature. Please refresh the page and try again.");
        }

        // Extract inputs (do NOT use htmlspecialchars here, prepared statements handle safety)
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // 3. Fail early if empty
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            $sql = "SELECT u.*, b.name as barangay_name 
                FROM users u 
                LEFT JOIN barangays b ON u.barangay_id = b.id 
                WHERE u.username = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $passwordValid = false;

                    if (str_starts_with($user['password'], '$2y$')) {
                        $passwordValid = password_verify($password, $user['password']);
                    } else {
                        // Legacy MD5 check - migrate to bcrypt
                        if (md5($password) === $user['password']) {
                            $passwordValid = true;
                            $newHash = password_hash($password, PASSWORD_DEFAULT); // PASSWORD_DEFAULT is safer than hardcoding BCRYPT
                            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param("si", $newHash, $user['id']);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }
                        }
                    }

                    if ($passwordValid) {
                        // 4. Prevent Session Fixation
                        session_regenerate_id(true);

                        // Set session variables
                        $_SESSION['admin'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['barangay_id'] = $user['barangay_id'];
                        $_SESSION['barangay_name'] = $user['barangay_name'] ?? 'Super Admin';

                        // Log login activity
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'Login', 'User logged in', ?)");
                        if ($logStmt) {
                            $logStmt->bind_param("is", $user['id'], $ip);
                            $logStmt->execute();
                            $logStmt->close();
                        }

                        header("Location: shared/dashboard.php");
                        exit;
                    } else {
                        // Generic error to prevent user enumeration
                        $error = "Invalid username or password!";
                    }
                } else {
                    $error = "Invalid username or password!";
                }
                $stmt->close();
            } else {
                // Fail safely without exposing database errors
                error_log("Database prepare failed: " . $conn->error);
                $error = "A system error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Arteche CI System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0f2a44;
            --secondary: #1f6aa5;
            --accent: #2dd4bf;
            --danger: #c92a2a;
            --light-bg: #f4f7fb;
            --text: #1e2f4e;
            --muted: #6c7a89;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: var(--light-bg);
            color: var(--text);
            min-height: 100vh;
        }

        .admin-navbar {
            background: rgba(11, 31, 51, 0.88);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.16);
        }

        .admin-brand {
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #fff;
            text-decoration: none;
        }

        .admin-brand:hover {
            color: #fff;
        }

        .admin-brand .brand-highlight {
            color: #ffd966;
        }

        .admin-nav-link {
            color: rgba(255, 255, 255, 0.86);
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .admin-nav-link:hover {
            color: #fff;
        }

        .admin-hero {
            background:
                linear-gradient(135deg, rgba(11, 31, 51, 0.86), rgba(31, 106, 165, 0.78)),
                url("../../public/assets/img/bungto_han_arteche.png") center center / cover no-repeat;
            padding: 4rem 0 3.25rem;
            border-bottom: 6px solid var(--danger);
        }

        .hero-title {
            color: #fff;
            font-size: clamp(1.9rem, 4vw, 2.7rem);
            font-weight: 800;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.05rem;
        }

        .login-card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 14px 45px rgba(15, 42, 68, 0.12);
            margin-top: -2.3rem;
            overflow: hidden;
            border: 1px solid rgba(15, 42, 68, 0.06);
        }

        .login-icon {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #fff;
            font-size: 1.55rem;
            box-shadow: 0 10px 24px rgba(31, 106, 165, 0.25);
        }

        .form-label {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 1.5px solid #e5ebf1;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: var(--text);
            transition: 0.25s ease;
        }

        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(31, 106, 165, 0.1);
        }

        .input-group-text {
            border: 1.5px solid #e5ebf1;
            border-right: 0;
            background: #f8fafc;
            color: var(--secondary);
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: 0;
            border-radius: 0 12px 12px 0;
        }

        .btn-admin {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.82rem 1.5rem;
            font-weight: 700;
            transition: 0.25s ease;
        }

        .btn-admin:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(31, 106, 165, 0.3);
        }

        .alert {
            border: 0;
            border-radius: 14px;
            font-weight: 500;
        }

        .admin-note {
            background: #f8fafc;
            border: 1px solid #edf1f5;
            border-radius: 16px;
            padding: 1rem;
            color: var(--muted);
        }

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
            .admin-hero {
                padding: 3rem 0 2.5rem;
            }

            .login-card {
                margin: -1.2rem 1rem 0;
            }
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <nav class="admin-navbar sticky-top">
        <div class="container d-flex align-items-center justify-content-between py-3">
            <a class="admin-brand" href="../../public/index.html">
                <i class="bi bi-flag-fill me-1" style="color:#ffb4b4;"></i>
                AR<span class="brand-highlight">TECHE</span> · CIS
            </a>

            <a class="admin-nav-link" href="../../public/index.html">
                <i class="bi bi-house me-1"></i> Back to Home
            </a>
        </div>
    </nav>

    <section class="admin-hero">
        <div class="container text-center">
            <h1 class="hero-title mb-3">
                <i class="bi bi-shield-lock me-2"></i> Admin Portal
            </h1>
            <p class="hero-subtitle mb-0">
                Secure access for barangay document request management and system administration
            </p>
        </div>
    </section>

    <main class="flex-grow-1">
        <div class="container pb-5">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5">
                    <div class="login-card p-4 p-md-5">

                        <div class="text-center mb-4">
                            <div class="login-icon mb-3">
                                <i class="bi bi-person-lock"></i>
                            </div>
                            <h4 class="fw-bold mb-1">Administrator Login</h4>
                            <p class="text-muted mb-0">Sign in to manage Arteche CI System</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-4">
                                <label class="form-label" for="username">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        class="form-control"
                                        required
                                        autocomplete="username"
                                        placeholder="Enter admin username"
                                        value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Enter your password">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-admin w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Login to Dashboard
                            </button>
                        </form>

                        <hr class="my-4">

                        <div class="admin-note text-center">
                            <small>
                                <i class="bi bi-shield-check me-1"></i>
                                Authorized personnel only. All login activities are monitored.
                            </small>
                        </div>

                        <!-- Remove this block before real deployment -->
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <strong>Demo Accounts:</strong><br>
                                Super Admin: admin / admin123<br>
                                Tangbo Admin: tangbo_admin / tangbo123
                            </small>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="auth-footer">
        <div class="container">
            <div class="row align-items-center gy-2">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Protected administrative access
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        Arteche CI System · Municipality of Arteche, Eastern Samar
                    </small>
                </div>
            </div>
        </div>
    </footer>

</body>

</html>