<?php
require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/config/constants.php';
require_once __DIR__ . '/../../shared/bootstrap.php';

$conn = getDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin_login.php");
    exit;
}

if ($_SESSION['role'] !== 'super_admin') {
    die("Access Denied.");
}

$message = "";
$message_type = "success";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['create_user'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed.";
        $message_type = "danger";
    } else {

        if (strlen($_POST['password']) < 8) {
            $message = "Password must be at least 8 characters.";
            $message_type = "danger";
        } else {

            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $role = $_POST['role'];
            $barangay_id = !empty($_POST['barangay_id']) ? intval($_POST['barangay_id']) : NULL;

            $stmt = $conn->prepare("
                INSERT INTO users (username, full_name, password, barangay_id, role, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param("sssis", $username, $full_name, $password, $barangay_id, $role);

            if ($stmt->execute()) {
                $message = "User created successfully.";
                $message_type = "success";
            } else {
                $message = "Username already exists.";
                $message_type = "danger";
            }

            $stmt->close();
        }
    }
}

/* Toggle functionality removed as per decision */

if (isset($_GET['delete'])) {

    $user_id = intval($_GET['delete']);

    if ($user_id != $_SESSION['user_id']) {

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $message = "User deleted.";
    }
}

$users = $conn->query("
SELECT u.*,b.name AS barangay_name
FROM users u
LEFT JOIN barangays b ON u.barangay_id=b.id
ORDER BY u.created_at DESC
");

$barangays = $conn->query("SELECT id,name FROM barangays ORDER BY name");

$current_user = $_SESSION['full_name'] ?? "Super Admin";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI';
        }

        .sidebar {
            min-height: 100vh;
            background: #0f172a;
        }

        .sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
        }

        .sidebar-brand h4 {
            color: #fff;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
        }

        .sidebar-menu a:hover {
            background: #1e293b;
            color: #fff;
        }

        .sidebar-menu a.active {
            background: #1e40af;
            color: #fff;
        }

        .main-content {
            padding: 30px;
        }

        .page-header {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }

        .badge-role {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .role-super {
            background: #7c3aed;
            color: #fff;
        }

        .role-admin {
            background: #dc2626;
            color: #fff;
        }

        .status-active {
            background: #16a34a;
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .status-inactive {
            background: #64748b;
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>

</head>

<body>

    <div class="container-fluid">
        <div class="row">

            <div class="col-md-2 sidebar p-0">

                <div class="sidebar-brand text-center">
                    <h4><i class="bi bi-building"></i> Arteche CIS</h4>
                    <span style="color:#94a3b8;font-size:12px;">Super Admin</span>
                </div>

                <div class="sidebar-menu">

                    <a href="../shared/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>

                    <a href="users.php" class="active">
                        <i class="bi bi-people"></i> Users
                    </a>

                    <a href="manage_barangays.php">
                        <i class="bi bi-building"></i> Barangays
                    </a>

                    <a href="../../shared/logout.php" class="text-danger mt-3">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>

                </div>
            </div>

            <div class="col-md-10">

                <div class="main-content">

                    <div class="page-header d-flex justify-content-between">

                        <div>
                            <h4>User Management</h4>
                            <p class="text-muted">Manage system users</p>
                        </div>

                        <div>
                            <strong><?= htmlspecialchars($current_user) ?></strong>
                        </div>

                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">

                        <div class="card-header">
                            Create User
                        </div>

                        <div class="card-body">

                            <form method="POST" class="row g-3">

                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <div class="col-md-6">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control" minlength="8" required>
                                </div>

                                <div class="col-md-3">
                                    <label>Role</label>

                                    <select name="role" class="form-select">

                                        <option value="barangay_admin">Barangay Admin</option>
                                        <option value="super_admin">Super Admin</option>

                                    </select>

                                </div>

                                <div class="col-md-3">
                                    <label>Barangay</label>

                                    <select name="barangay_id" class="form-select">

                                        <option value="">None</option>

                                        <?php while ($b = $barangays->fetch_assoc()): ?>

                                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>

                                        <?php endwhile; ?>

                                    </select>

                                </div>

                                <div class="col-12">

                                    <button class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create User
                                    </button>

                                </div>

                            </form>

                        </div>
                    </div>

                    <div class="card">

                        <div class="card-header">
                            Users List
                        </div>

                        <div class="card-body p-0">

                            <table class="table table-hover mb-0">

                                <thead>

                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Barangay</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php while ($u = $users->fetch_assoc()):

                                        $status = isset($u['is_active']) && $u['is_active'] == 0 ? "Inactive" : "Active";

                                        $initials = strtoupper(substr($u['username'], 0, 2));

                                        ?>

                                        <tr>

                                            <td><?= $u['id'] ?></td>

                                            <td>

                                                <div class="d-flex align-items-center">

                                                    <div class="user-avatar me-2"><?= $initials ?></div>

                                                    <div>

                                                        <strong><?= htmlspecialchars($u['username']) ?></strong><br>

                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($u['full_name'] ?? "N/A") ?></small>

                                                    </div>

                                                </div>

                                            </td>

                                            <td>

                                                <?php if ($u['role'] == "super_admin"): ?>

                                                    <span class="badge-role role-super">
                                                        <i class="bi bi-shield-lock"></i> Super Admin
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge-role role-admin">
                                                        <i class="bi bi-person-badge"></i> Barangay Admin
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td><?= htmlspecialchars($u['barangay_name'] ?? "N/A") ?></td>

                                            <td>

                                                <?php if ($status == "Active"): ?>

                                                    <span class="status-active">
                                                        <i class="bi bi-check-circle"></i> Active
                                                    </span>

                                                <?php else: ?>

                                                    <span class="status-inactive">
                                                        <i class="bi bi-x-circle"></i> Inactive
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td><?= date("M d, Y", strtotime($u['created_at'])) ?></td>

                                            <td>

                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>

                                                    <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Delete this user?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>

                                                <?php else: ?>

                                                    <i class="bi bi-lock text-muted"></i>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>