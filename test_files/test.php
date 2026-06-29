<?php
session_start();
require_once "db.php"; // must define $conn (mysqli)

/* =========================
   SESSION & ROLE CHECK
========================= */
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$is_super_admin = ($role === 'super_admin');

$message = "";
$message_type = "";

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    error_log("POST RECEIVED: " . print_r($_POST, true));

    /* -------- BARANGAY ID -------- */
    if ($is_super_admin) {
        $selected_barangay_id = intval($_POST['barangay_id'] ?? 0);
    } else {
        if (empty($barangay_id)) {
            $message = "Barangay session missing. Please log in again.";
            $message_type = "danger";
            error_log("ERROR: Missing barangay_id in session");
            goto render;
        }
        $selected_barangay_id = intval($barangay_id);
    }

    /* -------- INPUT SANITIZATION -------- */
    $name = trim($_POST['name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $sex = $_POST['sex'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $household_size = intval($_POST['household_size'] ?? 0);
    $income_monthly = floatval($_POST['income_monthly'] ?? 0);
    $income_source = trim($_POST['income_source'] ?? '');
    $four_ps = $_POST['four_ps'] ?? 'No';
    $housing_type = trim($_POST['housing_type'] ?? '');
    $water_source = trim($_POST['water_source'] ?? '');
    $toilet_type = trim($_POST['toilet_type'] ?? '');
    $employment = trim($_POST['employment'] ?? '');
    $disability = $_POST['disability'] ?? 'No';
    $senior_citizen = $_POST['senior_citizen'] ?? 'No';
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);

    /* -------- VALIDATION -------- */
    if (
        empty($name) ||
        $selected_barangay_id <= 0 ||
        $age <= 0 ||
        empty($sex) ||
        empty($civil_status) ||
        $household_size <= 0 ||
        $income_monthly < 0
    ) {
        $message = "Please complete all required fields.";
        $message_type = "danger";
        goto render;
    }

    /* -------- CALCULATIONS -------- */
    $income_per_capita = ($household_size > 0)
        ? round($income_monthly / $household_size, 2)
        : 0;

    $risk_score = 0;
    if ($income_per_capita < 1000)
        $risk_score += 30;
    if ($four_ps === 'Yes')
        $risk_score += 15;
    if ($disability === 'Yes')
        $risk_score += 20;
    if ($senior_citizen === 'Yes')
        $risk_score += 15;
    if ($household_size >= 6)
        $risk_score += 10;

    /* -------- GET BARANGAY NAME -------- */
    $barangay_name = '';
    $stmt_b = $conn->prepare("SELECT name FROM barangays WHERE id = ?");
    $stmt_b->bind_param("i", $selected_barangay_id);
    $stmt_b->execute();
    $stmt_b->bind_result($barangay_name);
    $stmt_b->fetch();
    $stmt_b->close();

    /* -------- INSERT QUERY -------- */
    $sql = "
        INSERT INTO households (
            barangay_id, name, contact_number, age, sex, civil_status,
            household_size, income_monthly, income_per_capita,
            income_source, four_ps, housing_type, water_source,
            toilet_type, employment, disability, senior_citizen,
            latitude, longitude, barangay, risk_score
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $message = "Database error: " . $conn->error;
        $message_type = "danger";
        goto render;
    }

    $stmt->bind_param(
        "ississiddssssssssddsi",
        $selected_barangay_id,
        $name,
        $contact_number,
        $age,
        $sex,
        $civil_status,
        $household_size,
        $income_monthly,
        $income_per_capita,
        $income_source,
        $four_ps,
        $housing_type,
        $water_source,
        $toilet_type,
        $employment,
        $disability,
        $senior_citizen,
        $latitude,
        $longitude,
        $barangay_name,
        $risk_score
    );

    if ($stmt->execute()) {
        $message = "Household survey saved successfully!";
        $message_type = "success";
    } else {
        $message = "Insert failed: " . $stmt->error;
        $message_type = "danger";
    }

    $stmt->close();
}

render:
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Household Survey</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container mt-4">
        <h3>Household Survey Form</h3>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" id="surveyForm">

            <?php if ($is_super_admin): ?>
                <div class="mb-3">
                    <label class="form-label">Barangay</label>
                    <select name="barangay_id" class="form-select" required>
                        <option value="">Select Barangay</option>
                        <?php
                        $res = $conn->query("SELECT id, name FROM barangays ORDER BY name");
                        while ($row = $res->fetch_assoc()):
                            ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">Please select a barangay</div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label>Sex</label>
                    <select name="sex" class="form-select" required>
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                    <div class="invalid-feedback">Required</div>
                </div>

                <div class="col-md-3 mb-3">
                    <label>Civil Status</label>
                    <select name="civil_status" class="form-select" required>
                        <option value="">Select</option>
                        <option>Single</option>
                        <option>Married</option>
                        <option>Widowed</option>
                        <option>Separated</option>
                    </select>
                    <div class="invalid-feedback">Required</div>
                </div>

                <div class="col-md-3 mb-3">
                    <label>Household Size</label>
                    <input type="number" name="household_size" class="form-control" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Monthly Income</label>
                    <input type="number" step="0.01" name="income_monthly" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label>Income Source</label>
                    <input type="text" name="income_source" class="form-control">
                </div>
            </div>

            <button type="submit" name="submit" class="btn btn-primary">Submit Survey</button>

        </form>
    </div>

    <script>
        document.getElementById('surveyForm').addEventListener('submit', function (e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                alert("Please complete all required fields.");
            }
        });
    </script>

</body>

</html>