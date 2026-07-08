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
    <title>Arteche CI System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h2 {
            color: #333;
            font-weight: bold;
        }

        .logo p {
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <div class="logo">
                        <h2><i class="bi bi-geo-alt"></i> Arteche CI System</h2>
                        <p>Municipality of Arteche, Eastern Samar</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <hr class="my-4">
                    <div class="text-center">
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
</body>

</html>