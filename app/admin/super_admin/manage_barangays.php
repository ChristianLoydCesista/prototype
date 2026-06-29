<?php
require_once __DIR__ . '/../../shared/bootstrap.php';
// bootstrapped

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'super_admin') {
    header('Location: ../admin_login.php');
    exit();
}

require_once __DIR__ . '/../../shared/config/database.php';
$conn = getDB();

$username = $_SESSION['username'] ?? 'Admin';
$message = '';
$message_type = 'info';

// ============================================
// CRUD Operations for Barangays
// ============================================

// Update coordinates (existing functionality)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_coordinates'])) {
    $id = intval($_POST['id']);
    $lat = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lon = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    $stmt = $conn->prepare("UPDATE barangays SET latitude = ?, longitude = ? WHERE id = ?");
    $stmt->bind_param("ddi", $lat, $lon, $id);

    if ($stmt->execute()) {
        $message = "Coordinates updated successfully!";
        $message_type = "success";

        // Log the activity
        $ip = $_SERVER['REMOTE_ADDR'];
        $conn->query("INSERT INTO activity_logs (user_id, action, details, ip_address) 
                      VALUES ({$_SESSION['user_id']}, 'Barangay Coordinates Update', 'Updated coordinates for barangay ID: $id', '$ip')");
    } else {
        $message = "Error updating coordinates: " . $conn->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// Add new barangay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_barangay'])) {
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $municipality = $conn->real_escape_string($_POST['municipality'] ?? 'Arteche');
    $province = $conn->real_escape_string($_POST['province'] ?? 'Eastern Samar');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $population = intval($_POST['population'] ?? 0);

    if (empty($name)) {
        $message = "Barangay name is required.";
        $message_type = "danger";
    } else {
        // Check if barangay already exists
        $check_stmt = $conn->prepare("SELECT id FROM barangays WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Barangay '$name' already exists.";
            $message_type = "danger";
        } else {
            // Insert new barangay
            $stmt = $conn->prepare("INSERT INTO barangays (name, municipality, province, latitude, longitude, population) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddi", $name, $municipality, $province, $latitude, $longitude, $population);

            if ($stmt->execute()) {
                $message = "Barangay '$name' added successfully!";
                $message_type = "success";

                // Log the activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $conn->query("INSERT INTO activity_logs (user_id, action, details, ip_address) 
                              VALUES ({$_SESSION['user_id']}, 'Barangay Management', 'Added new barangay: $name', '$ip')");
            } else {
                $message = "Error adding barangay: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Update barangay details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_barangay'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $municipality = $conn->real_escape_string($_POST['municipality'] ?? 'Arteche');
    $province = $conn->real_escape_string($_POST['province'] ?? 'Eastern Samar');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $population = intval($_POST['population'] ?? 0);

    if (empty($name)) {
        $message = "Barangay name is required.";
        $message_type = "danger";
    } else {
        // Check if barangay name already exists (excluding current barangay)
        $check_stmt = $conn->prepare("SELECT id FROM barangays WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $name, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Barangay '$name' already exists.";
            $message_type = "danger";
        } else {
            // Update barangay
            $stmt = $conn->prepare("UPDATE barangays SET name = ?, municipality = ?, province = ?, latitude = ?, longitude = ?, population = ? WHERE id = ?");
            $stmt->bind_param("sssddii", $name, $municipality, $province, $latitude, $longitude, $population, $id);

            if ($stmt->execute()) {
                $message = "Barangay updated successfully!";
                $message_type = "success";

                // Log the activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $conn->query("INSERT INTO activity_logs (user_id, action, details, ip_address) 
                              VALUES ({$_SESSION['user_id']}, 'Barangay Management', 'Updated barangay: $name (ID: $id)', '$ip')");
            } else {
                $message = "Error updating barangay: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Delete barangay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_barangay'])) {
    $id = intval($_POST['id']);

    // First, get barangay name for logging
    $get_stmt = $conn->prepare("SELECT name FROM barangays WHERE id = ?");
    $get_stmt->bind_param("i", $id);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    $barangay = $get_result->fetch_assoc();
    $barangay_name = $barangay['name'] ?? '';
    $get_stmt->close();

    // Check if barangay has users or households
    $check_users = $conn->query("SELECT COUNT(*) as user_count FROM users WHERE barangay_id = $id")->fetch_assoc();
    $check_households = $conn->query("SELECT COUNT(*) as household_count FROM households WHERE barangay_id = $id")->fetch_assoc();

    if ($check_users['user_count'] > 0 || $check_households['household_count'] > 0) {
        $message = "Cannot delete barangay '$barangay_name' because it has users or households assigned to it.";
        $message_type = "danger";
    } else {
        // Delete barangay
        $stmt = $conn->prepare("DELETE FROM barangays WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Barangay '$barangay_name' deleted successfully!";
            $message_type = "success";

            // Log the activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $conn->query("INSERT INTO activity_logs (user_id, action, details, ip_address) 
                          VALUES ({$_SESSION['user_id']}, 'Barangay Management', 'Deleted barangay: $barangay_name', '$ip')");
        } else {
            $message = "Error deleting barangay: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// ============================================
// Get all barangays with statistics
// ============================================
$barangays = $conn->query("
    SELECT b.*, 
           COUNT(DISTINCT h.id) as household_count,
           COUNT(DISTINCT u.id) as user_count,
           COALESCE(SUM(h.household_size), 0) as total_population,
           COALESCE(AVG(h.risk_score), 0) as avg_risk_score
    FROM barangays b
    LEFT JOIN households h ON b.id = h.barangay_id
    LEFT JOIN users u ON b.id = u.barangay_id
    GROUP BY b.id
    ORDER BY b.name
")->fetch_all(MYSQLI_ASSOC);

// Get total statistics
$total_barangays = count($barangays);
$total_households = array_sum(array_column($barangays, 'household_count'));
$total_users = array_sum(array_column($barangays, 'user_count'));
$total_population = array_sum(array_column($barangays, 'total_population'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Barangays - Arteche CI System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .coordinate-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .coordinate-form input {
            width: 120px;
        }

        .page-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            padding: 20px 0;
            margin-bottom: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .header-title {
            color: white;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .action-btn {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            border: none;
            color: #333;
            padding: 8px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <!-- Enhanced Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="header-title">
                        <i class="bi bi-buildings"></i> Barangay Management
                    </h1>
                    <p class="header-subtitle mb-0">
                        <i class="bi bi-geo-alt"></i> Municipality of Arteche, Eastern Samar
                    </p>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <a href="../shared/dashboard.php" class="btn back-btn">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <!--<button class="btn action-btn" data-bs-toggle="modal" data-bs-target="#addBarangayModal">
                        <i class="bi bi-plus-circle"></i> Add New Barangay
                    </button>-->
                </div>
            </div>
        </div>
    </div>



    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Barangays</h6>
                                <h2 class="mb-0"><?= $total_barangays ?></h2>
                            </div>
                            <i class="bi bi-building fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Households</h6>
                                <h2 class="mb-0"><?= $total_households ?></h2>
                            </div>
                            <i class="bi bi-house-door fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Users</h6>
                                <h2 class="mb-0"><?= $total_users ?></h2>
                            </div>
                            <i class="bi bi-people fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Population</h6>
                                <h2 class="mb-0"><?= $total_population ?></h2>
                            </div>
                            <i class="bi bi-person-badge fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="card shadow">
            <div class="card-header text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-buildings"></i> Barangay Management</h4>
                        <small>Municipality of Arteche, Eastern Samar</small>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Barangays Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Barangay Name</th>
                                <th>Municipality</th>
                                <th>Province</th>
                                <th>Coordinates</th>
                                <th>Population</th>
                                <th>Households</th>
                                <th>Users</th>
                                <th>Avg Risk</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($barangays)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No barangays found. Add your first barangay!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($barangays as $index => $barangay): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($barangay['name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($barangay['municipality']) ?></td>
                                        <td><?= htmlspecialchars($barangay['province']) ?></td>
                                        <td>
                                            <!-- Coordinate Update Form (your existing feature) -->
                                            <form method="POST" class="coordinate-form">
                                                <input type="hidden" name="id" value="<?= $barangay['id'] ?>">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Lat</span>
                                                    <input type="number" step="any" name="latitude"
                                                        value="<?= $barangay['latitude'] ?>" class="form-control"
                                                        placeholder="12.264000">
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Lng</span>
                                                    <input type="number" step="any" name="longitude"
                                                        value="<?= $barangay['longitude'] ?>" class="form-control"
                                                        placeholder="125.402000">
                                                </div>
                                                <button type="submit" name="update_coordinates" class="btn btn-sm btn-primary"
                                                    title="Update Coordinates">
                                                    <i class="bi bi-geo-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td><?= $barangay['population'] ? number_format($barangay['population']) : '0' ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?= $barangay['household_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $barangay['user_count'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($barangay['avg_risk_score'] > 0): ?>
                                                <span
                                                    class="badge bg-<?= $barangay['avg_risk_score'] > 50 ? 'danger' : ($barangay['avg_risk_score'] > 30 ? 'warning' : 'success') ?>">
                                                    <?= number_format($barangay['avg_risk_score'], 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-success view-btn" data-bs-toggle="modal"
                                                    data-bs-target="#viewBarangayModal" data-id="<?= $barangay['id'] ?>"
                                                    data-name="<?= htmlspecialchars($barangay['name']) ?>"
                                                    data-municipality="<?= htmlspecialchars($barangay['municipality']) ?>"
                                                    data-province="<?= htmlspecialchars($barangay['province']) ?>"
                                                    data-latitude="<?= $barangay['latitude'] ?? 'N/A' ?>"
                                                    data-longitude="<?= $barangay['longitude'] ?? 'N/A' ?>"
                                                    data-population="<?= $barangay['population'] ?>"
                                                    data-households="<?= $barangay['household_count'] ?>"
                                                    data-users="<?= $barangay['user_count'] ?>"
                                                    data-risk="<?= round($barangay['avg_risk_score'], 1) ?>"
                                                    title="Quick view barangay summary">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="../shared/dashboard.php?barangay_id=<?= $barangay['id'] ?>"
                                                    class="btn btn-sm btn-outline-info" title="View Dashboard">
                                                    <i class="bi bi-graph-up"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                    data-bs-target="#editBarangayModal" data-id="<?= $barangay['id'] ?>"
                                                    data-name="<?= htmlspecialchars($barangay['name']) ?>"
                                                    data-municipality="<?= htmlspecialchars($barangay['municipality']) ?>"
                                                    data-province="<?= htmlspecialchars($barangay['province']) ?>"
                                                    data-latitude="<?= $barangay['latitude'] ?>"
                                                    data-longitude="<?= $barangay['longitude'] ?>"
                                                    data-population="<?= $barangay['population'] ?>"
                                                    title="Edit Barangay Details">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Barangay Modal -->
    <div class="modal fade" id="addBarangayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <<div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Barangay</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Barangay Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Tangbo">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Municipality</label>
                                <input type="text" name="municipality" class="form-control" value="Arteche"
                                    placeholder="Municipality">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Province</label>
                                <input type="text" name="province" class="form-control" value="Eastern Samar"
                                    placeholder="Province">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" name="latitude" class="form-control"
                                    placeholder="e.g., 12.264000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" name="longitude" class="form-control"
                                    placeholder="e.g., 125.402000">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Population</label>
                            <input type="number" name="population" class="form-control" value="0" min="0"
                                placeholder="Estimated population">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_barangay" class="btn btn-primary">Add Barangay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Barangay Modal -->
    <div class="modal fade" id="editBarangayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Barangay</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Barangay Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required
                                placeholder="e.g., Tangbo">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Municipality</label>
                                <input type="text" name="municipality" id="edit_municipality" class="form-control"
                                    placeholder="Municipality">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Province</label>
                                <input type="text" name="province" id="edit_province" class="form-control"
                                    placeholder="Province">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" name="latitude" id="edit_latitude" class="form-control"
                                    placeholder="e.g., 12.264000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" name="longitude" id="edit_longitude"
                                    class="form-control" placeholder="e.g., 125.402000">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Population</label>
                            <input type="number" name="population" id="edit_population" class="form-control" min="0"
                                placeholder="Estimated population">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_barangay" class="btn btn-primary">Update Barangay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteBarangayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Are you sure you want to delete the barangay <strong id="delete_name"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> This action cannot be undone. Please ensure there are no
                            users or households assigned to this barangay.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_barangay" class="btn btn-danger">Delete Barangay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Barangay Details Modal -->
    <div class="modal fade" id="viewBarangayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-info-circle"></i> Barangay Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><strong><i class="bi bi-building"></i> Basic Information</strong></h6>
                            <table class="table table-borderless">
                                <tr><td><strong>Name:</strong></td><td id="view_name">-</td></tr>
                                <tr><td><strong>Municipality:</strong></td><td id="view_municipality">-</td></tr>
                                <tr><td><strong>Province:</strong></td><td id="view_province">-</td></tr>
                                <tr><td><strong>Population:</strong></td><td id="view_population">-</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><strong><i class="bi bi-geo-alt"></i> Location</strong></h6>
                            <p id="view_coordinates">-</p>
                            <a id="view_maps_link" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-geo-alt"></i> Open in Google Maps
                            </a>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <span class="badge bg-primary fs-6 mb-2 d-block" id="view_households_badge">-</span>
                                    <h6>Households</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <span class="badge bg-info fs-6 mb-2 d-block" id="view_users_badge">-</span>
                                    <h6>Users</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <span class="badge bg-warning fs-6 mb-2 d-block" id="view_risk_badge">-</span>
                                    <h6>Avg Risk Score</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="view_id">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit modal data
        const editModal = document.getElementById('editBarangayModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const modal = this;

                modal.querySelector('#edit_id').value = button.getAttribute('data-id');
                modal.querySelector('#edit_name').value = button.getAttribute('data-name');
                modal.querySelector('#edit_municipality').value = button.getAttribute('data-municipality');
                modal.querySelector('#edit_province').value = button.getAttribute('data-province');
                modal.querySelector('#edit_latitude').value = button.getAttribute('data-latitude');
                modal.querySelector('#edit_longitude').value = button.getAttribute('data-longitude');
                modal.querySelector('#edit_population').value = button.getAttribute('data-population');
            });
        }

        // Handle delete modal data
        const deleteModal = document.getElementById('deleteBarangayModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const modal = this;

                modal.querySelector('#delete_id').value = button.getAttribute('data-id');
                modal.querySelector('#delete_name').textContent = button.getAttribute('data-name');
            });
        }

        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Add confirmation for delete
        const deleteForms = document.querySelectorAll('form[action*="delete"]');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function (e) {
                if (!confirm('Are you sure you want to delete this barangay?')) {
                    e.preventDefault();
                }
            });
        });

        // Quick coordinate update feedback
        document.querySelectorAll('.coordinate-form').forEach(form => {
            form.addEventListener('submit', function () {
                const btn = this.querySelector('button[type="submit"]');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-hourglass"></i>';
                btn.disabled = true;

                // Restore button after 2 seconds
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 2000);
            });
        });

        // Handle view modal data
        const viewModal = document.getElementById('viewBarangayModal');
        if (viewModal) {
            viewModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const modal = this;

                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const municipality = button.getAttribute('data-municipality');
                const province = button.getAttribute('data-province');
                const latitude = button.getAttribute('data-latitude');
                const longitude = button.getAttribute('data-longitude');
                const population = button.getAttribute('data-population');
                const households = button.getAttribute('data-households');
                const users = button.getAttribute('data-users');
                const risk = button.getAttribute('data-risk');

                modal.querySelector('#view_id').value = id;
                modal.querySelector('#view_name').textContent = name;
                modal.querySelector('#view_municipality').textContent = municipality;
                modal.querySelector('#view_province').textContent = province;
                modal.querySelector('#view_population').textContent = population ? population.toLocaleString() : 'N/A';

                // Coordinates
                const coordsText = latitude !== 'N/A' && longitude !== 'N/A' ? `${latitude}, ${longitude}` : 'Not set';
                modal.querySelector('#view_coordinates').textContent = coordsText;

                // Maps link
                const mapsUrl = latitude !== 'N/A' && longitude !== 'N/A' ? `https://maps.google.com/?q=${latitude},${longitude}&ll=${latitude},${longitude}&z=15` : '#';
                modal.querySelector('#view_maps_link').href = mapsUrl;

                // Stats badges
                modal.querySelector('#view_households_badge').textContent = households;
                modal.querySelector('#view_users_badge').textContent = users;
                const riskBadge = risk > 50 ? 'bg-danger' : (risk > 30 ? 'bg-warning' : 'bg-success');
                modal.querySelector('#view_risk_badge').textContent = risk !== '0' ? risk + '%' : 'N/A';
                modal.querySelector('#view_risk_badge').className = `badge ${riskBadge} fs-6 mb-2 d-block`;

                // Quick links
                modal.querySelector('#view_dashboard_link').href = `../shared/dashboard.php?barangay_id=${id}`;
            });
        }

        // Enable Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>

</html>
