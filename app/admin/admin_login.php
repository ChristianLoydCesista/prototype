<?php
require_once __DIR__ . '/../shared/bootstrap.php';
if (isset($_SESSION['admin'])) {
    header("Location: shared/dashboard.php");
    exit;
}

// database connection provided by bootstrap
$conn = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT u.*, b.name as barangay_name 
            FROM users u 
            LEFT JOIN barangays b ON u.barangay_id = b.id 
            WHERE u.username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Use password_verify for secure authentication
        // Note: For existing MD5 passwords, you'll need to migrate to bcrypt
        // This checks both MD5 (legacy) and bcrypt (new) passwords
        $passwordValid = false;

        // Check if password is already hashed with bcrypt (new format)
        if (substr($user['password'], 0, 4) === '$2y$') {
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Legacy MD5 check - migrate to bcrypt on successful login
            if (md5($password) === $user['password']) {
                $passwordValid = true;
                // Upgrade to bcrypt on next successful login
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newHash, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        if ($passwordValid) {
            $_SESSION['admin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['barangay_id'] = $user['barangay_id'];
            $_SESSION['barangay_name'] = $user['barangay_name'] ?? 'Super Admin';

            // Log login activity using prepared statement
            $ip = $_SERVER['REMOTE_ADDR'];
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'Login', 'User logged in', ?)");
            $logStmt->bind_param("is", $user['id'], $ip);
            $logStmt->execute();
            $logStmt->close();

            header("Location: shared/dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
    $stmt->close();
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
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
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